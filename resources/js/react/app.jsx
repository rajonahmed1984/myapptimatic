import '../bootstrap';
import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { formatDate, parseDate } from './utils/datetime';

const DISPLAY_DATE_PLACEHOLDER = 'DD-MM-YYYY';
const DEFAULT_APP_NAME = 'MyApptimatic';
const INITIAL_APP_NAME = (typeof document !== 'undefined' && document.title ? document.title : DEFAULT_APP_NAME).trim();

const normalizeDisplayDateValue = (value) => {
    if (typeof value !== 'string') {
        return value;
    }

    const trimmed = value.trim();
    if (trimmed === '') {
        return '';
    }

    const parsed = parseDate(trimmed);
    if (!parsed) {
        return value;
    }

    return formatDate(parsed, value);
};

const normalizeDateInputsInDocument = () => {
    const inputs = document.querySelectorAll(`input[placeholder="${DISPLAY_DATE_PLACEHOLDER}"]`);
    inputs.forEach((input) => {
        const normalized = normalizeDisplayDateValue(input.value);
        if (normalized !== input.value) {
            input.value = normalized;
        }
    });
};

const escapeRegex = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const normalizeBrowserTitle = (title, appName) => {
    const safeAppName = String(appName || DEFAULT_APP_NAME).trim();
    let safeTitle = String(title || '').trim();

    if (safeTitle !== '') {
        safeTitle = safeTitle.replace(/\s+/g, ' ').trim();
    }

    if (safeTitle === '') {
        return safeAppName;
    }

    const lowerTitle = safeTitle.toLowerCase();
    const lowerApp = safeAppName.toLowerCase();
    if (
        lowerTitle === lowerApp ||
        lowerTitle.endsWith(`| ${lowerApp}`) ||
        lowerTitle.endsWith(`- ${lowerApp}`) ||
        lowerTitle.endsWith(`\u2014 ${lowerApp}`)
    ) {
        return safeTitle;
    }

    return `${safeTitle} | ${safeAppName}`;
};

const titleCaseWords = (value) => value.replace(/\w\S*/g, (word) => {
    const lower = word.toLowerCase();
    return lower.charAt(0).toUpperCase() + lower.slice(1);
});

const headingFromPathname = (pathname) => {
    const normalized = String(pathname || '')
        .split('?')[0]
        .split('#')[0]
        .replace(/\/+/g, '/')
        .replace(/\/$/, '')
        .trim();

    if (normalized === '' || normalized === '/') {
        return 'Overview';
    }

    const parts = normalized.split('/').filter(Boolean);
    const ignored = new Set(['admin', 'client', 'employee', 'rep', 'support', 'portal']);
    const meaningful = parts.filter((part) => !ignored.has(part.toLowerCase()));
    const source = meaningful.length > 0 ? meaningful[meaningful.length - 1] : parts[parts.length - 1];

    if (!source) {
        return 'Overview';
    }

    if (/^\d+$/.test(source) && meaningful.length > 1) {
        return titleCaseWords(meaningful[meaningful.length - 2].replace(/[_-]+/g, ' '));
    }

    return titleCaseWords(source.replace(/[_-]+/g, ' '));
};

const extractHeadingFromTitle = (documentTitle, fallbackTitle, appName) => {
    const safeFallback = String(fallbackTitle || '').trim();
    const safeTitle = String(documentTitle || '').trim();
    const safeAppName = String(appName || '').trim();

    if (/^\(\d+\)\s+Unread Chat$/i.test(safeTitle)) {
        return safeFallback || 'Overview';
    }

    let heading = safeTitle;
    if (safeAppName !== '') {
        const prefixPattern = new RegExp(`^${escapeRegex(safeAppName)}\\s*[-|:\\u2014]?\\s*`, 'i');
        const suffixPattern = new RegExp(`\\s*(?:\\||-|\\u2014)\\s*${escapeRegex(safeAppName)}$`, 'i');
        heading = heading.replace(prefixPattern, '').trim();
        heading = heading.replace(suffixPattern, '').trim();
    }

    if (heading === '') {
        return safeFallback || 'Overview';
    }

    heading = heading
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (heading === '') {
        return safeFallback || 'Overview';
    }

    if (/^[a-z0-9\s]+$/.test(heading)) {
        heading = titleCaseWords(heading);
    }

    return heading;
};

const syncLayoutPageHeading = (heading) => {
    if (typeof document === 'undefined') {
        return;
    }

    const safeHeading = String(heading || '').trim() || 'Overview';
    document.querySelectorAll('[data-current-page-title]').forEach((element) => {
        element.textContent = safeHeading;
    });
};

function DateInputRuntimeAdapter({ App, props }) {
    const appNameFromPage = props?.initialPage?.props?.app?.name || INITIAL_APP_NAME;

    useEffect(() => {
        normalizeDateInputsInDocument();

        const handleFocusOut = (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }
            if (target.placeholder !== DISPLAY_DATE_PLACEHOLDER) {
                return;
            }

            const normalized = normalizeDisplayDateValue(target.value);
            if (normalized !== target.value) {
                target.value = normalized;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        document.addEventListener('focusout', handleFocusOut, true);

        return () => {
            document.removeEventListener('focusout', handleFocusOut, true);
        };
    }, []);

    useEffect(() => {
        let lastHeading =
            String(document.querySelector('[data-current-page-title]')?.textContent || '').trim() ||
            'Overview';

        const syncFromDocumentTitle = () => {
            const currentTitle = document.title;
            if (/^\(\d+\)\s+Unread Chat$/i.test(String(currentTitle || '').trim())) {
                syncLayoutPageHeading(lastHeading);
                return;
            }

            const resolved = extractHeadingFromTitle(currentTitle, '', appNameFromPage);
            const fallbackHeading = headingFromPathname(window.location.pathname);
            const nextHeading = String(resolved || '').trim();
            lastHeading = nextHeading && nextHeading.toLowerCase() !== 'overview'
                ? nextHeading
                : (fallbackHeading || lastHeading || 'Overview');
            syncLayoutPageHeading(lastHeading);
        };

        const raf = window.requestAnimationFrame(() => {
            syncFromDocumentTitle();
        });

        const titleEl = document.querySelector('title');
        const observer = titleEl
            ? new MutationObserver(() => {
                syncFromDocumentTitle();
            })
            : null;

        if (titleEl && observer) {
            observer.observe(titleEl, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        }

        return () => {
            window.cancelAnimationFrame(raf);
            observer?.disconnect();
        };
    }, [appNameFromPage]);

    return <App {...props} />;
}

createInertiaApp({
    title: (title) => normalizeBrowserTitle(title, INITIAL_APP_NAME),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page.default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<DateInputRuntimeAdapter App={App} props={props} />);
    },
});
