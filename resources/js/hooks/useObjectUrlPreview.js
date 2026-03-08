import { useEffect, useState } from 'react';

export default function useObjectUrlPreview(file, options = {}) {
    const { enabled = true } = options;
    const [previewUrl, setPreviewUrl] = useState('');

    useEffect(() => {
        if (!file || !enabled) {
            setPreviewUrl('');
            return undefined;
        }

        const objectUrl = URL.createObjectURL(file);
        setPreviewUrl(objectUrl);

        return () => {
            URL.revokeObjectURL(objectUrl);
        };
    }, [enabled, file]);

    return previewUrl;
}
