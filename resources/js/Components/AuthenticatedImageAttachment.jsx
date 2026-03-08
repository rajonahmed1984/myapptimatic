import React, { useEffect, useState } from 'react';

const guessImageMime = (input) => {
    const value = String(input || '').toLowerCase();
    if (value.endsWith('.png')) return 'image/png';
    if (value.endsWith('.webp')) return 'image/webp';
    if (value.endsWith('.gif')) return 'image/gif';
    if (value.endsWith('.bmp')) return 'image/bmp';
    if (value.endsWith('.svg')) return 'image/svg+xml';
    if (value.endsWith('.avif')) return 'image/avif';
    if (value.endsWith('.jpg') || value.endsWith('.jpeg')) return 'image/jpeg';

    return '';
};

export default function AuthenticatedImageAttachment({
    url = '',
    name = 'Attachment preview',
    imageClassName = '',
    linkClassName = '',
    wrapperClassName = '',
}) {
    const [imageUrl, setImageUrl] = useState('');
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const targetUrl = String(url || '').trim();
        if (targetUrl === '') {
            setImageUrl('');
            setFailed(false);
            return undefined;
        }

        let active = true;
        let objectUrl = '';
        const controller = new AbortController();

        setFailed(false);

        const loadImage = async () => {
            try {
                const response = await fetch(targetUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'image/*,*/*;q=0.8',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`Image request failed with ${response.status}`);
                }

                const contentType = String(response.headers.get('content-type') || '').toLowerCase();
                if (contentType.includes('text/html')) {
                    throw new Error('Attachment response is HTML, not an image.');
                }

                const blob = await response.blob();
                const normalizedType = contentType.startsWith('image/')
                    ? contentType
                    : guessImageMime(name) || guessImageMime(targetUrl) || blob.type || 'image/jpeg';
                const normalizedBlob = normalizedType === blob.type
                    ? blob
                    : new Blob([blob], { type: normalizedType });

                objectUrl = URL.createObjectURL(normalizedBlob);
                if (!active) {
                    URL.revokeObjectURL(objectUrl);
                    return;
                }

                setImageUrl(objectUrl);
            } catch (_error) {
                if (!active || controller.signal.aborted) {
                    return;
                }

                setImageUrl('');
                setFailed(true);
            }
        };

        void loadImage();

        return () => {
            active = false;
            controller.abort();
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [name, url]);

    if (!String(url || '').trim()) {
        return null;
    }

    return (
        <div className={wrapperClassName}>
            {imageUrl ? (
                <a href={url} target="_blank" rel="noopener" className="inline-block">
                    <img src={imageUrl} alt={name} className={imageClassName} loading="lazy" />
                </a>
            ) : failed ? (
                <div className="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-700">
                    Preview unavailable. Open the attachment below.
                </div>
            ) : (
                <div className="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-500">
                    Loading image preview...
                </div>
            )}
            <a href={url} target="_blank" rel="noopener" className={linkClassName}>
                {name || 'Attachment'}
            </a>
        </div>
    );
}
