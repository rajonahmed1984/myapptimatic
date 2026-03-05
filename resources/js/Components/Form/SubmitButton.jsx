import React from 'react';

export default function SubmitButton({ children, className = '', ...props }) {
    return (
        <button
            type="submit"
            className={[
                'w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            {...props}
        >
            {children}
        </button>
    );
}
