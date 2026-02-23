const CONTENT_SELECTOR = '#appContent';
const SIDEBAR_SELECTOR = '#adminSidebar, #clientSidebar, #repSidebar, #supportSidebar';
const PAGE_TITLE_SELECTOR = '[data-current-page-title]';

const AJAX_MODAL_ID = 'ajaxModal';
const AJAX_MODAL_BODY_ID = 'ajaxModalBody';
const AJAX_MODAL_TITLE_ID = 'ajaxModalTitle';
const AJAX_TOAST_HOST_ID = 'ajaxToastHost';
const AJAX_TOP_PROGRESS_ID = 'ajaxTopProgress';

const LARGE_UPLOAD_LIMIT_BYTES = 2 * 1024 * 1024;
const LIVE_FILTER_DEBOUNCE_MS = 320;

const CRITICAL_ROUTE_PATTERNS = [
    /(^|\/)__ui(\/|$)/i,
    /(^|\/)admin\/expenses\/recurring(\/|$)/i,
    /(^|\/)admin\/automation-status(\/|$)/i,
    /(^|\/)admin\/users\/activity-summary(\/|$)/i,
    /(^|\/)admin\/logs(\/|$)/i,
    /(^|\/)admin\/chats(\/|$)/i,
    /(^|\/)admin\/payment-gateways(\/|$)/i,
    /(^|\/)admin\/accounting(\/?$|\/ledger(\/|$))/i,
    /(^|\/)admin\/commission-payouts(\/|$)/i,
    /(^|\/)admin\/finance\/reports(\/|$)/i,
    /(^|\/)admin\/finance\/payment-methods(\/|$)/i,
    /(^|\/)admin\/support-tickets(\/|$)/i,
    /(^|\/)(login|logout|register)(\/|$)/i,
    /(^|\/)(password|forgot-password|reset-password)(\/|$)/i,
    /(^|\/)(two-factor|2fa)(\/|$)/i,
    /(^|\/)(session|impersonate)(\/|$)/i,
    /(^|\/)(payment|payments|checkout|gateway|manual-payment)(\/|$)/i,
    /(^|\/)(download|export|backup)(\/|$)/i,
];

const CRITICAL_MUTATION_PATTERNS = [
    /(^|\/)(bulk|mass|truncate|destroy-all|wipe)(\/|$)/i,
];

const state = {
    initialized: false,
    content: null,
    sidebar: null,
    activeNavigationController: null,
    activeModalController: null,
    activeActionControllers: new WeakMap(),
    activeFormControllers: new WeakMap(),
    liveFilterTimers: new WeakMap(),
    progressTimer: null,
    progressValue: 0,
    scriptHashes: new Set(),
    scrollByHistoryKey: new Map(),
    shownToastKeys: new Set(),
    originalAlert: null,
};

const toUrl = (value) => {
    try {
        return new URL(value, window.location.origin);
    } catch (_error) {
        return null;
    }
};

const isSameOriginUrl = (value) => {
    const url = toUrl(value);
    return Boolean(url && url.origin === window.location.origin);
};

const isHttpLike = (url) => url.protocol === 'http:' || url.protocol === 'https:';

const hasModifierKeys = (event) => event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const normalizePath = (path) => {
    if (!path) {
        return '/';
    }

    const value = path.endsWith('/') && path !== '/' ? path.slice(0, -1) : path;
    return value || '/';
};

const looksLikeDocument = (html) => /<html[\s>]|<!doctype/i.test(String(html || ''));

const isSessionFailureStatus = (status) => status === 401 || status === 419;

const isNativeOptOut = (element) => {
    if (!element) {
        return false;
    }

    if (element.closest('[data-native="true"]')) {
        return true;
    }

    return element.dataset?.native === 'true' || element.dataset?.noAjax === 'true' || element.dataset?.ajaxNav === 'off';
};

const hasLikelyDownloadIntent = (url) => {
    const pathname = url.pathname.toLowerCase();
    const search = url.search.toLowerCase();

    if (/\.(pdf|zip|csv|xlsx|xls|doc|docx|json|xml)$/i.test(pathname)) {
        return true;
    }

    return search.includes('download=') || search.includes('export=') || search.includes('format=csv') || search.includes('format=xlsx') || search.includes('format=pdf');
};

const isCriticalUrl = (url, method = 'GET') => {
    const pathname = url.pathname.toLowerCase();
    const search = url.search.toLowerCase();
    const upperMethod = String(method || 'GET').toUpperCase();

    if (hasLikelyDownloadIntent(url)) {
        return true;
    }

    if (CRITICAL_ROUTE_PATTERNS.some((pattern) => pattern.test(pathname))) {
        return true;
    }

    if (search.includes('download=') || search.includes('export=')) {
        return true;
    }

    if (upperMethod !== 'GET' && CRITICAL_MUTATION_PATTERNS.some((pattern) => pattern.test(pathname))) {
        return true;
    }

    return false;
};

