import React, { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

const formatSeconds = (seconds) => {
    const total = Math.max(0, Number(seconds || 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = Math.floor(total % 60);
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

export default function GlobalWorkTimer() {
    const page = usePage();
    const csrfToken = page.props?.csrf_token || (typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : '');
    const [activeSeconds, setActiveSeconds] = useState(0);
    const [isActive, setIsActive] = useState(false);
    const [status, setStatus] = useState('Stopped');

    const summaryUrl = '/employee/work-summaries/today';
    const pingUrl = '/employee/work-sessions/ping';

    useEffect(() => {
        let isMounted = true;

        const applyPayload = (payload) => {
            if (!isMounted) return;
            const sec = Number(payload?.active_seconds || 0);
            const act = Boolean(payload?.is_active);
            const st = payload?.status || 'stopped';
            setActiveSeconds(sec);
            setIsActive(act);
            setStatus(!act ? 'Stopped' : st === 'idle' ? 'Idle' : 'Working');
        };

        const fetchSummary = async () => {
            try {
                const res = await fetch(summaryUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.ok) {
                    const json = await res.json();
                    applyPayload(json?.data || null);
                }
            } catch (e) {}
        };

        const pingSession = async () => {
            if (!csrfToken) return;
            try {
                const formData = new FormData();
                formData.append('_token', csrfToken);
                const res = await fetch(pingUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });
                if (res.ok) {
                    const json = await res.json();
                    applyPayload(json?.data || null);
                }
            } catch (e) {}
        };

        fetchSummary();

        const secInterval = setInterval(() => {
            setActiveSeconds((prev) => (isActive ? prev + 1 : prev));
        }, 1000);

        const summaryInterval = setInterval(fetchSummary, 10000);

        const pingInterval = setInterval(() => {
            if (isActive && document.visibilityState === 'visible') {
                pingSession();
            }
        }, 60000);

        const handleCustomUpdate = (event) => {
            applyPayload(event.detail || null);
        };
        window.addEventListener('employee-work-session:update', handleCustomUpdate);

        return () => {
            isMounted = false;
            clearInterval(secInterval);
            clearInterval(summaryInterval);
            clearInterval(pingInterval);
            window.removeEventListener('employee-work-session:update', handleCustomUpdate);
        };
    }, [csrfToken, isActive]);

    return (
        <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-slate-100">
            <div className="flex items-center justify-between gap-2">
                <div className="text-[11px] uppercase tracking-[0.25em] text-slate-300">Session</div>
                <div className="text-xs text-slate-300">{status}</div>
            </div>
            <div className="mt-1 text-2xl font-semibold leading-none">{formatSeconds(activeSeconds)}</div>
        </div>
    );
}
