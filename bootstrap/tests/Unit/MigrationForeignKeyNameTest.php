<?php

namespace Tests\Unit;

use Tests\TestCase;

class MigrationForeignKeyNameTest extends TestCase
{
    public function test_explicit_foreign_key_constraint_names_are_unique_across_schema(): void
    {
        $seen = [];
        $duplicates = [];

        foreach (glob(database_path('migrations/*.php')) ?: [] as $migration) {
            $contents = file_get_contents($migration);

            if ($contents === false) {
                continue;
            }

            preg_match_all(
                "/->foreign\\([^\\n,]+,\\s*'([^']+)'\\)/",
                $contents,
                $matches
            );

            foreach ($matches[1] ?? [] as $constraintName) {
                if (isset($seen[$constraintName])) {
                    $duplicates[$constraintName] = [
                        $seen[$constraintName],
                        basename($migration),
                    ];
                    continue;
                }

                $seen[$constraintName] = basename($migration);
            }
        }

        $this->assertSame(
            [],
            $duplicates,
            'MariaDB/InnoDB foreign-key constraint names must be unique within a schema: '.json_encode($duplicates)
        );
    }
}