const pathMatchScore = (candidatePath, currentPath) => {
    const candidate = normalizePath(candidatePath);
    const current = normalizePath(currentPath);

    if (candidate === current) {
        return candidate.length + 1000;
    }

    if (candidate !== '/' && current.startsWith(`${candidate}/`)) {
        return candidate.length;
    }

    if (candidate === '/' && current === '/') {
        return 1;
    }

    return -1;
};

const updateSidebarActiveState = (sidebar, currentUrl) => {
    if (!sidebar) {
        return;
    }

    const allLinks = Array.from(sidebar.querySelectorAll('a[href]'));
    const currentPath = normalizePath(currentUrl.pathname);
    const isSubmenuLink = (link) => !link.classList.contains('nav-link') && Boolean(link.closest('.ml-8'));

    allLinks.forEach((link) => {
        link.classList.remove('nav-link-active', 'ajax-nav-active');
        // Submenu active state is server-rendered via utility classes; clear stale ones on AJAX nav.
        if (isSubmenuLink(link)) {
            link.classList.remove('text-teal-300');
            if (!link.classList.contains('hover:text-slate-200')) {
                link.classList.add('hover:text-slate-200');
            }
        }
    });

    let bestAnyLink = null;
    let bestAnyScore = -1;
    let bestNavLink = null;
    let bestNavScore = -1;

    allLinks.forEach((link) => {
        const url = toUrl(link.href);
        if (!url || url.origin !== window.location.origin) {
            return;
        }

        const score = pathMatchScore(url.pathname, currentPath);
        if (score > bestAnyScore) {
            bestAnyScore = score;
            bestAnyLink = link;
        }

        if (link.classList.contains('nav-link') && score > bestNavScore) {
            bestNavScore = score;
            bestNavLink = link;
        }
    });

    if (bestAnyLink) {
        bestAnyLink.classList.add('ajax-nav-active');
        if (isSubmenuLink(bestAnyLink)) {
            bestAnyLink.classList.add('text-teal-300');
            bestAnyLink.classList.remove('hover:text-slate-200');
        }
    }

    if (bestNavLink) {
        bestNavLink.classList.add('nav-link-active');
    }
};

const ensureGlobalStyles = () => {
    if (document.getElementById('ajax-engine-style')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'ajax-engine-style';
    style.textContent = `
#${AJAX_TOP_PROGRESS_ID}{
    position:fixed;top:0;left:0;height:3px;width:0;z-index:120;
    background:linear-gradient(90deg,#14b8a6,#0ea5e9);opacity:0;transition:width .18s ease,opacity .22s ease;
}
#${AJAX_TOP_PROGRESS_ID}.is-active{opacity:1}
.global-toast-host{
    position:fixed;top:1rem;right:1rem;z-index:95;display:flex;flex-direction:column;gap:.5rem;
    pointer-events:none;max-width:min(92vw,28rem);
}
.global-toast{
    pointer-events:auto;border-radius:.75rem;border:1px solid rgb(203 213 225 / .9);
    background:#fff;color:#0f172a;box-shadow:0 14px 30px rgb(15 23 42 / .12);
    padding:.625rem .875rem;font-size:.8125rem;line-height:1.15rem;transform:translateY(-6px);
    opacity:0;transition:transform .2s ease,opacity .2s ease;
}
.global-toast.is-visible{transform:translateY(0);opacity:1}
.global-toast--success{border-color:rgb(94 234 212 / .9);background:rgb(240 253 250);color:rgb(15 118 110)}
.global-toast--error{border-color:rgb(252 165 165 / .9);background:rgb(254 242 242);color:rgb(185 28 28)}
.global-toast--warning{border-color:rgb(253 224 71 / .9);background:rgb(254 252 232);color:rgb(161 98 7)}
.global-toast--info{border-color:rgb(125 211 252 / .9);background:rgb(240 249 255);color:rgb(3 105 161)}
.ajax-submit-loading{opacity:.7;pointer-events:none}
`;
    document.head.appendChild(style);
};

const ensureProgressBar = () => {
    let bar = document.getElementById(AJAX_TOP_PROGRESS_ID);
    if (bar) {
        return bar;
    }

    bar = document.createElement('div');
    bar.id = AJAX_TOP_PROGRESS_ID;
    document.body.appendChild(bar);
    return bar;
};

const startProgress = () => {
    const bar = ensureProgressBar();
    if (state.progressTimer) {
        window.clearInterval(state.progressTimer);
    }

    state.progressValue = 14;
    bar.classList.add('is-active');
    bar.style.width = `${state.progressValue}%`;

    state.progressTimer = window.setInterval(() => {
        state.progressValue = Math.min(state.progressValue + (Math.random() * 9), 88);
        bar.style.width = `${state.progressValue}%`;
    }, 180);
};

const finishProgress = () => {
    const bar = ensureProgressBar();
    if (state.progressTimer) {
        window.clearInterval(state.progressTimer);
        state.progressTimer = null;
    }

    state.progressValue = 100;
    bar.style.width = '100%';

    window.setTimeout(() => {
        bar.classList.remove('is-active');
        bar.style.width = '0';
    }, 220);
};

const ensureToastHost = () => {
    const existingHost = document.getElementById(AJAX_TOAST_HOST_ID) || document.getElementById('globalToastHost');
    if (existingHost) {
        existingHost.classList.add('global-toast-host');
        existingHost.setAttribute('aria-live', 'polite');
        existingHost.setAttribute('aria-atomic', 'false');
        return existingHost;
    }

    const host = document.createElement('div');
    host.id = 'globalToastHost';
    host.className = 'global-toast-host';
    host.setAttribute('aria-live', 'polite');
    host.setAttribute('aria-atomic', 'false');
    document.body.appendChild(host);
    return host;
};

const showToast = (message, type = 'info', timeoutMs = 3200) => {
    const safeMessage = String(message || '').trim();
    if (!safeMessage) {
        return;
    }

    const host = ensureToastHost();
    const toast = document.createElement('div');
    const normalizedType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';

    toast.className = `global-toast global-toast--${normalizedType}`;
    toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
    toast.textContent = safeMessage;
    host.appendChild(toast);

    window.requestAnimationFrame(() => {
        toast.classList.add('is-visible');
    });

    const removeToast = () => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => {
            toast.remove();
        }, 200);
    };

    window.setTimeout(removeToast, Math.max(1200, Number(timeoutMs) || 3200));
};

