<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

/**
 * One merged permission view (concrete row + all its sources).
 */
final readonly class PermissionListItem
{
    /**
     * @param array<int, array<string,mixed>> $sourcesDirect
     * @param array<int, array<string,mixed>> $sourcesTags
     * @param array<string,mixed>             $concrete
     * @param array<string,mixed>             $effectiveActions
     * @param array<string,mixed>             $presentation
     */
    public function __construct(
        public string $type,
        public int    $concreteId,
        public ?string $naturalKey,
        public array  $presentation,
        public array  $effectiveActions,
        public array  $concrete,
        public array  $sourcesDirect,
        public array  $sourcesTags,
        public bool   $required,
        public bool   $activeEffective
    ) {}

    public function toArray(): array
    {
        return [
            'type'            => $this->type,
            'concreteId'      => $this->concreteId,
            'naturalKey'      => $this->naturalKey,
            'presentation'    => $this->presentation,
            'effectiveActions'=> $this->effectiveActions,
            'concrete'        => $this->concrete,
            'sources'         => [
                'direct' => $this->sourcesDirect,
                'tags'   => $this->sourcesTags,
            ],
            'required'        => $this->required,
            'activeEffective' => $this->activeEffective,
        ];
    }
}