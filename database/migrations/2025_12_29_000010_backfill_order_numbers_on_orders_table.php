<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sequenceByPrefix = [];

        DB::table('orders')
            ->whereNull('order_number')
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunk(100, function ($orders) use (&$sequenceByPrefix) {
                foreach ($orders as $order) {
                    $createdAt = $order->created_at ? Carbon::parse($order->created_at) : Carbon::now();
                    $prefix = $createdAt->format('Ym');

                    if (! array_key_exists($prefix, $sequenceByPrefix)) {
                        $existingMax = DB::table('orders')
                            ->where('order_number', 'like', $prefix.'%')
                            ->selectRaw('MAX(CAST(SUBSTRING(order_number, 7) AS UNSIGNED)) as seq')
                            ->value('seq');

                        $sequenceByPrefix[$prefix] = (int) $existingMax;
                    }

                    $sequenceByPrefix[$prefix]++;

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['order_number' => $prefix.$sequenceByPrefix[$prefix]]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: order_number values are generated based on existing data.
    }
};
