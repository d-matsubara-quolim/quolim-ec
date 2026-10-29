<?php

namespace App\Seasonal;

/**
 * キャンペーン定義（config/seasonal/*.php）の読み込み。ファイル名がキャンペーンの URL になる。
 */
final class CampaignRepository
{
    /**
     * @return list<Campaign>
     */
    public function all(): array
    {
        $campaigns = [];
        foreach (config('seasonal', []) as $slug => $config) {
            $campaigns[] = Campaign::fromConfig((string) $slug, $config);
        }

        return $campaigns;
    }

    /**
     * @return list<Campaign>
     */
    public function open(): array
    {
        return array_values(array_filter($this->all(), fn (Campaign $campaign) => $campaign->isOpen()));
    }

    public function find(string $slug): ?Campaign
    {
        $config = config("seasonal.{$slug}");

        return is_array($config) && isset($config['products']) ? Campaign::fromConfig($slug, $config) : null;
    }
}
