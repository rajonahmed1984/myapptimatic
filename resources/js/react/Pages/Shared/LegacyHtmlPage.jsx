import React, { useEffect } from 'react';
import { Head } from '@inertiajs/react';

const runInlineScript = (code) => {
    if (!code || typeof code !== 'string') {
        return;
    }

    const originalAddEventListener = document.addEventListener.bind(document);
    const domContentLoadedEvent = new Event('DOMContentLoaded');

    document.addEventListener = (type, listener, options) => {
        if (type === 'DOMContentLoaded') {
            try {
                if (typeof listener === 'function') {
                    listener(domContentLoadedEvent);
                } else if (listener && typeof listener.handleEvent === 'function') {
                    listener.handleEvent(domContentLoadedEvent);
                }
            } catch (error) {
                console.warn('Legacy DOMContentLoaded listener failed', error);
            }

            return;
        }

        return originalAddEventListener(type, listener, options);
    };

    try {
        window.eval(`(() => {\n${code}\n})();`);
    } finally {
        document.addEventListener = originalAddEventListener;
    }
};

export default function LegacyHtmlPage({
    fallbackTitle = 'Portal',
    pageTitle = '',
    pageHeading = 'Overview',
    pageKey = '',
    content_html = '',
    script_html = '',
    style_html = '',
}) {
    const resolvedTitle = pageTitle || fallbackTitle;

    useEffect(() => {
        const headingEl = document.querySelector('[data-current-page-title]');
        if (headingEl && pageHeading) {
            headingEl.textContent = pageHeading;
        }
    }, [pageHeading]);

    useEffect(() => {
        if (!style_html) {
            return undefined;
        }

        const temp = document.createElement('div');
        temp.innerHTML = style_html;
        const styleNodes = Array.from(temp.querySelectorAll('style'));
        const appended = [];

        styleNodes.forEach((node) => {
            const styleEl = document.createElement('style');
            styleEl.textContent = node.textContent || '';
            styleEl.setAttribute('data-legacy-style', pageKey || 'legacy');
            document.head.appendChild(styleEl);
            appended.push(styleEl);
        });

        return () => {
            appended.forEach((node) => node.remove());
        };
    }, [style_html, pageKey]);

    useEffect(() => {
        const stack = document.getElementById('pageScriptStack');
        if (stack) {
            stack.innerHTML = script_html || '';
        }

        if (!script_html) {
            return undefined;
        }

        const temp = document.createElement('div');
        temp.innerHTML = script_html;
        const scriptNodes = Array.from(temp.querySelectorAll('script'));
        const appendedExternal = [];

        scriptNodes.forEach((node) => {
            const src = node.getAttribute('src');
            if (src) {
                const scriptEl = document.createElement('script');
                Array.from(node.attributes).forEach((attr) => {
                    scriptEl.setAttribute(attr.name, attr.value);
                });
                scriptEl.setAttribute('data-legacy-script', pageKey || 'legacy');
                document.body.appendChild(scriptEl);
                appendedExternal.push(scriptEl);
                return;
            }

            runInlineScript(node.textContent || '');
        });

        return () => {
            appendedExternal.forEach((node) => node.remove());
            if (stack) {
                stack.innerHTML = '';
            }
        };
    }, [script_html, pageKey]);

    return (
        <>
            <Head title={resolvedTitle} />
            <div dangerouslySetInnerHTML={{ __html: content_html }} />
        </>
    );
}
