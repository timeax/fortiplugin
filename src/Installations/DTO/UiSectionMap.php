<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * Container for items inside a single section.
 *
 * @phpstan-type TUiSectionMap array{
 *   section: string,
 *   items: list<TUiItem>,
 *   extensions: array<string, list<TUiItem>>
 * }
 */
final readonly class UiSectionMap implements ArraySerializable
{
    /**
     * @param string   $section
     * @param UiItem[] $items
     * @param array<string, list<UiItem>> $extensions
     */
    public function __construct(
        public string $section,
        public array  $items,
        public array  $extensions
    ) {}

    /** @param TUiSectionMap $data */
    public static function fromArray(array $data): static
    {
        $section = (string)($data['section'] ?? '');
        if ($section === '') throw new \InvalidArgumentException('UiSectionMap requires section');

        $items = [];
        foreach ((array)($data['items'] ?? []) as $raw) {
            $items[] = $raw instanceof UiItem ? $raw : UiItem::fromArray($raw);
        }

        $ext = [];
        foreach ((array)($data['extensions'] ?? []) as $targetId => $list) {
            $bucket = [];
            foreach ((array)$list as $raw) {
                $bucket[] = $raw instanceof UiItem ? $raw : UiItem::fromArray($raw);
            }
            $ext[(string)$targetId] = $bucket;
        }

        return new self($section, $items, $ext);
    }

    /** @return TUiSectionMap */
    public function toArray(): array
    {
        $items = array_map(static fn(UiItem $i) => $i->toArray(), $this->items);

        $ext = [];
        foreach ($this->extensions as $targetId => $list) {
            $ext[$targetId] = array_map(static fn(UiItem $i) => $i->toArray(), $list);
        }

        return [
            'section'    => $this->section,
            'items'      => $items,
            'extensions' => $ext,
        ];
    }
}