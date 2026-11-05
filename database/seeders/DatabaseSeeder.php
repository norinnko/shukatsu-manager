<?php

namespace Database\Seeders;

use App\Support\ShukatsuImporter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database from the personal data file, or from
     * the bundled example when that file does not exist.
     */
    public function run(ShukatsuImporter $importer): void
    {
        $path = config('shukatsu.seed_file');

        if (! is_file($path)) {
            $path = database_path('seed-data/shukatsu.example.json');
            $this->command?->warn("Personal seed file not found, using {$path}");
        }

        $counts = $importer->importFile($path);

        $this->command?->info('Imported: '.json_encode($counts));
    }
}
