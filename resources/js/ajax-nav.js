(() => {
    const SIDEBAR_SELECTOR = '#adminSidebar, #clientSidebar, #repSidebar, #supportSidebar';
    const CONTENT_SELECTOR = '#appContent';
    const LOADING_HTML = '<div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Loading...</div>';

    const normalizePath = (path) => {
        if (!path) {
            return '/';
        }

        const normalized = path.endsWith('/') && path !== '/' ? path.slice(0, -1) : path;
        return normalized === '' ? '/' : normalized;
    };

    const toUrl = (href) => {
        try {
            return new URL(href, window.location.origin);
        } catch (error) {
            return null;
        }
    };

    const isNavigableSidebarLink = (link, sidebar) => {
        if (!(link instanceof HTMLAnchorElement) || !sidebar.contains(link)) {
            return false;
        }

        if (link.target && link.target !== '_self') {
            return false;
        }

        if (link.hasAttribute('download') || link.dataset.noAjax === 'true' || link.dataset.ajaxNav === 'off') {
            return false;
        }

        const href = link.getAttribute('href') || '';
        if (
            href.startsWith('#')
            || href.startsWith('mailto:')
            || href.startsWith('tel:')
            || href.startsWith('javascript:')
        ) {
            return false;
        }

        const url = toUrl(link.href);
        if (!url || url.origin !== window.location.origin) {
            return false;
        }

        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return false;
        }

        return true;
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
        const allLinks = Array.from(sidebar.querySelectorAll('a[href]'));
        const currentPath = normalizePath(currentUrl.pathname);

        allLinks.forEach((link) => {
            link.classList.remove('nav-link-active', 'ajax-nav-active');
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
        }

        if (bestNavLink) {
            bestNavLink.classList.add('nav-link-active');
        }
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

    const runEmbeddedScripts = (root) => {
        const scripts = Array.from(root.querySelectorAll('script'));

        scripts.forEach((script) => {
            const replacement = document.createElement('script');
            Array.from(script.attributes).forEach((attribute) => {
                replacement.setAttribute(attribute.name, attribute.value);
            });

            if (script.src) {
                replacement.src = script.src;
            } else {
                replacement.textContent = script.textContent || '';
            }

            const restoreDomContentLoadedPatch = patchDomContentLoadedHandlers();
            try {
                script.replaceWith(replacement);
            } finally {
                restoreDomContentLoadedPatch();
            }
        });
    };

    const looksLikeFullDocument = (html) => /<html[\s>]|<!doctype/i.test(html);

    const init = () => {
        if (window.__ajaxSidebarNavigationBooted) {
            return;
        }

        const sidebar = document.querySelector(SIDEBAR_SELECTOR);
        const content = document.querySelector(CONTENT_SELECTOR);

        if (!sidebar || !content) {
            return;
        }

        window.__ajaxSidebarNavigationBooted = true;
        window.PageInit = window.PageInit || {};

        let activeRequestController = null;

        if (!history.state || history.state.ajaxNav !== true) {
            history.replaceState({ ajaxNav: true, url: window.location.href }, '', window.location.href);
        }

        updateSidebarActiveState(sidebar, new URL(window.location.href));

        const applyPageMetadata = (response) => {
            const headerTitle = (response.headers.get('X-Page-Title') || '').trim();
            const headerPageKey = (response.headers.get('X-Page-Key') || '').trim();

            const inlineTitle = (content.querySelector('[data-page-title]')?.getAttribute('data-page-title') || '').trim();
            const inlinePageKey = (content.querySelector('[data-page-key]')?.getAttribute('data-page-key') || '').trim();

            const title = headerTitle || inlineTitle;
            const pageKey = headerPageKey || inlinePageKey;

            if (title !== '') {
                document.title = title;
                content.dataset.pageTitle = title;
            }

            if (pageKey !== '') {
                content.dataset.pageKey = pageKey;
            }

            return pageKey;
        };

        const runPageInitializers = (pageKey) => {
            if (typeof window.bindInvoiceItems === 'function') {
                window.bindInvoiceItems(content);
            }

            if (window.htmx && typeof window.htmx.process === 'function') {
                window.htmx.process(content);
            }

            if (pageKey && typeof window.PageInit[pageKey] === 'function') {
                window.PageInit[pageKey](content);
            }

            document.dispatchEvent(new CustomEvent('ajax-nav:loaded', {
                detail: {
                    pageKey,
                    url: window.location.href,
                },
            }));
        };

        const navigateWithPartial = async (url, { pushToHistory = true } = {}) => {
            if (activeRequestController) {
                activeRequestController.abort();
            }

            const controller = new AbortController();
            activeRequestController = controller;

            content.setAttribute('aria-busy', 'true');
            content.innerHTML = LOADING_HTML;

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        Accept: 'text/html,application/xhtml+xml',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Partial': 'true',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Unexpected status: ${response.status}`);
                }

                const html = await response.text();
                const isPartial = response.headers.get('X-Partial-Response') === 'true';

                if (!isPartial || looksLikeFullDocument(html)) {
                    throw new Error('Fallback to full navigation');
                }

                content.innerHTML = html;
                const pageKey = applyPageMetadata(response);

                runEmbeddedScripts(content);
                runPageInitializers(pageKey);

                if (pushToHistory) {
                    history.pushState({ ajaxNav: true, url }, '', url);
                }

                updateSidebarActiveState(sidebar, new URL(url, window.location.origin));
                window.scrollTo({ top: 0, behavior: 'auto' });
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                window.location.assign(url);
            } finally {
                if (activeRequestController === controller) {
                    activeRequestController = null;
                }

                content.removeAttribute('aria-busy');
            }
        };

        document.addEventListener('click', (event) => {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
            ) {
                return;
            }

            const link = event.target.closest('a[href]');
            if (!isNavigableSidebarLink(link, sidebar)) {
                return;
            }

            const url = toUrl(link.href);
            if (!url) {
                return;
            }

            if (url.href === window.location.href) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            navigateWithPartial(url.href, { pushToHistory: true });
        });

        window.addEventListener('popstate', () => {
            navigateWithPartial(window.location.href, { pushToHistory: false });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
