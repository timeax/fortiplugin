<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * Root DTO that groups UI items by section and extension containers.
 *
 * Input shape (from config):
 *   { "items": [ TUiItem, ... ] }
 *
 * Output (normalized mapping):
 *   {
 *     "sections": {
 *       "<section>": {
 *         "section": "<section>",
 *         "items": [TUiItem...],                  // base items (no extend)
 *         "extensions": { "<targetId>": [TUiItem...] } // items extending a host target
 *       },
 *       ...
 *     }
 *   }
 *
 * @phpstan-type TUiConfigInput array{items: list<TUiItem>}
 * @phpstan-type TUiConfigMap array{sections: array<string, TUiSectionMap>}
 */
final readonly class UiConfigMap implements ArraySerializable
{
    /** @param array<string, UiSectionMap> $sections */
    public function __construct(
        public array $sections
    )
    {
    }

    /** @param TUiConfigInput $data */
    public static function fromArray(array $data): static
    {
        $items = (array)($data['items'] ?? []);
        return self::fromItems($items);
    }

    /**
     * Build from a flat list of items (already decoded from JSON).
     * @param list<array|UiItem> $items
     */
    public static function fromItems(array $items): static
    {
        /** @var array<string, array{items:list<UiItem>, extensions: array<string, list<UiItem>>}> $bucket */
        $bucket = [];

        foreach ($items as $raw) {
            $item = $raw instanceof UiItem ? $raw : UiItem::fromArray($raw);

            if (!isset($bucket[$item->section])) {
                $bucket[$item->section] = ['items' => [], 'extensions' => []];
            }

            if ($item->extend_target_id) {
                $t = $item->extend_target_id;
                if (!isset($bucket[$item->section]['extensions'][$t])) {
                    $bucket[$item->section]['extensions'][$t] = [];
                }
                $bucket[$item->section]['extensions'][$t][] = $item;
            } else {
                $bucket[$item->section]['items'][] = $item;
            }
        }

        $sections = [];
        foreach ($bucket as $section => $data) {
            $sections[$section] = new UiSectionMap(
                section: $section,
                items: $data['items'],
                extensions: $data['extensions']
            );
        }

        return new self($sections);
    }

    /** @return TUiConfigMap */
    public function toArray(): array
    {
        $out = array_map(static function ($section) {
            return $section->toArray();
        }, $this->sections);
        return ['sections' => $out];
    }
}