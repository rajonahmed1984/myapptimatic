import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Employees',
    employees = [],
    pagination = {},
    routes = {},
}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">Employees</div>
                </div>
                <a href={routes?.create} data-native="true" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add employee</a>
            </div>

            <div className="card p-6 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2 px-3">ID</th>
                                <th className="py-2 px-3">Name</th>
                                <th className="py-2 px-3">Designation</th>
                                <th className="py-2 px-3">Employment</th>
                                <th className="py-2 px-3">Join Date</th>
                                <th className="py-2 px-3">Manager</th>
                                <th className="py-2 px-3">Status</th>
                                <th className="py-2 px-3">Login</th>
                                <th className="py-2 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.length === 0 ? (
                                <tr><td colSpan={9} className="py-3 px-3 text-center text-slate-500">No employees found.</td></tr>
                            ) : employees.map((employee) => (
                                <tr key={employee.id} className="border-b border-slate-100">
                                    <td className="py-2 px-3 font-semibold text-slate-900">{employee.id}</td>
                                    <td className="py-2 px-3">
                                        <div className="font-semibold text-slate-900">
                                            <a href={employee.routes.show} data-native="true" className="hover:text-teal-600">{employee.name}</a>
                                        </div>
                                        <div className="text-xs text-slate-500">{employee.email}</div>
                                    </td>
                                    <td className="py-2 px-3">{employee.designation}</td>
                                    <td className="py-2 px-3">{employee.employment_type}</td>
                                    <td className="py-2 px-3">{employee.join_date}</td>
                                    <td className="py-2 px-3">{employee.manager_name}</td>
                                    <td className="py-2 px-3">
                                        <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${employee.status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-300 text-slate-600 bg-slate-50'}`}>
                                            {employee.status_label}
                                        </span>
                                    </td>
                                    <td className="py-2 px-3">
                                        {employee.show_login_badge ? (
                                            <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${employee.login_classes}`}>{employee.login_label}</span>
                                        ) : null}
                                        <div className="mt-1 text-[11px] text-slate-400">Last login: {employee.last_login_at}</div>
                                    </td>
                                    <td className="py-2 px-3 text-right space-x-2">
                                        <a href={employee.routes.edit} data-native="true" className="text-xs text-emerald-700 hover:underline">Edit</a>
                                        <form method="POST" action={employee.routes.destroy} data-native="true" className="inline">
                                            <input type="hidden" name="_token" value={token} />
                                            <input type="hidden" name="_method" value="DELETE" />
                                            <button className="text-xs text-rose-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                        <a href={pagination?.previous_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.previous_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Previous</a>
                        <a href={pagination?.next_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.next_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Next</a>
                    </div>
                ) : null}
            </div>
        </>
    );
}
