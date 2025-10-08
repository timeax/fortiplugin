<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Manifest;

use InvalidArgumentException;
use Timeax\FortiPlugin\Core\Security\PermissionManifestValidator as CoreValidator;
use Timeax\FortiPlugin\Permissions\Contracts\CatalogProviderInterface;

/**
 * Adapter that reuses the core PermissionManifestValidator.
 *
 * Responsibilities:
 * - Pull host catalogs from CatalogProviderInterface.
 * - Call the core validator.
 * - Provide a "tryValidate" variant that returns ManifestErrors instead of throwing.
 */
final readonly class ManifestValidator
{
    public function __construct(
        private CatalogProviderInterface $catalog
    ) {}

    /**
     * Validate and return the normalized manifest produced by the core validator.
     * @param array|string $manifest
     * @return array Normalized manifest (e.g., ['required_permissions'=>[], 'optional_permissions'=>[]])
     * @throws InvalidArgumentException when invalid.
     */
    public function validateOrFail(array|string $manifest): array
    {
        $core = new CoreValidator(
            allowedChannels: $this->catalog->notificationChannels(),
            modelConfig:     $this->catalog->models(),
            moduleConfig:    $this->catalog->modules(),
            codecConfig:     $this->catalog->codecGroups()
        );

        // Core validator already performs structural + semantic checks and returns normalized data.
        return $core->validate($manifest);
    }

    /**
     * Non-throwing variant. Returns:
     *   ['ok'=>true, 'data'=>array]  OR  ['ok'=>false, 'errors'=>ManifestErrors]
     */
    public function tryValidate(array|string $manifest): array
    {
        try {
            $data = $this->validateOrFail($manifest);
            return ['ok' => true, 'data' => $data];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'errors' => ManifestErrors::fromException($e)];
        }
    }
}