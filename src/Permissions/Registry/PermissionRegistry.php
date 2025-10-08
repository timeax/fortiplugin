<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Registry;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\CodecChecker;
use Timeax\FortiPlugin\Permissions\Evaluation\DbChecker;
use Timeax\FortiPlugin\Permissions\Evaluation\FileChecker;
use Timeax\FortiPlugin\Permissions\Evaluation\ModuleChecker;
use Timeax\FortiPlugin\Permissions\Evaluation\NetworkChecker;
use Timeax\FortiPlugin\Permissions\Evaluation\NotificationChecker;
use Timeax\FortiPlugin\Permissions\Evaluation\RouteChecker;
use Timeax\FortiPlugin\Permissions\Ingestion\CodecIngestor;
use Timeax\FortiPlugin\Permissions\Ingestion\DbIngestor;
use Timeax\FortiPlugin\Permissions\Ingestion\FileIngestor;
use Timeax\FortiPlugin\Permissions\Ingestion\ModuleIngestor;
use Timeax\FortiPlugin\Permissions\Ingestion\NetworkIngestor;
use Timeax\FortiPlugin\Permissions\Ingestion\NotificationIngestor;

/**
 * Central registry for permission types → checkers & ingestors.
 * - ROUTE has a checker but no ingestor.
 * - Other types should have both.
 */
final class PermissionRegistry
{
    /** @var array<string,class-string<PermissionCheckerInterface>> */
    private array $checkers = [];
    /** @var array<string,class-string<PermissionIngestorInterface>> */
    private array $ingestors = [];

    public function __construct(private readonly Container $app)
    {
        // You can move these defaults to a service provider if you prefer.
        $this->bootDefaults();
    }

    /** Register a checker class for a type. */
    public function registerChecker(PermissionType|string $type, string $checkerFqcn): void
    {
        $key = $type instanceof PermissionType ? $type->value : (string)$type;
        $this->checkers[$key] = $checkerFqcn;
    }

    /** Register an ingestor class for a type. */
    public function registerIngestor(PermissionType|string $type, string $ingestorFqcn): void
    {
        $key = $type instanceof PermissionType ? $type->value : (string)$type;
        $this->ingestors[$key] = $ingestorFqcn;
    }

    /** Resolve a checker instance for a type.
     * @throws BindingResolutionException
     */
    public function checkerFor(PermissionType|string $type): PermissionCheckerInterface
    {
        $key = $type instanceof PermissionType ? $type->value : (string)$type;
        $fqcn = $this->checkers[$key] ?? null;
        if (!$fqcn) {
            throw new InvalidArgumentException("No checker registered for type '{$key}'");
        }
        /** @var PermissionCheckerInterface $inst */
        $inst = $this->app->make($fqcn);
        return $inst;
    }

    /**
     * Resolve an ingestor instance for a type.
     * Returns null for types that don't ingest (e.g., route).
     * @throws BindingResolutionException
     */
    public function ingestorFor(PermissionType|string $type): ?PermissionIngestorInterface
    {
        $key = $type instanceof PermissionType ? $type->value : (string)$type;
        $fqcn = $this->ingestors[$key] ?? null;
        if (!$fqcn) {
            return null;
        }
        /** @var PermissionIngestorInterface $inst */
        $inst = $this->app->make($fqcn);
        return $inst;
    }

    /** All registered types (union of checkers & ingestors). */
    public function types(): array
    {
        return array_values(array_unique(array_merge(array_keys($this->checkers), array_keys($this->ingestors))));
    }

    /** True if a type has a checker. */
    public function hasChecker(PermissionType|string $type): bool
    {
        $key = $type instanceof PermissionType ? $type->value : (string)$type;
        return isset($this->checkers[$key]);
    }

    /** True if a type has an ingestor. */
    public function hasIngestor(PermissionType|string $type): bool
    {
        $key = $type instanceof PermissionType ? $type->value : (string)$type;
        return isset($this->ingestors[$key]);
    }

    /** Default wiring — replace FQCNs with your concrete classes when implemented. */
    private function bootDefaults(): void
    {
        // Checkers (all types)
        $this->registerChecker(PermissionType::DB,           DbChecker::class);
        $this->registerChecker(PermissionType::FILE,         FileChecker::class);
        $this->registerChecker(PermissionType::NOTIFICATION, NotificationChecker::class);
        $this->registerChecker(PermissionType::MODULE,       ModuleChecker::class);
        $this->registerChecker(PermissionType::NETWORK,      NetworkChecker::class);
        $this->registerChecker(PermissionType::CODEC,        CodecChecker::class);
        $this->registerChecker(PermissionType::ROUTE,        RouteChecker::class);

        // Ingestors (no ROUTE)
        $this->registerIngestor(PermissionType::DB,           DbIngestor::class);
        $this->registerIngestor(PermissionType::FILE,         FileIngestor::class);
        $this->registerIngestor(PermissionType::NOTIFICATION, NotificationIngestor::class);
        $this->registerIngestor(PermissionType::MODULE,       ModuleIngestor::class);
        $this->registerIngestor(PermissionType::NETWORK,      NetworkIngestor::class);
        $this->registerIngestor(PermissionType::CODEC,        CodecIngestor::class);
    }
}