const consumeServerFlashMessages = (root = null) => {
    const scope = root || state.content || document;
    if (!scope) {
        return;
    }

    const uniqueNodes = new Set([
        ...scope.querySelectorAll('[data-flash-message]'),
        ...scope.querySelectorAll('[data-flash-auto="true"]'),
    ]);

    uniqueNodes.forEach((node) => {
        if (node.dataset.flashConsumed === 'true') {
            return;
        }

        const text = String(node.textContent || '').trim();
        if (!text) {
            return;
        }

        let type = (node.dataset.flashType || '').trim().toLowerCase();
        if (!['success', 'error', 'warning', 'info'].includes(type)) {
            type = 'info';
        }

        if (type === 'info' && (node.classList.contains('border-teal-200') || node.classList.contains('border-emerald-400/40'))) {
            type = 'success';
        } else if (node.classList.contains('border-red-200')) {
            type = 'error';
        } else if (node.classList.contains('border-red-500/40')) {
            type = 'error';
        } else if (node.classList.contains('border-amber-200')) {
            type = 'warning';
        }

        const toastKey = `${type}:${text}`;
        if (!state.shownToastKeys.has(toastKey)) {
            state.shownToastKeys.add(toastKey);
            showToast(text, type);
        }

        node.dataset.flashConsumed = 'true';
        if (node.dataset.flashPersist !== 'true') {
            node.classList.add('hidden');
        }
    });
};
const getModalElements = () => ({
    modal: document.getElementById(AJAX_MODAL_ID),
    body: document.getElementById(AJAX_MODAL_BODY_ID),
    title: document.getElementById(AJAX_MODAL_TITLE_ID),
});

const openModal = (titleText, html) => {
    const { modal, body, title } = getModalElements();
    if (!modal || !body) {
        return;
    }

    if (title) {
        title.textContent = titleText || 'Form';
    }

    body.innerHTML = html || '';
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');

    runEmbeddedScripts(body, '');
};

const closeModal = () => {
    const { modal, body } = getModalElements();
    if (!modal || !body) {
        return;
    }

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    body.innerHTML = '';
    document.body.classList.remove('overflow-hidden');
};

const clearFormErrors = (form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback[data-ajax-error="true"]').forEach((node) => node.remove());
};

const cssEscapeSafe = (value) => {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }

    return String(value || '').replace(/"/g, '\\"');
};

const findFieldByName = (form, rawName) => {
    const candidates = [rawName];

    if (rawName.includes('.')) {
        const dotConverted = rawName.split('.').reduce((carry, part, index) => {
            if (index === 0) {
                return part;
            }

            return `${carry}[${part}]`;
        }, '');

        candidates.push(dotConverted, `${dotConverted}[]`);
    } else {
        candidates.push(`${rawName}[]`);
    }

    for (const candidate of candidates) {
        const escaped = cssEscapeSafe(candidate);
        const field = form.querySelector(`[name="${escaped}"]`);
        if (field) {
            return field;
        }
    }

    return null;
};

const applyValidationErrors = (form, errors) => {
    if (!(form instanceof HTMLFormElement) || !errors || typeof errors !== 'object') {
        return;
    }

    clearFormErrors(form);

    Object.entries(errors).forEach(([fieldName, messages]) => {
        const field = findFieldByName(form, fieldName);
        if (!field) {
            return;
        }

        field.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.dataset.ajaxError = 'true';
        feedback.textContent = Array.isArray(messages) ? String(messages[0] || 'Invalid value') : String(messages || 'Invalid value');

        const anchor = field.closest('.field-group') || field;
        anchor.insertAdjacentElement('afterend', feedback);
    });
};

