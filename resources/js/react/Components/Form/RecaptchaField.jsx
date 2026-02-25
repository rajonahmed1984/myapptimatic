import React, { useEffect } from 'react';

const loadRecaptchaEnterprise = () => {
    if (document.querySelector('script[data-recaptcha-enterprise]')) {
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/enterprise.js';
    script.async = true;
    script.defer = true;
    script.dataset.recaptchaEnterprise = 'true';
    document.head.appendChild(script);
};

export default function RecaptchaField({
    enabled = false,
    siteKey = '',
    action = 'LOGIN',
    className = 'flex justify-center',
}) {
    useEffect(() => {
        if (enabled && siteKey) {
            loadRecaptchaEnterprise();
        }
    }, [enabled, siteKey]);

    if (!enabled || !siteKey) {
        return null;
    }

    return (
        <div className={className}>
            <div className="g-recaptcha" data-sitekey={siteKey} data-action={action}></div>
        </div>
    );
}
