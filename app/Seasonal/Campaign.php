<?php

namespace App\Seasonal;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class Campaign
{
    /**
     * @param  list<PickupStore>  $stores
     * @param  list<CampaignProduct>  $products
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $coverImage,
        public CarbonImmutable $opensAt,
        public CarbonImmutable $closesAt,
        public int $maxQuantityPerProduct,
        public array $stores,
        public array $products,
    ) {}

    public static function fromConfig(string $slug, array $config): self
    {
        return new self(
            slug: $slug,
            title: $config['title'],
            description: $config['description'] ?? '',
            coverImage: $config['cover_image'] ?? '',
            opensAt: CarbonImmutable::parse($config['opens_at']),
            closesAt: CarbonImmutable::parse($config['closes_at']),
            maxQuantityPerProduct: (int) $config['max_quantity_per_product'],
            stores: array_map(PickupStore::fromConfig(...), array_values($config['stores'])),
            products: array_map(CampaignProduct::fromConfig(...), array_values($config['products'])),
        );
    }

    public function isOpen(?DateTimeInterface $now = null): bool
    {
        $now = CarbonImmutable::instance($now ?? now());

        return $now->betweenIncluded($this->opensAt, $this->closesAt);
    }

    public function product(int $id): ?CampaignProduct
    {
        foreach ($this->products as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        return null;
    }

    /**
     * 個数の指定から、表示順の明細（単価・小計はキャンペーン定義の価格）を作る。
     *
     * @param  array<int, int>  $quantities  商品ID => 個数
     * @return list<array{product: CampaignProduct, quantity: int, subtotal: int}>
     */
    public function orderLines(array $quantities): array
    {
        $lines = [];
        foreach ($this->products as $product) {
            $quantity = (int) ($quantities[$product->id] ?? 0);
            if ($quantity > 0) {
                $lines[] = ['product' => $product, 'quantity' => $quantity, 'subtotal' => $product->price * $quantity];
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, int>  $quantities
     */
    public function orderTotal(array $quantities): int
    {
        return array_sum(array_column($this->orderLines($quantities), 'subtotal'));
    }

    public function store(string $code): ?PickupStore
    {
        foreach ($this->stores as $store) {
            if ($store->code === $code) {
                return $store;
            }
        }

        return null;
    }
}
