export default function mediaUrl(input) {
    const raw = String(input ?? '').trim();
    if (raw === '') {
        return null;
    }

    if (/^(https?:)?\/\//i.test(raw) || raw.startsWith('data:') || raw.startsWith('blob:')) {
        return raw;
    }

    if (raw.startsWith('/')) {
        return raw;
    }

    const normalized = raw
        .replace(/\\/g, '/')
        .replace(/^\/+/, '')
        .replace(/^storage\//i, '');

    if (normalized === '') {
        return null;
    }

    return `/storage/${normalized}`;
}
