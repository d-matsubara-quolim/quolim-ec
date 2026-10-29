<?php

namespace App\Seasonal;

final readonly class CampaignProduct
{
    /**
     * @param  array<string, int>  $posProductIds  受取店舗コード => POS 商品ID
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $nameEn,
        public int $price,
        public string $unit,
        public string $note,
        public string $image,
        public string $description,
        public array $posProductIds,
    ) {}

    public static function fromConfig(array $config): self
    {
        $posProductIds = [];
        foreach ($config['pos_product_ids'] as $storeCode => $posProductId) {
            $posProductIds[str_pad((string) $storeCode, 2, '0', STR_PAD_LEFT)] = (int) $posProductId;
        }

        return new self(
            id: (int) $config['id'],
            name: $config['name'],
            nameEn: $config['name_en'] ?? '',
            price: (int) $config['price'],
            unit: $config['unit'],
            note: $config['note'] ?? '',
            image: $config['image'] ?? '',
            description: trim($config['description'] ?? ''),
            posProductIds: $posProductIds,
        );
    }

    public function isAvailableAt(string $storeCode): bool
    {
        return array_key_exists($storeCode, $this->posProductIds);
    }

    /**
     * @return list<string>
     */
    public function storeCodes(): array
    {
        return array_map('strval', array_keys($this->posProductIds));
    }

    public function posProductIdAt(string $storeCode): int
    {
        return $this->posProductIds[$storeCode];
    }
}
