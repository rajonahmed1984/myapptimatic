<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use App\Models\District;
use App\Support\BangladeshLocations;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads the bundled district/upazila/area list. Safe to re-run: rows are matched on
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

        // Clean misplaced cities under Dhaka that are now areas
        City::whereIn('slug', ['gulshan', 'banani', 'dhanmondi', 'uttara'])->delete();

        foreach ($districts as $row) {
            $district = District::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'bn_name' => $row['bn_name'] ?? null,
                    'division' => $row['division'] ?? null,
                ]
            );

            foreach ($row['cities'] ?? [] as $cityRow) {
                $city = City::updateOrCreate(
                    ['district_id' => $district->id, 'slug' => $cityRow['slug']],
                    [
                        'name' => $cityRow['name'],
                        'bn_name' => $cityRow['bn_name'] ?? null,
                    ]
                );

                foreach ($cityRow['areas'] ?? [] as $areaRow) {
                    Area::updateOrCreate(
                        ['city_id' => $city->id, 'slug' => $areaRow['slug']],
                        [
                            'district_id' => $district->id,
                            'name' => $areaRow['name'],
                            'bn_name' => $areaRow['bn_name'] ?? null,
                        ]
                    );
                }
            }
        }

        BangladeshLocations::forget();

        $this->command?->info(sprintf(
            'Seeded %d districts, %d cities, and %d areas.',
            District::count(),
            City::count(),
            Area::count()
        ));
    }
}