const patchDomContentLoadedHandlers = () => {
    const originalAddEventListener = document.addEventListener.bind(document);

    document.addEventListener = (type, listener, options) => {
        if (type === 'DOMContentLoaded') {
            const event = new Event('DOMContentLoaded');

            if (typeof listener === 'function') {
                listener.call(document, event);
                return;
            }

            if (listener && typeof listener.handleEvent === 'function') {
                listener.handleEvent(event);
                return;
            }
        }

        originalAddEventListener(type, listener, options);
    };

    return () => {
        document.addEventListener = originalAddEventListener;
    };
};

const hashString = (value) => {
    const input = String(value || '');
    let hash = 0;

    for (let index = 0; index < input.length; index += 1) {
        hash = ((hash << 5) - hash) + input.charCodeAt(index);
        hash |= 0;
    }

    return (hash >>> 0).toString(36);
};

const scriptRuntimeKey = (script, pageKey = '', index = 0) => {
    const insidePartialStack = Boolean(script.closest('[data-partial-scripts]'));
    const explicitKey = (script.getAttribute('data-script-key') || '').trim();
    if (explicitKey !== '') {
        return `key:${explicitKey}`;
    }

    if (script.src) {
        const srcUrl = toUrl(script.src);
        return `src:${srcUrl ? srcUrl.href : script.src}`;
    }

    if (!insidePartialStack) {
        return null;
    }

    const inlineSource = (script.textContent || '').trim();
    if (inlineSource === '') {
        return `inline-empty:${pageKey}:${index}`;
    }

    return `inline:${pageKey}:${hashString(inlineSource)}`;
};

const runEmbeddedScripts = (root, pageKey = '') => {
    if (!root) {
        return;
    }

    const scripts = Array.from(root.querySelectorAll('script'));
    scripts.forEach((script, index) => {
        const runtimeKey = scriptRuntimeKey(script, pageKey, index);
        if (runtimeKey && state.scriptHashes.has(runtimeKey)) {
            script.remove();
            return;
        }

        const replacement = document.createElement('script');
        Array.from(script.attributes).forEach((attribute) => {
            replacement.setAttribute(attribute.name, attribute.value);
        });

        if (script.src) {
            replacement.src = script.src;
        } else {
            replacement.textContent = script.textContent || '';
        }

        const restore = patchDomContentLoadedHandlers();
        try {
            script.replaceWith(replacement);
        } finally {
            restore();
        }

        if (runtimeKey) {
            state.scriptHashes.add(runtimeKey);
        }
    });
};

