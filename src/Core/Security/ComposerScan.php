<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use Timeax\FortiPlugin\Core\PluginPolicy;

class ComposerScan
{
    protected PluginPolicy $policy;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Scan a composer.json file for forbidden packages.
     * @param string $composerJsonPath
     * @return array List of violations.
     * @throws JsonException
     */
    public function scan(string $composerJsonPath): array
    {
        $violations = [];
        if (!is_file($composerJsonPath)) {
            return [
                [
                    'type' => 'composer_file_missing',
                    'file' => $composerJsonPath,
                    'issue' => 'composer.json not found'
                ]
            ];
        }

        $json = json_decode(file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);
        if (!$json) {
            return [
                [
                    'type' => 'composer_file_invalid',
                    'file' => $composerJsonPath,
                    'issue' => 'Invalid JSON in composer.json'
                ]
            ];
        }

        $deps = array_merge(
            $json['require'] ?? [],
            $json['require-dev'] ?? []
        );

        foreach ($this->policy->getForbiddenPackages() as $forbidden) {
            foreach ($deps as $package => $version) {
                if (strtolower($package) === strtolower($forbidden)) {
                    $violations[] = [
                        'type' => 'forbidden_package_dependency',
                        'package' => $package,
                        'version' => $version,
                        'file' => $composerJsonPath,
                        'issue' => "Composer requires forbidden package: $package"
                    ];
                }
            }
        }

        return $violations;
    }
}