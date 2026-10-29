<?php

namespace App\Pos;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * 伝票番号（SLIP_NO）の採番。旧 POS UpdateSlip.php の SetAutoForInsert を移植。
 * 形式：伝票日付 YYMMDD + ブランドID 2桁 + ブランド枝番 + 連番 3桁（000 始まり）
 */
final class SlipNumberGenerator
{
    public function next(ConnectionInterface $db, CarbonImmutable $slipDate, int $brandId, int $brandBranchNo): string
    {
        $prefix = $slipDate->format('ymd')
            .str_pad((string) $brandId, 2, '0', STR_PAD_LEFT)
            .$brandBranchNo;

        $max = $db->table('P_SLIP_TRN')
            ->whereRaw('LEFT(SLIP_NO, 9) = ?', [$prefix])
            ->max('SLIP_NO');

        return $max === null ? $prefix.'000' : (string) ((int) $max + 1);
    }
}
