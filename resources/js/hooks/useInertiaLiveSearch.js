import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const normalize = (value) => String(value ?? '').trim();

export default function useInertiaLiveSearch({
    initialValue = '',
    url = '',
    param = 'search',
    debounce = 300,
    extraData = null,
}) {
    const { url: pageUrl = '' } = usePage();
    const [searchTerm, setSearchTerm] = useState(String(initialValue ?? ''));
    const isFirstRender = useRef(true);

    useEffect(() => {
        setSearchTerm(String(initialValue ?? ''));
    }, [initialValue]);

    const submitSearch = useCallback((value = searchTerm) => {
        const currentUrl = new URL(pageUrl || '/', window.location.origin);
        const params = new URLSearchParams(currentUrl.search);
        const normalized = normalize(value);

        if (normalized === '') {
            params.delete(param);
        } else {
            params.set(param, normalized);
        }

        if (extraData && typeof extraData === 'object') {
            Object.entries(extraData).forEach(([key, extraValue]) => {
                const normalizedExtraValue = normalize(extraValue);
                if (normalizedExtraValue === '') {
                    params.delete(key);
                    return;
                }

                params.set(key, normalizedExtraValue);
            });
        }

        // Search changes should always restart pagination from the first page.
        params.delete('page');

        router.get(
            url || currentUrl.pathname,
            Object.fromEntries(params.entries()),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, [extraData, pageUrl, param, searchTerm, url]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        if (normalize(searchTerm) === normalize(initialValue)) {
            return;
        }

        const timeout = window.setTimeout(() => {
            submitSearch(searchTerm);
        }, debounce);

        return () => window.clearTimeout(timeout);
    }, [debounce, initialValue, searchTerm, submitSearch]);

    return {
        searchTerm,
        setSearchTerm,
        submitSearch,
    };
}
