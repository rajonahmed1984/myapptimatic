import React from 'react';

const flattenErrors = (errors) => {
    if (!errors || typeof errors !== 'object') {
        return [];
    }

    return Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter((value) => typeof value === 'string' && value.length > 0);
};

export default function AlertStack({
    status = null,
    errors = null,
    singleError = false,
    statusClassName = 'mb-5 rounded-xl border border-emerald-300/40 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100',
    errorClassName = 'mb-5 rounded-xl border border-rose-300/40 bg-rose-400/10 px-4 py-3 text-sm text-rose-100',
}) {
    const messages = flattenErrors(errors);
    const outputErrors = singleError ? messages.slice(0, 1) : messages;

    return (
        <>
            {outputErrors.length > 0 ? (
                <div data-flash-message data-flash-type="error" className={errorClassName}>
                    {singleError ? (
                        outputErrors[0]
                    ) : (
                        <ul className="space-y-1">
                            {outputErrors.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : null}

            {status ? (
                <div data-flash-message data-flash-type="success" className={statusClassName}>
                    {status}
                </div>
            ) : null}
        </>
    );
}