const parseDocumentPayload = (html, fallbackPageKey = '') => {
    if (!html || !looksLikeDocument(html)) {
        return null;
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const contentNode = doc.querySelector(CONTENT_SELECTOR);
    if (!contentNode) {
        return null;
    }

    let inner = contentNode.innerHTML || '';
    const pageScriptStack = doc.querySelector('#pageScriptStack');
    if (pageScriptStack && String(pageScriptStack.innerHTML || '').trim() !== '') {
        inner += `\n<div data-partial-scripts hidden>\n${pageScriptStack.innerHTML}\n</div>`;
    }

    return {
        html: inner,
        title: contentNode.getAttribute('data-page-title') || doc.title || '',
        heading: contentNode.getAttribute('data-page-heading') || '',
        pageKey: contentNode.getAttribute('data-page-key') || fallbackPageKey || '',
    };
};

const parseHtmlPayloadFromResponse = async (response) => {
    const text = await response.text();
    const headerTitle = (response.headers.get('X-Page-Title') || '').trim();
    const headerHeading = (response.headers.get('X-Page-Heading') || '').trim();
    const headerPageKey = (response.headers.get('X-Page-Key') || '').trim();
    const isPartialResponse = response.headers.get('X-Partial-Response') === 'true';

    if (isPartialResponse && !looksLikeDocument(text)) {
        return {
            html: text,
            title: headerTitle,
            heading: headerHeading,
            pageKey: headerPageKey,
        };
    }

    const parsed = parseDocumentPayload(text, headerPageKey);
    if (!parsed) {
        return null;
    }

    return {
        html: parsed.html,
        title: parsed.title || headerTitle,
        heading: parsed.heading || headerHeading,
        pageKey: parsed.pageKey || headerPageKey,
    };
};

const runPageInitializers = (pageKey = '') => {
    const detail = {
        pageKey: pageKey || '',
        url: window.location.href,
        content: state.content,
    };

    if (typeof window.bindInvoiceItems === 'function') {
        window.bindInvoiceItems(state.content);
    }

    if (window.PageInit && pageKey && typeof window.PageInit[pageKey] === 'function') {
        window.PageInit[pageKey](detail, state.content);
    }

    document.dispatchEvent(new CustomEvent('ajax-nav:loaded', { detail }));
    document.dispatchEvent(new CustomEvent('ajax:nav:loaded', { detail }));
    document.dispatchEvent(new CustomEvent('ajax:content:loaded', { detail }));
};

const applyContentPayload = (payload) => {
    if (!state.content || !payload || typeof payload.html !== 'string') {
        throw new Error('Invalid content payload');
    }

    state.content.innerHTML = payload.html;

    if (payload.title) {
        document.title = payload.title;
        state.content.dataset.pageTitle = payload.title;
    }

    if (payload.heading) {
        state.content.dataset.pageHeading = payload.heading;
        document.querySelectorAll(PAGE_TITLE_SELECTOR).forEach((node) => {
            node.textContent = payload.heading;
        });
    }

    if (payload.pageKey) {
        state.content.dataset.pageKey = payload.pageKey;
    }

    runEmbeddedScripts(state.content, payload.pageKey || '');
    runPageInitializers(payload.pageKey || '');
    consumeServerFlashMessages(state.content);
};
const storeCurrentScroll = () => {
    const historyState = history.state;
    if (!historyState || historyState.ajaxHybrid !== true || !historyState.key) {
        return;
    }

    const scroll = { x: window.scrollX, y: window.scrollY };
    state.scrollByHistoryKey.set(historyState.key, scroll);
    history.replaceState({ ...historyState, scrollX: scroll.x, scrollY: scroll.y }, '', window.location.href);
};

const restoreScrollForKey = (key) => {
    if (!key) {
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        return;
    }

    const historyState = history.state || {};
    const mapped = state.scrollByHistoryKey.get(key);
    const x = mapped?.x ?? historyState.scrollX ?? 0;
    const y = mapped?.y ?? historyState.scrollY ?? 0;
    window.scrollTo({ top: y, left: x, behavior: 'auto' });
};

const nextHistoryKey = () => `h_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;

const ensureHistoryState = () => {
    if (history.state && history.state.ajaxHybrid === true) {
        return;
    }

    const key = nextHistoryKey();
    history.replaceState({ ajaxHybrid: true, key, url: window.location.href, scrollX: window.scrollX, scrollY: window.scrollY }, '', window.location.href);
    state.scrollByHistoryKey.set(key, { x: window.scrollX, y: window.scrollY });
};

const setFormBusyState = (form, isBusy) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.dataset.ajaxBusy = isBusy ? 'true' : 'false';
    const submitControls = form.querySelectorAll('button[type="submit"], input[type="submit"]');

    submitControls.forEach((control) => {
        const button = control;
        if (isBusy) {
            button.dataset.wasDisabled = button.disabled ? 'true' : 'false';
            button.disabled = true;
            button.classList.add('ajax-submit-loading');
        } else {
            const wasDisabled = button.dataset.wasDisabled === 'true';
            button.disabled = wasDisabled;
            button.classList.remove('ajax-submit-loading');
            delete button.dataset.wasDisabled;
        }
    });
};

const fallbackToNative = (url, replace = false) => {
    if (!url) {
        window.location.reload();
        return;
    }

    if (replace) {
        window.location.replace(url);
        return;
    }

    window.location.assign(url);
};

const handleSessionFailure = (response, fallbackUrl = '') => {
    showToast('Session expired. Reloading...', 'warning');
    if (response?.url) {
        window.location.assign(response.url);
        return;
    }

    fallbackToNative(fallbackUrl || '/login');
};

const navigate = async (urlLike, options = {}) => {
    const url = toUrl(urlLike);
    if (!url || !isHttpLike(url) || url.origin !== window.location.origin) {
        fallbackToNative(String(urlLike || window.location.href));
        return;
    }

    const historyMode = options.historyMode || 'push';
    const historyKey = options.historyKey || nextHistoryKey();

    if (state.activeNavigationController) {
        state.activeNavigationController.abort();
    }

    storeCurrentScroll();
    startProgress();

    const controller = new AbortController();
    state.activeNavigationController = controller;

    try {
        const response = await fetch(url.href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'text/html,application/xhtml+xml,*/*;q=0.9',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Partial': 'true',
            },
        });

        if (isSessionFailureStatus(response.status)) {
            handleSessionFailure(response, url.href);
            return;
        }

        if (!response.ok) {
            throw new Error(`Navigation failed with status ${response.status}`);
        }

        const payload = await parseHtmlPayloadFromResponse(response);
        if (!payload) {
            throw new Error('Missing app content payload');
        }

        applyContentPayload(payload);
        updateSidebarActiveState(state.sidebar, url);

        if (historyMode === 'push') {
            history.pushState({ ajaxHybrid: true, key: historyKey, url: url.href }, '', url.href);
            window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        } else if (historyMode === 'replace') {
            history.replaceState({ ajaxHybrid: true, key: historyKey, url: url.href }, '', url.href);
            window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        } else if (historyMode === 'pop') {
            history.replaceState({ ...(history.state || {}), ajaxHybrid: true, key: historyKey, url: url.href }, '', url.href);
            restoreScrollForKey(historyKey);
        }
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        fallbackToNative(url.href, historyMode === 'replace');
    } finally {
        if (state.activeNavigationController === controller) {
            state.activeNavigationController = null;
        }
        finishProgress();
    }
};

const shouldAjaxLink = (link, event) => {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    if (event && (event.defaultPrevented || event.button !== 0 || hasModifierKeys(event))) {
        return false;
    }

    if (isNativeOptOut(link)) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download')) {
        return false;
    }

    const href = (link.getAttribute('href') || '').trim();
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
        return false;
    }

    const url = toUrl(link.href);
    if (!url || !isHttpLike(url) || url.origin !== window.location.origin) {
        return false;
    }

    if (isCriticalUrl(url, 'GET')) {
        return false;
    }

    if (link.closest(`#${AJAX_MODAL_ID}`)) {
        return false;
    }

    return true;
};

