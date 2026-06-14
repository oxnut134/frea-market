<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;

trait ResetsAutoIncrement
{
    /**
     * Sync the table's auto-increment sequence with its current max id.
     *
     * Needed because upsert() seeds explicit primary keys without
     * advancing PostgreSQL's serial sequence.
     */
    protected function resetAutoIncrement(string $table): void
    {
        DB::statement(
            "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))"
        );
    }
}
