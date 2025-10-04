<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUndefinedVariableInspection */

/** @noinspection NotOptimalIfConditionsInspection */

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UnionType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use Timeax\FortiPlugin\Core\Security\Concerns\ResolvesNames;

/**
 * PluginSecurityScanner: Extensible, policy-driven, always-forbidden-aware PHP plugin validator
 */
class PluginSecurityScanner extends NodeVisitorAbstract
{
    use ResolvesNames;

    protected array $config;
    protected PluginPolicy $policy;
    protected array $aliases = []; // Aliases (from use statements) in this file
    protected array $matches = [];
    /** @var array<string, string[]>  lower(fqcn) => [lower(method)...] */
    private array $classAllowlist = [];
    /** @var array<string,string> $variableTypes // $var => FQCN (no leading \) */
    private array $variableTypes = [];
    /** @var array<string,string> $classNameVars // $var => FQCN (no leading \) */
    private array $classNameVars = [];
    protected CallGraphAnalyzer $callGraphAnalyzer;
    protected mixed $currentFile = null;

    protected array $variableValues = [];
    protected array $superglobals = ['_GET', '_POST', '_REQUEST', '_COOKIE', '_FILES', '_SESSION', '_SERVER', '_ENV'];

    public function __construct(array $config = null, $filePath = null)
    {
        $this->policy = new PluginPolicy($config);
        $this->callGraphAnalyzer = new CallGraphAnalyzer($this->policy);
        $this->config = $config;
        $this->currentFile = $filePath;
    }

    public function getPolicy(): PluginPolicy
    {
        return $this->policy;
    }

    public function setCurrentFile(string $file): static
    {
        $this->currentFile = $file;
        return $this;
    }

    private function initClassAllowlist(): void
    {
        $raw = $this->policy->getBlocklist() ?? [];
        $this->classAllowlist = $this->normalizeBlocklist($raw);
    }

    private function normalizeBlocklist(array $map): array
    {
        $out = [];
        foreach ($map as $class => $methods) {
            if (!is_array($methods)) continue;
            $key = $this->normClassKey($class);
            $out[$key] = array_values(array_unique(array_map('strtolower', $methods)));
        }
        return $out;
    }

    private function normClassKey(?string $name): string
    {
        return strtolower(ltrim((string)$name, '\\'));
    }

    /**
     * Scan a raw PHP source string and return violations.
     * - Runs NameResolver (FQCN / function names)
     * - Connects parent pointers and tags parent_class
     * - Builds call graph (functions/methods) for indirect-return checks
     * - Traverses with this scanner as a visitor
     *
     * @param string $phpSource
     * @param string|null $filePath Optional file path for context in reports
     * @return array                 Flat list of violation records
     */
    public function scanSource(string $phpSource, ?string $filePath = null): array
    {
        if (property_exists($this, 'currentFile')) {
            $this->currentFile = $filePath ?? $this->currentFile ?? '[source]';
        }

        // 1) Parse
        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $ast = $parser->parse($phpSource);
        } catch (Throwable $e) {
            return [[
                'type' => 'parse_error',
                'error' => $e->getMessage(),
                'file' => $filePath ?? '[source]',
                'line' => 0,
                'snippet' => '',
            ]];
        }
        if (!$ast) {
            return [];
        }

        // 2) Name resolution (fully-qualify names), then parent pointers
        $trResolve = new NodeTraverser();
        $trResolve->addVisitor(new NameResolver(options: [
            'preserveOriginalNames' => true,
            'replaceNodes' => true, // rewrite Name nodes to FullyQualified
        ]));
        $trResolve->addVisitor(new ParentConnectingVisitor());
        $ast = $trResolve->traverse($ast);

        $this->initClassAllowlist();

        // 3) Tag every node with its enclosing class ("parent_class") for easy lookups
        $trClassTag = new NodeTraverser();
        $trClassTag->addVisitor(new class extends NodeVisitorAbstract {
            private ?Node\Stmt\Class_ $current = null;

            public function enterNode(Node $node): void
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->current = $node;
                }
                if ($this->current && $node !== $this->current) {
                    $node->setAttribute('parent_class', $this->current);
                }
            }

