<?php /** @noinspection NotOptimalIfConditionsInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Policy;

use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;

/**
 * Evaluates rule/assignment conditions:
 *  - guard:   exact equality with context['guard'] (if provided)
 *  - env:     allow/deny lists; env is taken from context['env'] or envProvider()
 *  - setting_link: toggles by a plugin setting; settings taken from context['settings'] or settingsProvider(pluginId)
 *
 * Inject minimal providers instead of new interfaces to keep this class focused:
 *  - $envProvider:      fn(): string
 *  - $settingsProvider: fn(int $pluginId): array   // returns decoded settings as key => mixed
 */
final class ConditionsEvaluator implements ConditionsEvaluatorInterface
{
    /** @var callable():string */
    private $envProvider;

    /** @var callable(int):array */
    private $settingsProvider;

    /**
     * @param callable():string    $envProvider
     * @param callable(int):array  $settingsProvider
     */
    public function __construct(callable $envProvider, callable $settingsProvider)
    {
        $this->envProvider      = $envProvider;
        $this->settingsProvider = $settingsProvider;
    }

    /**
     * @param array $conditions e.g. ['guard'=>'api','env'=>['allow'=>['staging']],'setting_link'=>'enable_codec']
     * @param array $context    e.g. ['plugin_id'=>123,'guard'=>'api','env'=>'staging','settings'=>['enable_codec'=>true]]
     */
    public function matches(array $conditions, array $context): bool
    {
        if ($conditions === []) {
            return true;
        }

        // 1) guard
        if (array_key_exists('guard', $conditions)) {
            $needGuard = (string)$conditions['guard'];
            $haveGuard = isset($context['guard']) ? (string)$context['guard'] : '';
            if ($needGuard !== '' && $needGuard !== $haveGuard) {
                return false;
            }
        }

        // 2) env (allow/deny)
        if (array_key_exists('env', $conditions) && is_array($conditions['env'])) {
            $envSpec = $conditions['env'];
            $env = $context['env'] ?? null;
            if ($env === null) {
                $env = ($this->envProvider)();
            }
            $env = (string)$env;

            $allow = isset($envSpec['allow']) && is_array($envSpec['allow'])
                ? array_values(array_unique(array_map('strval', $envSpec['allow'])))
                : null;

            $deny  = isset($envSpec['deny']) && is_array($envSpec['deny'])
                ? array_values(array_unique(array_map('strval', $envSpec['deny'])))
                : null;

            if ($allow && !in_array($env, $allow, true)) {
                return false;
            }
            if ($deny && in_array($env, $deny, true)) {
                return false;
            }
        }

        // 3) setting_link (truthy/falsey switch)
        if (array_key_exists('setting_link', $conditions)) {
            $key = $conditions['setting_link'];
            // fetch settings from context or provider
            $settings = $context['settings'] ?? null;
            if (!is_array($settings)) {
                $pluginId = isset($context['plugin_id']) ? (int)$context['plugin_id'] : 0;
                $settings = $pluginId > 0 ? ($this->settingsProvider)($pluginId) : [];
            }

            // setting may be identified by string key or numeric id; normalize to string lookup first
            $value = null;
            if (is_string($key) && $key !== '' && array_key_exists($key, $settings)) {
                $value = $settings[$key];
            } elseif ((is_int($key) || (is_string($key) && ctype_digit($key))) && array_key_exists((string)$key, $settings)) {
                $value = $settings[(string)$key];
            }

            // treat common falsey values as "off"
            if (!$this->isTruthy($value)) {
                return false;
            }
        }

        return true;
    }

    /** conservative truthiness for settings (false, 0, '0', '', null are false) */
    private function isTruthy(mixed $v): bool
    {
        if ($v === null) return false;
        if ($v === false) return false;
        if ($v === 0) return false;
        if ($v === '0') return false;
        if ($v === '') return false;
        return true;
    }
}