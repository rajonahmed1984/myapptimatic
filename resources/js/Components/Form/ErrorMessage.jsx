import React from 'react';

export default function ErrorMessage({ message = null, className = 'mt-1 text-xs text-rose-300' }) {
    if (!message) {
        return null;
    }

    return <p className={className}>{message}</p>;
}
