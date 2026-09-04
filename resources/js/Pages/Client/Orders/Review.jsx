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
    const initialFloors = Math.max(1, Number(initialBuilding.total_floors) || 10);
    const initialPerFloor = Math.max(1, Number(initialBuilding.flats_per_floor) || 4);
    const initialHasGf = initialBuilding.has_ground_floor !== undefined ? Boolean(initialBuilding.has_ground_floor) : true;

    // Step state for MyBuilding wizard: 1 = Building Info, 2 = Floor-wise flats, 3 = Review & Confirm
    const [currentStep, setCurrentStep] = useState(1);
    const [validationError, setValidationError] = useState('');

    const [hasGroundFloor, setHasGroundFloor] = useState(initialHasGf);
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

    const getFloorLabel = (index) => {
        if (hasGroundFloor) {
            if (index === 0) return 'Ground Floor (GF)';
            const floorNum = index;
            if (floorNum === 1) return '1st Floor';
            if (floorNum === 2) return '2nd Floor';
            if (floorNum === 3) return '3rd Floor';
            return `${floorNum}th Floor`;
        } else {
            const floorNum = index + 1;
            if (floorNum === 1) return '1st Floor';
            if (floorNum === 2) return '2nd Floor';
            if (floorNum === 3) return '3rd Floor';
            return `${floorNum}th Floor`;
        }
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

    // Step Navigation Handlers
    const goToStep2 = () => {
        if (!building.building_name.trim()) {
            setValidationError('Please enter a building name to continue.');
            return;
        }
        if (!building.total_floors || Number(building.total_floors) < 1) {
            setValidationError('Please enter at least 1 floor.');
            return;
        }
        if (districts.length > 0 && (!building.district_id || !building.city_id || !building.area_id)) {
            setValidationError('Please select District, City, and Area for your building.');
            return;
        }
        setValidationError('');
        setCurrentStep(2);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const goToStep3 = () => {
        if (totalFlats < 1) {
            setValidationError('Please allocate at least 1 flat across the floors.');
            return;
        }
        setValidationError('');
        setCurrentStep(3);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const goToStep = (targetStep) => {
        if (targetStep < currentStep) {
            setValidationError('');
            setCurrentStep(targetStep);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    const selectedDistrictName = districts.find((d) => String(d.id) === String(building.district_id))?.name || '';
    const selectedCityName = cities.find((c) => String(c.id) === String(building.city_id))?.name || '';
    const selectedAreaName = areas.find((a) => String(a.id) === String(building.area_id))?.name || '';

    return (
        <>
            <Head title="Review & Checkout" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">
                        {isMybuilding ? 'Configure Your Building & Plan' : 'Review & Checkout'}
                    </h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {isMybuilding
                            ? 'Configure your building information, floors, and floor-wise flats.'
                            : 'Confirm your plan details before placing the order.'}
                    </p>
                </div>
                <a href={routes.index} data-native="true" className="text-sm font-medium text-slate-500 hover:text-teal-600 transition">
                    &larr; Back to products
                </a>
            </div>

            {/* Step Wizard Header (Only for MyBuilding) */}
            {isMybuilding && (
                <div className="mb-6 card p-4 border border-slate-200 bg-white shadow-xs rounded-2xl">
                    <div className="flex items-center justify-between max-w-2xl mx-auto">
                        {/* Step 1 */}
                        <div
                            onClick={() => goToStep(1)}
                            className={`flex items-center gap-2.5 cursor-pointer ${
                                currentStep === 1
                                    ? 'text-teal-600 font-bold'
                                    : currentStep > 1
                                    ? 'text-slate-800 font-semibold'
                                    : 'text-slate-400 font-medium'
                            }`}
                        >
                            <span
                                className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition ${
                                    currentStep === 1
                                        ? 'bg-teal-600 text-white shadow-xs'
                                        : currentStep > 1
                                        ? 'bg-teal-100 text-teal-800'
                                        : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                {currentStep > 1 ? '✓' : '1'}
                            </span>
                            <span className="text-xs sm:text-sm">Building &amp; Floors</span>
                        </div>

                        <div className={`h-0.5 flex-1 mx-2 sm:mx-4 transition ${currentStep >= 2 ? 'bg-teal-500' : 'bg-slate-200'}`} />

                        {/* Step 2 */}
                        <div
                            onClick={() => goToStep(2)}
                            className={`flex items-center gap-2.5 ${
                                currentStep >= 2 ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'
                            } ${
                                currentStep === 2
                                    ? 'text-teal-600 font-bold'
                                    : currentStep > 2
                                    ? 'text-slate-800 font-semibold'
                                    : 'text-slate-400 font-medium'
                            }`}
                        >
                            <span
                                className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition ${
                                    currentStep === 2
                                        ? 'bg-teal-600 text-white shadow-xs'
                                        : currentStep > 2
                                        ? 'bg-teal-100 text-teal-800'
                                        : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                {currentStep > 2 ? '✓' : '2'}
                            </span>
                            <span className="text-xs sm:text-sm">Floor-wise Flats</span>
                        </div>

                        <div className={`h-0.5 flex-1 mx-2 sm:mx-4 transition ${currentStep >= 3 ? 'bg-teal-500' : 'bg-slate-200'}`} />

                        {/* Step 3 */}
                        <div
                            className={`flex items-center gap-2.5 ${
                                currentStep === 3
                                    ? 'text-teal-600 font-bold'
                                    : 'text-slate-400 font-medium'
                            }`}
                        >
                            <span
                                className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition ${
                                    currentStep === 3
                                        ? 'bg-teal-600 text-white shadow-xs'
                                        : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                3
                            </span>
                            <span className="text-xs sm:text-sm">Review &amp; Checkout</span>
                        </div>
                    </div>
                </div>
            )}

            {validationError ? (
                <div className="mb-6 rounded-xl bg-rose-50 p-4 text-xs font-semibold text-rose-700 border border-rose-200 flex items-center justify-between">
                    <span>⚠️ {validationError}</span>
                    <button
                        type="button"
                        onClick={() => setValidationError('')}
                        className="text-rose-500 hover:text-rose-700 font-bold ml-4"
                    >
                        &times;
                    </button>
                </div>
            ) : null}

            <form method="POST" action={routes.store} data-native="true">
                <input type="hidden" name="_token" value={csrfToken} />
                <input type="hidden" name="plan_id" value={plan.id} />

                {/* Hidden state for fields when not active, ensuring all fields are submitted via POST */}
                <input type="hidden" name="has_ground_floor" value={hasGroundFloor ? '1' : '0'} />
                <input type="hidden" name="flats_per_floor" value={Math.round(totalFlats / building.total_floors) || 1} />

                <div className="grid gap-6 lg:grid-cols-3 items-start">
                    {/* Main Left Area */}
                    <div className="space-y-6 lg:col-span-2">
                        {isMybuilding ? (
                            <>
                                {/* STEP 1: Building Identity & Floors */}
                                <div className={currentStep === 1 ? 'space-y-6' : 'hidden'}>
                                    <div className="card p-6 border border-slate-200 bg-white shadow-xs rounded-2xl">
                                        <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600 font-bold text-sm">
                                                1
                                            </div>
                                            <div>
                                                <h2 className="text-base font-semibold text-slate-900">Building Details &amp; Address</h2>
                                                <p className="text-xs text-slate-500">Provide the building name, holding number, and street address.</p>
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

                                    {/* Location details */}
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

                                    {/* Floor Count Setup */}
                                    <div className="card p-6 border border-slate-200 bg-white shadow-xs rounded-2xl">
                                        <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600 font-bold text-sm">
                                                3
                                            </div>
                                            <div>
                                                <h2 className="text-base font-semibold text-slate-900">Building Floor Structure</h2>
                                                <p className="text-xs text-slate-500">Specify total floors. Next step lets you configure flats per floor.</p>
                                            </div>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2 items-center">
                                            <div>
                                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                                    Total Floors <span className="text-rose-500">*</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    name="total_floors"
                                                    min="1"
                                                    max="200"
                                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-base font-bold text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                    value={building.total_floors}
                                                    onChange={(e) => handleTotalFloorsChange(e.target.value)}
                                                />
                                                <span className="mt-1 block text-xs text-slate-500">
                                                    e.g. 10 floors for GF + Floors 1 to 9
                                                </span>
                                            </div>

                                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                                <label className="flex items-center gap-3 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={hasGroundFloor}
                                                        onChange={(e) => setHasGroundFloor(e.target.checked)}
                                                        className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                                    />
                                                    <div>
                                                        <span className="text-sm font-semibold text-slate-800">
                                                            Starts with Ground Floor (GF)
                                                        </span>
                                                        <span className="block text-xs text-slate-500">
                                                            Floors will be labeled: GF, 1st Floor, 2nd Floor...
                                                        </span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        <div className="mt-6 flex justify-end">
                                            <button
                                                type="button"
                                                onClick={goToStep2}
                                                className="rounded-xl bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-teal-500 active:scale-[0.99] transition flex items-center gap-2"
                                            >
                                                <span>Next: Configure Floors &amp; Flats</span>
                                                &rarr;
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {/* STEP 2: Floor-wise Flat Allocation ("next e gele") */}
                                <div className={currentStep === 2 ? 'space-y-6' : 'hidden'}>
                                    <div className="card p-6 border border-slate-200 bg-white shadow-xs rounded-2xl">
                                        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-4">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="rounded-md bg-teal-50 px-2 py-0.5 text-xs font-bold text-teal-700">
                                                        Step 2
                                                    </span>
                                                    <h2 className="text-lg font-semibold text-slate-900">
                                                        Floor-wise Flat Allocation
                                                    </h2>
                                                </div>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    Building: <strong>{building.building_name || 'My Building'}</strong> ({building.total_floors} Floors). Enter flat count on each floor.
                                                </p>
                                            </div>

                                            {/* Quick fill helper */}
                                            <div className="flex items-center gap-2 rounded-xl bg-slate-50 p-2.5 border border-slate-200">
                                                <span className="text-xs font-semibold text-slate-700">Quick set:</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="26"
                                                    className="w-14 rounded-lg border border-slate-300 px-2 py-1 text-center text-xs font-bold bg-white"
                                                    value={quickFlats}
                                                    onChange={(e) => setQuickFlats(e.target.value)}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={applyQuickFlatsToAll}
                                                    className="rounded-lg bg-slate-800 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-700 transition"
                                                >
                                                    Apply to all
                                                </button>
                                            </div>
                                        </div>

                                        {/* Floor-wise Inputs List */}
                                        <div className="space-y-2.5 max-h-[480px] overflow-y-auto p-1 pr-2">
                                            {floorPlan.map((count, index) => {
                                                const label = getFloorLabel(index);
                                                const isGf = hasGroundFloor && index === 0;

                                                return (
                                                    <div
                                                        key={index}
                                                        className={`flex items-center justify-between rounded-xl border px-4 py-3 shadow-2xs transition ${
                                                            isGf ? 'border-teal-200 bg-teal-50/40' : 'border-slate-200 bg-white hover:bg-slate-50/60'
                                                        }`}
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <span
                                                                className={`flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold ${
                                                                    isGf ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700'
                                                                }`}
                                                            >
                                                                {isGf ? 'GF' : hasGroundFloor ? index : index + 1}
                                                            </span>
                                                            <div>
                                                                <span className="text-sm font-semibold text-slate-800">
                                                                    {label}
                                                                </span>
                                                                {isGf ? (
                                                                    <span className="ml-2 inline-block rounded-md bg-teal-100 px-1.5 py-0.2 text-[10px] font-bold text-teal-800">
                                                                        Ground Level
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                type="number"
                                                                name={`floor_plan[${index}]`}
                                                                min="0"
                                                                max="26"
                                                                className="w-16 rounded-lg border border-slate-300 px-2 py-1.5 text-center text-sm font-bold text-slate-900 focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                                                value={count}
                                                                onChange={(e) => updateFloorCount(index, e.target.value)}
                                                            />
                                                            <span className="text-xs text-slate-500 font-medium">flats</span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        {/* Dynamic Total Summary Card */}
                                        <div className="mt-5 rounded-2xl bg-teal-50/70 p-4 border border-teal-200 flex flex-wrap items-center justify-between gap-4">
                                            <div>
                                                <span className="text-xs font-semibold uppercase tracking-wider text-teal-800">
                                                    Live Allocation Summary
                                                </span>
                                                <div className="mt-1 text-sm font-medium text-teal-950">
                                                    <strong>{building.total_floors} Floors</strong> configured &rarr;{' '}
                                                    <span className="text-base font-bold text-teal-700">{totalFlats} Total Flats</span>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <span className="text-xs text-teal-700">Estimated Monthly Bill</span>
                                                <div className="text-xl font-black text-teal-800">
                                                    {currency} {(totalFlats * flatRate).toFixed(2)}
                                                    <span className="text-xs font-normal text-teal-600"> / mo</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-6 flex items-center justify-between gap-4">
                                            <button
                                                type="button"
                                                onClick={() => goToStep(1)}
                                                className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition"
                                            >
                                                &larr; Back to Building Info
                                            </button>

                                            <button
                                                type="button"
                                                onClick={goToStep3}
                                                className="rounded-xl bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-teal-500 active:scale-[0.99] transition flex items-center gap-2"
                                            >
                                                <span>Next: Review &amp; Checkout</span>
                                                &rarr;
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {/* STEP 3: Review & Confirmation */}
                                <div className={currentStep === 3 ? 'space-y-6' : 'hidden'}>
                                    <div className="card p-6 border border-slate-200 bg-white shadow-xs rounded-2xl">
                                        <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                                            <span className="rounded-md bg-teal-50 px-2 py-0.5 text-xs font-bold text-teal-700">
                                                Step 3
                                            </span>
                                            <h2 className="text-base font-semibold text-slate-900">Review Building &amp; Plan Configuration</h2>
                                        </div>

                                        <div className="space-y-4 text-sm text-slate-700">
                                            <div className="grid gap-3 sm:grid-cols-2 rounded-xl bg-slate-50 p-4 border border-slate-200">
                                                <div>
                                                    <span className="text-xs text-slate-500 uppercase tracking-wider font-semibold">Building</span>
                                                    <div className="text-base font-bold text-slate-900 mt-0.5">{building.building_name}</div>
                                                    {building.building_number ? (
                                                        <div className="text-xs text-slate-600">Holding #{building.building_number}</div>
                                                    ) : null}
                                                    {building.building_address ? (
                                                        <div className="text-xs text-slate-500">{building.building_address}</div>
                                                    ) : null}
                                                </div>
                                                <div>
                                                    <span className="text-xs text-slate-500 uppercase tracking-wider font-semibold">Location</span>
                                                    <div className="text-sm font-semibold text-slate-800 mt-0.5">
                                                        {[selectedAreaName, selectedCityName, selectedDistrictName].filter(Boolean).join(', ') || 'Not specified'}
                                                    </div>
                                                    <div className="mt-2 text-xs text-slate-500">
                                                        Total Floors: <strong>{building.total_floors}</strong> · Ground Floor: <strong>{hasGroundFloor ? 'Included' : 'No'}</strong>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Floor-wise allocation pill badges */}
                                            <div>
                                                <div className="flex items-center justify-between mb-2">
                                                    <span className="text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                                        Floor-wise Breakdown
                                                    </span>
                                                    <span className="text-xs font-bold text-teal-700">
                                                        {totalFlats} Total Flats
                                                    </span>
                                                </div>

                                                <div className="flex flex-wrap gap-2 max-h-40 overflow-y-auto p-1">
                                                    {floorPlan.map((count, index) => (
                                                        <span
                                                            key={index}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 shadow-2xs"
                                                        >
                                                            <span className="font-semibold text-slate-900">{getFloorLabel(index)}:</span>
                                                            <span className="font-bold text-teal-700">{count} flats</span>
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-6 flex items-center justify-between pt-4 border-t border-slate-100">
                                            <button
                                                type="button"
                                                onClick={() => goToStep(2)}
                                                className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition"
                                            >
                                                &larr; Back to Floors &amp; Flats
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </>
                        ) : (
                            /* Standard Fixed Product Plan Details */
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
                            {/* If MyBuilding and not on step 3, show next step button; if step 3 (or standard product), submit form */}
                            {isMybuilding && currentStep === 1 ? (
                                <button
                                    type="button"
                                    onClick={goToStep2}
                                    className="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white shadow-md hover:bg-teal-700 active:scale-[0.99] transition flex items-center justify-center gap-2"
                                >
                                    <span>Continue to Floor Setup</span>
                                    &rarr;
                                </button>
                            ) : isMybuilding && currentStep === 2 ? (
                                <button
                                    type="button"
                                    onClick={goToStep3}
                                    className="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white shadow-md hover:bg-teal-700 active:scale-[0.99] transition flex items-center justify-center gap-2"
                                >
                                    <span>Continue to Review</span>
                                    &rarr;
                                </button>
                            ) : (
                                <button
                                    type="submit"
                                    className="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white shadow-md hover:bg-teal-700 active:scale-[0.99] transition flex items-center justify-center gap-2"
                                >
                                    <span>Confirm Order &amp; Proceed to Payment</span>
                                    &rarr;
                                </button>
                            )}

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
