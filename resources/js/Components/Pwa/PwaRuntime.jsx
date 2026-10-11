import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import InstallAppBanner from './InstallAppBanner';
import EnableNotificationsPrompt from './EnableNotificationsPrompt';
import { syncWebPush } from '../../utils/webPush';

// Sits next to the Inertia app (outside its page tree), so it follows the
// current page's props through navigation events.
export default function PwaRuntime({ initialPage }) {
    const [pageProps, setPageProps] = useState(initialPage?.props || {});

    useEffect(() => router.on('navigate', (event) => {
        setPageProps(event?.detail?.page?.props || {});
    }), []);

    const userId = pageProps?.auth?.user?.id ?? null;
    const publicKey = pageProps?.web_push?.public_key || null;

    useEffect(() => {
        if (userId && publicKey) {
            syncWebPush(publicKey);
        }
    }, [userId, publicKey]);

    return (
        <>
            <InstallAppBanner appName={pageProps?.branding?.company_name || undefined} />
            {userId && <EnableNotificationsPrompt publicKey={publicKey} />}
        </>
    );
}
