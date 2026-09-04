import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Review({
    plan = {},
    currency = 'USD',
    start_date_display = '--',
    period_end_display = '--',
    subtotal = 0,
    periodDays = null,
    cycleDays = null,
    showProration = false,
    dueDays = 0,
    routes = {},
    is_mybuilding = false,
    building: initialBuilding = {},
}) {
    const isMybuilding = Boolean(is_mybuilding);
    const [building, setBuilding] = useState({
        building_name: initialBuilding.building_name || '',
        building_address: initialBuilding.building_address || '',
        total_floors: initialBuilding.total_floors || 1,
        flats_per_floor: initialBuilding.flats_per_floor || 4,
    });
    const totalFlats = useMemo(
        () => Math.max(0, Number(building.total_floors || 0)) * Math.max(0, Number(building.flats_per_floor || 0)),
        [building.total_floors, building.flats_per_floor]
    );

    const { csrf_token: csrfToken } = usePage().props;

    return (
        <>
            <Head title="Review & Checkout" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Review & Checkout</h1>
                    <p className="mt-1 text-sm text-slate-500">Confirm your plan details before placing the order.</p>
                </div>
                <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to products
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="card p-6 lg:col-span-2">
                    <div className="section-label">Plan details</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        <div className="flex items-center justify-between">
                            <span>Product</span>
                            <span className="font-semibold text-slate-900">{plan.product_name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Plan</span>
                            <span className="font-semibold text-slate-900">{plan.name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Interval</span>
                            <span className="font-semibold text-slate-900">{plan.interval_label}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Billing period</span>
                            <span className="font-semibold text-slate-900">
                                {start_date_display} -&gt; {period_end_display}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Invoice due</span>
                            <span className="font-semibold text-slate-900">{dueDays} day(s) after issue</span>
                        </div>
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">Summary</div>
                    <div className="mt-4 text-sm text-slate-600">
                        <div className="flex items-center justify-between">
                            <span>Subtotal</span>
                            <span className="font-semibold text-slate-900">
                                {currency} {Number(subtotal).toFixed(2)}
                            </span>
                        </div>
                        {showProration && cycleDays ? (
                            <div className="mt-1 text-xs text-slate-500">
                                Prorated for {periodDays}/{cycleDays} days
                            </div>
                        ) : null}
                        <div className="mt-2 flex items-center justify-between">
                            <span>Total</span>
                            <span className="text-lg font-semibold text-slate-900">
                                {currency} {Number(subtotal).toFixed(2)}
                            </span>
                        </div>
                    </div>

                    {isMybuilding && (
                        <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 className="text-sm font-semibold text-slate-900">Your building</h3>
                            <p className="mb-3 text-xs text-slate-500">
                                We create every flat for you, so tell us the size of the building.
                            </p>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="text-sm">
                                    <span className="mb-1 block font-medium text-slate-700">Building name</span>
                                    <input
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        value={building.building_name}
                                        onChange={(e) => setBuilding({ ...building, building_name: e.target.value })}
                                        required
                                    />
                                </label>
                                <label className="text-sm">
                                    <span className="mb-1 block font-medium text-slate-700">Address</span>
                                    <input
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        value={building.building_address}
                                        onChange={(e) => setBuilding({ ...building, building_address: e.target.value })}
                                    />
                                </label>
                                <label className="text-sm">
                                    <span className="mb-1 block font-medium text-slate-700">Total floors</span>
                                    <input
                                        type="number" min="1" max="200"
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        value={building.total_floors}
                                        onChange={(e) => setBuilding({ ...building, total_floors: e.target.value })}
                                        required
                                    />
                                </label>
                                <label className="text-sm">
                                    <span className="mb-1 block font-medium text-slate-700">Flats per floor</span>
                                    <input
                                        type="number" min="1" max="26"
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        value={building.flats_per_floor}
                                        onChange={(e) => setBuilding({ ...building, flats_per_floor: e.target.value })}
                                        required
                                    />
                                </label>
                            </div>

                            <p className="mt-3 rounded-lg bg-white px-3 py-2 text-sm text-slate-700">
                                This order covers <strong>{totalFlats}</strong> flats.
                            </p>
                        </div>
                    )}

                    <form method="POST" action={routes.store} data-native="true" className="mt-6">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="plan_id" value={plan.id} />
                        {isMybuilding && (
                            <>
                                <input type="hidden" name="building_name" value={building.building_name} />
                                <input type="hidden" name="building_address" value={building.building_address} />
                                <input type="hidden" name="total_floors" value={building.total_floors} />
                                <input type="hidden" name="flats_per_floor" value={building.flats_per_floor} />
                            </>
                        )}
                        <button type="submit" className="w-full rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">
                            Place order
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