const hasLargeUpload = (form) => {
    const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
    let total = 0;

    for (const input of fileInputs) {
        const files = Array.from(input.files || []);
        for (const file of files) {
            total += Number(file.size || 0);
        }
    }

    return total > LARGE_UPLOAD_LIMIT_BYTES;
};

const shouldAjaxForm = (form) => {
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }

    if (isNativeOptOut(form)) {
        return false;
    }

    if (form.dataset.ajaxForm === 'false') {
        return false;
    }

    if (form.target && form.target !== '_self') {
        return false;
    }

    const action = form.getAttribute('action') || window.location.href;
    if (!isSameOriginUrl(action)) {
        return false;
    }

    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const actionUrl = toUrl(action);
    if (!actionUrl) {
        return false;
    }

    if (isCriticalUrl(actionUrl, method)) {
        return false;
    }

    const enctype = (form.getAttribute('enctype') || '').toLowerCase();
    if (enctype.includes('multipart/form-data') && hasLargeUpload(form)) {
        return false;
    }

    return true;
};

const getResponseJsonIfAny = async (response) => {
    const contentType = (response.headers.get('content-type') || '').toLowerCase();
    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return await response.json();
    } catch (_error) {
        return null;
    }
};

const applyPatches = (payload) => {
    const patches = Array.isArray(payload?.patches) ? payload.patches : [];
    patches.forEach((patch) => {
        const selector = String(patch?.selector || '').trim();
        const action = String(patch?.action || '').trim();
        const html = String(patch?.html || '');
        if (!selector || !action) {
            return;
        }

        const target = document.querySelector(selector);

        if (action === 'replace' && target) {
            target.outerHTML = html;
            return;
        }

        if (action === 'remove' && target) {
            target.remove();
            return;
        }

        if (action === 'append' && target) {
            target.insertAdjacentHTML('beforeend', html);
            return;
        }

        if (action === 'prepend' && target) {
            target.insertAdjacentHTML('afterbegin', html);
        }
    });
};

const handleJsonPayload = async (payload, context = {}) => {
    if (!payload || typeof payload !== 'object') {
        return;
    }

    const form = context.form instanceof HTMLFormElement ? context.form : null;
    const inModal = Boolean(form && form.closest(`#${AJAX_MODAL_ID}`));

    if (payload.errors && form) {
        applyValidationErrors(form, payload.errors);
    }

    if (payload.formHtml && inModal) {
        const { body } = getModalElements();
        if (body) {
            body.innerHTML = String(payload.formHtml);
            runEmbeddedScripts(body, '');
        }
    }

    if (payload.replace && typeof payload.replace === 'object') {
        Object.entries(payload.replace).forEach(([selector, html]) => {
            const target = document.querySelector(selector);
            if (target) {
                target.innerHTML = String(html || '');
                runEmbeddedScripts(target, '');
            }
        });
    }

    applyPatches(payload);

    if (payload.message) {
        const type = payload.status === 'error' || payload.ok === false ? 'error' : 'success';
        showToast(payload.message, type);
    }

    if (payload.closeModal) {
        closeModal();
    }

    if (payload.redirect) {
        const redirectUrl = toUrl(payload.redirect);
        if (!redirectUrl) {
            fallbackToNative(String(payload.redirect || window.location.href));
            return;
        }

        if (isCriticalUrl(redirectUrl, 'GET')) {
            fallbackToNative(redirectUrl.href);
            return;
        }

        await navigate(redirectUrl.href, { historyMode: 'push' });
    } else if (payload.reload === true) {
        await navigate(window.location.href, { historyMode: 'replace', historyKey: history.state?.key || nextHistoryKey() });
    }
};
const submitFormAjax = async (form, submitter = null) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const action = form.getAttribute('action') || window.location.href;
    const actionUrl = toUrl(action);
    if (!actionUrl) {
        fallbackToNative(window.location.href);
        return;
    }

    const method = (form.getAttribute('method') || 'GET').toUpperCase();

    if (method === 'GET') {
        const params = new URLSearchParams(new FormData(form));
        actionUrl.search = params.toString();
        await navigate(actionUrl.href, { historyMode: 'push' });
        return;
    }

    if (state.activeFormControllers.has(form)) {
        state.activeFormControllers.get(form)?.abort();
    }

    const controller = new AbortController();
    state.activeFormControllers.set(form, controller);
    setFormBusyState(form, true);
    clearFormErrors(form);
    startProgress();

    try {
        const formData = new FormData(form);
        if (submitter instanceof HTMLElement && submitter.getAttribute('name')) {
            formData.set(submitter.getAttribute('name'), submitter.getAttribute('value') || '');
        }

        const response = await fetch(actionUrl.href, {
            method,
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'application/json, text/html, application/xhtml+xml;q=0.9',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: formData,
        });

        if (isSessionFailureStatus(response.status)) {
            handleSessionFailure(response, actionUrl.href);
            return;
        }

        if (response.status === 422) {
            const payload = await getResponseJsonIfAny(response);
            if (payload?.errors) {
                applyValidationErrors(form, payload.errors);
            } else if (payload) {
                await handleJsonPayload(payload, { form, submitter });
            } else {
                showToast('Validation failed.', 'error');
            }
            return;
        }

        const payload = await getResponseJsonIfAny(response);
        if (payload) {
            await handleJsonPayload(payload, { form, submitter });
            return;
        }

        if (response.redirected && response.url) {
            const redirectUrl = toUrl(response.url);
            if (!redirectUrl || isCriticalUrl(redirectUrl, 'GET')) {
                fallbackToNative(response.url);
                return;
            }

            await navigate(response.url, { historyMode: 'push' });
            return;
        }

        const htmlPayload = await parseHtmlPayloadFromResponse(response);
        if (htmlPayload) {
            applyContentPayload(htmlPayload);
            const nextUrl = response.url || actionUrl.href;
            history.pushState({ ajaxHybrid: true, key: nextHistoryKey(), url: nextUrl }, '', nextUrl);
            updateSidebarActiveState(state.sidebar, new URL(nextUrl, window.location.origin));
            window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
            return;
        }

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        showToast('Saved.', 'success');
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        showToast('Request failed. Reloading page...', 'error');
        fallbackToNative(window.location.href, true);
    } finally {
        if (state.activeFormControllers.get(form) === controller) {
            state.activeFormControllers.delete(form);
        }

        setFormBusyState(form, false);
        finishProgress();
    }
};

