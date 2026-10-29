<?php

namespace App\Seasonal;

use Carbon\CarbonImmutable;

final readonly class PickupStore
{
    /**
     * @param  list<string>  $dates  YYYY-MM-DD
     * @param  list<string>  $timeSlots  HH:MM-HH:MM
     */
    public function __construct(
        public string $code,
        public string $name,
        public int $brandId,
        public array $dates,
        public array $timeSlots,
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            code: (string) $config['code'],
            name: $config['name'],
            brandId: (int) $config['brand_id'],
            dates: array_values($config['dates']),
            timeSlots: array_values($config['time_slots']),
        );
    }

    public function hasDate(string $date): bool
    {
        return in_array($date, $this->dates, true);
    }

    public function hasTimeSlot(string $timeSlot): bool
    {
        return in_array($timeSlot, $this->timeSlots, true);
    }

    /**
     * 最初の枠の開始から最後の枠の終了まで（例：12:00〜19:00）。
     */
    public function pickupHours(): string
    {
        $first = explode('-', $this->timeSlots[0]);
        $last = explode('-', $this->timeSlots[array_key_last($this->timeSlots)]);

        return self::formatTimeSlot($first[0].'-'.$last[1]);
    }

    public static function formatDate(string $date): string
    {
        $day = CarbonImmutable::parse($date);

        return $day->format('n月j日').'（'.['日', '月', '火', '水', '木', '金', '土'][$day->dayOfWeek].'）';
    }

    public static function formatTimeSlot(string $timeSlot): string
    {
        return str_replace('-', '〜', $timeSlot);
    }
}
