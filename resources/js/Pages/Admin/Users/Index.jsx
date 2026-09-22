import React, { useState, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3.5 py-1.5 font-semibold text-white hover:bg-teal-500 shadow-sm transition',
    secondary: 'border border-slate-300 rounded-full text-xs px-3.5 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600 transition',
};

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
    const [searchTerm, setSearchTerm] = useState('');

    const filteredUsers = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();
        if (!query) return users;
        return users.filter(
            (u) =>
                String(u.name || '').toLowerCase().includes(query) ||
                String(u.email || '').toLowerCase().includes(query) ||
                String(u.role_label || '').toLowerCase().includes(query)
        );
    }, [users, searchTerm]);

    const total = filteredUsers.length;

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <input
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Search users..."
                            className="w-full max-w-xs rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        />

                        <div className="flex flex-wrap items-center gap-1.5">
                            {roles.map((role) => (
                                <a
                                    key={role.value}
                                    href={role.route}
                                    data-native="true"
                                    className={`rounded-full text-xs px-3 py-1 font-semibold transition ${
                                        selectedRole === String(role.value)
                                            ? BTN.primary
                                            : BTN.secondary
                                    }`}
                                >
                                    {role.label}
                                </a>
                            ))}
                        </div>

                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            Showing {total > 0 ? 1 : 0} – {total} of {total} users
                        </span>
                    </div>

                    <a
                        href={routes?.create}
                        data-native="true"
                        className={BTN.primary}
                    >
                        New {selected_role_label}
                    </a>
                </div>

                <DataTable
                    framed={false}
                    rows={filteredUsers}
                    emptyMessage="No users found."
                    columns={[
                        {
                            key: 'name',
                            header: 'Name',
                            render: (user) => (
                                <div className="flex items-center gap-3">
                                    {user.avatar_url ? (
                                        <img src={user.avatar_url} alt={user.name} className="h-8 w-8 rounded-full border border-slate-200 object-cover" />
                                    ) : (
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-700">
                                            {initials(user.name)}
                                        </div>
                                    )}
                                    <div className="font-semibold text-slate-900">{user.name}</div>
                                </div>
                            ),
                        },
                        { key: 'email', header: 'Email', cellClassName: 'text-slate-600', render: (user) => user.email },
                        { key: 'role', header: 'Role', cellClassName: 'text-slate-600', render: (user) => user.role_label },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (user) => (
                                <div className="inline-flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href={user?.routes?.edit} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-700 transition">Edit</a>
                                    <span className="text-slate-300">·</span>
                                    <form
                                        method="POST"
                                        action={user?.routes?.destroy}
                                        data-native="true"
                                        className="inline"
                                        onSubmit={(event) => { if (!window.confirm(`Delete user ${user.name}?`)) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-700 transition">Delete</button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(user) => (
                        <MobileCard
                            avatar={user.avatar_url ? (
                                <img src={user.avatar_url} alt={user.name} className="h-11 w-11 rounded-full border border-slate-200 object-cover" />
                            ) : (
                                <div className="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-sm font-semibold text-slate-700">
                                    {initials(user.name)}
                                </div>
                            )}
                            title={user.name}
                            subtitle={user.email}
                            badge={user.role_label}
                            actions={
                                <>
                                    <a
                                        href={user?.routes?.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Edit
                                    </a>
                                    <form
                                        method="POST"
                                        action={user?.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm(`Delete user ${user.name}?`)) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                    </form>
                                </>
                            }
                        />
                    )}
                />

                <div className="border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                    Showing <span className="font-semibold text-slate-700">{total > 0 ? 1 : 0}</span> – <span className="font-semibold text-slate-700">{total}</span> of <span className="font-semibold text-slate-700">{total}</span> users
                </div>
            </div>
        </>
    );
}
