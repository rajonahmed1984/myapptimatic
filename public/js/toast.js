/**
 * toast.js — Global top-right floating notification system
 *
 * Sources picked up automatically:
 *  1. Blade data-flash-message elements already in the DOM on page load
 *  2. Inertia page-change events (flash.status / flash.error / flash.message props)
 *  3. Manual: window.showToast(message, type = 'success' | 'error' | 'warning' | 'info')
 */

(function () {
    'use strict';

    /* ── duration config ──────────────────────────────────────────── */
    const DURATION = {
        success: 4500,
        error:   7000,
        warning: 6000,
        info:    5000,
    };

    /* ── palette ──────────────────────────────────────────────────── */
    const STYLE = {
        success: {
            bg:     '#f0fdf4',
            border: '#86efac',
            icon:   '#16a34a',
            text:   '#15803d',
            bar:    '#22c55e',
            svg: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
        },
        error: {
            bg:     '#fff1f2',
            border: '#fca5a5',
            icon:   '#dc2626',
            text:   '#b91c1c',
            bar:    '#ef4444',
            svg: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
        },
        warning: {
            bg:     '#fffbeb',
            border: '#fcd34d',
            icon:   '#d97706',
            text:   '#92400e',
            bar:    '#f59e0b',
            svg: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        },
        info: {
            bg:     '#eff6ff',
            border: '#93c5fd',
            icon:   '#2563eb',
            text:   '#1e40af',
            bar:    '#3b82f6',
            svg: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
        },
    };

    /* ── dedup guard: same message shown within 300 ms ───────────── */
    const _recent = new Map();
    function isDuplicate(message, type) {
        const key = `${type}:${message}`;
        if (_recent.has(key)) return true;
        _recent.set(key, true);
        setTimeout(() => _recent.delete(key), 300);
        return false;
    }

    /* ── core show function ───────────────────────────────────────── */
    window.showToast = function showToast(message, type) {
        if (!message || typeof message !== 'string') return;
        message = message.trim();
        if (!message) return;

        type = (STYLE[type] ? type : 'info');
        if (isDuplicate(message, type)) return;

        const stack = document.getElementById('toast-stack');
        if (!stack) return;

        const s = STYLE[type];
        const duration = DURATION[type];

        /* outer wrapper — manages enter/exit animation */
        const wrapper = document.createElement('div');
        wrapper.style.cssText = [
            'pointer-events:auto',
            'transform:translateX(110%)',
            'opacity:0',
            'transition:transform .28s cubic-bezier(.22,1,.36,1), opacity .28s ease',
            'will-change:transform,opacity',
        ].join(';');

        /* card */
        const card = document.createElement('div');
        card.setAttribute('role', 'alert');
        card.style.cssText = [
            `background:${s.bg}`,
            `border:1.5px solid ${s.border}`,
            'border-radius:14px',
            'box-shadow:0 4px 24px rgba(0,0,0,.10),0 1px 4px rgba(0,0,0,.06)',
            'overflow:hidden',
            'position:relative',
        ].join(';');

        /* progress bar */
        const bar = document.createElement('div');
        bar.style.cssText = [
            'position:absolute',
            'bottom:0',
            'left:0',
            `background:${s.bar}`,
            'height:3px',
            `width:100%`,
            `transition:width ${duration}ms linear`,
        ].join(';');

        /* body row */
        const body = document.createElement('div');
        body.style.cssText = 'display:flex;align-items:flex-start;gap:10px;padding:13px 14px 16px;';

        /* icon */
        const iconWrap = document.createElement('div');
        iconWrap.style.cssText = [
            `color:${s.icon}`,
            'flex-shrink:0',
            'margin-top:1px',
            'line-height:0',
        ].join(';');
        iconWrap.innerHTML = s.svg;

        /* text */
        const textEl = document.createElement('div');
        textEl.style.cssText = [
            `color:${s.text}`,
            'font-size:13.5px',
            'font-weight:500',
            'line-height:1.45',
            'flex:1',
            'min-width:0',
            'word-break:break-word',
            'font-family:inherit',
        ].join(';');
        textEl.textContent = message;

        /* close button */
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Dismiss');
        closeBtn.style.cssText = [
            'flex-shrink:0',
            'border:none',
            'background:transparent',
            'padding:2px',
            'cursor:pointer',
            `color:${s.icon}`,
            'opacity:.5',
            'line-height:0',
            'margin-top:1px',
            'transition:opacity .15s',
        ].join(';');
        closeBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
        closeBtn.addEventListener('mouseenter', () => closeBtn.style.opacity = '1');
        closeBtn.addEventListener('mouseleave', () => closeBtn.style.opacity = '.5');

        body.appendChild(iconWrap);
        body.appendChild(textEl);
        body.appendChild(closeBtn);
        card.appendChild(body);
        card.appendChild(bar);
        wrapper.appendChild(card);
        stack.appendChild(wrapper);

        /* ── dismiss logic ── */
        let timer;
        let dismissed = false;

        const dismiss = () => {
            if (dismissed) return;
            dismissed = true;
            clearTimeout(timer);
            wrapper.style.transform = 'translateX(110%)';
            wrapper.style.opacity = '0';
            wrapper.addEventListener('transitionend', () => wrapper.remove(), { once: true });
        };

        closeBtn.addEventListener('click', dismiss);

        /* enter animation (next frame) */
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                wrapper.style.transform = 'translateX(0)';
                wrapper.style.opacity = '1';

                /* start progress bar shrink */
                requestAnimationFrame(() => {
                    bar.style.width = '0%';
                });
            });
        });

        timer = setTimeout(dismiss, duration);

        /* pause on hover */
        let remaining = duration;
        let hoverStart;
        wrapper.addEventListener('mouseenter', () => {
            if (dismissed) return;
            clearTimeout(timer);
            bar.style.transition = 'none';
            hoverStart = Date.now();
        });
        wrapper.addEventListener('mouseleave', () => {
            if (dismissed) return;
            remaining -= (Date.now() - hoverStart);
            remaining = Math.max(remaining, 400);
            /* resume bar */
            const pct = (remaining / duration) * 100;
            bar.style.width = `${pct}%`;
            requestAnimationFrame(() => {
                bar.style.transition = `width ${remaining}ms linear`;
                requestAnimationFrame(() => { bar.style.width = '0%'; });
            });
            timer = setTimeout(dismiss, remaining);
        });
    };

    /* ── helper: flash type detection ────────────────────────────── */
    function detectType(el) {
        const attr = (el.getAttribute('data-flash-type') || '').toLowerCase();
        if (attr === 'success') return 'success';
        if (attr === 'error')   return 'error';
        if (attr === 'warning') return 'warning';
        return 'info';
    }

    /* ── 1. Blade-rendered flash messages (traditional redirects) ── */
    function consumeBladeFlash() {
        document.querySelectorAll('[data-flash-message]').forEach((el) => {
            const text = el.innerText.trim();
            const type = detectType(el);
            if (text) window.showToast(text, type);
            /* hide the original inline element so it doesn't double-show */
            el.style.display = 'none';
        });
    }

    /* ── 2. Inertia SPA flash (flash prop on every page visit) ───── */
    function consumeInertiaFlash(props) {
        if (!props) return;
        const flash = props.flash || {};

        if (flash.status)  window.showToast(flash.status,  'success');
        if (flash.success) window.showToast(flash.success, 'success');
        if (flash.message) window.showToast(flash.message, 'info');
        if (flash.error)   window.showToast(flash.error,   'error');
        if (flash.warning) window.showToast(flash.warning, 'warning');
        if (flash.info)    window.showToast(flash.info,    'info');

        /* validation errors bag */
        const errors = props.errors || {};
        const msgs = Object.values(errors)
            .flatMap((v) => Array.isArray(v) ? v : [v])
            .filter((v) => typeof v === 'string' && v.trim());
        if (msgs.length) {
            window.showToast(msgs[0], 'error');
        }
    }

    /* ── boot ─────────────────────────────────────────────────────── */
    function boot() {
        /* Blade flash: fire on first load */
        consumeBladeFlash();

        /* Inertia: hook into router events */
        const hookInertia = () => {
            /* inertia:finish fires after every navigation */
            document.addEventListener('inertia:finish', (e) => {
                const page = e?.detail?.page || window?.__inertia_page || null;
                if (page) consumeInertiaFlash(page.props);
                /* also sweep any newly injected blade flash (partial reloads) */
                consumeBladeFlash();
            });

            /* Also catch inertia:success for some versions */
            document.addEventListener('inertia:success', (e) => {
                const page = e?.detail?.page || null;
                if (page) consumeInertiaFlash(page.props);
            });
        };

        hookInertia();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
