<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

final readonly class PermissionListSummary
{
    /**
     * @param array<string,int> $byType
     */
    public function __construct(
        public array $byType,
        public int   $total,
        public int   $active,
        public int   $inactive,
        public int   $requiredTotal,
        public int   $requiredSatisfied,
        public int   $requiredPending
    ) {}

    public function toArray(): array
    {
        return [
            'totals' => [
                'by_type' => $this->byType,
                'total'   => $this->total,
                'active'  => $this->active,
                'inactive'=> $this->inactive,
            ],
            'required' => [
                'total'     => $this->requiredTotal,
                'satisfied' => $this->requiredSatisfied,
                'pending'   => $this->requiredPending,
            ],
        ];
    }
}