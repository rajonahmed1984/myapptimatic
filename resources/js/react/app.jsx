import '../bootstrap';
import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { formatDate, parseDate } from './utils/datetime';

const DISPLAY_DATE_PLACEHOLDER = 'DD-MM-YYYY';
const DEFAULT_APP_NAME = 'MyApptimatic';
const INITIAL_APP_NAME = (typeof document !== 'undefined' && document.title ? document.title : DEFAULT_APP_NAME).trim();
const DATETIME_TOKEN_REGEX = /\b(?:\d{1,2}[-/]\d{1,2}[-/]\d{2,4}|\d{4}-\d{2}-\d{2})(?:\s+\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)?)?\b|\b\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)\b/g;
const DATETIME_TOKEN_TEST_REGEX = /\b(?:\d{1,2}[-/]\d{1,2}[-/]\d{2,4}|\d{4}-\d{2}-\d{2})(?:\s+\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)?)?\b|\b\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)\b/;
const DATETIME_SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'OPTION', 'SELECT']);

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

const textNodeParentIsValid = (node) => {
    const parent = node.parentElement;
    if (!parent) {
        return false;
    }

    if (DATETIME_SKIP_TAGS.has(parent.tagName)) {
        return false;
    }

    if (parent.closest('.datetime-nowrap')) {
        return false;
    }

    return true;
};

const wrapDateTimeTokensInNode = (node) => {
    if (node.nodeType !== Node.TEXT_NODE) {
        return false;
    }

    if (!textNodeParentIsValid(node)) {
        return false;
    }

    const text = node.textContent ?? '';
    if (!DATETIME_TOKEN_TEST_REGEX.test(text)) {
        return false;
    }

    DATETIME_TOKEN_REGEX.lastIndex = 0;
    const matches = [...text.matchAll(DATETIME_TOKEN_REGEX)];
    if (matches.length === 0) {
        return false;
    }

    const fragment = document.createDocumentFragment();
    let cursor = 0;

    matches.forEach((match) => {
        const token = match[0];
        const start = match.index ?? 0;
        const end = start + token.length;

        if (start > cursor) {
            fragment.append(document.createTextNode(text.slice(cursor, start)));
        }

        const span = document.createElement('span');
        span.className = 'datetime-nowrap tabular-nums';
        span.textContent = token;
        span.title = token;
        fragment.append(span);

        cursor = end;
    });

    if (cursor < text.length) {
        fragment.append(document.createTextNode(text.slice(cursor)));
    }

    node.parentNode?.replaceChild(fragment, node);
    return true;
};

const applyDateTimeNoWrapInDocument = (root = document.body) => {
    if (!root) {
        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let current = walker.nextNode();

    while (current) {
        nodes.push(current);
        current = walker.nextNode();
    }

    nodes.forEach((node) => {
        wrapDateTimeTokensInNode(node);
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
        applyDateTimeNoWrapInDocument();

        let frame = null;
        const scheduleApply = () => {
            if (frame !== null) {
                return;
            }

            frame = window.requestAnimationFrame(() => {
                frame = null;
                applyDateTimeNoWrapInDocument();
            });
        };

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type === 'characterData') {
                    scheduleApply();
                    break;
                }

                if (mutation.addedNodes.length > 0) {
                    scheduleApply();
                    break;
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
        });

        return () => {
            observer.disconnect();
            if (frame !== null) {
                window.cancelAnimationFrame(frame);
            }
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
