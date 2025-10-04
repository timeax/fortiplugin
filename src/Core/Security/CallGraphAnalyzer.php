<?php /** @noinspection GrazieInspection */

namespace Timeax\FortiPlugin\Core\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Timeax\FortiPlugin\Core\PluginPolicy;
use Timeax\FortiPlugin\Core\Security\Concerns\ResolvesNames;

class CallGraphAnalyzer
{
    use ResolvesNames;

    protected PluginPolicy $policy;
    protected int $maxDepth;

    /** @var array<string, Node\Stmt\Function_>  function fqn (lc) => node */
    protected array $functionDefs = [];

    /** @var array<string, array<string, Node\Stmt\ClassMethod>> class fqn (lc) => [method (lc) => node] */
    protected array $methodDefs = [];

    public function __construct(PluginPolicy $policy, int $maxDepth = 7)
    {
        $this->policy = $policy;
        $this->maxDepth = $maxDepth;
    }

    /** Debug helper */
    public function getMethodDefs(?string $class = null): array
    {
        if ($class === null) return $this->methodDefs;
        $key = $this->normClass($class);
        return $this->methodDefs[$key] ?? [];
    }

    /**
     * Collect top-level function & class method definitions.
     * Run AFTER NameResolver (recommended), but works without it too.
     *
     * @param array<int,array<int,Node>> $asts
     */
    public function collect(array $asts): void
    {
        foreach ($asts as $stmts) {
            foreach ($stmts as $node) {
                if ($node instanceof Node\Stmt\Function_) {
                    $name = $this->declFuncName($node);
                    if ($name) {
                        $this->functionDefs[strtolower($name)] = $node;
                    }
                } elseif ($node instanceof Node\Stmt\Class_) {
                    $classFqn = $this->declFqcn($node);
                    if (!$classFqn) continue;
                    $classKey = strtolower($classFqn);

                    foreach ($node->getMethods() as $method) {
                        $m = strtolower($method->name->toString());
                        $this->methodDefs[$classKey][$m] = $method;
                        // Tag method with its resolved class (lc fqn) for convenience
                        $method->setAttribute('forti_class', $classKey);
                    }
                }
            }
        }
    }

    /* ==================== public queries ==================== */

    /** Does function (by name) return (directly/indirectly) a forbidden/unsupported surface? */
    public function hasForbiddenReturnChain(string $functionName, array $visited = [], int $depth = 0): bool
    {
        if ($depth > $this->maxDepth) return false;

        // Prefer fully-qualified; accept raw name too (best-effort)
        $fnKey = strtolower(ltrim($functionName, '\\'));
        if (in_array($fnKey, $visited, true)) return false;
        $visited[] = $fnKey;

        $fnNode = $this->functionDefs[$fnKey] ?? null;
        if (!$fnNode) return false;

        foreach ($fnNode->getStmts() as $stmt) {
            if (!$stmt instanceof Node\Stmt\Return_) continue;
            $expr = $stmt->expr;

            if ($this->isForbiddenReturn($expr)) {
                return true;
            }

            // return someOtherFunction(...)
            if ($expr instanceof FuncCall && $expr->name instanceof Name) {
                $called = $this->fqNameOf($expr->name);
                $ckey = $called ? strtolower($called) : null;
                if ($ckey && isset($this->functionDefs[$ckey]) &&
                    $this->hasForbiddenReturnChain($ckey, $visited, $depth + 1)) {
                    return true;
                }
            }

            // return 'exec';
            if ($expr instanceof String_) {
                $s = strtolower($expr->value);
                if ($this->policy->isForbiddenFunction($s) || $this->policy->isUnsupportedFunction($s)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Does class::method return (directly/indirectly) a forbidden/unsupported surface? */
    public function hasForbiddenMethodReturnChain(string $className, string $methodName, array $visited = [], int $depth = 0): bool
    {
        if ($depth > $this->maxDepth) return false;

        $classKey = strtolower($this->normClass($className));
        $methodKey = strtolower($methodName);
        $visitKey = $classKey . '::' . $methodKey;

        if (in_array($visitKey, $visited, true)) return false;
        $visited[] = $visitKey;

        $methNode = $this->methodDefs[$classKey][$methodKey] ?? null;
        if (!$methNode) return false;

        foreach ((array)$methNode->getStmts() as $stmt) {
            if (!$stmt instanceof Node\Stmt\Return_) continue;
            $expr = $stmt->expr;

            if ($this->isForbiddenReturn($expr)) {
                return true;
            }

            // return $this->foo();
            if ($expr instanceof MethodCall
                && $expr->var instanceof Node\Expr\Variable
                && $expr->var->name === 'this'
                && $expr->name instanceof Identifier) {

                $m2 = strtolower($expr->name->toString());
                if (isset($this->methodDefs[$classKey][$m2]) &&
                    $this->hasForbiddenMethodReturnChain($classKey, $m2, $visited, $depth + 1)) {
                    return true;
                }
            }

            // return self::foo() / static::foo() / parent::foo() / FQCN::foo()
            if ($expr instanceof StaticCall && $expr->name instanceof Identifier) {
                $targetClass = $this->resolveStaticClassRef($expr->class, $classKey);
                $m2 = strtolower($expr->name->toString());

                if ($targetClass && isset($this->methodDefs[$targetClass][$m2]) &&
                    $this->hasForbiddenMethodReturnChain($targetClass, $m2, $visited, $depth + 1)) {
                    return true;
                }
            }

            // return 'exec';
            if ($expr instanceof String_) {
                $s = strtolower($expr->value);
                if ($this->policy->isForbiddenFunction($s) || $this->policy->isUnsupportedFunction($s)) {
                    return true;
                }
            }
        }

        return false;
    }

    /* ==================== helpers ==================== */

    /**
     * Is a return-expression itself forbidden/unsupported?
     * eval/new/ClassName and function calls.
     */
    protected function isForbiddenReturn(?Node\Expr $expr): bool
    {
        if (!$expr) return false;

        if ($expr instanceof Eval_) return true;

        if ($expr instanceof New_) {
            $class = $this->fqNameOf($expr->class);
            return $class && (
                    $this->policy->isForbiddenNamespace($class) ||
                    $this->policy->isForbiddenReflection($class)
                );
        }

        if ($expr instanceof FuncCall) {
            $name = $expr->name instanceof Name ? $this->fqNameOf($expr->name) : null;
            $name = $name ? strtolower($name) : null;
            return $name && (
                    $this->policy->isForbiddenFunction($name) ||
                    $this->policy->isUnsupportedFunction($name)
                );
        }

        // (Optional) extend: closures returning forbidden expressions, etc.
        return false;
    }

    /**
     * Collect a simple left-deep call chain: f(g(h($x)))
     * Returns lowercased function names in order: ['f','g','h']
     */
    public function collectFuncCallChain($expr, int $maxDepth = 6): array
    {
        $out = [];
        $depth = 0;
        $cur = $expr;

        while ($cur instanceof FuncCall && $cur->name instanceof Name && $depth < $maxDepth) {
            $name = strtolower($this->fqNameOf($cur->name) ?? '');
            if ($name === '') break;
            $out[] = $name;
            if (empty($cur->args)) break;
            $cur = $cur->args[0]->value; // follow first arg (typical for obfuscators)
            $depth++;
        }

        return $out;
    }
}