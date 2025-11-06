<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use Throwable;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\ErrorCodes;

/**
 * UiConfigValidationSection
 *
 * Validates plugin UIConfig against host-defined UIScheme and the plugin's known route IDs.
 * - Never blocks install; only logs to installation.json and emits installer events.
 * - Supports floating UI via sections named "floating.{zoneId}" (zones come from hostScheme['floating']['zones']).
 * - Ensures each item.id matches a known route id from the plugin routes.
 * - Checks section/target extendability and extra prop typing.
 */
final readonly class UiConfigValidationSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
    ) {}

    /**
     * @param InstallMeta $meta Must include paths['staging'] (for fortiplugin.json).
     * @param list<string> $knownRouteIds Route IDs validated earlier (from routes JSON).
     * @param array<string,mixed> $hostScheme Host UIScheme:
     *        [
     *          'sections' => [
     *              'header.main' => ['extendable'=>true,'extraProps'=>[...],'allowUnknownProps'=>bool,'targets'=>[...]],
     *              'sidebar.primary' => [...],
     *              ...
     *          ],
     *          'floating' => [
     *              'zones' => [
     *                  'bottom-right' => ['extendable'=>true,'extraProps'=>[...],'allowUnknownProps'=>bool]
     *              ]
     *          ]
     *        ]
     * @param callable|null $emit Optional: fn(array $payload): void
     *
     * @return array{
     *   status:'ok',
     *   declared:int,
     *   accepted:int,
     *   errors:list<array<string,mixed>>,
     *   warnings:list<array<string,mixed>>
     * }
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        InstallMeta $meta,
        array       $knownRouteIds,
        array       $hostScheme,
        ?callable   $emit = null
    ): array
    {
        $staging = (string)($meta->paths['staging'] ?? '');
        $cfgPath = rtrim($staging, "\\/") . DIRECTORY_SEPARATOR . 'fortiplugin.json';

        $errors = [];
        $warnings = [];
        $placements = [];
        $declared = 0;
        $accepted = 0;

        // Build the unified section map (includes floating zones as "floating.{zone}")
        $sections = $this->buildSectionsIndex($hostScheme);

        // Start event
        $start = [
            'title' => 'UI_CONFIG_CHECK_START',
            'description' => 'Validating UI configuration',
            'meta' => [
                'staging' => $staging,
                'scheme_sections' => array_keys($sections),
            ],
        ];
        $emit && $emit($start);
        $this->log->appendInstallerEmit($start);

        // If no fortiplugin.json → nothing to validate (OK)
        if (!$this->afs->fs()->exists($cfgPath)) {
            $ok = [
                'title' => 'UI_CONFIG_CHECK_OK',
                'description' => 'No fortiplugin.json found; skipping UIConfig validation',
            ];
            $emit && $emit($ok);
            $this->log->appendInstallerEmit($ok);

            $this->log->writeSection('ui_config', [
                'declared' => 0,
                'accepted' => 0,
                'errors' => [],
                'warnings' => [],
                'placements' => [],
            ]);

            return ['status' => 'ok', 'declared' => 0, 'accepted' => 0, 'errors' => [], 'warnings' => []];
        }

        // Read plugin UIConfig
        $uiItems = [];
        try {
            $cfg = $this->afs->fs()->readJson($cfgPath);
            $uiItems = (array)($cfg['uiConfig']['items'] ?? []);
        } catch (Throwable $e) {
            $warnings[] = ['code' => ErrorCodes::CONFIG_READ_FAILED, 'detail' => 'Cannot read fortiplugin.json', 'exception' => $e->getMessage()];
        }

        $declared = count($uiItems);
        $routeSet = array_fill_keys(array_map('strval', $knownRouteIds), true);

        $seenComposite = []; // de-dup on (section, targetId?, id)

        foreach ($uiItems as $idx => $item) {
            if (!is_array($item)) {
                $warnings[] = ['code' => ErrorCodes::UI_ITEM_INVALID, 'itemIndex' => $idx, 'detail' => 'Item is not an object'];
                continue;
            }

            $section = (string)($item['section'] ?? '');
            $routeId = (string)($item['id'] ?? '');      // IMPORTANT: id is the route id
            $text    = (string)($item['text'] ?? '');
            $icon    = isset($item['icon']) ? (string)$item['icon'] : null;
            $href    = isset($item['href']) ? (string)$item['href'] : null;
            $extend  = (array)($item['extend'] ?? []);
            $props   = (array)($item['props'] ?? []);

            // Section existence
            $sec = $sections[$section] ?? null;
            if ($sec === null) {
                $errors[] = [
                    'code' => ErrorCodes::UI_SECTION_NOT_FOUND,
                    'itemIndex' => $idx,
                    'section' => $section,
                ];
                continue;
            }

            // Determine target context (if any)
            $targetId = isset($extend['targetId']) ? (string)$extend['targetId'] : null;
            $target = null;
            if ($targetId !== null && $targetId !== '') {
                $target = (array)($sec['targets'][$targetId] ?? null);
                if ($target === [] || $target === null) {
                    $errors[] = [
                        'code' => ErrorCodes::UI_TARGET_NOT_FOUND,
                        'itemIndex' => $idx,
                        'section' => $section,
                        'targetId' => $targetId
                    ];
                    continue;
                }
                if (!($target['extendable'] ?? false)) {
                    $errors[] = [
                        'code' => ErrorCodes::UI_TARGET_NOT_EXTENDABLE,
                        'itemIndex' => $idx,
                        'section' => $section,
                        'targetId' => $targetId
                    ];
                    continue;
                }
            } else if (!($sec['extendable'] ?? false)) {
                $warnings[] = [
                    'code' => ErrorCodes::UI_SECTION_NOT_EXTENDABLE,
                    'itemIndex' => $idx,
                    'section' => $section
                ];
                // continue anyway (host may still accept)
            }

            // Route linkage via id
            if ($routeId === '' || !isset($routeSet[$routeId])) {
                $errors[] = [
                    'code' => ErrorCodes::UI_ROUTE_ID_MISSING,
                    'itemIndex' => $idx,
                    'section' => $section,
                    'id' => $routeId
                ];
                continue;
            }

            // Optional href sanity (non-blocking)
            if ($href !== null && $href !== '' && !str_starts_with($href, '/') && !preg_match('#^https?://#i', $href)) {
                $warnings[] = [
                    'code' => ErrorCodes::UI_HREF_SUSPECT,
                    'itemIndex' => $idx,
                    'href' => $href,
                    'detail' => 'Href is not absolute or http(s); host may override from route'
                ];
            }

            // Duplicate composite (section + targetId + id)
            $dupKey = $section . "\n" . ($targetId ?? '') . "\n" . $routeId;
            if (isset($seenComposite[$dupKey])) {
                $warnings[] = [
                    'code' => ErrorCodes::UI_DUPLICATE_ITEM,
                    'itemIndex' => $idx,
                    'section' => $section,
                    'targetId' => $targetId,
                    'id' => $routeId
                ];
                // continue; not fatal
            } else {
                $seenComposite[$dupKey] = true;
            }

            // Extra props typing — merge section+target schemas (target overrides)
            $propSpec = (array)($sec['extraProps'] ?? []);
            if ($target) {
                $propSpec = $this->mergePropSpec($propSpec, (array)($target['extraProps'] ?? []));
            }
            $allowUnknown = (bool)($sec['allowUnknownProps'] ?? true);
            if ($target && array_key_exists('allowUnknownProps', $target)) {
                $allowUnknown = (bool)$target['allowUnknownProps'];
            }

            // Validate props
            $propIssues = $this->validateProps($props, $propSpec, $allowUnknown);
            foreach ($propIssues['errors'] as $e) {
                $e['itemIndex'] = $idx;
                $e['section'] = $section;
                if ($targetId) $e['targetId'] = $targetId;
                $errors[] = $e;
            }
            foreach ($propIssues['warnings'] as $w) {
                $w['itemIndex'] = $idx;
                $w['section'] = $section;
                if ($targetId) $w['targetId'] = $targetId;
                $warnings[] = $w;
            }

            // Record placement snapshot
            $placements[] = [
                'section' => $section,
                'targetId' => $targetId,
                'id' => $routeId,
                'text' => $text,
                'icon' => $icon,
                'props' => $props,
                'kind' => str_starts_with($section, 'floating.') ? 'floating' : 'nav',
            ];

            $accepted++;
        }

        // Persist results
        $block = [
            'declared' => $declared,
            'accepted' => $accepted,
            'errors' => $errors,
            'warnings' => $warnings,
            'placements' => $placements,
        ];
        try {
            $this->log->writeSection('ui_config', $block);
        } catch (Throwable $e) {
            // non-blocking
        }

        // Emit end
        if ($errors !== []) {
            $this->log->appendInstallerEmit([
                'title' => 'UI_CONFIG_CHECK_FAIL',
                'description' => 'UIConfig validation recorded errors (non-blocking)',
                'meta' => ['declared' => $declared, 'accepted' => $accepted, 'errors' => count($errors), 'warnings' => count($warnings)],
            ]);
            $emit && $emit([
                'title' => 'UI_CONFIG_CHECK_FAIL',
                'description' => 'UIConfig validation recorded errors (non-blocking)',
                'meta' => ['declared' => $declared, 'accepted' => $accepted, 'errors' => count($errors), 'warnings' => count($warnings)],
            ]);
        } else {
            $msg = [
                'title' => 'UI_CONFIG_CHECK_OK',
                'description' => 'UIConfig validation completed',
                'meta' => ['declared' => $declared, 'accepted' => $accepted, 'warnings' => count($warnings)],
            ];
            $this->log->appendInstallerEmit($msg);
            $emit && $emit($msg);
        }

        return ['status' => 'ok', 'declared' => $declared, 'accepted' => $accepted, 'errors' => $errors, 'warnings' => $warnings];
    }

    /** Build a flat sections index, expanding floating.zones → "floating.{zoneId}". */
    private function buildSectionsIndex(array $hostScheme): array
    {
        $sections = (array)($hostScheme['sections'] ?? []);
        $floating = (array)($hostScheme['floating']['zones'] ?? []);
        foreach ($floating as $zoneId => $spec) {
            $sections['floating.' . $zoneId] = (array)$spec;
        }
        // Normalize each entry
        foreach ($sections as $k => $v) {
            $v = (array)$v;
            $v['targets'] = (array)($v['targets'] ?? []);
            $sections[$k] = $v;
        }
        return $sections;
    }

    /** Merge section + target extraProps (target overrides). */
    private function mergePropSpec(array $base, array $override): array
    {
        // shallow override per prop name
        foreach ($override as $k => $def) {
            $base[$k] = $def;
        }
        return $base;
    }

    /**
     * Validate props against a simple schema:
     *   $spec = ['badge'=>['type'=>'number'], 'align'=>['type'=>'string','enum'=>['left','right']], ...]
     * Returns arrays of structured issues with codes in ErrorCodes.
     *
     * @return array{errors:list<array<string,mixed>>, warnings:list<array<string,mixed>>}
     */
    private function validateProps(array $props, array $spec, bool $allowUnknown): array
    {
        $errors = [];
        $warnings = [];

        // Unknown prop detection
        if (!$allowUnknown) {
            foreach ($props as $k => $_) {
                if (!array_key_exists($k, $spec)) {
                    $warnings[] = ['code' => ErrorCodes::UI_UNKNOWN_PROP, 'prop' => (string)$k];
                }
            }
        }

        // Type + enum checks
        foreach ($spec as $name => $rule) {
            if (!array_key_exists($name, $props)) {
                // If you ever add "required" to spec, check here
                continue;
            }
            $type = (string)($rule['type'] ?? 'string');
            $val = $props[$name];

            if (!$this->isType($val, $type)) {
                $errors[] = [
                    'code' => ErrorCodes::UI_PROP_TYPE_MISMATCH,
                    'prop' => (string)$name,
                    'expected' => $type,
                    'got' => get_debug_type($val),
                ];
                continue;
            }
            if (isset($rule['enum']) && is_array($rule['enum'])) {
                $allowed = array_map('strval', $rule['enum']);
                if (!in_array((string)$val, $allowed, true)) {
                    $errors[] = [
                        'code' => ErrorCodes::UI_PROP_ENUM_VIOLATION,
                        'prop' => (string)$name,
                        'allowed' => $allowed,
                        'got' => (string)$val,
                    ];
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function isType(mixed $v, string $type): bool
    {
        return match (strtolower($type)) {
            'string'  => is_string($v),
            'number', 'float', 'double' => is_int($v) || is_float($v),
            'integer','int' => is_int($v),
            'boolean','bool' => is_bool($v),
            'array'   => is_array($v),
            'object'  => is_array($v) || is_object($v), // we allow assoc array as "object" here
            default   => true, // unknown rule → don’t block
        };
    }
}