<?php

namespace App\Seasonal;

/**
 * 完了画面に表示する予約結果。個人情報は持たない。
 */
final readonly class CompletedReservation
{
    /**
     * @param  array<int, int>  $quantities
     */
    public function __construct(
        public string $slipNo,
        public string $storeCode,
        public string $date,
        public string $timeSlot,
        public array $quantities,
    ) {}

    public static function fromReservation(string $slipNo, ReservationData $data): self
    {
        return new self($slipNo, $data->storeCode, $data->date, $data->timeSlot, $data->quantities);
    }

    public static function fromArray(array $data): self
    {
        return new self($data['slip_no'], $data['store'], $data['date'], $data['time_slot'], $data['quantities']);
    }

    public function toArray(): array
    {
        return [
            'slip_no' => $this->slipNo,
            'store' => $this->storeCode,
            'date' => $this->date,
            'time_slot' => $this->timeSlot,
            'quantities' => $this->quantities,
        ];
    }
}
