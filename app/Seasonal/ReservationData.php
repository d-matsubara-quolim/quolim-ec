<?php

namespace App\Seasonal;

/**
 * 確認画面から登録までセッションで受け渡す予約内容。
 * 価格は持たず、表示・登録のたびにキャンペーン定義から求める。
 */
final readonly class ReservationData
{
    /**
     * @param  array<int, int>  $quantities  商品ID => 個数（1 以上のみ）
     */
    public function __construct(
        public string $campaign,
        public array $quantities,
        public string $storeCode,
        public string $date,
        public string $timeSlot,
        public string $name,
        public string $kana,
        public string $tel,
        public string $note,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            campaign: $data['campaign'],
            quantities: array_map('intval', $data['quantities']),
            storeCode: $data['store'],
            date: $data['date'],
            timeSlot: $data['time_slot'],
            name: $data['name'],
            kana: $data['kana'],
            tel: $data['tel'],
            note: $data['note'] ?? '',
        );
    }

    /**
     * フォームの入力値と同じキーの配列。セッション保存と「修正する」で入力欄に戻すときに使う。
     */
    public function toArray(): array
    {
        return [
            'campaign' => $this->campaign,
            'quantities' => $this->quantities,
            'store' => $this->storeCode,
            'date' => $this->date,
            'time_slot' => $this->timeSlot,
            'name' => $this->name,
            'kana' => $this->kana,
            'tel' => $this->tel,
            'note' => $this->note,
        ];
    }
}
