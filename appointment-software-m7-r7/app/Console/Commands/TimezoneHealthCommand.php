<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TimezoneHealthCommand extends Command
{
    protected $signature = 'app:timezone-health';
    protected $description = 'Verify that MariaDB timezone tables are loaded and CONVERT_TZ can use IANA zones.';

    public function handle(): int
    {
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $this->warn('Current connection is not MariaDB/MySQL.');
                return self::FAILURE;
            }

            $row = DB::selectOne("SELECT CONVERT_TZ('2026-01-15 12:00:00', 'UTC', 'America/Toronto') AS converted");

            if (empty($row?->converted)) {
                $this->error('CONVERT_TZ returned NULL. MariaDB timezone tables are probably not loaded.');
                $this->line('Run: mariadb-tzinfo-to-sql /usr/share/zoneinfo | mariadb -u root mysql');
                return self::FAILURE;
            }

            $this->info('MariaDB timezone support is healthy: '.$row->converted);
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
