import '../bootstrap';
import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { formatDate, parseDate } from './utils/datetime';
import 'flatpickr/dist/flatpickr.min.css';
import { enhanceEasyDateInputsInDocument } from './utils/easyDateEnhancer';
import { getBreadcrumb, getPageTitle } from './utils/pageTitle';

const DISPLAY_DATE_PLACEHOLDER = 'DD-MM-YYYY';
const DEFAULT_APP_NAME = 'MyApptimatic';
const INITIAL_APP_NAME = (typeof document !== 'undefined' && document.title ? document.title : DEFAULT_APP_NAME).trim();
const DATETIME_TOKEN_REGEX = /\b(?:\d{1,2}[-/]\d{1,2}[-/]\d{2,4}|\d{4}-\d{2}-\d{2})(?:\s+\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)?)?\b|\b\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)\b/g;
const DATETIME_TOKEN_TEST_REGEX = /\b(?:\d{1,2}[-/]\d{1,2}[-/]\d{2,4}|\d{4}-\d{2}-\d{2})(?:\s+\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)?)?\b|\b\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM|am|pm)\b/;
const DATETIME_SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'OPTION', 'SELECT']);
const COMPONENT_TITLE_MAP = {};

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

const parsePathname = (pageUrl) => {
    const raw = String(pageUrl || '').trim();
    if (raw === '') {
        return window.location.pathname;
    }

    try {
        return new URL(raw, window.location.origin).pathname;
    } catch (error) {
        return raw.split('?')[0].split('#')[0] || window.location.pathname;
    }
};

const ensureBreadcrumbElement = (titleElement) => {
    const wrapper = titleElement?.parentElement;
    if (!wrapper) {
        return null;
    }

    let breadcrumbElement = wrapper.querySelector('[data-current-page-breadcrumb]');
    if (breadcrumbElement) {
        return breadcrumbElement;
    }

    breadcrumbElement = document.createElement('div');
    breadcrumbElement.setAttribute('data-current-page-breadcrumb', 'true');
    breadcrumbElement.className = 'mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500';
    titleElement.insertAdjacentElement('afterend', breadcrumbElement);
    return breadcrumbElement;
};

const renderBreadcrumb = (breadcrumbElement, items) => {
    if (!breadcrumbElement) {
        return;
    }

    const list = Array.isArray(items) ? items : [];
    breadcrumbElement.replaceChildren();
    if (list.length === 0) {
        return;
    }

    list.forEach((item, index) => {
        if (index > 0) {
            const divider = document.createElement('span');
            divider.className = 'text-slate-300';
            divider.textContent = '/';
            breadcrumbElement.appendChild(divider);
        }

        const safeLabel = String(item?.label || '').trim();
        if (!safeLabel) {
            return;
        }

        if (item?.href && index < list.length - 1) {
            const anchor = document.createElement('a');
            anchor.href = item.href;
            anchor.className = 'hover:text-teal-600';
            anchor.textContent = safeLabel;
            anchor.setAttribute('data-native', 'true');
            breadcrumbElement.appendChild(anchor);
            return;
        }

        const label = document.createElement('span');
        label.className = 'font-medium text-slate-600';
        label.textContent = safeLabel;
        breadcrumbElement.appendChild(label);
    });
};

const syncLayoutPageHeader = (title, breadcrumb) => {
    if (typeof document === 'undefined') {
        return;
    }

    const safeHeading = String(title || '').trim() || 'Overview';
    document.querySelectorAll('[data-current-page-title]').forEach((element) => {
        element.textContent = safeHeading;
        const breadcrumbElement = ensureBreadcrumbElement(element);
        renderBreadcrumb(breadcrumbElement, breadcrumb);
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
        const syncFromPagePayload = (pagePayload) => {
            if (!pagePayload || typeof pagePayload !== 'object') {
                return;
            }

            const pageProps = pagePayload?.props || {};
            const pathname = parsePathname(pagePayload?.url || pageProps?.page?.url || window.location.pathname);
            const routeName = pageProps?.page?.route_name || '';
            const portal = pageProps?.auth?.portal || '';
            const explicitTitle = COMPONENT_TITLE_MAP[pagePayload?.component] || '';
            const title = getPageTitle({
                component: pagePayload?.component,
                props: pageProps,
                routeName,
                pathname,
                explicitTitle,
            });
            const breadcrumb = getBreadcrumb({
                routeName,
                pathname,
                title,
                portal,
            });

            syncLayoutPageHeader(title, breadcrumb);
            document.title = normalizeBrowserTitle(title, appNameFromPage);
        };

        syncFromPagePayload(props?.initialPage);

        const handleNavigate = (event) => {
            const pagePayload = event?.detail?.page || event?.detail?.visit?.page || null;
            syncFromPagePayload(pagePayload);
        };

        const handleSuccess = (event) => {
            const pagePayload = event?.detail?.page || event?.detail?.visit?.page || null;
            syncFromPagePayload(pagePayload);
        };

        document.addEventListener('inertia:navigate', handleNavigate);
        document.addEventListener('inertia:success', handleSuccess);

        return () => {
            document.removeEventListener('inertia:navigate', handleNavigate);
            document.removeEventListener('inertia:success', handleSuccess);
        };
    }, [appNameFromPage, props?.initialPage]);

    useEffect(() => {
        normalizeDateInputsInDocument();
        enhanceEasyDateInputsInDocument();

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

            enhanceEasyDateInputsInDocument();
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
                enhanceEasyDateInputsInDocument();
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

    return (
        <>
            <App {...props} />
        </>
    );
}

createInertiaApp({
    title: (title) => normalizeBrowserTitle(title, INITIAL_APP_NAME),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        const moduleDefault = page.default;
        const explicitTitle = moduleDefault?.title || page.pageTitle || moduleDefault?.pageTitle || '';
        if (typeof explicitTitle === 'string' && explicitTitle.trim() !== '') {
            COMPONENT_TITLE_MAP[name] = explicitTitle.trim();
        }

        return moduleDefault;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<DateInputRuntimeAdapter App={App} props={props} />);
    },
});
