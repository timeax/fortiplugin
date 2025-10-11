<?php

namespace Timeax\FortiPlugin\Services;

use JsonException;

/**
 * ErrorReaderService
 *
 * Purpose:
 * - Normalize and render readable error/violation entries produced by FortiPlugin scanners.
 * - Provide a catalog of known error slugs with human-friendly names.
 *
 * Inputs supported:
 * - PluginSecurityScanner::getMatches() items (include: type, severity, line, file, + data)
 * - ContentValidator::scanSource()/scanFile() items (include: type, file, line, snippet, issue, ...)
 * - ComposerScan::scan() items (include: type, file, issue, package/version when applicable)
 * - ConfigValidator::validate() results (can be ["error"=>..., "details"=>...])
 */
class ErrorReaderService
{
    /**
     * Turn a raw error/violation array into a normalized, readable shape.
     *
     * Output shape:
     * - slug: string  (original type when present; or derived fallback)
     * - name: string  (human-readable title)
     * - description: string (expanded with best-available details)
     * - severity: string (critical|high|medium|low|info) when available
     * - file: string|null
     * - line: int|null
     * - column: int|null
     * - snippet: string|null
     * - extra: array (original payload for debugging)
     * @throws JsonException
     */
    public function format(array $error): array
    {
        // Some validators return an error summary instead of a typed violation.
        if (!isset($error['type']) && isset($error['error'])) {
            return $this->formatConfigValidatorError($error);
        }

        $slug = (string)($error['type'] ?? 'unknown_error');
        $severity = (string)($error['severity'] ?? ($this->defaultSeverityMap()[$slug] ?? 'high'));
        $file = $error['file'] ?? null;
        $line = isset($error['line']) ? (int)$error['line'] : null;
        $column = isset($error['column']) ? (int)$error['column'] : null;
        $snippet = $error['snippet'] ?? null;

        $catalog = self::catalog();
        $meta = $catalog[$slug] ?? [
            'name' => self::slugToTitle($slug),
            'description' => 'An issue of type "' . $slug . '" was reported.',
        ];

        $name = (string)$meta['name'];
        $descriptionTpl = (string)($meta['description'] ?? '');
        $description = $this->renderTemplate($descriptionTpl, $error);

        // Fallback description using generic fields if template was empty
        if ($description === '' || $description === $descriptionTpl) {
            $issue = (string)($error['issue'] ?? '');
            if ($issue !== '') {
                $description = $issue;
            }
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'column' => $column,
            'snippet' => $snippet,
            'extra' => $error,
        ];
    }

    /**
     * Convenience: format an array of errors.
     * @param array $errors
     * @return array
     * @throws JsonException
     * @throws JsonException
     */
    public function formatMany(array $errors): array
    {
        $out = [];
        foreach ($errors as $e) {
            if (!is_array($e)) continue;
            $out[] = $this->format($e);
        }
        return $out;
    }

