<?php

namespace App\Http\Requests\Seasonal;

use App\Rules\JapanesePhoneNumber;
use App\Seasonal\Campaign;
use App\Seasonal\ReservationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 予約内容の検証。価格は受け取らず、商品・店舗・日時はキャンペーン定義にあるものだけを受け付ける。
 */
class ReservationRequest extends FormRequest
{
    // POS のテーブルが utf8mb3 のため、4 バイト文字（絵文字など）は登録できない
    private const string NO_FOUR_BYTE_CHARS = 'not_regex:/[\x{10000}-\x{10FFFF}]/u';

    public function campaign(): Campaign
    {
        return $this->route('campaign');
    }

    protected function prepareForValidation(): void
    {
        $quantities = $this->input('quantities');

        $this->merge([
            'quantities' => is_array($quantities) ? array_map(fn ($q) => $q === null || $q === '' ? 0 : $q, $quantities) : [],
            'name' => $this->withoutControlChars($this->input('name')),
            'kana' => $this->withoutControlChars($this->input('kana')),
            'note' => $this->withoutControlChars($this->input('note')),
            'tel' => is_string($this->input('tel')) ? JapanesePhoneNumber::normalize($this->input('tel')) : $this->input('tel'),
        ]);
    }

    public function rules(): array
    {
        $campaign = $this->campaign();

        return [
            'quantities' => ['array'],
            'quantities.*' => ['integer', 'min:0', 'max:'.$campaign->maxQuantityPerProduct],
            'store' => ['required', 'string', Rule::in(array_map(fn ($store) => $store->code, $campaign->stores))],
            'date' => ['required', 'string'],
            'time_slot' => ['required', 'string'],
            'name' => ['required', 'string', 'max:20', self::NO_FOUR_BYTE_CHARS],
            'kana' => ['required', 'string', 'max:20', self::NO_FOUR_BYTE_CHARS],
            'tel' => ['required', 'string', new JapanesePhoneNumber],
            'note' => ['nullable', 'string', 'max:150', self::NO_FOUR_BYTE_CHARS],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $campaign = $this->campaign();
                $errors = $validator->errors();
                $quantities = $this->input('quantities', []);

                if ($errors->has('quantities') || $errors->has('quantities.*')) {
                    return;
                }

                foreach (array_keys($quantities) as $productId) {
                    if ($campaign->product((int) $productId) === null) {
                        $errors->add('quantities', 'ご予約の商品が正しくありません。');

                        return;
                    }
                }

                if (array_sum(array_map('intval', $quantities)) < 1) {
                    $errors->add('quantities', 'ご予約の商品と個数を選択してください。');
                }

                $store = $errors->has('store') ? null : $campaign->store((string) $this->input('store'));
                if ($store === null) {
                    return;
                }

                if (! $errors->has('date') && ! $store->hasDate((string) $this->input('date'))) {
                    $errors->add('date', '受取日を選択してください。');
                }
                if (! $errors->has('time_slot') && ! $store->hasTimeSlot((string) $this->input('time_slot'))) {
                    $errors->add('time_slot', '受取時間を選択してください。');
                }

                foreach ($quantities as $productId => $quantity) {
                    $product = $campaign->product((int) $productId);
                    if ((int) $quantity > 0 && ! $product->isAvailableAt($store->code)) {
                        $errors->add('store', "「{$product->name}」は「{$store->name}」ではお受け取りできません。");
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        $max = $this->campaign()->maxQuantityPerProduct;

        return [
            'quantities.*.integer' => '個数が正しくありません。',
            'quantities.*.min' => '個数が正しくありません。',
            'quantities.*.max' => "個数は1商品につき{$max}個までです。",
            'store.required' => '受取店舗を選択してください。',
            'store.in' => '受取店舗を選択してください。',
            'date.required' => '受取日を選択してください。',
            'time_slot.required' => '受取時間を選択してください。',
            'name.required' => 'お名前を入力してください。',
            'name.max' => 'お名前は20文字以内で入力してください。',
            'kana.required' => 'ふりがなを入力してください。',
            'kana.max' => 'ふりがなは20文字以内で入力してください。',
            'tel.required' => '電話番号を入力してください。',
            'note.max' => 'ご要望は150文字以内で入力してください。',
            '*.not_regex' => '絵文字など、一部の文字はご利用いただけません。',
            '*.string' => '入力内容が正しくありません。',
        ];
    }

    public function toReservationData(): ReservationData
    {
        $quantities = [];
        foreach ($this->validated('quantities', []) as $productId => $quantity) {
            if ((int) $quantity > 0) {
                $quantities[(int) $productId] = (int) $quantity;
            }
        }

        return new ReservationData(
            campaign: $this->campaign()->slug,
            quantities: $quantities,
            storeCode: $this->validated('store'),
            date: $this->validated('date'),
            timeSlot: $this->validated('time_slot'),
            name: $this->validated('name'),
            kana: $this->validated('kana'),
            tel: $this->validated('tel'),
            note: $this->validated('note') ?? '',
        );
    }

    private function withoutControlChars(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/[\x00-\x1F\x7F]/u', '', $value) : $value;
    }
}
