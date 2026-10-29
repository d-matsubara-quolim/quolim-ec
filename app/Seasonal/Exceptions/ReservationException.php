<?php

namespace App\Seasonal\Exceptions;

use RuntimeException;

final class ReservationException extends RuntimeException
{
    public const int CLOSED = 1;

    public const int EXPIRED = 2;

    public const int BUSY = 3;

    public const int FAILED = 4;

    public static function closed(): self
    {
        return new self('Reservation period is closed.', self::CLOSED);
    }

    public static function expired(): self
    {
        return new self('Reservation confirmation has expired.', self::EXPIRED);
    }

    public static function busy(): self
    {
        return new self('The same reservation is still being processed.', self::BUSY);
    }

    public static function failed(): self
    {
        return new self('POS registration failed.', self::FAILED);
    }

    public function isClosed(): bool
    {
        return $this->getCode() === self::CLOSED;
    }

    public function isExpired(): bool
    {
        return $this->getCode() === self::EXPIRED;
    }

    public function isBusy(): bool
    {
        return $this->getCode() === self::BUSY;
    }
}
