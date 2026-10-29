<?php

namespace Tests\Unit;

use App\Rules\JapanesePhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JapanesePhoneNumberTest extends TestCase
{
    public static function phoneNumbers(): array
    {
        return [
            ['090-1234-5678', true],
            ['09012345678', true],
            ['080-1234-5678', true],
            ['070-1234-5678', true],
            ['050-1234-5678', true],
            ['060-1234-5678', true],
            ['0956-12-3456', true],
            ['0956123456', true],
            ['03-1234-5678', true],
            ['090-1234-567', false],
            ['060-123-4567', false],
            ['0956-12-34567', false],
            ['1956-12-3456', false],
            ['090-12-34-5678', false],
            ['090 1234 5678', false],
            ['', false],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_passes(string $tel, bool $expected): void
    {
        $this->assertSame($expected, JapanesePhoneNumber::passes($tel));
    }

    public function test_normalize_converts_full_width_and_dashes(): void
    {
        $this->assertSame('060-1234-5678', JapanesePhoneNumber::normalize('０６０ー１２３４－５６７８'));
        $this->assertSame('09012345678', JapanesePhoneNumber::normalize(' 090 1234　5678 '));
    }
}
