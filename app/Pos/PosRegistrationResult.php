<?php

namespace App\Pos;

final readonly class PosRegistrationResult
{
    public function __construct(
        public int $slipId,
        public string $slipNo,
    ) {}
}
