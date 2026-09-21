<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\MyBuildingProvision;
use App\Services\MyBuildingLicenseSync;
use App\Services\MyBuildingProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives building changes made inside a MyBuilding installation (building
 * details edited, floors deactivated or deleted, flats merged) and answers
 * with the licence state the installation should enforce.
 *
 * The building's contracted flat count is what a per-flat plan bills on, so
 * a change there updates the subscription amount here as well.
 */
class MyBuildingSyncController extends Controller
{
    public function __construct(
        private readonly MyBuildingLicenseSync $sync,
        private readonly MyBuildingProvisioner $provisioner,
    ) {}

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
            'building_id' => ['nullable', 'integer'],
            'building_name' => ['nullable', 'string', 'max:255'],
            'building_address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:0', 'max:500'],
            'contracted_flats' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'total_flats' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'active_flats' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'inactive_floors' => ['nullable', 'array', 'max:500'],
            'inactive_floors.*' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
        ]);

        $license = License::query()
            ->with(['subscription.customer', 'subscription.plan.product', 'product'])
            ->where('license_key', $data['license_key'])
            ->first();

        if (! $license) {
            return response()->json([
                'success' => false,
                'status' => 'blocked',
                'blocked' => true,
                'reason' => 'license_not_found',
                'subscription_status' => 'cancelled',
            ], 404);
        }

        $provision = MyBuildingProvision::query()->where('license_id', $license->id)->first();

        DB::transaction(function () use ($license, $provision, $data) {
            if (isset($data['active_flats'])) {
                $license->forceFill(['last_seats_reported' => (int) $data['active_flats']]);
            }
            $license->forceFill(['last_check_at' => Carbon::now()])->saveQuietly();

            if (! $provision) {
                return;
            }

            $updates = array_filter([
                'building_name' => $data['building_name'] ?? null,
                'building_address' => $data['building_address'] ?? null,
                'total_floors' => ! empty($data['total_floors']) ? (int) $data['total_floors'] : null,
                'contracted_flats' => ! empty($data['contracted_flats']) ? (int) $data['contracted_flats'] : null,
                'total_flats' => $data['total_flats'] ?? null,
                'active_flats' => $data['active_flats'] ?? null,
                'remote_building_id' => $provision->remote_building_id ?: ($data['building_id'] ?? null),
            ], fn ($value) => $value !== null);

            if (array_key_exists('inactive_floors', $data)) {
                $updates['inactive_floors'] = array_values(array_filter(
                    $data['inactive_floors'] ?? [],
                    fn ($floor) => $floor !== null && $floor !== ''
                ));
            }

            // The installation is calling about this key, so the building is
            // there even if the original hand-off was never confirmed here.
            if (! $provision->isProvisioned()) {
                $updates['status'] = MyBuildingProvision::STATUS_PROVISIONED;
                $updates['provisioned_at'] = $provision->provisioned_at ?? Carbon::now();
                $updates['last_error'] = null;
            }

            $updates['last_synced_at'] = Carbon::now();
            $updates['last_sync_action'] = $data['action'] ?? null;

            $contractedChanged = isset($updates['contracted_flats'])
                && (int) $updates['contracted_flats'] !== (int) $provision->contracted_flats;

            $provision->forceFill($updates)->save();

            if ($contractedChanged) {
                $this->provisioner->syncPerFlatSubscriptionAmount($license, (int) $provision->contracted_flats);
            }
        });

        Log::info('MyBuilding building synced.', [
            'license_id' => $license->id,
            'action' => $data['action'] ?? null,
            'contracted_flats' => $data['contracted_flats'] ?? null,
        ]);

        $license = $license->fresh(['subscription.customer']);
        $state = $this->sync->state($license);
        $blocked = $state['subscription_status'] !== 'active';

        return response()->json(array_merge($state, [
            'success' => true,
            'status' => $blocked ? 'blocked' : 'active',
            'blocked' => $blocked,
            'reason' => $blocked ? 'license_'.$state['subscription_status'] : null,
            'amount_due' => $state['due_amount'],
            'contracted_flats' => $provision?->fresh()?->contracted_flats,
            'subscription_amount' => $license->subscription?->subscription_amount !== null
                ? (float) $license->subscription->subscription_amount
                : null,
        ]));
    }
}
