import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

export default function FormPage({
    mode = 'create',
    pageTitle = 'Employee',
    heading = 'Employee',
    subheading = '',
    submitLabel = 'Save',
    action = '',
    backUrl = '',
    method = 'POST',
    managers = [],
    users = [],
    currencyOptions = ['BDT', 'USD'],
    values = {},
    documentLinks = {},
}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const [employmentType, setEmploymentType] = useState(values.employment_type || 'full_time');
    const [salaryType, setSalaryType] = useState(values.salary_type || 'monthly');
    const [photoPreview, setPhotoPreview] = useState(values.photo_path ? `/storage/${values.photo_path}` : null);

    const basicPayOptional = useMemo(
        () => employmentType === 'contract' && salaryType === 'project_base',
        [employmentType, salaryType],
    );

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">{heading}</div>
                    <div className="text-sm text-slate-500">{subheading}</div>
                </div>
                <a href={backUrl} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to employees</a>
            </div>

            <div className="card p-6 max-w-4xl">
                <form method="POST" action={action} encType="multipart/form-data" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={token} />
                    {method.toUpperCase() !== 'POST' ? <input type="hidden" name="_method" value={method.toUpperCase()} /> : null}

                    <div className="section-label">Profile</div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input label="Name" name="name" defaultValue={values.name || ''} required />
                        <Input label="Email" name="email" type="email" defaultValue={values.email || ''} required />
                        <Input label="Phone" name="phone" defaultValue={values.phone || ''} />
                        <Input label="Address" name="address" defaultValue={values.address || ''} />
                        <Input label="Department (optional)" name="department" defaultValue={values.department || ''} />
                        <Input label="Designation" name="designation" defaultValue={values.designation || ''} />
                        <div>
                            <label className="text-xs text-slate-500">Manager (optional)</label>
                            <select name="manager_id" defaultValue={values.manager_id || ''} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">-- none --</option>
                                {managers.map((manager) => <option key={manager.id} value={manager.id}>{manager.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Linked user (optional)</label>
                            <select name="user_id" defaultValue={values.user_id || ''} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">-- none --</option>
                                {users.map((user) => <option key={user.id} value={user.id}>{user.name} ({user.email})</option>)}
                            </select>
                        </div>
                        <Input label="Login password (optional)" name="user_password" type="password" />
                        <Input label="Confirm password" name="user_password_confirmation" type="password" />
                        <Input label="Join date" name="join_date" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" defaultValue={values.join_date || ''} required />
                    </div>

                    <div className="section-label">Employment</div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-xs text-slate-500">Employment type</label>
                            <select name="employment_type" value={employmentType} onChange={(e) => setEmploymentType(e.target.value)} required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="full_time">Full-time</option>
                                <option value="part_time">Part-time</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Work mode</label>
                            <select name="work_mode" defaultValue={values.work_mode || 'remote'} required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="remote">Remote</option>
                                <option value="on_site">On-site</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Status</label>
                            <select name="status" defaultValue={values.status || 'active'} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div className="section-label">Compensation</div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-xs text-slate-500">Salary type</label>
                            <select name="salary_type" value={salaryType} onChange={(e) => setSalaryType(e.target.value)} required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="monthly">Monthly</option>
                                <option value="hourly">Hourly</option>
                                <option value="project_base">Project base</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Currency</label>
                            <select name="currency" defaultValue={values.currency || 'BDT'} required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                {currencyOptions.map((currency) => <option key={currency} value={currency}>{currency}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Basic pay</label>
                            <input name="basic_pay" type="number" min="0" step="0.01" defaultValue={values.basic_pay ?? 0} required={!basicPayOptional} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            <p className="mt-1 text-xs text-slate-400">{basicPayOptional ? 'Optional for contract employees on project-base salary.' : 'Required unless contract + project base.'}</p>
                        </div>
                        <Input label="Hourly rate (optional)" name="hourly_rate" type="number" min="0" step="0.01" defaultValue={values.hourly_rate || ''} />
                    </div>

                    <div className="section-label">Additional</div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <label className="text-xs text-slate-500">NID upload (jpg/png/pdf)</label>
                            <input type="file" name="nid_file" accept=".jpg,.jpeg,.png,.pdf" className="w-full text-sm text-slate-700" />
                            {documentLinks?.nid ? <a href={documentLinks.nid} data-native="true" className="text-xs text-teal-600 hover:text-teal-500">View current NID</a> : null}
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs text-slate-500">Photo (jpg/png)</label>
                            <input type="file" name="photo" accept=".jpg,.jpeg,.png" className="w-full text-sm text-slate-700" onChange={(e) => {
                                const file = e.target.files && e.target.files[0];
                                if (file) setPhotoPreview(URL.createObjectURL(file));
                            }} />
                            <div className="mt-2 h-16 w-16 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                                {photoPreview ? <img src={photoPreview} alt="Photo preview" className="h-full w-full object-cover" /> : <div className="flex h-full w-full items-center justify-center text-sm font-semibold text-slate-500">{(values.name || 'E').charAt(0).toUpperCase()}</div>}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs text-slate-500">CV upload (pdf)</label>
                            <input type="file" name="cv_file" accept=".pdf" className="w-full text-sm text-slate-700" />
                            {documentLinks?.cv ? <a href={documentLinks.cv} data-native="true" className="text-xs text-teal-600 hover:text-teal-500">View current CV</a> : null}
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <a href={backUrl} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                        <button type="submit" className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">{submitLabel}</button>
                    </div>
                </form>
            </div>
        </>
    );
}

function Input({ label, ...props }) {
    return (
        <div>
            <label className="text-xs text-slate-500">{label}</label>
            <input {...props} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
        </div>
    );
}
