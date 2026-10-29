<?php

namespace App\Pos;

use Illuminate\Database\ConnectionInterface;

/**
 * 更新履歴（P_UPDATED_TRN）の登録。旧 POS DB.php の CreateInsertSQL / BuildParametersHistory を移植。
 * 新規登録の履歴は COLUMN_NAME・BEFORE_VALUE・AFTER_VALUE が空文字になる。
 */
final class UpdateHistoryRecorder
{
    public function recordInsert(ConnectionInterface $db, int $slipId, string $table, int $keyValue, int $operator): void
    {
        $db->table('P_UPDATED_TRN')->insert([
            'SLIP_ID' => $slipId,
            'TABLE_NAME' => $table,
            'COLUMN_NAME' => '',
            'KEY_VALUE' => $keyValue,
            'BEFORE_VALUE' => '',
            'AFTER_VALUE' => '',
            'OPERATOR' => $operator,
            'INSERTED' => $db->raw('NOW()'),
            'UPDATED' => $db->raw('NOW()'),
            'INSERTED_BY_ID' => $operator,
            'UPDATED_BY_ID' => $operator,
        ]);
    }
}
