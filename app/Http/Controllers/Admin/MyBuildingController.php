<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\License;
use App\Models\MyBuildingProvision;
use App\Models\Product;
use App\Services\MyBuildingProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * MyBuilding licences and the building each one provisions.
 */
class MyBuildingController extends Controller
{
    public function __construct(private readonly MyBuildingProvisioner $provisioner)
    {
    }

    public function index(Request $request): InertiaResponse
    {
        $product = Product::query()
            ->where('slug', config('mybuilding.product_slug'))
            ->first();

        $licenses = License::query()
            ->with(['subscription.customer', 'subscription.plan', 'domains'])
            ->when($product, fn ($q) => $q->where('product_id', $product->id))
            ->when(!$product, fn ($q) => $q->whereRaw('1 = 0'))
            ->latest('id')
            ->get();

        $provisions = MyBuildingProvision::query()
            ->whereIn('license_id', $licenses->pluck('id'))
            ->get()
            ->keyBy('license_id');

        $rows = $licenses->map(function (License $license) use ($provisions) {
            $p = $provisions->get($license->id);
            $customer = $license->subscription?->customer;

            return [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'license_status' => $license->status,
                'expires_at' => $license->expires_at?->toDateString(),
                'customer' => $customer?->name,
                'customer_id' => $customer?->id,
                'plan' => $license->subscription?->plan?->name,
                'domains' => $license->domains->pluck('domain')->all(),

                'provision' => $p ? [
                    'id' => $p->id,
                    'building_name' => $p->building_name,
                    'total_floors' => $p->total_floors,
                    'flats_per_floor' => $p->flats_per_floor,
                    'floor_plan' => $p->floor_plan,
                    'contracted_flats' => $p->contracted_flats,
                    'install_url' => $p->install_url,
                    'owner_name' => $p->owner_name,
                    'owner_email' => $p->owner_email,
                    'owner_phone' => $p->owner_phone,
                    'status' => $p->status,
                    'attempts' => $p->attempts,
                    'last_error' => $p->last_error,
                    'provisioned_at' => $p->provisioned_at?->toDateTimeString(),
                    'remote_building_id' => $p->remote_building_id,
                    'registration_code' => $p->registration_code,
                ] : null,
            ];
        })->values();

        return Inertia::render('Admin/MyBuilding/Index', [
            'product' => $product ? ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug] : null,
            'rows' => $rows,
            'summary' => [
                'licenses' => $rows->count(),
                'provisioned' => $rows->filter(fn ($r) => ($r['provision']['status'] ?? null) === 'provisioned')->count(),
                'pending' => $rows->filter(fn ($r) => in_array($r['provision']['status'] ?? 'none', ['pending', 'none'], true))->count(),
                'failed' => $rows->filter(fn ($r) => ($r['provision']['status'] ?? null) === 'failed')->count(),
                'flats' => $rows->sum(fn ($r) => (int) ($r['provision']['contracted_flats'] ?? 0)),
            ],
            'config' => [
                'secret_configured' => $this->provisioner->configured(),
                'default_install_url' => config('mybuilding.default_install_url'),
                'product_slug' => config('mybuilding.product_slug'),
            ],
        ]);
    }

    /**
     * Record (or update) what a licence's building should look like.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'license_id' => ['required', 'exists:licenses,id'],
            'building_name' => ['required', 'string', 'max:255'],
            'building_address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['required', 'integer', 'min:1', 'max:200'],
            'flats_per_floor' => ['required', 'integer', 'min:1', 'max:26'],
            'floor_plan' => ['nullable', 'array', 'max:200'],
            'floor_plan.*' => ['integer', 'min:0', 'max:26'],
            'install_url' => ['required', 'url', 'max:255'],
            'district_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_phone' => ['required', 'string', 'max:32'],
        ]);

        $license = License::with('subscription.customer')->findOrFail($data['license_id']);

        $provision = MyBuildingProvision::firstOrNew(['license_id' => $license->id]);

        if ($provision->exists && $provision->isProvisioned()) {
            return back()->withErrors([
                'license_id' => 'This building has already been created in the installation and cannot be re-ordered here.',
            ]);
        }

        $floorPlan = array_values(array_filter($data['floor_plan'] ?? [], fn ($v) => $v !== null));

        $provision->fill([
            'customer_id' => $license->subscription?->customer?->id,
            'building_name' => $data['building_name'],
            'building_address' => $data['building_address'] ?? null,
            'total_floors' => $data['total_floors'],
            'flats_per_floor' => $data['flats_per_floor'],
            'floor_plan' => $floorPlan ?: null,
            'install_url' => $data['install_url'],
            'district_id' => $data['district_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'owner_phone' => $data['owner_phone'],
            'status' => MyBuildingProvision::STATUS_PENDING,
        ]);

        $provision->contracted_flats = $provision->calculatedFlats();
        $provision->save();

        return back()->with('status', 'Building details saved. You can now provision it.');
    }

    /**
     * Location options read live from a customer's installation, so the
     * district/city/area ids are the ones that exist over there.
     */
    public function locations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'install_url' => ['required', 'url', 'max:255'],
        ]);

        return response()->json($this->provisioner->locations($data['install_url']));
    }

    /**
     * Send it to the customer's installation (also used to retry a failure).
     */
    public function provision(MyBuildingProvision $provision): RedirectResponse
    {
        $provision->loadMissing(['license', 'customer']);

        if ($this->provisioner->provision($provision)) {
            return back()->with('status', 'Building created in the installation.');
        }

        return back()->withErrors([
            'provision' => $provision->fresh()->last_error ?? 'Provisioning failed.',
        ]);
    }
}
