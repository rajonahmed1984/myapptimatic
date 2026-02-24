import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const initials = (name = '') =>
    String(name)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || '')
        .join('') || 'U';

export default function Index({
    pageTitle = 'Admin Users',
    selected_role_label = 'Users',
    roles = [],
    routes = {},
    users = [],
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const selectedRole = String(props?.selected_role || '');

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div className="section-label">User Management</div>
                    <div className="text-xl font-semibold text-slate-900">{selected_role_label}</div>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="inline-flex items-center gap-2 rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-600"
                >
                    New {selected_role_label}
                </a>
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {roles.map((role) => (
                    <a
                        key={role.value}
                        href={role.route}
                        data-native="true"
                        className={`rounded-full border px-3 py-1 text-sm font-semibold transition ${
                            selectedRole === String(role.value)
                                ? 'border-teal-500 bg-teal-50 text-teal-700'
                                : 'border-slate-300 text-slate-600 hover:border-teal-300 hover:text-teal-700'
                        }`}
                    >
                        {role.label}
                    </a>
                ))}
            </div>

            <div className="card p-6">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th className="py-3">Name</th>
                                <th className="py-3">Email</th>
                                <th className="py-3">Role</th>
                                <th className="py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.length > 0 ? (
                                users.map((user) => (
                                    <tr key={user.id}>
                                        <td className="py-3">
                                            <div className="flex items-center gap-3">
                                                {user.avatar_url ? (
                                                    <img
                                                        src={user.avatar_url}
                                                        alt={user.name}
                                                        className="h-8 w-8 rounded-full border border-slate-200 object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-700">
                                                        {initials(user.name)}
                                                    </div>
                                                )}
                                                <div className="font-semibold text-slate-900">{user.name}</div>
                                            </div>
                                        </td>
                                        <td className="py-3 text-slate-600">{user.email}</td>
                                        <td className="py-3 text-slate-600">{user.role_label}</td>
                                        <td className="py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                <a
                                                    href={user?.routes?.edit}
                                                    data-native="true"
                                                    className="text-sm font-semibold text-teal-600 hover:text-teal-700"
                                                >
                                                    Edit
                                                </a>
                                                <form
                                                    method="POST"
                                                    action={user?.routes?.destroy}
                                                    data-native="true"
                                                    onSubmit={(event) => {
                                                        if (!window.confirm(`Delete user ${user.name}?`)) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <input type="hidden" name="_token" value={csrf} />
                                                    <input type="hidden" name="_method" value="DELETE" />
                                                    <button
                                                        type="submit"
                                                        className="text-sm font-semibold text-rose-600 hover:text-rose-700"
                                                    >
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={4} className="py-6 text-center text-slate-500">
                                        No users found for this role.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
