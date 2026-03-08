const appBaseUrl = () => {
    const metaValue = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') || '';
    const normalized = String(metaValue).trim().replace(/\\/g, '/');

    if (normalized === '' || normalized === '/') {
        return '';
    }

    return `/${normalized.replace(/^\/+|\/+$/g, '')}`;
};

const mediaAvatarPath = (path) => {
    const normalized = String(path)
        .replace(/\\/g, '/')
        .replace(/^\/+/, '')
        .replace(/^avatars\//i, '');

    return `${appBaseUrl()}/media/avatars/${normalized}`;
};
const storagePath = (path) => `${appBaseUrl()}/storage/${String(path).replace(/^\/+/, '')}`;

export default function mediaUrl(input) {
    const raw = String(input ?? '').trim();
    if (raw === '') {
        return null;
    }

    if (/^(https?:)?\/\//i.test(raw) || raw.startsWith('data:') || raw.startsWith('blob:')) {
        return raw;
    }

    if (raw.startsWith('/')) {
        if (raw.startsWith('/storage/avatars/')) {
            return mediaAvatarPath(raw.replace(/^\/storage\/+/i, ''));
        }

        if (raw.startsWith('/storage/')) {
            return `${appBaseUrl()}${raw}`;
        }

        return raw;
    }

    const normalized = raw
        .replace(/\\/g, '/')
        .replace(/^\/+/, '')
        .replace(/^storage\//i, '');

    if (normalized === '') {
        return null;
    }

    if (normalized.startsWith('avatars/')) {
        return mediaAvatarPath(normalized);
    }

    return storagePath(normalized);
}
