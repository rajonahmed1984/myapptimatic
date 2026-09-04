import React, { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const statusClass = (status) => {
    switch (status) {
        case 'provisioned':
            return 'bg-emerald-100 text-emerald-700 border-emerald-200';
        case 'failed':
            return 'bg-rose-100 text-rose-700 border-rose-200';
        case 'pending':
            return 'bg-amber-100 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};

function Stat({ label, value, tone = 'slate' }) {
    return (
        <div className="card px-4 py-3">
            <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold text-${tone}-700`}>{value}</div>
        </div>
    );
}

/**
 * Building details ordered against one licence. Saving records what the
 * customer bought; provisioning creates it inside their installation.
 */
function ProvisionForm({ row, defaultInstallUrl, onDone }) {
    const p = row.provision || {};
    const { data, setData, post, processing, errors } = useForm({
        license_id: row.license_id,
        building_name: p.building_name || '',
        building_address: p.building_address || '',
        total_floors: p.total_floors || 1,
        flats_per_floor: p.flats_per_floor || 4,
        install_url: p.install_url || defaultInstallUrl || '',
        district_id: p.district_id || '',
        city_id: p.city_id || '',
        area_id: p.area_id || '',
        owner_name: p.owner_name || row.customer || '',
        owner_email: p.owner_email || '',
        owner_phone: p.owner_phone || '',
    });

    const [districts, setDistricts] = useState([]);
    const [loadingLocations, setLoadingLocations] = useState(false);
    const [locationError, setLocationError] = useState('');

    // The installation owns these ids, so they are fetched from it rather
    // than guessed or typed by hand.
    const loadLocations = async () => {
        setLoadingLocations(true);
        setLocationError('');
        try {
            const res = await fetch('/admin/mybuilding/locations', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ install_url: data.install_url }),
            });
            const json = await res.json();
            if (!json.ok) throw new Error(json.error || 'Could not read locations.');
            setDistricts(json.districts || []);
            if ((json.districts || []).length === 0) {
                setLocationError('The installation returned no districts.');
            }
        } catch (err) {
            setLocationError(err.message);
            setDistricts([]);
        } finally {
            setLoadingLocations(false);
        }
    };

    const cities = useMemo(
        () => districts.find((d) => String(d.id) === String(data.district_id))?.cities || [],
        [districts, data.district_id]
    );
    const areas = useMemo(
        () => cities.find((c) => String(c.id) === String(data.city_id))?.areas || [],
        [cities, data.city_id]
    );

    const flats = useMemo(
        () => Math.max(0, Number(data.total_floors || 0)) * Math.max(0, Number(data.flats_per_floor || 0)),
        [data.total_floors, data.flats_per_floor]
    );

    const submit = (e) => {
        e.preventDefault();
        post('/admin/mybuilding', { preserveScroll: true, onSuccess: onDone });
    };

    const field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm';

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-2">
            <label className="text-sm">
                <span className="mb-1 block font-medium text-slate-700">Building name</span>
                <input className={field} value={data.building_name} onChange={(e) => setData('building_name', e.target.value)} required />
                {errors.building_name && <em className="text-xs text-rose-600">{errors.building_name}</em>}
            </label>

            <label className="text-sm">
                <span className="mb-1 block font-medium text-slate-700">Address</span>
                <input className={field} value={data.building_address} onChange={(e) => setData('building_address', e.target.value)} />
            </label>

            <label className="text-sm">
                <span className="mb-1 block font-medium text-slate-700">Total floors</span>
                <input type="number" min="1" max="200" className={field} value={data.total_floors} onChange={(e) => setData('total_floors', e.target.value)} required />
                {errors.total_floors && <em className="text-xs text-rose-600">{errors.total_floors}</em>}
            </label>

            <label className="text-sm">
                <span className="mb-1 block font-medium text-slate-700">Flats per floor</span>
                <input type="number" min="1" max="26" className={field} value={data.flats_per_floor} onChange={(e) => setData('flats_per_floor', e.target.value)} required />
                {errors.flats_per_floor && <em className="text-xs text-rose-600">{errors.flats_per_floor}</em>}
            </label>

            <div className="md:col-span-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600">
                This order covers <strong className="text-slate-900">{flats}</strong> flats
                ({data.total_floors || 0} floors &times; {data.flats_per_floor || 0}).
            </div>

            <label className="text-sm md:col-span-2">
                <span className="mb-1 block font-medium text-slate-700">Installation URL</span>
                <input className={field} value={data.install_url} onChange={(e) => setData('install_url', e.target.value)} placeholder="https://customer-domain.com" required />
                {errors.install_url && <em className="text-xs text-rose-600">{errors.install_url}</em>}
            </label>

            <label className="text-sm">
                <span className="mb-1 block font-medium text-slate-700">Owner name</span>
                <input className={field} value={data.owner_name} onChange={(e) => setData('owner_name', e.target.value)} required />
            </label>

            <label className="text-sm">
                <span className="mb-1 block font-medium text-slate-700">Owner email</span>
                <input type="email" className={field} value={data.owner_email} onChange={(e) => setData('owner_email', e.target.value)} required />
                {errors.owner_email && <em className="text-xs text-rose-600">{errors.owner_email}</em>}
            </label>

            <label className="text-sm">
                <span className="mb-1 block font-medium text-slate-700">Owner phone</span>
                <input className={field} value={data.owner_phone} onChange={(e) => setData('owner_phone', e.target.value)} required />
            </label>

            <div className="md:col-span-2">
                <div className="mb-2 flex items-center gap-2">
                    <span className="text-sm font-medium text-slate-700">Location</span>
                    <button
                        type="button"
                        onClick={loadLocations}
                        disabled={loadingLocations || !data.install_url}
                        className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold disabled:opacity-50"
                    >
                        {loadingLocations ? 'Loading…' : 'Load from installation'}
                    </button>
                    {locationError && <span className="text-xs text-rose-600">{locationError}</span>}
                </div>

                {districts.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500">
                        Load the districts from the installation to pick a location. The ids only exist over there.
                    </p>
                ) : (
                    <div className="grid grid-cols-3 gap-2 text-sm">
                        <label>
                            <span className="mb-1 block font-medium text-slate-700">District</span>
                            <select
                                className={field}
                                value={data.district_id}
                                onChange={(e) => { setData('district_id', e.target.value); setData('city_id', ''); setData('area_id', ''); }}
                            >
                                <option value="">Select…</option>
                                {districts.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                        </label>

                        <label>
                            <span className="mb-1 block font-medium text-slate-700">City</span>
                            <select
                                className={field}
                                value={data.city_id}
                                onChange={(e) => { setData('city_id', e.target.value); setData('area_id', ''); }}
                                disabled={cities.length === 0}
                            >
                                <option value="">Select…</option>
                                {cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </label>

                        <label>
                            <span className="mb-1 block font-medium text-slate-700">Area</span>
                            <select
                                className={field}
                                value={data.area_id}
                                onChange={(e) => setData('area_id', e.target.value)}
                                disabled={areas.length === 0}
                            >
                                <option value="">Select…</option>
                                {areas.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                        </label>
                    </div>
                )}
            </div>

            <div className="md:col-span-2 flex justify-end gap-2">
                <button type="submit" disabled={processing} className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">
                    {processing ? 'Saving…' : 'Save building details'}
                </button>
            </div>
        </form>
    );
}

export default function Index({ pageTitle = 'MyBuilding', product = null, rows = [], summary = {}, config = {} }) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const [openLicense, setOpenLicense] = useState(null);

    const provisionNow = (provisionId) => {
        router.post(`/admin/mybuilding/${provisionId}/provision`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">MyBuilding</h1>
                    <p className="text-sm text-slate-500">
                        Licences for the building-management product, and the building each one creates in the customer&apos;s installation.
                    </p>
                </div>
            </div>

            {!product && (
                <div className="card mb-4 border-l-4 border-amber-400 px-4 py-3 text-sm text-amber-800">
                    No product with slug <code>{config.product_slug}</code> exists yet. Create it under Products first.
                </div>
            )}

            {!config.secret_configured && (
                <div className="card mb-4 border-l-4 border-rose-400 px-4 py-3 text-sm text-rose-800">
                    <strong>MYBUILDING_PROVISION_SECRET is not set.</strong> Provisioning is disabled until this server and the
                    customer installation share the same secret.
                </div>
            )}

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Stat label="Licences" value={summary.licenses ?? 0} />
                <Stat label="Provisioned" value={summary.provisioned ?? 0} tone="emerald" />
                <Stat label="Awaiting" value={summary.pending ?? 0} tone="amber" />
                <Stat label="Failed" value={summary.failed ?? 0} tone="rose" />
                <Stat label="Flats sold" value={summary.flats ?? 0} />
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Customer</th>
                            <th className="px-4 py-3">Licence</th>
                            <th className="px-4 py-3">Building</th>
                            <th className="px-4 py-3">Size</th>
                            <th className="px-4 py-3">Provisioning</th>
                            <th className="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                    No MyBuilding licences yet.
                                </td>
                            </tr>
                        )}

                        {rows.map((row) => {
                            const p = row.provision;
                            const open = openLicense === row.license_id;

                            return (
                                <React.Fragment key={row.license_id}>
                                    <tr className="border-b border-slate-200 align-top">
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-slate-900">{row.customer || '—'}</div>
                                            <div className="text-xs text-slate-500">{row.plan || ''}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <code className="text-xs">{row.license_key}</code>
                                            <div className="text-xs text-slate-500">
                                                {row.license_status}
                                                {row.expires_at ? ` · expires ${row.expires_at}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">{p?.building_name || <span className="text-slate-400">not set</span>}</td>
                                        <td className="px-4 py-3">
                                            {p ? (
                                                <>
                                                    {p.total_floors} floors &times; {p.flats_per_floor}
                                                    <div className="text-xs text-slate-500">{p.contracted_flats} flats</div>
                                                </>
                                            ) : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(p?.status)}`}>
                                                {p?.status || 'not ordered'}
                                            </span>
                                            {p?.provisioned_at && (
                                                <div className="text-xs text-slate-500">{p.provisioned_at}</div>
                                            )}
                                            {p?.last_error && (
                                                <div className="mt-1 max-w-xs text-xs text-rose-600">{p.last_error}</div>
                                            )}
                                            {p?.registration_code && (
                                                <div className="text-xs text-slate-500">code: {p.registration_code}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                {!p?.status || p.status !== 'provisioned' ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setOpenLicense(open ? null : row.license_id)}
                                                        className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold"
                                                    >
                                                        {open ? 'Close' : (p ? 'Edit' : 'Set up')}
                                                    </button>
                                                ) : null}

                                                {p && p.status !== 'provisioned' && config.secret_configured && (
                                                    <button
                                                        type="button"
                                                        onClick={() => provisionNow(p.id)}
                                                        className="rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white"
                                                    >
                                                        {p.status === 'failed' ? 'Retry' : 'Provision'}
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>

                                    {open && (
                                        <tr className="border-b border-slate-200">
                                            <td colSpan={6} className="px-4 py-4">
                                                <ProvisionForm
                                                    row={row}
                                                    defaultInstallUrl={config.default_install_url}
                                                    onDone={() => setOpenLicense(null)}
                                                />
                                            </td>
                                        </tr>
                                    )}
                                </React.Fragment>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <input type="hidden" value={csrfToken} readOnly />
        </>
    );
}
