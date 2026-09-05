import React, { useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import SearchableSelect from '../../Components/SearchableSelect';

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
    const mailboxOptions = useMemo(
        () => [
            { value: '', label: 'Select mailbox' },
            ...options.map((mailbox) => ({
                value: String(mailbox.email || ''),
                label: mailbox.display_name ? `${mailbox.display_name} (${mailbox.email})` : String(mailbox.email || ''),
            })),
        ],
        [options],
    );

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

    const onMailboxPick = (value) => {
        const nextValue = String(value || '');
        setSelectedEmail(nextValue);
        setData('email', nextValue);
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="flex items-center justify-center py-2 md:py-6">
                <div className="w-full max-w-md">
                    <div className="card p-4 sm:p-6 md:p-8 shadow-lg border border-slate-200/80 bg-white rounded-2xl">
                        <div className="mb-6 text-center">
                            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-tr from-teal-600 to-emerald-500 text-white shadow-md">
                                <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div className="text-xs font-bold uppercase tracking-[0.2em] text-teal-600">{portal}</div>
                            <h1 className="mt-1 text-2xl font-bold text-slate-900">Email Login</h1>
                            <p className="mt-1.5 text-xs text-slate-500">
                                Login with your mail server email address and password to access Apptimatic Email.
                            </p>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            {options.length > 0 ? (
                                <label className="block space-y-1">
                                    <span className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Assigned Mailbox</span>
                                    <SearchableSelect
                                        name="mailbox_email"
                                        value={selectedEmail}
                                        onChange={onMailboxPick}
                                        options={mailboxOptions}
                                        placeholder="Select mailbox"
                                    />
                                </label>
                            ) : null}

                            <label className="block space-y-1">
                                <span className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Email</span>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(event) => setData('email', event.target.value)}
                                    className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 outline-none ring-0 transition focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                    placeholder="youremail@apptimatic.com"
                                    required
                                />
                                {errors.email ? <div className="text-xs text-rose-600 font-medium">{errors.email}</div> : null}
                            </label>

                            <label className="block space-y-1">
                                <span className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Password</span>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-sm text-slate-800 outline-none ring-0 transition focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                    placeholder="••••••••"
                                    required
                                />
                                {errors.password ? <div className="text-xs text-rose-600 font-medium">{errors.password}</div> : null}
                            </label>

                            <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.remember)}
                                    onChange={(event) => setData('remember', event.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer"
                                />
                                <span>Remember this email login</span>
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-[10px] bg-teal-600 px-4 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-teal-700 active:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span>{processing ? 'Signing in...' : 'Login to Inbox'}</span>
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </form>

                        <div className="mt-6 pt-4 border-t border-slate-100 text-center">
                            <p className="text-[11px] text-slate-400">
                                Mailbox login stays active until credentials change or you logout.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
