<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Support\BangladeshLocations;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads the bundled district/upazila list. Safe to re-run: rows are matched on
 * slug, so ids stay stable for provisions that already reference them.
 */
class BangladeshLocationSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/bd-locations.json');

        if (! is_file($path)) {
            throw new RuntimeException("Location dataset missing: {$path}");
        }

        $districts = json_decode((string) file_get_contents($path), true);

        if (! is_array($districts)) {
            throw new RuntimeException("Location dataset is not valid JSON: {$path}");
        }

        foreach ($districts as $row) {
            $district = District::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'bn_name' => $row['bn_name'] ?? null,
                    'division' => $row['division'] ?? null,
                ]
            );

            foreach ($row['cities'] ?? [] as $city) {
                City::updateOrCreate(
                    ['district_id' => $district->id, 'slug' => $city['slug']],
                    [
                        'name' => $city['name'],
                        'bn_name' => $city['bn_name'] ?? null,
                    ]
                );
            }
        }

        BangladeshLocations::forget();

        $this->command?->info(sprintf(
            'Seeded %d districts and %d cities.',
            District::count(),
            City::count()
        ));
    }
}
