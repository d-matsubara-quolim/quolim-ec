<?php

namespace Tests\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * テストごとに POS スキーマを作り直す。テスト専用 DB 以外では実行しない。
 */
trait UsesPosTestDatabase
{
    protected function setUpUsesPosTestDatabase(): void
    {
        $database = config('database.connections.pos.database');
        if ($database !== 'pos_test') {
            throw new RuntimeException("Refusing to reset POS schema on database [{$database}].");
        }

        DB::connection('pos')->unprepared(file_get_contents(base_path('tests/Fixtures/pos_schema.sql')));
    }

    protected function posTable(string $table): Builder
    {
        return DB::connection('pos')->table($table);
    }
}