    /**
     * List all known error kinds, with name and slug.
     * @return array<array{slug:string,name:string}>
     */
    public function listAllPossibleErrors(): array
    {
        $list = [];
        foreach (self::catalog() as $slug => $meta) {
            $list[] = [
                'slug' => $slug,
                'name' => ($meta['name'] ?? self::slugToTitle($slug)),
            ];
        }
        return $list;
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    private function formatConfigValidatorError(array $error): array
    {
        $name = 'Configuration validation error';
        $description = (string)($error['error'] ?? '');
        $file = isset($error['file']) ? (string)$error['file'] : null;
        $details = $error['details'] ?? [];

        // Try to enrich description with first detail, if available
        if (is_array($details) && count($details) > 0) {
            $d = $details[0];
            $path = $d['path'] ?? '';
            $msg = $d['message'] ?? '';
            if ($msg !== '') {
                $description .= ($description !== '' ? ' — ' : '') . $msg;
            }
            if ($path !== '') {
                $description .= ($description !== '' ? ' ' : '') . "at $path";
            }
        }

        return [
            'slug' => 'config_validation_error',
            'name' => $name,
            'description' => $description,
            'severity' => 'high',
            'file' => $file,
            'line' => null,
            'column' => null,
            'snippet' => null,
            'extra' => $error,
        ];
    }

    private function renderTemplate(string $tpl, array $vars): string
    {
        if ($tpl === '') return '';

        // Simple {key} replacement using root keys of $vars
        return preg_replace_callback('/\{([a-zA-Z0-9_.]+)}/', function ($m) use ($vars) {
            $key = $m[1];
            // support dot access like details.0.message if present
            $value = $this->getDot($vars, $key);
            if ($value === null) return $m[0];
            if (is_scalar($value)) return (string)$value;
            /** @noinspection PhpUnhandledExceptionInspection */
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $tpl) ?? $tpl;
    }

    private function getDot(array $arr, string $path)
    {
        $parts = explode('.', $path);
        $cur = $arr;
        foreach ($parts as $p) {
            if (is_array($cur)) {
                if (array_key_exists($p, $cur)) {
                    $cur = $cur[$p];
                    continue;
                }
                // numeric index
                if (ctype_digit($p)) {
                    $i = (int)$p;
                    if (isset($cur[$i])) {
                        $cur = $cur[$i];
                        continue;
                    }
                }
            }
            return null;
        }
        return $cur;
    }

    private static function slugToTitle(string $slug): string
    {
        $title = str_replace(['_', '-'], ' ', $slug);
        return ucwords($title);
    }

    private function defaultSeverityMap(): array
    {
        return [
            'always_forbidden_function' => 'critical',
            'always_forbidden_wrapper_stream' => 'high',
            'always_forbidden_reflection' => 'high',
            'always_forbidden_magic_method' => 'high',
            'always_forbidden_dynamic_include' => 'high',
            'always_forbidden_wrapper_stream_include' => 'high',
            'always_forbidden_callback_to_forbidden_function' => 'high',
            'always_forbidden_obfuscated_eval' => 'critical',
            'return_forbidden_function' => 'critical',
            'return_indirect_forbidden_chain' => 'critical',
            'return_indirect_forbidden_method_chain' => 'critical',
            'function_call_chain_forbidden' => 'critical',
            'include_forbidden_wrapper' => 'critical',
            'backdoor_dynamic_class_instantiation_forbidden' => 'critical',
            'backdoor_dynamic_method_call_forbidden' => 'critical',
            'dynamic_property_access_chain_forbidden' => 'high',
            'reflection_usage' => 'critical',
            'config_dangerous_function' => 'medium',
            'config_risky_function' => 'low',
            'config_blocked_method' => 'high',
            'config_file_too_large' => 'low',
            'forbidden_namespace_import' => 'critical',
            'forbidden_namespace_reference' => 'critical',
            'forbidden_namespace_extends' => 'critical',
            'forbidden_namespace_implements' => 'critical',
            'forbidden_namespace_string_reference' => 'high',
            'blocklist_instantiation' => 'high',
            'blocklist_constructor' => 'high',
            'blocklist_class_reference' => 'high',
            'blocklist_method' => 'high',
            'forbidden_function' => 'high',
            'forbidden_function_assignment' => 'high',
            'unsupported_function_call' => 'medium',
            'unsupported_function' => 'medium',
            'read_error' => 'high',
            'composer_file_missing' => 'high',
            'composer_file_invalid' => 'high',
            'forbidden_package_dependency' => 'high',
            'invalid_token_usage' => 'high',
            'invalid_token_assignment' => 'high',
            'invalid_token_function_argument' => 'high',
            'include_dynamic_path_superglobal' => 'high',
            'include_dynamic_path' => 'high',
            'obfuscation_function' => 'medium',
            'anonymous_class_leak' => 'high',
            'anonymous_function_leak' => 'high',
            'global_or_session_leak' => 'high',
            'static_variable_leak' => 'medium',
            'dynamic_property_access' => 'low',
            'variable_variable_usage' => 'low',
            'magic_method_defined' => 'low',
            'closure_calls_always_forbidden' => 'critical',
            'closure_calls_unsupported' => 'medium',
            'closure_calls_forbidden_chain' => 'critical',
            'callback_always_forbidden' => 'critical',
            'callback_unsupported' => 'medium',
            'callback_user_defined_forbidden_chain' => 'critical',
            'backdoor_dynamic_class_instantiation_superglobal' => 'high',
            'backdoor_dynamic_class_instantiation_unresolved' => 'high',
            'backdoor_dynamic_class_instantiation_complex' => 'high',
            'backdoor_dynamic_method_call_chain_forbidden' => 'critical',
            'dynamic_static_property_access' => 'medium',
            'config_validation_error' => 'high',
            // ValidatorService and scanner orchestration
            'composer.composer_file_missing' => 'high',
            'composer.composer_file_invalid' => 'high',
            'composer.forbidden_package_dependency' => 'high',
            'config.schema' => 'high',
            'config.exception' => 'high',
            'hostconfig.error' => 'high',
            'route.invalid' => 'high',
            'scanner.exception' => 'high',
            'content.exception' => 'high',
            'token.exception' => 'high',
            'ast.exception' => 'high',
            'ast.violation' => 'high',
            'scan.issue' => 'medium',
            'suspicious_filename_unicode' => 'medium',
            'suspicious_double_extension' => 'medium',
            'php_payload_in_non_php' => 'high',
        ];
    }

    /**
     * Catalog of known error slugs with human-readable names and description templates.
     * Placeholders like {function}, {class}, {namespace}, {value}, {expression}, {chain}, etc. are filled from the raw error.
     */
    public static function catalog(): array
    {
        return [
            // Always forbidden
            'always_forbidden_function' => [
                'name' => 'Forbidden function call',
                'description' => 'Call to forbidden function {function}.',
            ],
            'always_forbidden_wrapper_stream' => [
                'name' => 'Forbidden wrapper stream',
                'description' => 'File operation {function} uses forbidden stream wrapper: {value}.',
            ],
            'always_forbidden_reflection' => [
                'name' => 'Reflection usage is forbidden',
                'description' => 'Use of reflection class {class} is not allowed.',
            ],
            'always_forbidden_magic_method' => [
                'name' => 'Forbidden magic method',
                'description' => 'Definition of magic method {method} is not allowed.',
            ],
            'always_forbidden_dynamic_include' => [
                'name' => 'Dynamic include/require',
                'description' => 'Dynamic include/require expression of type {expr_type} is not allowed.',
            ],
            'always_forbidden_wrapper_stream_include' => [
                'name' => 'Include uses forbidden wrapper',
                'description' => 'Including via forbidden stream wrapper: {value}.',
            ],
            'always_forbidden_callback_to_forbidden_function' => [
                'name' => 'Forbidden callback registered',
                'description' => 'Callback {callback} registered via {registration} is forbidden.',
            ],
            'always_forbidden_obfuscated_eval' => [
                'name' => 'Obfuscated eval',
                'description' => 'Obfuscated call: {outer}({inner}(...)).',
            ],

            // Policy/config driven
            'config_dangerous_function' => [
                'name' => 'Dangerous function (policy)',
                'description' => 'Call to dangerous function per policy: {function}.',
            ],
            'config_risky_function' => [
                'name' => 'Risky function (policy)',
                'description' => 'Call to risky function per policy: {function}.',
            ],
            'config_blocked_method' => [
                'name' => 'Blocked static method',
                'description' => 'Blocked method {class}::{method} per policy.',
            ],
            'config_file_too_large' => [
                'name' => 'File exceeds policy size',
                'description' => 'File {file} exceeds maximum size {max_bytes} bytes.',
            ],

            // Returns/indirect
            'return_forbidden_function' => [
                'name' => 'Return/call forbidden function',
                'description' => 'Execution path returns or calls forbidden function {function}.',
            ],
            'return_indirect_forbidden_chain' => [
                'name' => 'Indirect forbidden call chain',
                'description' => 'Indirect call chain reaches forbidden routine: {chain}.',
            ],
            'return_indirect_forbidden_method_chain' => [
                'name' => 'Indirect forbidden method chain',
                'description' => 'Indirect method chain reaches forbidden routine: {chain}.',
            ],
            'function_call_chain_forbidden' => [
                'name' => 'Forbidden function via chain',
                'description' => 'Function participation in forbidden call chain: {function}.',
            ],

            // Namespace issues
            'forbidden_namespace_import' => [
                'name' => 'Forbidden namespace import',
                'description' => 'Import of forbidden namespace or child: {namespace}.',
            ],
            'forbidden_namespace_reference' => [
                'name' => 'Forbidden namespace reference',
                'description' => 'Reference to forbidden namespace/class: {namespace}.',
            ],
            'forbidden_namespace_extends' => [
                'name' => 'Forbidden parent class',
                'description' => 'Class extends forbidden parent: {namespace}.',
            ],
            'forbidden_namespace_implements' => [
                'name' => 'Forbidden interface',
                'description' => 'Class implements forbidden interface: {namespace}.',
            ],
            'forbidden_namespace_string_reference' => [
                'name' => 'Forbidden namespace string',
                'description' => 'Forbidden namespace used as a string: {namespace}.',
            ],

            // ContentValidator namespace variant
            'forbidden_namespace_string' => [
                'name' => 'Forbidden namespace string',
                'description' => 'Forbidden namespace/class referenced as a string.',
            ],

            // ContentValidator tokens/functions
            'invalid_token_usage' => [
                'name' => 'Invalid token usage',
                'description' => 'Direct usage of invalid token {token}.',
            ],
            'invalid_token_assignment' => [
                'name' => 'Invalid token assignment',
                'description' => 'Invalid token {token} assigned to a variable/property.',
            ],
            'invalid_token_function_argument' => [
                'name' => 'Invalid token in function argument',
                'description' => 'Invalid token {token} used as a function argument.',
            ],
            'blocklist_instantiation' => [
                'name' => 'Blocked class instantiation',
                'description' => 'Instantiation of blocked class {token}.',
            ],
            'blocklist_constructor' => [
                'name' => 'Blocked constructor',
                'description' => 'Constructor call {token}::__construct is blocked.',
            ],
            'blocklist_class_reference' => [
                'name' => 'Blocked class reference',
                'description' => 'Reference to blocked class {token}::class.',
            ],
            'blocklist_method' => [
                'name' => 'Blocked method call',
                'description' => 'Blocked method {token}::{method}.',
            ],
            'forbidden_function' => [
                'name' => 'Forbidden function call',
                'description' => 'Call to forbidden function {function}.',
            ],
            'forbidden_function_assignment' => [
                'name' => 'Forbidden function in assignment',
                'description' => 'Forbidden function {function} assigned to a variable/property.',
            ],
            'unsupported_function' => [
                'name' => 'Unsupported/risky function',
                'description' => 'Call to unsupported or risky function {function}.',
            ],

            // Variable/dynamic/concat function call backdoors
            'backdoor_variable_function_call' => [
                'name' => 'Variable function call',
                'description' => 'Variable function call via ${var}.',
            ],
            'backdoor_variable_function_call_chain_forbidden' => [
                'name' => 'Variable function resolves to forbidden',
                'description' => 'Variable function resolves or chains to forbidden function {resolved_function}.',
            ],
            'backdoor_concat_function_call_unknown' => [
                'name' => 'Concatenated function call (unknown)',
                'description' => 'Function name constructed by concatenation: {expression}.',
            ],
            'backdoor_concat_function_call_always_forbidden' => [
                'name' => 'Concatenated function call (forbidden)',
                'description' => 'Concatenated function name resolves to forbidden: {expression}.',
            ],
            'backdoor_concat_function_call_unsupported' => [
                'name' => 'Concatenated function call (unsupported)',
                'description' => 'Concatenated function name resolves to unsupported: {expression}.',
            ],
            'backdoor_concat_function_call_chain_forbidden' => [
                'name' => 'Concatenated function call (forbidden chain)',
                'description' => 'Concatenated function name participates in forbidden chain: {expression}.',
            ],

            // Advanced backdoor/heuristics (PluginSecurityScanner)
            'reflection_usage' => [
                'name' => 'Reflection usage',
                'description' => 'Suspicious reflection usage with class {class}.',
            ],
            'include_forbidden_wrapper' => [
                'name' => 'Include with forbidden wrapper',
                'description' => 'Include/require uses a forbidden stream wrapper: {value}.',
            ],
            'include_dynamic_path_superglobal' => [
                'name' => 'Dynamic include path from superglobal',
                'description' => 'Dynamic include path sourced from a superglobal.',
            ],
            'include_dynamic_path' => [
                'name' => 'Dynamic include path',
                'description' => 'Dynamic include path via expression: {expression}.',
            ],
            'obfuscation_function' => [
                'name' => 'Obfuscation routine',
                'description' => 'Use of known obfuscation routine {function}.',
            ],
            'anonymous_class_leak' => [
                'name' => 'Anonymous class leaks dangerous content',
                'description' => 'Anonymous class contains dangerous content: {dangerous_content}.',
            ],
            'anonymous_function_leak' => [
                'name' => 'Anonymous function leaks dangerous content',
                'description' => 'Anonymous function contains dangerous content: {dangerous_content}.',
            ],
            'global_or_session_leak' => [
                'name' => 'Global/session leak',
                'description' => 'Global or session variable with dangerous content: {dangerous_content}.',
            ],
            'static_variable_leak' => [
                'name' => 'Static variable leak',
                'description' => 'Static variable with dangerous content: {dangerous_content}.',
            ],
            'return_forbidden_class' => [
                'name' => 'Return forbidden class',
                'description' => 'Function/method returns an instance of forbidden class {class}.',
            ],
            'backdoor_dynamic_class_instantiation_superglobal' => [
                'name' => 'Dynamic class instantiation from superglobal',
                'description' => 'Class name derived from superglobal detected in instantiation.',
            ],
            'backdoor_dynamic_class_instantiation_forbidden' => [
                'name' => 'Dynamic class instantiation (forbidden)',
                'description' => 'Dynamic class instantiation for forbidden class {class}.',
            ],
            'backdoor_dynamic_class_instantiation_unresolved' => [
                'name' => 'Dynamic class instantiation (unresolved)',
                'description' => 'Dynamic class instantiation with unresolved class name.',
            ],
            'backdoor_dynamic_class_instantiation_complex' => [
                'name' => 'Dynamic class instantiation (complex)',
                'description' => 'Dynamic class instantiation with complex expression.',
            ],
            'backdoor_dynamic_method_call_forbidden' => [
                'name' => 'Dynamic method call (forbidden)',
                'description' => 'Dynamic method call to forbidden routine on class {class}.',
            ],
            'backdoor_dynamic_method_call_chain_forbidden' => [
                'name' => 'Dynamic method call chain (forbidden)',
                'description' => 'Dynamic call chain reaches forbidden routine on class {class}.',
            ],
            'dynamic_property_access_chain_forbidden' => [
                'name' => 'Dynamic property access chain (forbidden)',
                'description' => 'Dynamic property access chain indicates potential backdoor.',
            ],
            'dynamic_property_access' => [
                'name' => 'Dynamic property access',
                'description' => 'Dynamic property access observed: {expression}.',
            ],
            'variable_variable_usage' => [
                'name' => 'Variable-variable usage',
                'description' => 'Variable-variable usage may indicate dynamic code execution.',
            ],
            'magic_method_defined' => [
                'name' => 'Magic method defined',
                'description' => 'Magic method defined: {method}.',
            ],
            'closure_calls_always_forbidden' => [
                'name' => 'Closure calls forbidden function',
                'description' => 'Closure calls forbidden function {function}.',
            ],
            'closure_calls_unsupported' => [
                'name' => 'Closure calls unsupported function',
                'description' => 'Closure calls unsupported function {function}.',
            ],
            'closure_calls_forbidden_chain' => [
                'name' => 'Closure participates in forbidden chain',
                'description' => 'Closure participates in forbidden call chain for {function}.',
            ],
            'callback_always_forbidden' => [
                'name' => 'Callback calls forbidden function',
                'description' => 'Registered callback calls forbidden function {function}.',
            ],
            'callback_unsupported' => [
                'name' => 'Callback calls unsupported function',
                'description' => 'Registered callback calls unsupported function {function}.',
            ],
            'callback_user_defined_forbidden_chain' => [
                'name' => 'Callback triggers forbidden chain',
                'description' => 'Registered callback triggers forbidden call chain for {function}.',
            ],

            // Composer/config
            'composer_file_missing' => [
                'name' => 'composer.json missing',
                'description' => 'composer.json not found at {file}.',
            ],
            'composer_file_invalid' => [
                'name' => 'composer.json invalid',
                'description' => 'Invalid JSON in composer.json at {file}.',
            ],
            'forbidden_package_dependency' => [
                'name' => 'Forbidden composer package',
                'description' => 'Composer requires forbidden package {package} ({version}).',
            ],

            // Prefixed composer.* variants emitted by ValidatorService
            'composer.composer_file_missing' => [
                'name' => 'composer.json missing',
                'description' => 'composer.json not found at {file}.',
            ],
            'composer.composer_file_invalid' => [
                'name' => 'composer.json invalid',
                'description' => 'Invalid JSON in composer.json at {file}.',
            ],
            'composer.forbidden_package_dependency' => [
                'name' => 'Forbidden composer package',
                'description' => 'Composer requires forbidden package {package} ({version}).',
            ],

            // Filesystem/content
            'read_error' => [
                'name' => 'File read error',
                'description' => 'Unable to read file {file}.',
            ],

            // Scanner pre-flag issues (from FileScanner)
            'suspicious_filename_unicode' => [
                'name' => 'Suspicious filename (Unicode control chars)',
                'description' => 'Filename may contain bidi control characters indicating spoofing.',
            ],
            'suspicious_double_extension' => [
                'name' => 'Suspicious double extension',
                'description' => 'File name looks like a double extension (e.g. .jpg.php or .php.txt).',
            ],
            'php_payload_in_non_php' => [
                'name' => 'PHP payload in non-PHP file',
                'description' => 'PHP code payload detected in a non-PHP file.',
            ],

            // Orchestration/service emitted
            'config.schema' => [
                'name' => 'Config schema violation',
                'description' => 'fortiplugin.json failed schema validation: {issue}',
            ],
            'config.exception' => [
                'name' => 'Config validation exception',
                'description' => 'Exception during config validation: {issue}',
            ],
            'hostconfig.error' => [
                'name' => 'Host config error',
                'description' => 'Host configuration validation error: {issue}',
            ],
            'manifest.invalid' => [
                'name' => 'Permission manifest invalid',
                'description' => 'Permission manifest validation failed: {issue}',
            ],
            'route.invalid' => [
                'name' => 'Route file invalid',
                'description' => 'Route file validation failed: {issue}',
            ],
            'scanner.exception' => [
                'name' => 'Scanner exception',
                'description' => 'File scanner threw an exception: {issue}',
            ],
            'content.exception' => [
                'name' => 'Content validator exception',
                'description' => 'Content validation threw an exception: {issue}',
            ],
            'token.exception' => [
                'name' => 'Token analyzer exception',
                'description' => 'TokenUsageAnalyzer threw an exception: {issue}',
            ],
            'ast.exception' => [
                'name' => 'AST scanner exception',
                'description' => 'AST scanner threw an exception: {issue}',
            ],
            'ast.violation' => [
                'name' => 'AST violation',
                'description' => '{issue}',
            ],
            'scan.issue' => [
                'name' => 'Scan issue',
                'description' => '{issue}',
            ],
        ];
    }
}
