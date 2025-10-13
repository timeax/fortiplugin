<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TComposerAction 'skip'|'add'|'conflict'
 * @phpstan-type TComposerPlan array{
 *   actions: array<string,TComposerAction>,     # package => action
 *   core_conflicts: list<string>,              # e.g. ['laravel/framework','php']
 * }
 */
final readonly class ComposerPlan implements ArraySerializable
{
    /** @param array<string,'skip'|'add'|'conflict'> $actions */
    public function __construct(
        public array $actions,
        /** @var list<string> */
        public array $core_conflicts,
    ) {}

    /** @param TComposerPlan $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['actions'] ?? [],
            array_values($data['core_conflicts'] ?? []),
        );
    }

    /** @return TComposerPlan */
    public function toArray(): array
    {
        return [
            'actions' => $this->actions,
            'core_conflicts' => $this->core_conflicts,
        ];
    }
}