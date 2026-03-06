import React, { useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function Login({
    pageTitle = 'Email Login',
    portal = 'Portal',
    mailboxes = [],
    prefill_email = '',
    routes = {},
}) {
    const initialEmail = String(prefill_email || '');
    const [selectedEmail, setSelectedEmail] = useState(initialEmail);
    const options = useMemo(() => Array.isArray(mailboxes) ? mailboxes : [], [mailboxes]);

    const { data, setData, post, processing, errors } = useForm({
        email: initialEmail,
        password: '',
        remember: true,
    });

    React.useEffect(() => {
        const nextEmail = String(prefill_email || '');
        setSelectedEmail(nextEmail);
        setData('email', nextEmail);
    }, [prefill_email, setData]);

    const submit = (event) => {
        event.preventDefault();
        post(routes.login);
    };

    const onMailboxPick = (event) => {
        const value = event.target.value;
        setSelectedEmail(value);
        setData('email', value);
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mx-auto w-full max-w-xl">
                <div className="card p-6 md:p-8">
                    <div className="mb-6">
                        <div className="text-xs uppercase tracking-[0.22em] text-slate-500">{portal}</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">Email Login</h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Login to your assigned mailbox to access Apptimatic Email.
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            Mailbox login stays active until credentials change or you logout.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        {options.length > 0 ? (
                            <label className="block space-y-1">
                                <span className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Assigned Mailbox</span>
                                <select
                                    value={selectedEmail}
                                    onChange={onMailboxPick}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800 outline-none ring-0 transition focus:border-teal-400"
                                >
                                    <option value="">Select mailbox</option>
                                    {options.map((mailbox) => (
                                        <option key={mailbox.id} value={mailbox.email}>
                                            {mailbox.display_name ? `${mailbox.display_name} (${mailbox.email})` : mailbox.email}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Email</span>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800 outline-none ring-0 transition focus:border-teal-400"
                                required
                            />
                            {errors.email ? <div className="text-xs text-rose-600">{errors.email}</div> : null}
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Password</span>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800 outline-none ring-0 transition focus:border-teal-400"
                                required
                            />
                            {errors.password ? <div className="text-xs text-rose-600">{errors.password}</div> : null}
                        </label>

                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                checked={Boolean(data.remember)}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                            />
                            Remember this email login
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex w-full items-center justify-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing ? 'Signing in...' : 'Login to Inbox'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
