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
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return District::query()
                ->with([
                    'cities' => fn ($query) => $query->orderBy('name')->with(['areas' => fn ($q) => $q->orderBy('name')]),
                ])
                ->orderByRaw("CASE WHEN slug = 'dhaka' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->map(fn (District $district) => [
                    'id' => $district->id,
                    'slug' => $district->slug,
                    'name' => $district->name,
                    'cities' => $district->cities
                        ->map(fn ($city) => [
                            'id' => $city->id,
                            'slug' => $city->slug,
                            'name' => $city->name,
                            'areas' => $city->areas
                                ->map(fn ($area) => [
                                    'id' => $area->id,
                                    'slug' => $area->slug,
                                    'name' => $area->name,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all();
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('bd_locations_tree');
    }
}
