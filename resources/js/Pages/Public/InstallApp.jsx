import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import GuestAuthLayout from '../../Layouts/GuestAuthLayout';
import {
    detectInAppBrowser,
    detectInstallPlatform,
    isIosSafari,
    isNativeShell,
    usePwaInstall,
} from '../../utils/pwa';

const TABS = [
    { key: 'ios', label: 'iPhone / iPad' },
    { key: 'android', label: 'Android' },
    { key: 'desktop', label: 'Computer' },
];

const ShareIcon = () => (
    <svg className="inline h-4 w-4 align-[-3px] text-sky-600" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-label="Share">
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v12M8 7l4-4 4 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
    </svg>
);

const PlusSquareIcon = () => (
    <svg className="inline h-4 w-4 align-[-3px] text-slate-700" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3" y="3" width="18" height="18" rx="4" />
        <path strokeLinecap="round" d="M12 8v8M8 12h8" />
    </svg>
);

const Steps = ({ items }) => (
    <ol className="space-y-3">
        {items.map((item, index) => (
            <li key={index} className="flex gap-3 text-sm leading-relaxed text-slate-700">
                <span className="grid h-6 w-6 flex-shrink-0 place-items-center rounded-full bg-teal-50 text-xs font-bold text-teal-700">
                    {index + 1}
                </span>
                <span>{item}</span>
            </li>
        ))}
    </ol>
);

const Note = ({ tone = 'slate', children }) => {
    const tones = {
        slate: 'border-slate-200 bg-slate-50 text-slate-600',
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    };

    return <div className={`rounded-xl border px-4 py-3 text-xs leading-relaxed ${tones[tone]}`}>{children}</div>;
};

export default function InstallApp() {
    const { branding = {} } = usePage().props;
    const appName = branding?.company_name || 'Apptimatic';
    const platform = detectInstallPlatform();
    const inAppBrowser = detectInAppBrowser();
    const { canPrompt, installed, promptInstall } = usePwaInstall();
    const [tab, setTab] = useState(platform);
    const [copied, setCopied] = useState(false);

    const alreadyInApp = installed || isNativeShell();
    const showPromptButton = canPrompt && !inAppBrowser && tab === platform;

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(window.location.href);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch (error) {
            window.prompt('Copy this link and open it in your browser:', window.location.href);
        }
    };

    const iosSteps = [
        <>Open this page in <b>Safari</b>.{!isIosSafari() && platform === 'ios' && ' (Chrome and Edge also work on iOS 16.4 or later.)'}</>,
        <>Tap the <b>Share</b> button <ShareIcon />. In Safari it is in the toolbar; on newer iOS you may need to tap <b>•••</b> first.</>,
        <>Scroll down and tap <b>Add to Home Screen</b> <PlusSquareIcon />. If you don&apos;t see it, tap <b>View More</b>.</>,
        <>Keep <b>Open as Web App</b> switched on, then tap <b>Add</b>.</>,
        <>Open <b>{appName}</b> from your home screen and sign in.</>,
    ];

    const androidSteps = [
        <>Open this page in <b>Chrome</b> (or Samsung Internet / Edge).</>,
        <>Tap the menu button <b>⋮</b> at the top right.</>,
        <>Tap <b>Install app</b> or <b>Add to Home screen</b>, then <b>Install</b>.</>,
        <>Open <b>{appName}</b> from your home screen or app drawer and sign in.</>,
    ];

    const desktopSteps = [
        <>Open this page in <b>Chrome</b> or <b>Edge</b>.</>,
        <>Click the install icon at the right end of the address bar, or open the browser menu and choose <b>Install {appName}</b> (Chrome: <b>Cast, save and share → Install page as app</b>).</>,
        <>On a Mac with Safari, choose <b>File → Add to Dock</b>.</>,
    ];

    return (
        <>
            <Head title="Install App" />
            <section className="relative overflow-hidden rounded-3xl border border-slate-200/60 bg-white p-6 text-slate-800 shadow-xl shadow-slate-200/30 sm:p-8">
                <div className="text-center">
                    <img src="/icons/icon-192x192.png" alt={appName} className="mx-auto h-16 w-16 rounded-2xl shadow-md" />
                    <h1 className="mt-4 text-xl font-bold tracking-tight text-slate-900">Install {appName}</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Use {appName} like an app on your phone or computer. No App Store or Play Store needed.
                    </p>
                </div>

                <div className="mt-6 space-y-4">
                    {alreadyInApp && (
                        <Note tone="emerald">
                            You are already using the installed app. <a href="/login" className="font-semibold underline">Continue to sign in</a>.
                        </Note>
                    )}

                    {!alreadyInApp && inAppBrowser && (
                        <Note tone="amber">
                            <div>
                                You opened this link inside <b>{inAppBrowser}</b>, which can&apos;t install apps. Tap <b>•••</b> and choose
                                {' '}<b>Open in {platform === 'ios' ? 'Safari' : 'browser'}</b>, or copy the link and paste it into
                                {' '}{platform === 'ios' ? 'Safari' : 'Chrome'}.
                            </div>
                            <button
                                type="button"
                                onClick={copyLink}
                                className="mt-2 rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                            >
                                {copied ? 'Link copied' : 'Copy link'}
                            </button>
                        </Note>
                    )}

                    {!alreadyInApp && showPromptButton && (
                        <button
                            type="button"
                            onClick={promptInstall}
                            className="w-full rounded-full bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-teal-500"
                        >
                            Install app
                        </button>
                    )}

                    {!alreadyInApp && (
                        <>
                            <div className="grid grid-cols-3 gap-1 rounded-full bg-slate-100 p-1" role="tablist">
                                {TABS.map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={tab === item.key}
                                        onClick={() => setTab(item.key)}
                                        className={`rounded-full px-2 py-2 text-xs font-semibold transition ${
                                            tab === item.key ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                                        }`}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>

                            <div className="rounded-2xl border border-slate-200 p-4">
                                {tab === 'ios' && <Steps items={iosSteps} />}
                                {tab === 'android' && <Steps items={androidSteps} />}
                                {tab === 'desktop' && <Steps items={desktopSteps} />}
                            </div>

                            {tab === 'ios' && (
                                <Note>
                                    Apple does not allow installing app files (like an Android APK) directly on iPhone. Adding this page to the
                                    Home Screen gives you the same app with its own icon and full-screen window.
                                </Note>
                            )}
                        </>
                    )}

                    <div className="pt-1 text-center">
                        <a href="/login" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                            Continue in the browser instead
                        </a>
                    </div>
                </div>
            </section>
        </>
    );
}

InstallApp.layout = (page) => <GuestAuthLayout>{page}</GuestAuthLayout>;
