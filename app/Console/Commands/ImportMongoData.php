<?php

namespace App\Console\Commands;

use App\Services\MongoImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportMongoData extends Command
{
    protected $signature = 'mongo:import {path : Folder containing the exported collection .json files}';

    protected $description = 'Import data exported from the original MongoDB database (ids are preserved)';

    public function handle(MongoImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_dir($path)) {
            $this->error("Folder not found: {$path}");

            return self::FAILURE;
        }

        try {
            $imported = $importer->importDirectory($path, function (string $collection, int $count) {
                $this->line(sprintf('  %-16s %d document(s)', $collection, $count));
            });
        } catch (Throwable $exception) {
            $this->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($imported === []) {
            $this->warn('No collection files found. Expected files such as products.json, users.json, orders.json...');

            return self::FAILURE;
        }

        $this->info('Import complete.');

        return self::SUCCESS;
    }
}