            public function leaveNode(Node $node): void
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->current = null;
                }
            }
        });
        $ast = $trClassTag->traverse($ast);

        // 4) Build (or reuse) the call graph index for indirect-return checks
        if (!property_exists($this, 'callGraphAnalyzer')) {
            // assumes $this->policy (PluginPolicy) exists on the scanner
            $this->callGraphAnalyzer = new CallGraphAnalyzer($this->policy);
        }
        $this->callGraphAnalyzer->collect([$ast]);

        // 5) Security scan — treat this scanner as a NodeVisitor
        $trScan = new NodeTraverser();
        $trScan->addVisitor($this); // $this must extend NodeVisitorAbstract
        $trScan->traverse($ast);

        // 6) Return flat list of matches (whatever your scanner accumulates)
        return method_exists($this, 'getMatches') ? $this->getMatches() : [];
    }

    /**
     * Parity helper: read a file and delegate to scanSource().
     */
    public function scanFile(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return [[
                'type' => 'read_error',
                'file' => $filePath,
                'line' => 0,
                'snippet' => '',
                'issue' => 'Unable to read file',
            ]];
        }

        return $this->scanSource($code, $filePath);
    }

    public function getFileErrors(): array
    {
        $errors = $this->matches;
        $this->matches = [];
        return $errors;
    }

    // Pass in alias map after first pass (see below)
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Track simple variable assignments for:
     *  - string/concat/superglobal values (existing behavior → $this->variableValues)
     *  - instance types from `new \Fqcn(...)` and simple `$b = $a` propagation (→ $this->variableTypes)
     */
    /**
     * Track simple variable assignments for:
     *  - string/concat/superglobal values (→ $this->variableValues)
     *  - class literals via ::class and dynamic-new resolution (→ $this->classNameVars, $this->variableTypes)
     *  - instance types from `new \Fqcn(...)` and simple `$b = $a` propagation (→ $this->variableTypes)
     */
    public function trackAssignments($node): void
    {
        // $x = ...   or   $x =& ...
        if (($node instanceof Assign || $node instanceof AssignRef)
            && $node->var instanceof Variable
            && is_string($node->var->name)) {

            $varName = $node->var->name;
            $expr = $node->expr; // same for AssignRef

            // Reset stale info unless set below
            unset($this->variableValues[$varName], $this->variableTypes[$varName], $this->classNameVars[$varName]);

            // ── value tracking (strings / concat / superglobals)
            if ($expr instanceof String_) {
                $this->variableValues[$varName] = $expr->value;
                return;
            }

            if ($expr instanceof Node\Expr\BinaryOp\Concat) {
                $this->variableValues[$varName] = $this->stringifyDynamic($expr);
                return;
            }

            if ($expr instanceof Node\Expr\ArrayDimFetch
                && $expr->var instanceof Variable
                && is_string($expr->var->name)
                && in_array($expr->var->name, $this->superglobals, true)) {
                $this->variableValues[$varName] = '{superglobal}';
                return;
            }

            // ── class literal: $class = A::class; (imported or FQCN)
            if ($expr instanceof Node\Expr\ClassConstFetch
                && $expr->name instanceof Identifier
                && strtolower($expr->name->toString()) === 'class') {

                $fq = null;
                if ($expr->class instanceof Name) {
                    $fq = $this->fqNameOf($expr->class) ?? $expr->class->toString();
                } /** @noinspection PhpConditionAlreadyCheckedInspection */ elseif (is_string($expr->class)) {
                    $fq = $expr->class;
                }
                if ($fq) {
                    $this->classNameVars[$varName] = ltrim($fq, '\\');
                }
                return;
            }

            // ── dynamic new via class var: $obj = new $class();
            if ($expr instanceof New_
                && $expr->class instanceof Variable
                && is_string($expr->class->name)) {

                $clsVar = $expr->class->name;
                if (isset($this->classNameVars[$clsVar])) {
                    $this->variableTypes[$varName] = $this->classNameVars[$clsVar];
                    return;
                }
            }

            // ── direct instance type: $x = new \Vendor\Class(...);
            if ($expr instanceof New_) {
                $fq = $this->getClassName($expr->class); // resolver-aware in your codebase
                if ($fq) {
                    $this->variableTypes[$varName] = ltrim($fq, '\\');
                }
                return;
            }

            // ── simple propagation: $b = $a;  (carry value/type/class-literal if known)
            if ($expr instanceof Variable && is_string($expr->name)) {
                if (array_key_exists($expr->name, $this->variableValues)) {
                    $this->variableValues[$varName] = $this->variableValues[$expr->name];
                }
                if (array_key_exists($expr->name, $this->variableTypes)) {
                    $this->variableTypes[$varName] = $this->variableTypes[$expr->name];
                }
                if (array_key_exists($expr->name, $this->classNameVars)) {
                    $this->classNameVars[$varName] = $this->classNameVars[$expr->name];
                }
                return;
            }

            // Anything else → leave unset (unknown)
            return;
        }

        // unset($x) — forget tracked info
        if ($node instanceof Node\Stmt\Unset_) {
            foreach ($node->vars as $v) {
                if ($v instanceof Variable && is_string($v->name)) {
                    unset($this->variableValues[$v->name], $this->variableTypes[$v->name], $this->classNameVars[$v->name]);
                }
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function enterNode(Node $node): void
    {
        $this->trackAssignments($node);

        // -- 1. ALWAYS FORBIDDEN CHECKS --
        // A. Functions
        if ($node instanceof Node\Expr\FuncCall) {
            $fname = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($fname && $this->policy->isForbiddenFunction($fname)) {
                $this->report('always_forbidden_function', ['function' => $fname], $node);
            }

            // Wrapper stream usage in file ops
            if (in_array($fname, ['fopen', 'file_get_contents', 'file_put_contents', 'file', 'readfile'], true) && !empty($node->args[0])) {
                $arg = $node->args[0]->value;
                if ($arg instanceof String_) {
                    $path = $arg->value;
                    foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                        if (stripos($path, $prefix) === 0) {
                            $this->report('always_forbidden_wrapper_stream', [
                                'function' => $fname, 'value' => $path
                            ], $node);
                        }
                    }
                }
            }
        }

        if ($node instanceof Eval_) {
            $this->report(
                'always_forbidden_function',
                ['function' => 'eval'],
                $node,
                'critical'
            );
        }

        // B. Reflection classes (instantiation, static, instanceof, type hint)
        if (
            ($node instanceof New_ && $this->isReflectionClass($node->class)) ||
            ($node instanceof StaticCall && $this->isReflectionClass($node->class)) ||
            ($node instanceof Node\Expr\Instanceof_ && $this->isReflectionClass($node->class)) ||
            ($node instanceof Node\Param && $node->type && $this->isReflectionClass($node->type))
        ) {
            $class = $this->getClassName($node->class ?? $node->type);
            $this->report('always_forbidden_reflection', ['class' => $class], $node);
        }

        // C. Forbidden magic method definitions
        if ($node instanceof Node\Stmt\ClassMethod) {
            $mname = strtolower($node->name->toString());
            if (in_array($mname, $this->policy->getForbiddenMagicMethods(), true)) {
                $this->report('always_forbidden_magic_method', ['method' => $node->name->toString()], $node);
            }
        }

        // D. Dynamic includes/requires
        if ($node instanceof Node\Expr\Include_) {
            if (!($node->expr instanceof String_)) {
                $this->report('always_forbidden_dynamic_include', [
                    'expr_type' => get_class($node->expr)
                ], $node);
            } else {
                $path = $node->expr->value;
                foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                    if (stripos($path, $prefix) === 0) {
                        $this->report('always_forbidden_wrapper_stream_include', [
                            'value' => $path
                        ], $node);
                    }
                }
            }
        }

        // E. Callback/handler registration with forbidden function
        if ($node instanceof Node\Expr\FuncCall) {
            $regName = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;
            if (in_array($regName, [
                    'register_shutdown_function',
                    'set_error_handler',
                    'set_exception_handler',
                    'register_tick_function'
                ], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if (in_array($cbFunc, $this->policy->getForbiddenFunctions(), true)) {
                        $this->report('always_forbidden_callback_to_forbidden_function', [
                            'registration' => $regName, 'callback' => $cbFunc
                        ], $node);
                    }
                }
            }
        }

        // F. Obfuscated eval (eval(obfuscator(...)))
        if ($node instanceof Eval_) {
            $payload = $node->expr;

            // 1) Single-level: eval(obfuscator(...))
            if ($payload instanceof Node\Expr\FuncCall && $payload->name instanceof Node\Name) {
                $inner = strtolower($this->fqNameOf($payload->name) ?? '');
                if ($inner && in_array($inner, $this->policy->getObfuscators(), true)) {
                    $this->report('always_forbidden_obfuscated_eval', [
                        'outer' => 'eval',
                        'inner' => $inner
                    ], $node, 'critical');
                }
            }

            // 2) Nested chains: eval(gzinflate(base64_decode(...)))
            $chain = $this->callGraphAnalyzer->collectFuncCallChain($payload); // ['gzinflate','base64_decode', ...]
            if ($chain && count($chain) > 1 && array_intersect($chain, $this->policy->getObfuscators())) {
                $this->report('always_forbidden_obfuscated_eval', [
                    'outer' => 'eval',
                    'chain' => $chain
                ], $node, 'critical');
            }
        }

        // -- 2. CONFIGURABLE DANGEROUS/POLICY CHECKS --
        // A. Dangerous/risky functions (from config overlays)
        if ($node instanceof Node\Expr\FuncCall) {
            $fname = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($fname) {
                $cfgDanger = array_map('strtolower', $this->config['dangerous_functions'] ?? []);
                $cfgTokens = array_map('strtolower', $this->config['tokens'] ?? []);

                if (in_array($fname, $cfgDanger, true)) {
                    $this->report('config_dangerous_function', ['function' => $fname], $node);
                }
                if (in_array($fname, $cfgTokens, true)) {
                    $this->report('config_risky_function', ['function' => $fname], $node);
                }
            }
        }

        // B. Class/method blocklist (effective allowlist)
        if ($node instanceof StaticCall) {
            $class = $this->getClassName($node->class); // keep: already upgraded
            $method = $node->name instanceof Identifier ? strtolower($node->name->toString()) : null;

            if ($class && $method) {
                $blocklist = $this->policy->getBlocklist(); // merged with overrides
                if (isset($blocklist[$class])) {
                    $allowed = $blocklist[$class];
                    if (!in_array('*', $allowed, true) && !in_array($method, $allowed, true)) {
                        $this->report('config_blocked_method', [
                            'class' => $class, 'method' => $method
                        ], $node);
                    }
                }
            }
        }

        // C. Warn on large files (scan_size)
        if (isset($this->config['scan_size']) && $this->currentFile) {
            $ext = strtolower(pathinfo($this->currentFile, PATHINFO_EXTENSION));
            if (isset($this->config['scan_size'][$ext])) {
                $max = (int)$this->config['scan_size'][$ext];
                $size = @filesize($this->currentFile);
                if ($size !== false && $size > $max) {
                    $this->report('config_file_too_large', [
                        'file' => $this->currentFile, 'max_bytes' => $max
                    ], $node);
                }
            }
        }

        // -- 3. ADVANCED BACKDOOR/HEURISTIC CHECKS (SAMPLE) --
        $this->runBlocklist($node);
        $this->runNamespaceCheck($node);
        $this->advancedBackdoorDetection($node);
    }

    // Helper: check if class is Reflection*

    /** Return fully-qualified class-like names from a type-ish node. */
    private function extractTypeNames(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): array
    {
        // Fully-qualified or resolved simple names
        if ($node instanceof Name || $node instanceof Identifier) {
            $n = $this->fqNameOf($node);
            return $n ? [ltrim($n, '\\')] : [];
        }

        // ?T
        if ($node instanceof NullableType) {
            return $this->extractTypeNames($node->type);
        }

        // T1|T2
        if ($node instanceof UnionType) {
            $out = [];
            foreach ($node->types as $t) {
                foreach ($this->extractTypeNames($t) as $n) {
                    $out[] = $n;
                }
            }
            return array_values(array_unique($out));
        }

        if ($node instanceof IntersectionType) {
            $out = [];
            foreach ($node->types as $t) {
                foreach ($this->extractTypeNames($t) as $n) {
                    $out[] = $n;
                }
            }
            return array_values(array_unique($out));
        }

        // plain string (rare here)
        if (is_string($node)) {
            return [ltrim($node, '\\')];
        }

        // Anything dynamic (Variable/Expr/etc.) → we can’t resolve safely
        return [];
    }

    /** True if any resolved name is a Reflection* class (policy-driven). */
    private function isReflectionClass(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): bool
    {
        foreach ($this->extractTypeNames($node) as $fqcn) {
            if ($this->policy->isForbiddenReflection($fqcn)) {
                return true;
            }
        }
        return false;
    }

    /** Safe, best-effort single class-like name (for reporting). */
    private function safeClassLikeName(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): ?string
    {
        $names = $this->extractTypeNames($node);
        return $names[0] ?? null;
    }

    // Helper: get class name from node/identifier/string
    private function getClassName($classNode): ?string
    {
        return $this->fqNameOf($classNode);
    }

    // Resolve class using aliases map
    private function resolveClassName($classNode)
    {
        $class = $this->getClassName($classNode);
        if ($class && isset($this->aliases[$class])) return $this->aliases[$class];
        return $class;
    }

    private function stringifyConcat($expr): string
    {
        // Recursively flatten simple string concat
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->stringifyConcat($expr->left) . $this->stringifyConcat($expr->right);
        }
        if ($expr instanceof String_) {
            return $expr->value;
        }
        return '{dynamic}';
    }

    // Report a finding
    private function report($type, $data, $node, $severity = 'high'): void
    {
        $this->matches[] = array_merge([
            'type' => $type,
            'severity' => $severity,
            'line' => $node->getLine(),
            'file' => $this->currentFile,
        ], $data);
    }

    public function getMatches(): array
    {
        return $this->matches;
    }

    protected function runBlocklist($node): void
    {
        // Static calls: \Vendor\Class::method()
        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $class = $this->getClassName($node->class); // resolver-aware in your code
            $meth = strtolower($node->name->toString());
            $this->enforceClassAllowlist($class, $meth, $node, true);
        }

        // Instance calls: $this->method()
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $meth = strtolower($node->name->toString());

            // $this->method() → enclosing class
            if ($node->var instanceof Variable && $node->var->name === 'this') {
                $class = $this->enclosingClassName($node);
                $this->enforceClassAllowlist($class, $meth, $node, false);
            }

            // $x->method() → if we know $x is an instance of FQCN
            if ($node->var instanceof Variable && is_string($node->var->name)) {
                $fq = $this->variableTypes[$node->var->name] ?? null;
                if ($fq) {
                    $this->enforceClassAllowlist($fq, $meth, $node, false);
                }
            }
        }

        // Nullsafe calls: $x?->method()
        if ($node instanceof NullsafeMethodCall && $node->name instanceof Identifier) {
            $meth = strtolower($node->name->toString());
            if ($node->var instanceof Variable && is_string($node->var->name)) {
                $fq = $this->variableTypes[$node->var->name] ?? null;
                if ($fq) {
                    $this->enforceClassAllowlist($fq, $meth, $node, false);
                }
            }
        }
    }

    private function enforceClassAllowlist(?string $class, string $method, Node $node, bool $isStatic): void
    {
        if (!$class) return;

        $fqcn = ltrim($class, '\\');
        $key = $this->normClassKey($fqcn);

        // Only enforce for classes present in the policy map
        if (!array_key_exists($key, $this->classAllowlist)) return;

        $allowed = $this->classAllowlist[$key];

        // Semantics: if a class is listed, ONLY these methods are allowed.
        // An empty array => no methods allowed.
        if (!in_array($method, $allowed, true)) {
            $this->report(
                'config_blocked_method',
                ['class' => $fqcn, 'method' => $method, 'call' => $isStatic ? 'static' : 'instance'],
                $node,
                'critical'
            );
        }
    }

    protected function runForbiddenFuncCall($node, $checkReturns = false): void
    {
        $calledName = null;

        // direct eval
        if ($node instanceof Eval_) {
            $this->report('return_forbidden_function', ['function' => 'eval'], $node, 'critical');
            return;
        }

        // plain function call: foo()
        if ($node instanceof Node\Expr\FuncCall) {
            $calledName = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($calledName && ($this->policy->isForbiddenFunction($calledName) || $this->policy->isUnsupportedFunction($calledName))) {
                $this->report('return_forbidden_function', ['function' => $calledName], $node, 'critical');
            }

            if ($checkReturns && $calledName && isset($this->callGraphAnalyzer) &&
                $this->callGraphAnalyzer->hasForbiddenReturnChain($calledName)) {
                $this->report('return_indirect_forbidden_chain', ['chain' => $calledName], $node, 'critical');
            }

            return; // handled
        }

        if (!$checkReturns || !isset($this->callGraphAnalyzer)) {
            return;
        }

        // static method call: ClassName::method()
        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $class = $this->getClassName($node->class); // already resolver-aware in your codebase
            $method = strtolower($node->name->toString());

            if ($class && $method &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($class, $method)) {
                $this->report('return_indirect_forbidden_chain', [
                    'chain' => $class . '::' . $method
                ], $node, 'critical');
            }
            return;
        }

        // instance method call on $this: $this->method()
        if ($node instanceof MethodCall && $node->name instanceof Identifier && $node->var instanceof Variable && $node->var->name === 'this') {
            $classNode = $node->getAttribute('parent_class'); // set in scanSource() prepass
            $className = null;
            if ($classNode instanceof Node\Stmt\Class_) {
                // prefer namespacedName from NameResolver; fallback to local name
                $className = isset($classNode->namespacedName)
                    ? ltrim($classNode->namespacedName->toString(), '\\')
                    : ($classNode->name?->toString());
            }

            $method = strtolower($node->name->toString());

            if ($className && $method &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $method)) {
                $this->report('return_indirect_forbidden_chain', [
                    'chain' => $className . '::' . $method
                ], $node, 'critical');
            }
        }
    }

    protected function runNamespaceCheck(Node $node): void
    {
        // Helper: normalized check against policy (after NameResolver)
        $isForbidden = function (?string $ns): bool {
            if (!$ns) return false;
            $ns = ltrim($ns, '\\');
            return $ns !== '' && $this->policy->isForbiddenNamespace($ns);
        };

        // 1) use Foo\Bar;  use function Foo\bar;  use const Foo\BAR;
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                // Note: NameResolver does not set resolvedName for use-imports; build from token.
                $full = ltrim($this->fqNameOf($use->name) ?? $use->name->toString(), '\\');

                $kind = match ($use->type) {
                    Node\Stmt\Use_::TYPE_FUNCTION => 'function',
                    Node\Stmt\Use_::TYPE_CONSTANT => 'const',
                    default => 'class',
                };

                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_import' . ($kind !== 'class' ? "_$kind" : ''),
                        ['namespace' => $full, 'kind' => $kind],
                        $node,
                        'critical'
                    );
                }
            }
            return;
        }

        // 1b) use Prefix\{A, B as C, function f, const X};
        if ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $this->fqNameOf($node->prefix) ?? $node->prefix->toString();
            $prefix = rtrim($prefix, '\\');

            foreach ($node->uses as $use) {
                // Each leaf can optionally carry its own type; fall back to group type.
                $type = ($use->type !== 0) ? $use->type : $node->type;
                $kind = match ($type) {
                    Node\Stmt\Use_::TYPE_FUNCTION => 'function',
                    Node\Stmt\Use_::TYPE_CONSTANT => 'const',
                    default => 'class',
                };

                $leaf = $use->name->toString();              // e.g. "DB" or "Route"
                $full = ltrim($prefix . '\\' . $leaf, '\\'); // Prefix\Leaf

                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_import' . ($kind !== 'class' ? "_$kind" : ''),
                        ['namespace' => $full, 'kind' => $kind],
                        $node,
                        'critical'
                    );
                }
            }
            return;
        }

        // 1c) Trait imports inside classes: use Some\TraitName;
        if ($node instanceof Node\Stmt\TraitUse) {
            foreach ($node->traits as $t) {
                $full = ltrim($this->fqNameOf($t) ?? $t->toString(), '\\');
                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_trait_use',
                        ['namespace' => $full],
                        $node,
                        'critical'
                    );
                }
            }
            // continue scanning other checks below (no early return)
        }

        // 2) References in expressions: new, static call, const fetch, instanceof
        if (
            $node instanceof New_
            || $node instanceof StaticCall
            || $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\Instanceof_
        ) {
            $class = $this->getClassName($node->class ?? null); // resolver-aware in your codebase
            if ($isForbidden($class)) {
                $this->report(
                    'forbidden_namespace_reference',
                    ['namespace' => $class],
                    $node,
                    'critical'
                );
            }
        }

        // 3) Class extends / implements
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->extends) {
                $parent = ltrim($this->fqNameOf($node->extends) ?? $node->extends->toString(), '\\');
                if ($isForbidden($parent)) {
                    $this->report('forbidden_namespace_extends', ['namespace' => $parent], $node, 'critical');
                }
            }
            foreach ($node->implements as $impl) {
                $iface = ltrim($this->fqNameOf($impl) ?? $impl->toString(), '\\');
                if ($isForbidden($iface)) {
                    $this->report('forbidden_namespace_implements', ['namespace' => $iface], $node, 'critical');
                }
            }
        }

        // 4) String references to classes (e.g. "$c = 'GuzzleHttp\\Client';")
        if ($node instanceof String_) {
            $str = ltrim($node->value, '\\');
            if ($isForbidden($str)) {
                $this->report(
                    'forbidden_namespace_string_reference',
                    ['namespace' => $node->value],
                    $node
                );
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function advancedBackdoorDetection($node): void
    {
        // Backdoor 1 - Variable/dynamic function calls
        if ($node instanceof Node\Expr\FuncCall) {
            // 1. Variable function: $func()
            if ($node->name instanceof Variable) {
                $funcVar = $node->name->name;
                $resolved = $this->resolvedVarString($funcVar);

                $reportType = 'backdoor_variable_function_call';
                $severity = 'high';
                $extra = [
                    'var' => is_string($funcVar) ? $funcVar : json_encode($funcVar, JSON_THROW_ON_ERROR)
                ];

                if ($resolved && $this->policy->isForbiddenFunction($resolved)) {
                    $severity = 'critical';
                    $reportType = 'backdoor_variable_function_call_chain_forbidden';
                    $extra['resolved_function'] = $resolved;
                } elseif ($resolved && isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($resolved)) {
                    $severity = 'critical';
                    $reportType = 'backdoor_variable_function_call_chain_forbidden';
                    $extra['resolved_function'] = $resolved;
                }

                $this->report($reportType, $extra, $node, $severity);
            }

            // 2. Dynamic concat: ("eva"."l")()
            if ($node->name instanceof Node\Expr\BinaryOp\Concat) {
                $exprStr = strtolower($this->stringifyConcat($node->name));
                $severity = 'high';
                $type = 'backdoor_concat_function_call_unknown';

                if ($this->policy->isForbiddenFunction($exprStr)) {
                    $severity = 'critical';
                    $type = 'backdoor_concat_function_call_always_forbidden';
                } elseif ($this->policy->isUnsupportedFunction($exprStr)) {
                    $type = 'backdoor_concat_function_call_unsupported';
                } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($exprStr)) {
                    $severity = 'critical';
                    $type = 'backdoor_concat_function_call_chain_forbidden';
                }

                $this->report($type, ['expression' => $exprStr], $node, $severity);
            }

            // 3. Direct call by resolved name
            if ($node->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
                if ($this->policy->isForbiddenFunction($name)) {
                    $this->report('always_forbidden_function', ['function' => $name], $node, 'critical');
                } elseif ($this->policy->isUnsupportedFunction($name)) {
                    $this->report('unsupported_function', ['function' => $name], $node);
                } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    $this->report('function_call_chain_forbidden', ['function' => $name], $node, 'critical');
                }
            }
        }

        // Backdoor 2 - Closures & callbacks
        if ($node instanceof Node\Expr\FuncCall) {
            $callbackFunctions = array_map('strtolower', $this->policy->getCallbackFunctions());
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;

            if ($name && in_array($name, $callbackFunctions, true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;

                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc)) {
                        $this->report('callback_always_forbidden', [
                            'function' => $name, 'callback' => $cbFunc
                        ], $node, 'critical');
                    } elseif ($this->policy->isUnsupportedFunction($cbFunc)) {
                        $this->report('callback_unsupported', [
                            'function' => $name, 'callback' => $cbFunc
                        ], $node);
                    } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($cbFunc)) {
                        $this->report('callback_user_defined_forbidden_chain', [
                            'function' => $name,
                            'callback_chain' => $cbFunc
                        ], $node, 'critical');
                    }
                } elseif ($cb instanceof Node\Expr\Closure || $cb instanceof Node\Expr\ArrowFunction) {
                    $danger = false;
                    foreach ($cb->getStmts() as $stmt) {
                        $danger = $this->closureScan($stmt);
                        if ($danger) break;
                    }
                    if ($danger) {
                        $this->report('callback_closure_forbidden', [
                            'function' => $name,
                            'closure_dangerous' => true
                        ], $node, 'critical');
                    }
                }
            }
        }

        // Backdoor 3 - Dynamic class instantiation
        if ($node instanceof New_) {
            if ($node->class instanceof Variable) {
                $classVar = $node->class->name;
                $resolved = is_string($classVar) ? ($this->variableValues[$classVar] ?? null) : null;

                if ($resolved === '{superglobal}') {
                    $this->report('backdoor_dynamic_class_instantiation_superglobal', [
                        'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                    ], $node, 'critical');
                } elseif ($resolved) {
                    if ($this->policy->isForbiddenReflection($resolved) || $this->policy->isForbiddenNamespace(ltrim($resolved, '\\'))) {
                        $this->report('backdoor_dynamic_class_instantiation_forbidden', [
                            'resolved_class' => $resolved,
                            'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                        ], $node, 'critical');
                    }
                } else {
                    $this->report('backdoor_dynamic_class_instantiation_unresolved', [
                        'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                    ], $node);
                }
            } elseif (!($node->class instanceof Node\Name)) {
                $classStr = $this->stringifyDynamic($node->class);
                $this->report('backdoor_dynamic_class_instantiation_complex', [
                    'expression' => $classStr,
                ], $node);
            }
        }

        // Backdoor 4 & 11 (unified) - Dynamic member access (method/property)
        if ($node instanceof MethodCall && $node->name instanceof Variable) {
            $methodVar = $node->name->name;
            $resolved = $this->resolvedVarString($methodVar);
            $className = $this->enclosingClassName($node);

            $this->handleDynamicMember('method', $className, $resolved, '$' . (is_string($methodVar) ? $methodVar : '{dynamic}'), $node);
        } elseif ($node instanceof MethodCall && !($node->name instanceof Identifier)) {
            $methodStr = $this->stringifyDynamic($node->name);
            $this->report('backdoor_dynamic_method_call_complex', ['expression' => $methodStr], $node);
        }

        if ($node instanceof Node\Expr\PropertyFetch && $node->name instanceof Variable) {
            $propVar = $node->name->name;
            $resolved = $this->resolvedVarString($propVar);
            $className = $this->enclosingClassName($node);

            $this->handleDynamicMember('property', $className, $resolved, '$obj->$' . (is_string($propVar) ? $propVar : '{dynamic}'), $node);
        }

        if ($node instanceof Node\Expr\StaticPropertyFetch && $node->name instanceof Variable) {
            $var = $node->name->name;
            $this->report('dynamic_static_property_access', [
                'expression' => '::$' . (is_string($var) ? $var : '{dynamic}')
            ], $node);
        }

        if ($node instanceof Variable && is_object($node->name)) {
            $this->report('variable_variable_usage', [
                'expression' => '$$' . $this->stringifyDynamic($node->name)
            ], $node);
        }

        // Backdoor 5 - Forbidden magic methods (scan body)
        if ($node instanceof Node\Stmt\ClassMethod) {
            $name = strtolower($node->name->toString());
            if (in_array($name, $this->policy->getForbiddenMagicMethods(), true)) {
                $dangerInfo = $this->scanMagicMethodBody($node);
                $this->report('magic_method_defined', [
                    'method' => $name,
                    'dangerous_content' => $dangerInfo['danger'],
                    'explanation' => $dangerInfo['explanation']
                ], $node, $dangerInfo['severity']);
            }
        }

        // Backdoor 6 - Reflection usage
        if (
            ($node instanceof New_ && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof StaticCall && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof Node\Expr\Instanceof_ && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof Node\Param && $node->type && $this->policy->isForbiddenReflection($this->getClassName($node->type)))
        ) {
            $class = $this->getClassName($node->class ?? $node->type) ?? '{dynamic}';
            $this->report('reflection_usage', ['class' => $class], $node, 'critical');
        }

        // Backdoor 7 - File includes
        if ($node instanceof Node\Expr\Include_) {
            if ($node->expr instanceof String_) {
                $path = $node->expr->value;
                foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                    if (stripos($path, $prefix) === 0) {
                        $this->report('include_forbidden_wrapper', ['value' => $path], $node, 'critical');
                    }
                }
            } else {
                $exprString = $this->stringifyDynamic($node->expr);
                $resolved = null;
                $varName = null;
                if ($node->expr instanceof Variable && is_string($node->expr->name)) {
                    $varName = $node->expr->name;
                    $resolved = $this->variableValues[$varName] ?? null;
                }

                if ($resolved === '{superglobal}') {
                    $this->report('include_dynamic_path_superglobal', [
                        'expression' => $varName ? ('$' . $varName) : '{dynamic}'
                    ], $node, 'critical');
                } else {
                    $this->report('include_dynamic_path', ['expression' => $exprString], $node);
                }
            }
        }

        // Backdoor 8 - Obfuscators
        if ($node instanceof Node\Expr\FuncCall) {
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;
            if ($name && in_array($name, $this->policy->getObfuscators(), true)) {
                $this->report('obfuscation_function', ['function' => $name], $node);
            }
        }

        // Backdoor 9 - Anonymous class / closure leakage
        if ($node instanceof New_ && $node->class instanceof Class_) {
            $danger = $this->scanAnonymousClass($node->class);
            $this->report('anonymous_class_leak', ['dangerous_content' => $danger], $node, $danger ? 'critical' : 'info');
        }
        if ($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction) {
            $danger = $this->scanClosureBody($node);
            $this->report('anonymous_function_leak', ['dangerous_content' => $danger], $node, $danger ? 'critical' : 'info');
        }

        // Backdoor 10 - Assignments to superglobals
        if ($node instanceof Node\Expr\Assign
            && $node->var instanceof Node\Expr\ArrayDimFetch
            && $node->var->var instanceof Variable) {

            $arrayName = $node->var->var->name;
            if (in_array($arrayName, ['GLOBALS', '_SESSION', '_ENV', '_SERVER'], true)) {
                $danger = $this->containsDangerousValue($node->expr);
                $this->report('global_or_session_leak', [
                    'array' => '$' . $arrayName,
                    'dangerous_content' => $danger,
                ], $node, $danger ? 'critical' : 'high');
            }
        }

        if ($node instanceof Node\Stmt\Static_) {
            foreach ($node->vars as $staticVar) {
                $danger = $this->containsDangerousValue($staticVar->default);
                $this->report('static_variable_leak', [
                    'var' => $staticVar->var->name,
                    'dangerous_content' => $danger,
                ], $node, $danger ? 'critical' : 'high');
            }
        }

        // Backdoor 12 - Chained/indirect returns
        if ($node instanceof Node\Stmt\Return_) {
            $expr = $node->expr;

            // Direct: return new ForbiddenClass();
            if ($expr instanceof New_) {
                $class = $this->getClassName($expr->class);
                if ($class && ($this->policy->isForbiddenReflection($class) || $this->policy->isForbiddenNamespace($class))) {
                    $this->report('return_forbidden_class', ['class' => $class], $node, 'critical');
                }
            }

            // Direct/indirect via function
            $this->runForbiddenFuncCall($expr, true);

            // Indirect: return $this->method()
            if ($expr instanceof MethodCall && $expr->name instanceof Identifier) {
                $className = $this->enclosingClassName($node);
                $methName = strtolower($expr->name->toString());
                if ($className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $methName)) {
                    $this->report('return_indirect_forbidden_method_chain', [
                        'chain' => $className . '::' . $methName
                    ], $node, 'critical');
                }
            }

            // Indirect: return SomeClass::method()
            if ($expr instanceof StaticCall && $expr->name instanceof Identifier) {
                $className = $this->getClassName($expr->class);
                $methName = strtolower($expr->name->toString());
                if ($className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $methName)) {
                    $this->report('return_indirect_forbidden_method_chain', [
                        'chain' => $className . '::' . $methName
                    ], $node, 'critical');
                }
            }
        }
    }

    /* ====================== helpers to DRY Backdoor 4 & 11 ====================== */

    private function enclosingClassName(Node $node): ?string
    {
        $classNode = $node->getAttribute('parent_class');
        if ($classNode instanceof Node\Stmt\Class_) {
            return isset($classNode->namespacedName)
                ? ltrim($classNode->namespacedName->toString(), '\\')
                : ($classNode->name?->toString());
        }
        return null;
    }

    /** Return strtolower($this->variableValues[$name]) or null, safely. */
    private function resolvedVarString($name): ?string
    {
        if (!is_string($name)) return null;
        $v = $this->variableValues[$name] ?? null;
        if (!is_string($v)) return null;
        $v = strtolower($v);
        return $v !== '' ? $v : null;
    }

    /**
     * Unified handler for dynamic member access.
     * $kind: 'method'|'property'
     */
    private function handleDynamicMember(string $kind, ?string $className, ?string $resolved, string $exprLabel, Node $node): void
    {
        if ($resolved === '{superglobal}') {
            $this->report(
                $kind === 'method' ? 'backdoor_dynamic_method_call_superglobal' : 'dynamic_property_access_superglobal',
                ['expression' => $exprLabel],
                $node,
                'critical'
            );
            return;
        }

        if ($resolved === null) {
            $this->report(
                $kind === 'method' ? 'backdoor_dynamic_method_call_unresolved' : 'dynamic_property_access',
                ['expression' => $exprLabel],
                $node
            );
            return;
        }

        if ($kind === 'method') {
            if ($this->policy->isForbiddenFunction($resolved) || $this->policy->isUnsupportedFunction($resolved)) {
                $this->report('backdoor_dynamic_method_call_forbidden', [
                    'resolved_method' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }
            if ($className && isset($this->callGraphAnalyzer) &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {
                $this->report('backdoor_dynamic_method_call_chain_forbidden', [
                    'class' => $className,
                    'resolved_method' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }

            // else: benign/suspicious dynamic call, no report needed beyond the general one already emitted
            return;
        }

        // property kind — treat resolved property as potential method reference
        if ($className && isset($this->callGraphAnalyzer)) {
            $defs = $this->callGraphAnalyzer->getMethodDefs($className);
            if (isset($defs[strtolower($resolved)]) &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {
                $this->report('dynamic_property_access_chain_forbidden', [
                    'class' => $className,
                    'resolved_property' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }
        }

        // Default informational report for dynamic property access
        $this->report('dynamic_property_access', ['expression' => $exprLabel], $node);
    }

    // Helper to scan closure body for forbidden/unsupported function calls
    private function closureScan(Node $node): bool
    {
        // Direct forbidden/unsupported function call
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
            if ($this->policy->isForbiddenFunction($name)) {
                $this->report('closure_calls_always_forbidden', ['function' => $name], $node, 'critical');
                return true;
            }
            if ($this->policy->isUnsupportedFunction($name)) {
                $this->report('closure_calls_unsupported', ['function' => $name], $node);
                return true;
            }
            // Analyzer: user-defined function returns forbidden
            if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                $this->report('closure_calls_forbidden_chain', ['function' => $name], $node, 'critical');
                return true;
            }
        }

        // Recursively scan nested nodes
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                if ($this->closureScan($child)) return true;
            } elseif (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node && $this->closureScan($c)) return true;
                }
            }
        }
        return false;
    }

    private function stringifyDynamic($expr): string
    {
        if ($expr instanceof Variable) {
            return '$' . (is_string($expr->name) ? $expr->name : '{complex}');
        }
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->stringifyDynamic($expr->left) . $this->stringifyDynamic($expr->right);
        }
        if ($expr instanceof String_) {
            return $expr->value;
        }
        if ($expr instanceof Node\Scalar\Encapsed) {
            $parts = [];
            foreach ($expr->parts as $p) {
                if ($p instanceof Node\Scalar\EncapsedStringPart) {
                    $parts[] = $p->value;
                } elseif ($p instanceof Variable) {
                    $parts[] = '$' . (is_string($p->name) ? $p->name : '{var}');
                } else {
                    $parts[] = '{expr}';
                }
            }
            return implode('', $parts);
        }
        return '{dynamic}';
    }

    /**
     * Scan a magic method body for dangerous patterns.
     * @return array{danger: bool, severity: string, explanation: string}
     */
    private function scanMagicMethodBody(Node\Stmt\ClassMethod $node): array
    {
        $danger = false;
        $severity = 'low';
        $explanation = '';

        foreach ($node->getStmts() ?? [] as $stmt) {
            // First pass: generic analyzer for this statement (recursive)
            $check = $this->analyzeMagicBodyStmt($stmt);
            if ($check['danger']) {
                return $check;
            }

            // Analyzer integration: $this->$name() inside magic method
            if ($stmt instanceof MethodCall
                && $stmt->var instanceof Variable
                && $stmt->var->name === 'this'
                && $stmt->name instanceof Variable) {

                $methodVar = $stmt->name->name;
                $resolved = $this->resolvedVarString($methodVar);
                $classNode = $node->getAttribute('parent_class');
                $className = null;

                if ($classNode instanceof Node\Stmt\Class_) {
                    $className = isset($classNode->namespacedName)
                        ? ltrim($classNode->namespacedName->toString(), '\\')
                        : ($classNode->name?->toString());
                }

                if ($resolved && $className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {

                    return [
                        'danger' => true,
                        'severity' => 'critical',
                        'explanation' => "Dynamic call to forbidden chain ($className::$resolved) via magic method"
                    ];
                }
            }
        }

        return [
            'danger' => false,
            'severity' => $severity,
            'explanation' => "No dangerous dynamic calls"
        ];
    }

    /**
     * Recursive inspector for magic method statements.
     * @return array{danger: bool, severity: string, explanation: string}
     */
    private function analyzeMagicBodyStmt(Node $node): array
    {
        // Direct forbidden/unsupported function call
        if ($node instanceof Node\Expr\FuncCall) {
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;

            if ($name && ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name))) {
                return ['danger' => true, 'severity' => 'critical', 'explanation' => 'Direct forbidden/unsupported function called'];
            }

            // call_user_func / call_user_func_array checks
            if (in_array($name, ['call_user_func', 'call_user_func_array'], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc) || $this->policy->isUnsupportedFunction($cbFunc)) {
                        return ['danger' => true, 'severity' => 'critical', 'explanation' => "call_user_func to forbidden/unsupported"];
                    }
                    if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($cbFunc)) {
                        return ['danger' => true, 'severity' => 'critical', 'explanation' => "call_user_func to forbidden chain"];
                    }
                } elseif ($cb instanceof Variable) {
                    return ['danger' => true, 'severity' => 'high', 'explanation' => "call_user_func to unknown variable"];
                }
            }
        }

        // Variable/dynamic method or function name in magic method
        if (
            ($node instanceof MethodCall && !($node->name instanceof Identifier)) ||
            ($node instanceof Node\Expr\FuncCall && !($node->name instanceof Node\Name))
        ) {
            return ['danger' => true, 'severity' => 'high', 'explanation' => "Dynamic method/function call in magic method"];
        }

        // Recurse into children
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                $check = $this->analyzeMagicBodyStmt($child);
                if ($check['danger']) return $check;
            } elseif (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node) {
                        $check = $this->analyzeMagicBodyStmt($c);
                        if ($check['danger']) return $check;
                    }
                }
            }
        }

        return ['danger' => false, 'severity' => 'low', 'explanation' => "No dangerous dynamic calls"];
    }

    private function scanAnonymousClass(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($this->magicMethodContainsDanger($method)) {
                return true;
            }
        }
        return false;
    }

    private function scanClosureBody($closure): bool
    {
        if (!($closure instanceof Node\Expr\Closure)) return false;
        foreach ($closure->getStmts() ?? [] as $stmt) {
            // Direct forbidden/unsupported function call, or forbidden chain
            if ($stmt instanceof Node\Expr\FuncCall && $stmt->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($stmt->name) ?? $stmt->name->toString());
                if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    return true;
                }
            }
            // Recurse
            foreach ($stmt->getSubNodeNames() as $sub) {
                $child = $stmt->$sub;
                if ($child instanceof Node) {
                    if ($this->scanClosureBody($child)) return true;
                } elseif (is_array($child)) {
                    foreach ($child as $c) {
                        if ($c instanceof Node && $this->scanClosureBody($c)) return true;
                    }
                }
            }
        }
        return false;
    }

    private function magicMethodContainsDanger(Node $node): bool
    {
        // Direct forbidden/unsupported calls
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
            if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                return true;
            }
            if (in_array($name, ['call_user_func', 'call_user_func_array'], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc) || $this->policy->isUnsupportedFunction($cbFunc)) {
                        return true;
                    }
                }
            }
        }

        // Variable function/method calls ($this->{$x}(), $fn(), etc.)
        if ($node instanceof MethodCall || $node instanceof Node\Expr\FuncCall) {
            if (!($node->name instanceof Identifier) && !($node->name instanceof Node\Name)) {
                return true;
            }
        }

        // Recurse
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node && $this->magicMethodContainsDanger($child)) {
                return true;
            }
            if (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node && $this->magicMethodContainsDanger($c)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function containsDangerousValue($expr): bool
    {
        // Direct: new {Class}
        if ($expr instanceof New_) {
            $class = $this->getClassName($expr->class);
            return $class && ($this->policy->isForbiddenNamespace($class) || $this->policy->isForbiddenReflection($class));
        }

        // Direct: forbidden/unsupported function call
        if ($expr instanceof Node\Expr\FuncCall) {
            if ($expr->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($expr->name) ?? $expr->name->toString());
                if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    return true;
                }
            } else {
                // Variable/dynamic function in value context — err on safe side
                return true;
            }
        }

        // Closure/arrow fn — scan body
        if ($expr instanceof Node\Expr\Closure || $expr instanceof Node\Expr\ArrowFunction) {
            foreach ($expr->getStmts() ?? [] as $stmt) {
                if ($this->scanClosureBody($stmt)) return true;
            }
        }

        // Array literal — recurse
        if ($expr instanceof Node\Expr\Array_) {
            foreach ($expr->items as $item) {
                if ($item && $this->containsDangerousValue($item->value)) return true;
            }
        }

        // Variable — try resolve a tracked value that might be callable
        if ($expr instanceof Variable && is_string($expr->name)) {
            $resolved = $this->variableValues[$expr->name] ?? null;
            if (is_string($resolved)) {
                $resolvedLc = strtolower($resolved);
                if ($this->policy->isForbiddenFunction($resolvedLc) || $this->policy->isUnsupportedFunction($resolvedLc)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($resolvedLc)) {
                    return true;
                }
            }
        }

        return false;
    }
}