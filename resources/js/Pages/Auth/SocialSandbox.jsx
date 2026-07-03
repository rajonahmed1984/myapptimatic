import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';

export default function SocialSandbox({ provider, portal, users = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        name: '',
        portal: portal,
    });

    const handleSelectUser = (user) => {
        setData({
            email: user.email,
            name: user.name,
            portal: portal,
        });

        // Submit form after updating state
        setTimeout(() => {
            const formData = { email: user.email, name: user.name, portal };
            // We can post directly using Inertia's router or the hook
            post(`/auth/sandbox/${provider.toLowerCase()}/login`);
        }, 100);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/auth/sandbox/${provider.toLowerCase()}/login`);
    };

    return (
        <>
            <Head title={`${provider} Login Sandbox`} />
            <GuestAuthLayout wide={true}>
                <section className="bg-slate-900 border border-slate-800/80 relative overflow-hidden rounded-3xl p-8 text-slate-200 shadow-2xl sm:p-10">
                    {/* Glowing background gradient elements */}
                    <div className="absolute top-0 right-0 -mt-24 -mr-24 h-96 w-96 rounded-full bg-teal-500/10 blur-3xl" />
                    <div className="absolute bottom-0 left-0 -mb-24 -ml-24 h-96 w-96 rounded-full bg-indigo-500/10 blur-3xl" />

                    <div className="relative z-10">
                        <div className="text-center mb-8">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-teal-500/20 bg-teal-500/5 text-xs font-semibold text-teal-400 mb-4 tracking-wide uppercase">
                                <span className="flex h-2 w-2 relative">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                    <span className="relative inline-flex rounded-full h-2 w-2 bg-teal-500"></span>
                                </span>
                                Developer Sandbox
                            </div>
                            <h1 className="text-3xl font-extrabold text-white tracking-tight">
                                {provider} Login Simulator
                            </h1>
                            <p className="mt-2 text-xs text-slate-400 max-w-md mx-auto leading-relaxed">
                                OAuth credentials for <span className="text-teal-400 font-semibold">{provider}</span> are not configured. 
                                Select an existing client account or register a new one to simulate the authentication callback.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                            {/* Column 1: Choose Existing Account */}
                            <div className="space-y-4">
                                <h2 className="text-sm font-semibold text-slate-300 flex items-center gap-2 pb-2 border-b border-slate-800">
                                    <svg className="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.386 11.386 0 0110.089 18M15 19.128a11.386 11.386 0 00-4.912-1.128M15 19.128v.109c0 .218-.086.43-.241.586l-.586.586a1.125 1.125 0 01-1.591 0l-.586-.586a1.125 1.125 0 00-.786-.33H7.5M10.089 18a11.36 11.36 0 01-2.589-1.128M10.089 18v.109a12 12 0 01-3 0v-.109m0 0a11.36 11.36 0 002.589-1.128M7.5 16.5h.008v.008H7.5V16.5zm0-3h.008v.008H7.5v-.008zm0-3h.008v.008H7.5V10.5z" />
                                    </svg>
                                    Simulate with Existing Account
                                </h2>

                                <div className="space-y-2 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                                    {users.length === 0 ? (
                                        <p className="text-xs text-slate-500 italic py-4 text-center bg-slate-950/40 rounded-2xl border border-slate-800/40">
                                            No client users found in the database.
                                        </p>
                                    ) : (
                                        users.map((user) => (
                                            <button
                                                key={user.id}
                                                type="button"
                                                onClick={() => handleSelectUser(user)}
                                                className="w-full text-left p-3.5 rounded-2xl bg-slate-950/40 hover:bg-slate-950/80 border border-slate-800/60 hover:border-teal-500/50 transition-all duration-200 group flex flex-col gap-1 active:scale-[0.99]"
                                            >
                                                <div className="flex items-center justify-between w-full">
                                                    <span className="font-semibold text-slate-200 group-hover:text-teal-400 transition-colors text-xs">
                                                        {user.name}
                                                    </span>
                                                    <span className="text-[10px] text-slate-500 px-2 py-0.5 rounded-full bg-slate-900 border border-slate-800/80">
                                                        Client
                                                    </span>
                                                </div>
                                                <span className="text-[11px] text-slate-500 group-hover:text-slate-400 transition-colors break-all">
                                                    {user.email}
                                                </span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            </div>

                            {/* Column 2: Simulated Registration */}
                            <div className="space-y-4">
                                <h2 className="text-sm font-semibold text-slate-300 flex items-center gap-2 pb-2 border-b border-slate-800">
                                    <svg className="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-9 0a3 3 0 11-6 0 3 3 0 016 0zM4 19.25v.109a11.386 11.386 0 001.246 5.142A11.386 11.386 0 0010.388 24h3.224a11.386 11.386 0 005.142-1.246 11.386 11.386 0 001.246-5.142v-.109m-16 0A11.36 11.36 0 0110.389 18h3.224A11.36 11.36 0 0119 19.25z" />
                                    </svg>
                                    Simulate New Account Registration
                                </h2>

                                <form onSubmit={handleSubmit} className="space-y-4 text-left">
                                    <div>
                                        <label className="block text-[11px] font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">
                                            Email Address
                                        </label>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="jane.doe@example.com"
                                            required
                                            className="w-full h-11 px-4 text-xs bg-slate-950/60 border border-slate-800 hover:border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 rounded-full text-slate-200 outline-none transition-all placeholder:text-slate-600"
                                        />
                                        {errors.email && (
                                            <p className="mt-1 text-[11px] text-rose-500">{errors.email}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-[11px] font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">
                                            Full Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="Jane Doe"
                                            className="w-full h-11 px-4 text-xs bg-slate-950/60 border border-slate-800 hover:border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 rounded-full text-slate-200 outline-none transition-all placeholder:text-slate-600"
                                        />
                                        {errors.name && (
                                            <p className="mt-1 text-[11px] text-rose-500">{errors.name}</p>
                                        )}
                                    </div>

                                    <div className="pt-2">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full h-11 rounded-full bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white text-xs font-bold tracking-wide transition-all duration-200 flex items-center justify-center gap-2 shadow-lg shadow-teal-500/10 hover:shadow-teal-500/20 active:scale-[0.98] disabled:opacity-50"
                                        >
                                            Simulate OAuth Callback
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Back to normal login */}
                        <div className="mt-8 text-center pt-4 border-t border-slate-800/40">
                            <a
                                href={`/login`}
                                className="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white transition-colors"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                                </svg>
                                Back to Sign In
                            </a>
                        </div>
                    </div>
                </section>
            </GuestAuthLayout>
        </>
    );
}
