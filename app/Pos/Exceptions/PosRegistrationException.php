<?php

namespace App\Pos\Exceptions;

use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

/**
 * POS への予約登録の失敗。
 * メッセージには SQL やバインド値（個人情報）を含めない。
 */
final class PosRegistrationException extends RuntimeException
{
    public static function fromDatabaseError(Throwable $e): self
    {
        $sqlState = $e instanceof QueryException ? ($e->errorInfo[0] ?? $e->getCode()) : $e->getCode();
        $driverCode = $e instanceof QueryException ? ($e->errorInfo[1] ?? null) : null;

        return new self(sprintf(
            'POS registration failed (%s, SQLSTATE %s, driver code %s)',
            $e::class,
            $sqlState,
            $driverCode ?? '-',
        ));
    }
}
