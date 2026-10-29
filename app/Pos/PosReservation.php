<?php

namespace App\Pos;

use Carbon\CarbonImmutable;

final readonly class PosReservation
{
    /**
     * @param  string  $startTime  HH:MM
     * @param  string  $endTime  HH:MM
     * @param  string  $customerRequest  お客様からの要望（未入力は空文字）
     * @param  list<PosOrderItem>  $items
     */
    public function __construct(
        public int $brandId,
        public CarbonImmutable $slipDate,
        public string $startTime,
        public string $endTime,
        public string $customerName,
        public string $customerNameKana,
        public string $tel,
        public string $customerRequest,
        public array $items,
    ) {}
}
