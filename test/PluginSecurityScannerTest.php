<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */

use PHPUnit\Framework\TestCase;
use Timeax\FortiPlugin\Core\PluginPolicy;
use Timeax\FortiPlugin\Core\Security\PluginSecurityScanner;

class PluginSecurityScannerTest extends TestCase
{
    protected PluginSecurityScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new PluginSecurityScanner([]);
    }

    // ----------------- Always-forbidden basics -----------------

    public function testDetectsForbiddenFunctionCall()
    {
        $code = '<?php eval("echo hacked;"); ?>';
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'always_forbidden_function', 'critical'));
    }

    public function testDetectsVariableFunctionCallWithForbidden()
    {
        $code = <<<'PHP'
            <?php
            $x = "eval";
            $x("echo hacked;");
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'backdoor_variable_function_call_chain_forbidden', 'critical'));
    }

    public function testDetectsIndirectReturnChain()
    {
        $code = <<<'PHP'
            <?php
            function evil() { return eval("1"); }
            function wrapper() { return evil(); }
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'return_indirect_forbidden_chain', 'critical'));
    }

    public function testAllowsSafeFunctionCall()
    {
        $code = '<?php strtoupper("hello"); ?>';
        $result = $this->scanner->scanSource($code);
        $this->assertFalse($this->hasReport($result, 'always_forbidden_function', 'critical'));
    }

    public function testWrapperStreamsInFileOps()
    {
        $code = <<<'PHP'
            <?php
            file_get_contents('php://input');
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'always_forbidden_wrapper_stream', 'high'));
    }

    public function testReflectionInstantiation()
    {
        $code = <<<'PHP'
            <?php
            new \ReflectionClass('StdClass');
        PHP;
        $result = $this->scanner->scanSource($code);
        // Two possible reports: reflection_usage (critical) and/or always_forbidden_reflection
        $this->assertTrue(
            $this->hasReport($result, 'reflection_usage', 'critical') ||
            $this->hasReport($result, 'always_forbidden_reflection', 'high')
        );
    }

    public function testForbiddenMagicMethodDefinition()
    {
        $code = <<<'PHP'
            <?php
            class C { public function __invoke(){} }
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'always_forbidden_magic_method', 'high'));
        // Advanced analyzer also reports magic_method_defined
        $this->assertTrue($this->hasType($result, 'magic_method_defined'));
    }

    public function testDynamicIncludeAndWrapper()
    {
        $code1 = <<<'PHP'
            <?php
            include $x;
        PHP;
        $r1 = $this->scanner->scanSource($code1);
        $this->assertTrue($this->hasReport($r1, 'always_forbidden_dynamic_include', 'high'));

        $code2 = <<<'PHP'
            <?php
            include 'phar://archive/file.php';
        PHP;
        $r2 = $this->scanner->scanSource($code2);
        $this->assertTrue($this->hasReport($r2, 'include_forbidden_wrapper', 'critical'));
    }

    public function testCallbackRegistrationWithForbidden()
    {
        $code = <<<'PHP'
            <?php
            set_error_handler('eval');
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'always_forbidden_function'));
        $this->assertTrue($this->hasType($result, 'always_forbidden_callback_to_forbidden_function'));
    }

    public function testObfuscatedEval()
    {
        $code = <<<'PHP'
            <?php
            eval(base64_decode('ZQo='));
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'always_forbidden_obfuscated_eval'));
    }

    // ----------------- Config-driven checks -----------------

    public function testConfigDangerousAndTokens()
    {
        $scanner = new PluginSecurityScanner([
            'dangerous_functions' => ['print_r'],
            'tokens' => ['var_dump']
        ]);
        $r1 = $scanner->scanSource('<?php print_r(1); ?>');
        $r2 = $scanner->scanSource('<?php var_dump(1); ?>');
        $this->assertTrue($this->hasType($r1, 'config_dangerous_function'));
        $this->assertTrue($this->hasType($r2, 'config_risky_function'));
    }

    public function testBlocklistClassMethodAllowlist()
    {
        $scanner = new PluginSecurityScanner([
            'blocklist' => ['Some\\Cls' => ['allowed']]
        ]);
        $code = <<<'PHP'
            <?php
            \Some\Cls::blocked();
        PHP;
        $result = $scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'config_blocked_method'));
    }

    public function testScanSizeWarnsOnLargeFile()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fp_');
        // Write 2KB
        file_put_contents($tmp, str_repeat('A', 2048));
        $scanner = new PluginSecurityScanner(['scan_size' => ['tmp' => 1024]]);
        $scanner->setCurrentFile($tmp);
        $result = $scanner->scanSource('<?php echo 1;');
        $this->assertTrue($this->hasType($result, 'config_file_too_large'));
        @unlink($tmp);
    }

    // ----------------- Namespace checks -----------------

    public function testForbiddenNamespaceImportAndReference()
    {
        $use = <<<'PHP'
            <?php
            use Illuminate\Support\Facades\DB;
        PHP;
        $rUse = $this->scanner->scanSource($use);
        $this->assertTrue($this->hasReport($rUse, 'forbidden_namespace_import', 'critical'));

        $new = <<<'PHP'
            <?php
            new \Illuminate\Support\Facades\DB();
        PHP;
        $rNew = $this->scanner->scanSource($new);
        $this->assertTrue($this->hasReport($rNew, 'forbidden_namespace_reference', 'critical'));

        $str = <<<'PHP'
            <?php
            $x = "Illuminate\\Support\\Facades\\DB";
        PHP;
        $rStr = $this->scanner->scanSource($str);
        $this->assertTrue($this->hasType($rStr, 'forbidden_namespace_string_reference'));
    }

    // ----------------- Advanced backdoor heuristics -----------------

    public function testConcatFunctionNameCall()
    {
        $code = <<<'PHP'
            <?php
            ("e"."val")('1');
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'backdoor_concat_function_call_always_forbidden'));
    }

    public function testDynamicClassInstantiationFromSuperglobal()
    {
        $code = <<<'PHP'
            <?php
            $cls = $_GET['x'];
            new $cls();
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'backdoor_dynamic_class_instantiation_superglobal', 'critical'));
    }

    public function testDynamicMethodCallFromSuperglobal()
    {
        $code = <<<'PHP'
            <?php
            class T {
                public function run(){ $m = $_GET['x']; $this->$m(); }
            }
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'backdoor_dynamic_method_call_superglobal', 'critical'));
    }

    public function testDynamicPropertyAccessReports()
    {
        $code = <<<'PHP'
            <?php
            class T { public function run($obj){ $p = 'foo'; $obj->$p; } }
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'dynamic_property_access'));
    }

    public function testVariableVariableUsage()
    {
        $code = <<<'PHP'
            <?php
            $x = 'y';
            echo $$x;
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'variable_variable_usage'));
    }

    // ----------------- Include heuristics -----------------

    public function testIncludeDynamicPathSuperglobalAndPlain()
    {
        $code1 = <<<'PHP'
            <?php
            $p = $_GET['f'];
            include $p;
        PHP;
        $r1 = $this->scanner->scanSource($code1);
        $this->assertTrue($this->hasReport($r1, 'include_dynamic_path_superglobal', 'critical'));

        $code2 = <<<'PHP'
            <?php
            $p = 'file.php';
            include $p;
        PHP;
        $r2 = $this->scanner->scanSource($code2);
        $this->assertTrue($this->hasType($r2, 'include_dynamic_path'));
    }

    // ----------------- Obfuscators -----------------

    public function testObfuscationFunctionFlag()
    {
        $result = $this->scanner->scanSource('<?php base64_decode("a");');
        $this->assertTrue($this->hasType($result, 'obfuscation_function'));
    }

    // ----------------- Anonymous class and closure leak -----------------

    public function testAnonymousClassLeakAndClosureLeak()
    {
        $anon = <<<'PHP'
            <?php
            new class { public function __call($n,$a){ eval('1'); } };
        PHP;
        $rAnon = $this->scanner->scanSource($anon);
        $this->assertTrue($this->hasType($rAnon, 'anonymous_class_leak'));

        $clos = <<<'PHP'
            <?php
            $f = function(){ eval('1'); };
            $f();
        PHP;
        $rClos = $this->scanner->scanSource($clos);
        $this->assertTrue($this->hasType($rClos, 'anonymous_function_leak'));
    }

    // ----------------- Superglobal/static leak checks -----------------

    public function testGlobalOrSessionLeakAndStaticLeak()
    {
        $g = $this->scanner->scanSource('<?php $_SESSION["x"] = eval("1");');
        $this->assertTrue($this->hasReport($g, 'global_or_session_leak', 'high'));

        $s = $this->scanner->scanSource('<?php function f(){ static $a = eval("1"); }');
        $this->assertTrue($this->hasReport($s, 'static_variable_leak', 'high'));
    }

    // ----------------- Return-time checks -----------------

    public function testReturnForbiddenClass()
    {
        $code = <<<'PHP'
            <?php
            function r(){ return new \ReflectionClass('StdClass'); }
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'return_forbidden_class', 'critical'));
    }

    public function testReturnIndirectForbiddenMethodChain()
    {
        $code = <<<'PHP'
            <?php
            class A {
                public function evil(){ return eval('1'); }
                public function run(){ return $this->evil(); }
            }
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'return_indirect_forbidden_method_chain'));
    }

    // ----------------- Complex namespace scenarios -----------------

    public function testGroupUseForbiddenNamespaceImport()
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\{DB, Route};
    PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'forbidden_namespace_import', 'critical'));
    }

    public function testForbiddenNamespaceExtendsAndImplements()
    {
        $code = <<<'PHP'
<?php
namespace X;
class C extends \Illuminate\Routing\Router implements \Illuminate\Database\ConnectionInterface {}
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'forbidden_namespace_extends', 'critical'));
        $this->assertTrue($this->hasReport($result, 'forbidden_namespace_implements', 'critical'));
    }

    // ----------------- Config allowlist semantics -----------------

    public function testAllowlistAllowsWhitelistedMethod()
    {
        $scanner = new PluginSecurityScanner([
            'blocklist' => ['Some\\Cls' => ['allowed']]
        ]);
        $code = <<<'PHP'
            <?php
            \Some\Cls::allowed();
        PHP;
        $result = $scanner->scanSource($code);
        $this->assertFalse($this->hasType($result, 'config_blocked_method'));
    }

    // ----------------- Variable resolution & dynamic calls -----------------

    public function testVariableFunctionFromConcatAssignment()
    {
        $code = <<<'PHP'
            <?php
            $f = 'ev'.'al';
            $f('1');
        PHP;
        $result = $this->scanner->scanSource($code);
        // Should be treated as variable function call resolving to forbidden
        $this->assertTrue($this->hasType($result, 'backdoor_variable_function_call_chain_forbidden'));
    }

    public function testMagicMethodCallUserFuncToForbidden()
    {
        $code = <<<'PHP'
            <?php
            class M {
                public function __call($n, $a) {
                    call_user_func('eval', '1');
                }
            }
        PHP;
        $result = $this->scanner->scanSource($code);
        // baseline magic method defined
        $this->assertTrue($this->hasType($result, 'always_forbidden_magic_method'));
        // analyzer should escalate due to call_user_func('eval')
        $this->assertTrue($this->hasReport($result, 'magic_method_defined', 'critical'));
    }

    public function testDynamicStaticPropertyAccess()
    {
        $code = <<<'PHP'
            <?php
            class K { public static $x; }
            $p = 'x';
            K::$$p;
        PHP;
        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasType($result, 'dynamic_static_property_access'));
    }

    public function testIncludeWithEncapsedDynamicPath()
    {
        $code = <<<'PHP'
            <?php
            $f = $_GET['f'];
            $p = "dir/{$f}.php";
            include $p;
        PHP;
        $result = $this->scanner->scanSource($code);
        // Currently tracked as include_dynamic_path (not superglobal) due to concat/encapsed
        $this->assertTrue($this->hasType($result, 'include_dynamic_path'));
    }

    /**
     * @throws JsonException
     */
    public function testBlocklist_InstanceDirectNew_DisallowedMethod()
    {
        // Only 'allowed' is permitted on Foo\A
        $this->setPolicyBlocklist(['Foo\\A' => ['allowed']]);

        $code = <<<'PHP'
<?php
$a = new \Foo\A();
$a->blocked();
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'config_blocked_method', 'critical'), json_encode($result, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     */
    public function testBlocklist_InstancePropagation_DisallowedMethod()
    {
        // Only 'allowed' is permitted on Foo\A
        $this->setPolicyBlocklist(['Foo\\A' => ['allowed']]);

        $code = <<<'PHP'
<?php
$a = new \Foo\A();
$b = $a;
$b->blocked();
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'config_blocked_method', 'critical'), json_encode($result, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     */
    public function testBlocklist_InstanceReassignedToUnknown_NoReport()
    {
        // Only 'allowed' is permitted on Foo\A
        $this->setPolicyBlocklist(['Foo\\A' => ['allowed']]);

        $code = <<<'PHP'
<?php
$a = new \Foo\A();
$a = $_GET['x']; // becomes unknown; tracked type must be cleared
$a->blocked();   // unknown receiver → no enforcement
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertFalse($this->hasReport($result, 'config_blocked_method', 'critical'), json_encode($result, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     */
    public function testBlocklist_NullsafeCall_WithPropagation_DisallowedMethod()
    {
        // Empty list => no methods allowed on Foo\B
        $this->setPolicyBlocklist(['Foo\\B' => []]);

        $code = <<<'PHP'
<?php
$a = new \Foo\B();
$b = $a;
$b?->anything();
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'config_blocked_method', 'critical'), json_encode($result, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     */
    public function testBlocklist_ImportedClass_NoAlias_DisallowedMethod()
    {
        // Only 'allowed' is permitted on Foo\A
        $this->setPolicyBlocklist(['Foo\\A' => ['allowed']]);

        $code = <<<'PHP'
<?php
use Foo\A;

$obj = new A();
$obj->blocked();
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue(
            $this->hasReport($result, 'config_blocked_method', 'critical'),
            json_encode($result, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @throws JsonException
     */
    public function testImportedClass_AssignedToVariableName_ThenInstantiated_DynamicBackdoorFlag()
    {
        // Blocklist present (won't trigger on dynamic new, but we should at least flag the dynamic instantiation)
        $this->setPolicyBlocklist(['Foo\\A' => ['allowed']]);

        $code = <<<'PHP'
<?php
use Foo\A;

$class = A::class;   // assign imported class name to a variable
$obj   = new $class(); // dynamic instantiation
$obj->blocked();       // instance call (likely not enforceable from type tracker)
PHP;

        $result = $this->scanner->scanSource($code);

        // Dynamic "new $class()" should be flagged by backdoor detector as unresolved
        $this->assertTrue(
            $this->hasReport($result, 'backdoor_dynamic_class_instantiation_unresolved', 'info')
            || $this->hasReport($result, 'backdoor_dynamic_class_instantiation_unresolved', 'high')
            || $this->hasReport($result, 'backdoor_dynamic_class_instantiation_unresolved', 'critical'),
            json_encode($result, JSON_THROW_ON_ERROR)
        );

        // Optional: depending on your current type-tracking, this may or may not fire.
        // It's okay not to assert config_blocked_method here unless you've implemented dynamic-new typing.
        // $this->assertTrue($this->hasReport($result, 'config_blocked_method', 'critical'), json_encode($result));
    }

    /**
     * @throws JsonException
     */
    public function testImportedClass_ClassConst_VarNew_ThenBlocked()
    {
        $this->setPolicyBlocklist(['Foo\\A' => ['allowed']]); // only 'allowed' permitted

        $code = <<<'PHP'
<?php
use Foo\A;

$class = A::class;    // tracked to \Foo\A
$obj   = new $class(); // resolved to instance of \Foo\A
$obj->blocked();       // should hit config_blocked_method
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'config_blocked_method', 'critical'), json_encode($result, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     */
    public function testBlocklist_ImportedClassAlias_DisallowedMethod()
    {
        // Only 'allowed' is permitted on Foo\A
        $this->setPolicyBlocklist(['Foo\\A' => ['allowed']]);

        $code = <<<'PHP'
<?php
namespace TestNS;
use Foo\A as ImportedA;

$obj = new ImportedA();
$obj->blocked();
PHP;

        $result = $this->scanner->scanSource($code);
        $this->assertTrue($this->hasReport($result, 'config_blocked_method', 'critical'), json_encode($result, JSON_THROW_ON_ERROR));
    }
    // ----------------- Helpers -----------------

    /** Helper to check presence of a report by type and severity */
    protected function hasReport(array $result, string $type, string $severity): bool
    {
        foreach ($result as $report) {
            if (($report['type'] ?? null) === $type && ($report['severity'] ?? null) === $severity) {
                return true;
            }
        }
        return false;
    }

    /** Helper to check presence of a report type (any severity) */
    protected function hasType(array $result, string $type): bool
    {
        foreach ($result as $report) {
            if (($report['type'] ?? null) === $type) {
                return true;
            }
        }
        return false;
    }

    /** @noinspection PhpExpressionResultUnusedInspection */
    private function setPolicyBlocklist(array $map): void
    {
        // Normalize: FQCN keys (no leading \), lowercase method names, unique
        $normalized = [];
        foreach ($map as $class => $methods) {
            if (!is_array($methods)) continue;
            $fq = ltrim((string)$class, '\\');
            $normalized[$fq] = array_values(array_unique(array_map('strtolower', $methods)));
        }

        // Try to update existing policy on the scanner
        $policy = null;
        if (method_exists($this->scanner, 'getPolicy')) {
            $policy = $this->scanner->getPolicy();
        }

        if ($policy instanceof PluginPolicy) {
            // Update internal $blocklist and mirror in $config['blocklist']
            $rpo = new ReflectionObject($policy);

            if ($rpo->hasProperty('blocklist')) {
                $p = $rpo->getProperty('blocklist');
                $p->setAccessible(true);
                $p->setValue($policy, $normalized);
            }
            if ($rpo->hasProperty('config')) {
                $c = $rpo->getProperty('config');
                $c->setAccessible(true);
                $cfg = (array)$c->getValue($policy);
                $cfg['blocklist'] = $normalized;
                $c->setValue($policy, $cfg);
            }
        }
    }
}