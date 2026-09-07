<?php

namespace App\Support;

use App\Models\District;
use Illuminate\Support\Facades\Cache;

/**
 * The district/city list handed to the ordering screens. Read from this app's
 * own tables so the screen works whether or not a MyBuilding installation is
 * reachable; the slugs let that installation resolve its own ids later.
 */
class BangladeshLocations
{
    private const CACHE_KEY = 'bd_locations_tree_v2';

    /**
     * @return array<int, array{id:int, slug:string, name:string, cities:array<int, array{id:int, slug:string, name:string, areas:array<int, array{id:int, slug:string, name:string}>}>}>
     */
    public static function tree(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        // If the database has no districts yet, attempt to auto-seed them
        if (District::query()->count() === 0) {
            try {
                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => \Database\Seeders\BangladeshLocationSeeder::class,
                    '--force' => true,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('BangladeshLocations auto-seed error: ' . $e->getMessage());
            }
        }

        try {
            $districts = District::query()
                ->with([
                    'cities' => fn ($query) => $query->orderBy('name')->with(['areas' => fn ($q) => $q->orderBy('name')]),
                ])
                ->orderByRaw("CASE WHEN slug = 'dhaka' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get();
        } catch (\Throwable) {
            // Fallback if areas table has not been migrated yet
            $districts = District::query()
                ->with([
                    'cities' => fn ($query) => $query->orderBy('name'),
                ])
                ->orderByRaw("CASE WHEN slug = 'dhaka' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get();
        }

        $tree = $districts
            ->map(fn (District $district) => [
                'id' => $district->id,
                'slug' => $district->slug,
                'name' => $district->name,
                'cities' => $district->cities
                    ->map(fn ($city) => [
                        'id' => $city->id,
                        'slug' => $city->slug,
                        'name' => $city->name,
                        'areas' => isset($city->areas)
                            ? $city->areas
                                ->map(fn ($area) => [
                                    'id' => $area->id,
                                    'slug' => $area->slug,
                                    'name' => $area->name,
                                ])
                                ->values()
                                ->all()
                            : [],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        if (! empty($tree)) {
            Cache::forever(self::CACHE_KEY, $tree);
        }

        return $tree;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('bd_locations_tree');
    }
}
