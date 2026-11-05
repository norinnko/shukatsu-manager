<?php

namespace App\Console\Commands;

use App\Support\ShukatsuImporter;
use Illuminate\Console\Command;

class ImportShukatsuData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'shukatsu:import {path? : JSON file (defaults to config shukatsu.seed_file)}';

    /**
     * @var string
     */
    protected $description = 'Import profile, companies, events and ES answers from a JSON file';

    public function handle(ShukatsuImporter $importer): int
    {
        $path = $this->argument('path') ?? config('shukatsu.seed_file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $counts = $importer->importFile($path);

        $this->info('Import finished (existing records were left untouched).');
        $this->table(['Type', 'Created'], collect($counts)->map(fn ($n, $type) => [$type, $n])->values()->all());

        return self::SUCCESS;
    }
}
