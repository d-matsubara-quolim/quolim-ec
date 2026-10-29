<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 電話番号の形式（旧 Web 予約 common.js isTel を移植し、060 を追加）。
 * 先頭 0、半角数字とハイフンのみ、ハイフンは 2 つまで、
 * 060・070・080・090・050 始まりは 11 桁、それ以外は 10 桁。
 * 全角数字・長音などの変換は呼び出し側（normalize）で済ませておく。
 */
final class JapanesePhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('電話番号が正しくありません。お手数ですがご確認ください。');
        }
    }

    public static function passes(string $tel): bool
    {
        if (preg_match('/\A0[0-9-]+\z/', $tel) !== 1 || substr_count($tel, '-') > 2) {
            return false;
        }

        $digits = str_replace('-', '', $tel);

        return preg_match('/\A0[56789]0/', $tel) === 1
            ? strlen($digits) === 11
            : strlen($digits) === 10;
    }

    /**
     * 全角数字を半角に、各種ダッシュ・長音をハイフンに変換し、空白を除く（common.js ConvertTelEn）。
     */
    public static function normalize(string $tel): string
    {
        $tel = mb_convert_kana($tel, 'n');
        $tel = preg_replace('/[ー－−‐―]/u', '-', $tel);

        return preg_replace('/[\s　]+/u', '', $tel);
    }
}
