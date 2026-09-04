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
    locations = [],
    unit_price = 0,
    building: initialBuilding = {},
}) {
    const isMybuilding = Boolean(is_mybuilding);
    const initialFloors = Math.max(1, Number(initialBuilding.total_floors) || 1);
    const initialPerFloor = Math.max(1, Number(initialBuilding.flats_per_floor) || 4);

    const [building, setBuilding] = useState({
        building_name: initialBuilding.building_name || '',
        building_number: initialBuilding.building_number || '',
        building_address: initialBuilding.building_address || '',
        total_floors: initialFloors,
        flats_per_floor: initialPerFloor,
        district_id: initialBuilding.district_id || '',
        city_id: initialBuilding.city_id || '',
        area_id: initialBuilding.area_id || '',
    });

    const [floorPlan, setFloorPlan] = useState(() => {
        if (Array.isArray(initialBuilding.floor_plan) && initialBuilding.floor_plan.length > 0) {
            return initialBuilding.floor_plan.map((v) => Math.max(0, Number(v) || 0));
        }
        return Array.from({ length: initialFloors }, () => initialPerFloor);
    });

    const [quickFlats, setQuickFlats] = useState(initialPerFloor);

    const handleTotalFloorsChange = (newFloorsCount) => {
        const n = Math.max(1, Math.min(200, Number(newFloorsCount) || 1));
        setBuilding((prev) => ({ ...prev, total_floors: n }));
        setFloorPlan((prev) => {
            const next = [...prev];
            if (n > next.length) {
                const fillVal = Math.max(0, Number(quickFlats) || 4);
                while (next.length < n) {
                    next.push(fillVal);
                }
            } else if (n < next.length) {
                next.length = n;
            }
            return next;
        });
    };

    const updateFloorCount = (index, value) => {
        const val = Math.max(0, Math.min(26, Number(value) || 0));
        setFloorPlan((prev) => {
            const next = [...prev];
            next[index] = val;
            return next;
        });
    };

    const applyQuickFlatsToAll = () => {
        const val = Math.max(0, Math.min(26, Number(quickFlats) || 0));
        setFloorPlan(Array.from({ length: building.total_floors }, () => val));
        setBuilding((prev) => ({ ...prev, flats_per_floor: val }));
    };

    const districts = useMemo(() => (Array.isArray(locations) ? locations : []), [locations]);
    const cities = useMemo(
        () => districts.find((d) => String(d.id) === String(building.district_id))?.cities || [],
        [districts, building.district_id]
    );
    const areas = useMemo(
        () => cities.find((c) => String(c.id) === String(building.city_id))?.areas || [],
        [cities, building.city_id]
    );

    const totalFlats = useMemo(() => {
        if (!isMybuilding) {
            return 1;
        }
        const sum = floorPlan.reduce((acc, curr) => acc + (Number(curr) || 0), 0);
        return Math.max(1, sum);
    }, [isMybuilding, floorPlan]);

    const flatRate = Number(plan.price ?? unit_price ?? 0);
    const dynamicSubtotal = useMemo(() => {
        if (!isMybuilding) {
            return Number(subtotal || 0);
        }
        const base = totalFlats * flatRate;
        if (showProration && cycleDays && periodDays && cycleDays > 0) {
            return Number((base * (periodDays / cycleDays)).toFixed(2));
        }
        return Number(base.toFixed(2));
    }, [isMybuilding, subtotal, totalFlats, flatRate, showProration, cycleDays, periodDays]);

    const { csrf_token: csrfToken } = usePage().props;

    return (
        <>
            <Head title="Review & Checkout" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Review & Checkout</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {isMybuilding
                            ? 'Enter your building details, location, and floor-wise flat counts to configure your plan.'
                            : 'Confirm your plan details before placing the order.'}
                    </p>
                </div>
                <a href={routes.index} data-native="true" className="text-sm font-medium text-slate-500 hover:text-teal-600 transition">
                    &larr; Back to products
                </a>
            </div>

            <form method="POST" action={routes.store} data-native="true">
                <input type="hidden" name="_token" value={csrfToken} />
                <input type="hidden" name="plan_id" value={plan.id} />

                <div className="grid gap-6 lg:grid-cols-3 items-start">
                    {/* Main Content Area */}
                    <div className="space-y-6 lg:col-span-2">
                        {isMybuilding ? (
                            <>
                                {/* Section 1: Building Identity & Address */}
                                <div className="card p-6 border border-slate-200 bg-white shadow-xs rounded-2xl">
                                    <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600 font-bold text-sm">
                                            1
                                        </div>
                                        <div>
                                            <h2 className="text-base font-semibold text-slate-900">Building Details & Address</h2>
                                            <p className="text-xs text-slate-500">Basic identification of the building.</p>
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                                Building Name <span className="text-rose-500">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                name="building_name"
                                                required
                                                placeholder="e.g. Green Valley Tower, Rose Dale"
                                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                value={building.building_name}
                                                onChange={(e) => setBuilding({ ...building, building_name: e.target.value })}
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                                Building / Holding Number
                                            </label>
                                            <input
                                                type="text"
                                                name="building_number"
                                                placeholder="e.g. House #14, Plot #5B"
                                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                value={building.building_number}
                                                onChange={(e) => setBuilding({ ...building, building_number: e.target.value })}
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                                Address / Road
                                            </label>
                                            <input
                                                type="text"
                                                name="building_address"
                                                placeholder="e.g. Road #7, Sector 3"
                                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                value={building.building_address}
                                                onChange={(e) => setBuilding({ ...building, building_address: e.target.value })}
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Section 2: District, City, Area */}
                                <div className="card p-6 border border-slate-200 bg-white shadow-xs rounded-2xl">
                                    <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600 font-bold text-sm">
                                            2
                                        </div>
                                        <div>
                                            <h2 className="text-base font-semibold text-slate-900">Location Details</h2>
                                            <p className="text-xs text-slate-500">Select the district, city, and area for the building.</p>
                                        </div>
                                    </div>

                                    {districts.length > 0 ? (
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                                    District <span className="text-rose-500">*</span>
                                                </label>
                                                <select
                                                    name="district_id"
                                                    required
                                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 bg-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                    value={building.district_id}
                                                    onChange={(e) => setBuilding({ ...building, district_id: e.target.value, city_id: '', area_id: '' })}
                                                >
                                                    <option value="">Select District</option>
                                                    {districts.map((d) => (
                                                        <option key={d.id} value={d.id}>{d.name}</option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                                    City <span className="text-rose-500">*</span>
                                                </label>
                                                <select
                                                    name="city_id"
                                                    required
                                                    disabled={cities.length === 0}
                                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 bg-white disabled:bg-slate-100 disabled:text-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                    value={building.city_id}
                                                    onChange={(e) => setBuilding({ ...building, city_id: e.target.value, area_id: '' })}
                                                >
                                                    <option value="">{cities.length === 0 ? 'Select district first' : 'Select City'}</option>
                                                    {cities.map((c) => (
                                                        <option key={c.id} value={c.id}>{c.name}</option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                                    Area <span className="text-rose-500">*</span>
                                                </label>
                                                <select
                                                    name="area_id"
                                                    required
                                                    disabled={areas.length === 0}
                                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 bg-white disabled:bg-slate-100 disabled:text-slate-400 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                    value={building.area_id}
                                                    onChange={(e) => setBuilding({ ...building, area_id: e.target.value })}
                                                >
                                                    <option value="">{areas.length === 0 ? 'Select city first' : 'Select Area'}</option>
                                                    {areas.map((a) => (
                                                        <option key={a.id} value={a.id}>{a.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-amber-700 bg-amber-50 rounded-xl p-3 border border-amber-200">
                                            Location records will be synchronized from the central system during initial setup.
                                        </p>
                                    )}
                                </div>

                                {/* Section 3: Building Floors & Floor-wise Flats */}
                                <div className="card p-6 border border-slate-200 bg-white shadow-xs rounded-2xl">
                                    <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600 font-bold text-sm">
                                            3
                                        </div>
                                        <div>
                                            <h2 className="text-base font-semibold text-slate-900">Building Floors & Flat Breakdown</h2>
                                            <p className="text-xs text-slate-500">Configure the total floors and specify how many flats each floor has.</p>
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3 items-end mb-5">
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">
                                                Total Floors <span className="text-rose-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                name="total_floors"
                                                min="1"
                                                max="200"
                                                required
                                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                value={building.total_floors}
                                                onChange={(e) => handleTotalFloorsChange(e.target.value)}
                                            />
                                        </div>

                                        <div className="sm:col-span-2 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-3 border border-slate-200">
                                            <div className="text-xs text-slate-600">
                                                <span className="font-semibold text-slate-800">Quick set flats:</span>
                                                <span className="ml-1 text-slate-500">Apply standard count to all floors</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="26"
                                                    className="w-16 rounded-lg border border-slate-300 px-2 py-1.5 text-center text-sm font-semibold bg-white"
                                                    value={quickFlats}
                                                    onChange={(e) => setQuickFlats(e.target.value)}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={applyQuickFlatsToAll}
                                                    className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 transition"
                                                >
                                                    Apply to all floors
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t border-slate-100 pt-4">
                                        <div className="flex items-center justify-between mb-3">
                                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Floor-wise Flat Allocation</span>
                                            <span className="rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-bold text-teal-800">
                                                Total: {totalFlats} Flats
                                            </span>
                                        </div>

                                        <div className="grid gap-2.5 sm:grid-cols-2 md:grid-cols-3 max-h-64 overflow-y-auto p-1 border border-slate-100 rounded-xl bg-slate-50/50">
                                            {floorPlan.map((count, index) => {
                                                const floorNum = index + 1;
                                                const label = floorNum === 1 ? '1st Floor' : floorNum === 2 ? '2nd Floor' : floorNum === 3 ? '3rd Floor' : `${floorNum}th Floor`;
                                                return (
                                                    <div key={index} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-xs">
                                                        <span className="text-xs font-semibold text-slate-700">{label}</span>
                                                        <div className="flex items-center gap-1.5">
                                                            <input
                                                                type="number"
                                                                name={`floor_plan[${index}]`}
                                                                min="0"
                                                                max="26"
                                                                required
                                                                className="w-14 rounded-lg border border-slate-300 px-2 py-1 text-center text-xs font-bold text-slate-900 focus:border-teal-500 focus:outline-none"
                                                                value={count}
                                                                onChange={(e) => updateFloorCount(index, e.target.value)}
                                                            />
                                                            <span className="text-xs text-slate-400 font-medium">flats</span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <input type="hidden" name="flats_per_floor" value={Math.round(totalFlats / building.total_floors) || 1} />
                                </div>
                            </>
                        ) : (
                            /* Standard Product Plan Details */
                            <div className="card p-6 border border-slate-200 bg-white rounded-2xl shadow-xs">
                                <h2 className="text-base font-semibold text-slate-900 mb-4 pb-2 border-b border-slate-100">Plan Details</h2>
                                <div className="space-y-3 text-sm text-slate-600">
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
                                        <span className="font-semibold text-slate-900">{start_date_display} &rarr; {period_end_display}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span>Invoice due</span>
                                        <span className="font-semibold text-slate-900">{dueDays} day(s) after issue</span>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Right Column: Sticky Summary & Checkout */}
                    <div className="card p-6 border border-slate-200 bg-white rounded-2xl shadow-xs lg:sticky lg:top-6">
                        <h2 className="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-4">
                            Order Summary
                        </h2>

                        <div className="space-y-3 text-sm text-slate-600">
                            <div className="flex items-center justify-between">
                                <span>Product</span>
                                <span className="font-semibold text-slate-900">{plan.product_name}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span>Plan</span>
                                <span className="font-semibold text-slate-900">{plan.name}</span>
                            </div>

                            {isMybuilding && (
                                <>
                                    <div className="flex items-center justify-between">
                                        <span>Rate per Flat</span>
                                        <span className="font-semibold text-teal-700">
                                            {currency} {flatRate.toFixed(2)} / flat
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span>Building Scope</span>
                                        <span className="font-semibold text-slate-900">
                                            {building.total_floors} Floors, {totalFlats} Flats
                                        </span>
                                    </div>
                                    <div className="rounded-xl bg-teal-50/60 p-3 border border-teal-100 text-xs text-teal-900 space-y-1">
                                        <div className="flex items-center justify-between">
                                            <span>Monthly Base Rate:</span>
                                            <span className="font-semibold">{totalFlats} × {currency} {flatRate.toFixed(2)}</span>
                                        </div>
                                        <div className="flex items-center justify-between font-bold text-teal-800">
                                            <span>Monthly Recurring Bill:</span>
                                            <span>{currency} {(totalFlats * flatRate).toFixed(2)}/mo</span>
                                        </div>
                                    </div>
                                </>
                            )}

                            <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                                <span>Billing Period</span>
                                <span className="text-xs font-semibold text-slate-700">{start_date_display} &rarr; {period_end_display}</span>
                            </div>

                            {showProration && cycleDays ? (
                                <div className="text-xs text-slate-500 bg-slate-50 p-2 rounded-lg">
                                    Prorated for {periodDays}/{cycleDays} days remaining this month
                                </div>
                            ) : null}

                            <div className="border-t border-slate-200 pt-3 mt-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-base font-medium text-slate-900">Total Due Today</span>
                                    <span className="text-2xl font-bold text-teal-700">
                                        {currency} {dynamicSubtotal.toFixed(2)}
                                    </span>
                                </div>
                                <p className="mt-1 text-[11px] text-slate-400">
                                    Includes calculated recurring flat rates for this billing cycle.
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 space-y-3">
                            <button
                                type="submit"
                                className="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white shadow-md hover:bg-teal-700 active:scale-[0.99] transition flex items-center justify-center gap-2"
                            >
                                <span>Confirm Order &amp; Proceed to Payment</span>
                                &rarr;
                            </button>

                            <p className="text-center text-[11px] text-slate-400">
                                Payment gateways (bKash, SSLCommerz, Cards) available on the next step.
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </>
    );
}