const openModalFromUrl = async (urlLike, titleText = 'Form') => {
    const url = toUrl(urlLike);
    if (!url || !isSameOriginUrl(url.href)) {
        fallbackToNative(String(urlLike || window.location.href));
        return;
    }

    if (state.activeModalController) {
        state.activeModalController.abort();
    }

    const controller = new AbortController();
    state.activeModalController = controller;
    startProgress();

    try {
        const response = await fetch(url.href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'text/html,application/xhtml+xml,*/*;q=0.9',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Partial': 'true',
                'X-Ajax-Action': 'true',
            },
        });

        if (!response.ok) {
            throw new Error(`Modal load failed with status ${response.status}`);
        }

        const html = await response.text();
        openModal(titleText, html);
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        showToast('Unable to open form.', 'error');
    } finally {
        if (state.activeModalController === controller) {
            state.activeModalController = null;
        }
        finishProgress();
    }
};

const submitActionRequest = async ({ trigger, urlLike, method = 'POST', payload = {} }) => {
    const url = toUrl(urlLike);
    if (!url || !isSameOriginUrl(url.href)) {
        fallbackToNative(String(urlLike || window.location.href));
        return;
    }

    if (isCriticalUrl(url, method)) {
        fallbackToNative(url.href);
        return;
    }

    const existing = state.activeActionControllers.get(trigger);
    if (existing) {
        existing.abort();
    }

    const controller = new AbortController();
    state.activeActionControllers.set(trigger, controller);
    startProgress();

    try {
        const upperMethod = String(method || 'POST').toUpperCase();
        const formData = new FormData();
        formData.set('_token', csrfToken());

        Object.entries(payload || {}).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                formData.set(key, String(value));
            }
        });

        const realMethod = ['GET', 'POST'].includes(upperMethod) ? upperMethod : 'POST';
        if (realMethod === 'POST' && upperMethod !== 'POST') {
            formData.set('_method', upperMethod);
        }

        const response = await fetch(url.href, {
            method: realMethod,
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'application/json, text/html;q=0.9',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: realMethod === 'GET' ? null : formData,
        });

        if (isSessionFailureStatus(response.status)) {
            handleSessionFailure(response, url.href);
            return;
        }

        const payloadJson = await getResponseJsonIfAny(response);
        if (payloadJson) {
            await handleJsonPayload(payloadJson, { trigger });
            return;
        }

        if (response.redirected && response.url) {
            const redirectUrl = toUrl(response.url);
            if (!redirectUrl || isCriticalUrl(redirectUrl, 'GET')) {
                fallbackToNative(response.url);
                return;
            }

            await navigate(response.url, { historyMode: 'push' });
            return;
        }

        if (!response.ok) {
            throw new Error(`Action failed with status ${response.status}`);
        }

        showToast('Action completed.', 'success');
        await navigate(window.location.href, { historyMode: 'replace', historyKey: history.state?.key || nextHistoryKey() });
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        showToast('Action failed.', 'error');
    } finally {
        if (state.activeActionControllers.get(trigger) === controller) {
            state.activeActionControllers.delete(trigger);
        }
        finishProgress();
    }
};

