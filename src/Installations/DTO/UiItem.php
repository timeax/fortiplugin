<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TUiItem array{
 *   section: string,
 *   id: string,
 *   text?: string,
 *   icon?: string,
 *   href?: string,
 *   extend?: array{targetId:string},
 *   props?: array<string,mixed>,
 *   overridelayout?: bool|array{name:string,variant?:string,props?:array<string,mixed>},
 *   overrideLayout?: bool|array{name:string,variant?:string,props?:array<string,mixed>},
 *   suggest?: bool
 * }
 *
 * Normalized representation of a single UI item suggestion.
 */
final readonly class UiItem implements ArraySerializable
{
    public function __construct(
        public string  $section,
        public string  $id,
        public ?string $text = null,
        public ?string $icon = null,
        public ?string $href = null,
        public ?string $extend_target_id = null,
        /** @var array<string,mixed> */
        public array   $props = [],
        /** true if boolean override was requested */
        public bool    $override_layout_requested = false,
        /** @var array{name:string,variant?:string,props?:array<string,mixed>}|null */
        public ?array  $override_layout = null,
        public bool    $suggest = true,
    ) {}

    /** @param TUiItem $data */
    public static function fromArray(array $data): static
    {
        $section = (string)($data['section'] ?? '');
        $id      = (string)($data['id'] ?? '');
        if ($section === '' || $id === '') {
            throw new \InvalidArgumentException('UiItem requires non-empty section and id');
        }

        // extend.targetId → extend_target_id
        $extendTarget = null;
        if (isset($data['extend']) && is_array($data['extend'])) {
            $t = (string)($data['extend']['targetId'] ?? '');
            $extendTarget = $t !== '' ? $t : null;
        }

        // Normalize override layout (accept overridelayout or overrideLayout)
        $overrideRequested = false;
        $overrideDetail = null;

        $rawOverride = $data['overridelayout'] ?? $data['overrideLayout'] ?? null;
        if (is_bool($rawOverride)) {
            $overrideRequested = $rawOverride;
        } elseif (is_array($rawOverride)) {
            if (!isset($rawOverride['name']) || !is_string($rawOverride['name']) || $rawOverride['name'] === '') {
                throw new \InvalidArgumentException("UiItem override layout object requires non-empty 'name'");
            }
            $overrideDetail = [
                'name'    => (string)$rawOverride['name'],
                'variant' => isset($rawOverride['variant']) ? (string)$rawOverride['variant'] : null,
                'props'   => isset($rawOverride['props']) && is_array($rawOverride['props']) ? $rawOverride['props'] : null,
            ];
        }

        return new self(
            section: $section,
            id: $id,
            text: isset($data['text']) ? (string)$data['text'] : null,
            icon: isset($data['icon']) ? (string)$data['icon'] : null,
            href: isset($data['href']) ? (string)$data['href'] : null,
            extend_target_id: $extendTarget,
            props: isset($data['props']) && is_array($data['props']) ? $data['props'] : [],
            override_layout_requested: $overrideRequested,
            override_layout: $overrideDetail,
            suggest: array_key_exists('suggest', $data) ? (bool)$data['suggest'] : true,
        );
    }

    /** @return TUiItem */
    public function toArray(): array
    {
        $out = [
            'section' => $this->section,
            'id'      => $this->id,
        ];
        if ($this->text !== null) $out['text'] = $this->text;
        if ($this->icon !== null) $out['icon'] = $this->icon;
        if ($this->href !== null) $out['href'] = $this->href;
        if ($this->extend_target_id !== null) {
            $out['extend'] = ['targetId' => $this->extend_target_id];
        }
        if ($this->props !== []) $out['props'] = $this->props;
        if ($this->override_layout !== null) {
            $out['overridelayout'] = $this->override_layout; // prefer the snake alias in output
        } elseif ($this->override_layout_requested) {
            $out['overridelayout'] = true;
        }
        if ($this->suggest !== true) $out['suggest'] = $this->suggest;

        return $out;
    }
}