const handleDocumentClick = async (event) => {
    if (event.defaultPrevented || event.button !== 0 || hasModifierKeys(event)) {
        return;
    }

    const modalCloseTrigger = event.target.closest('#ajaxModalClose, [data-ajax-modal-backdrop], [data-ajax-modal-close="true"]');
    if (modalCloseTrigger) {
        event.preventDefault();
        closeModal();
        return;
    }

    const modalTrigger = event.target.closest('[data-ajax-modal="true"]');
    if (modalTrigger && !isNativeOptOut(modalTrigger)) {
        const url = modalTrigger.getAttribute('data-url') || modalTrigger.getAttribute('href');
        if (url) {
            event.preventDefault();
            await openModalFromUrl(url, modalTrigger.getAttribute('data-modal-title') || 'Form');
        }
        return;
    }

    const actionTrigger = event.target.closest('[data-ajax-action="true"]');
    if (actionTrigger && !isNativeOptOut(actionTrigger)) {
        const url = actionTrigger.getAttribute('data-url') || actionTrigger.getAttribute('href');
        if (!url) {
            return;
        }

        const confirmMessage = actionTrigger.getAttribute('data-confirm');
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        event.preventDefault();

        let payload = {};
        const payloadRaw = actionTrigger.getAttribute('data-payload');
        if (payloadRaw) {
            try {
                payload = JSON.parse(payloadRaw);
            } catch (_error) {
                payload = {};
            }
        }

        await submitActionRequest({
            trigger: actionTrigger,
            urlLike: url,
            method: actionTrigger.getAttribute('data-method') || 'POST',
            payload,
        });
        return;
    }

    const link = event.target.closest('a[href]');
    if (!shouldAjaxLink(link, event)) {
        return;
    }

    event.preventDefault();
    await navigate(link.href, { historyMode: 'push' });
};

const handleDocumentSubmit = async (event) => {
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (!shouldAjaxForm(form)) {
        return;
    }

    event.preventDefault();
    await submitFormAjax(form, event.submitter || null);
};

const triggerLiveFilterSubmit = (form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const existing = state.liveFilterTimers.get(form);
    if (existing) {
        window.clearTimeout(existing);
    }

    const timer = window.setTimeout(() => {
        state.liveFilterTimers.delete(form);
        if (!shouldAjaxForm(form)) {
            return;
        }

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'GET') {
            return;
        }

        submitFormAjax(form).catch(() => {
            fallbackToNative(window.location.href, true);
        });
    }, LIVE_FILTER_DEBOUNCE_MS);

    state.liveFilterTimers.set(form, timer);
};

const handleInputForLiveFilter = (event) => {
    const field = event.target;
    if (!(field instanceof HTMLElement)) {
        return;
    }

    const form = field.closest('form[data-live-filter="true"], form[data-auto-submit="true"]');
    if (!form || form.dataset.ajaxBusy === 'true') {
        return;
    }

    triggerLiveFilterSubmit(form);
};

const patchWindowAlert = () => {
    if (state.originalAlert) {
        return;
    }

    state.originalAlert = window.alert.bind(window);
    window.alert = (message) => {
        showToast(message, 'info');
    };

    window.notify = (message, type = 'info', timeoutMs = 3200) => {
        showToast(message, type, timeoutMs);
    };
};

const initDelegatedEvents = () => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('submit', handleDocumentSubmit);
    document.addEventListener('input', handleInputForLiveFilter, true);
    document.addEventListener('change', handleInputForLiveFilter, true);

    document.addEventListener('keydown', (event) => {
        const { modal } = getModalElements();
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    window.addEventListener('popstate', async (event) => {
        const popState = event.state;
        if (!popState || popState.ajaxHybrid !== true) {
            window.location.reload();
            return;
        }

        await navigate(window.location.href, {
            historyMode: 'pop',
            historyKey: popState.key || nextHistoryKey(),
        });
    });
};

const init = () => {
    if (state.initialized) {
        return;
    }

    const content = document.querySelector(CONTENT_SELECTOR);
    if (!content) {
        return;
    }

    state.content = content;
    state.sidebar = document.querySelector(SIDEBAR_SELECTOR);
    state.initialized = true;

    ensureGlobalStyles();
    ensureProgressBar();
    ensureToastHost();
    ensureHistoryState();
    patchWindowAlert();
    initDelegatedEvents();

    updateSidebarActiveState(state.sidebar, new URL(window.location.href));
    runPageInitializers(content.dataset.pageKey || '');
    consumeServerFlashMessages(content);

    window.AjaxNav = {
        navigate: (url, options = {}) => {
            const historyMode = options.pushToHistory === false ? 'replace' : 'push';
            return navigate(url, { historyMode });
        },
        refresh: () => navigate(window.location.href, { historyMode: 'replace', historyKey: history.state?.key || nextHistoryKey() }),
        isEnabled: () => true,
    };

    window.AjaxEngine = {
        navigate,
        submitFormAjax,
        submitActionRequest,
        openModalFromUrl,
        openModal,
        closeModal,
        showToast,
        applyPatches,
    };
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

export {
    navigate,
    submitFormAjax,
    submitActionRequest,
    openModalFromUrl,
    openModal,
    closeModal,
    showToast,
    applyPatches,
};
