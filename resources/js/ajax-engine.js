const AJAX_MODAL_ID = 'ajaxModal';
const AJAX_MODAL_BODY_ID = 'ajaxModalBody';
const AJAX_MODAL_TITLE_ID = 'ajaxModalTitle';
const AJAX_TOAST_HOST_ID = 'ajaxToastHost';

const isSameOriginUrl = (urlLike) => {
    try {
        const url = new URL(urlLike, window.location.origin);
        return url.origin === window.location.origin;
    } catch (error) {
        return false;
    }
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const getJsonIfAny = async (response) => {
    const contentType = (response.headers.get('content-type') || '').toLowerCase();
    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return await response.json();
    } catch (error) {
        return null;
    }
};

const getTextIfAny = async (response) => {
    try {
        return await response.text();
    } catch (error) {
        return '';
    }
};

const showToast = (message, type = 'success') => {
    const host = document.getElementById(AJAX_TOAST_HOST_ID);
    if (!host || !message) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `ajax-toast ajax-toast-${type}`;
    toast.textContent = message;
    host.appendChild(toast);

    window.setTimeout(() => {
        toast.remove();
    }, 2600);
};

const getModalElements = () => {
    return {
        modal: document.getElementById(AJAX_MODAL_ID),
        body: document.getElementById(AJAX_MODAL_BODY_ID),
        title: document.getElementById(AJAX_MODAL_TITLE_ID),
    };
};

const openModal = (titleText, html) => {
    const { modal, body, title } = getModalElements();
    if (!modal || !body) {
        return;
    }

    if (title) {
        title.textContent = titleText || 'Form';
    }

    body.innerHTML = html || '';
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
};

const closeModal = () => {
    const { modal, body } = getModalElements();
    if (!modal || !body) {
        return;
    }

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    body.innerHTML = '';
    document.body.classList.remove('overflow-hidden');
};

const clearFormErrors = (form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.querySelectorAll('.is-invalid').forEach((field) => {
        field.classList.remove('is-invalid');
    });

    form.querySelectorAll('.invalid-feedback[data-ajax-error="true"]').forEach((errorNode) => {
        errorNode.remove();
    });
};

const cssEscapeSafe = (value) => {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }

    return value.replace(/"/g, '\\"');
};

const findFieldByName = (form, rawName) => {
    const candidates = [rawName];
    if (rawName.includes('.')) {
        const parts = rawName.split('.');
        const bracketed = parts.reduce((carry, part, index) => {
            if (index === 0) {
                return part;
            }

            return `${carry}[${part}]`;
        }, '');
        candidates.push(bracketed);
        candidates.push(`${bracketed}[]`);
    } else {
        candidates.push(`${rawName}[]`);
    }

    for (const candidate of candidates) {
        const escaped = cssEscapeSafe(candidate);
        const field = form.querySelector(`[name="${escaped}"]`);
        if (field) {
            return field;
        }
    }

    return null;
};

const applyValidationErrors = (form, errors) => {
    if (!(form instanceof HTMLFormElement) || typeof errors !== 'object' || errors === null) {
        return;
    }

    clearFormErrors(form);

    Object.entries(errors).forEach(([fieldName, messages]) => {
        const field = findFieldByName(form, fieldName);
        if (!field) {
            return;
        }

        field.classList.add('is-invalid');

        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.dataset.ajaxError = 'true';
        feedback.textContent = Array.isArray(messages) ? String(messages[0] || 'Invalid value') : String(messages);

        const anchor = field.closest('.field-group') || field;
        anchor.insertAdjacentElement('afterend', feedback);
    });
};

const applyPatches = (payload) => {
    const patches = Array.isArray(payload?.patches) ? payload.patches : [];
    patches.forEach((patch) => {
        const selector = String(patch?.selector || '').trim();
        const action = String(patch?.action || '').trim();
        const html = String(patch?.html || '');
        if (!selector || !action) {
            return;
        }

        const target = document.querySelector(selector);
        if (!target && action !== 'append' && action !== 'prepend') {
            return;
        }

        if (action === 'replace' && target) {
            target.outerHTML = html;
            return;
        }

        if (action === 'remove' && target) {
            target.remove();
            return;
        }

        if (action === 'append') {
            const container = document.querySelector(selector);
            if (container) {
                container.insertAdjacentHTML('beforeend', html);
            }
            return;
        }

        if (action === 'prepend') {
            const container = document.querySelector(selector);
            if (container) {
                container.insertAdjacentHTML('afterbegin', html);
            }
        }
    });
};

const ajaxFetchHTML = async (url, options = {}) => {
    const headers = {
        Accept: 'text/html,application/xhtml+xml',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Partial': 'true',
        'X-Ajax-Action': 'true',
        ...(options.headers || {}),
    };

    const response = await fetch(url, {
        method: options.method || 'GET',
        credentials: 'same-origin',
        ...options,
        headers,
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }

    return response.text();
};

const navigateByAjaxNav = (url, options = {}) => {
    if (window.AjaxNav && typeof window.AjaxNav.navigate === 'function') {
        return window.AjaxNav.navigate(url, options);
    }

    window.location.assign(url);
    return Promise.resolve();
};

const handleJsonPayload = async (payload, context = {}) => {
    const form = context.form instanceof HTMLFormElement ? context.form : null;
    const inModal = Boolean(form && form.closest(`#${AJAX_MODAL_ID}`));

    if (!payload || typeof payload !== 'object') {
        return;
    }

    if (payload.formHtml && inModal) {
        const { body } = getModalElements();
        if (body) {
            body.innerHTML = String(payload.formHtml);
        }
    }

    if (payload.errors && form) {
        applyValidationErrors(form, payload.errors);
    }

    applyPatches(payload);

    if (payload.message) {
        showToast(payload.message, payload.ok === false ? 'error' : 'success');
    }

    if (payload.closeModal) {
        closeModal();
    }

    if (payload.redirect) {
        await navigateByAjaxNav(payload.redirect, { pushToHistory: true });
    }
};

const submitActionRequest = async ({ url, method = 'POST', payload = {}, context = {} }) => {
    const upperMethod = String(method || 'POST').toUpperCase();
    const formData = new FormData();
    formData.set('_token', csrfToken());

    Object.entries(payload || {}).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            return;
        }

        formData.set(key, String(value));
    });

    const realMethod = ['GET', 'POST'].includes(upperMethod) ? upperMethod : 'POST';
    if (realMethod === 'POST' && upperMethod !== 'POST') {
        formData.set('_method', upperMethod);
    }

    const response = await fetch(url, {
        method: realMethod,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json, text/html;q=0.9',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Ajax-Action': 'true',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: realMethod === 'GET' ? null : formData,
    });

    if (response.status === 401 || response.status === 403) {
        if (response.redirected && response.url) {
            await navigateByAjaxNav(response.url, { pushToHistory: true });
            return;
        }

        showToast('Your session has expired. Please log in again.', 'error');
        window.location.assign('/login');
        return;
    }

    const payloadJson = await getJsonIfAny(response);
    if (payloadJson) {
        await handleJsonPayload(payloadJson, context);
        return;
    }

    if (response.redirected) {
        await navigateByAjaxNav(response.url, { pushToHistory: true });
        return;
    }

    if (!response.ok) {
        showToast('Action failed. Please try again.', 'error');
    }
};

const ajaxSubmitForm = async (formElement, submitter = null) => {
    if (!(formElement instanceof HTMLFormElement)) {
        return;
    }

    clearFormErrors(formElement);

    const method = (formElement.getAttribute('method') || 'GET').toUpperCase();
    const action = formElement.getAttribute('action') || window.location.href;
    const actionUrl = new URL(action, window.location.origin);

    if (method === 'GET') {
        const search = new URLSearchParams(new FormData(formElement));
        actionUrl.search = search.toString();
        await navigateByAjaxNav(actionUrl.toString(), { pushToHistory: true });
        return;
    }

    const formData = new FormData(formElement);
    if (submitter instanceof HTMLElement && submitter.getAttribute('name')) {
        formData.set(submitter.getAttribute('name'), submitter.getAttribute('value') || '');
    }

    const response = await fetch(actionUrl.toString(), {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json, text/html;q=0.9',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Ajax-Action': 'true',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });

    if (response.status === 401 || response.status === 403) {
        if (response.redirected && response.url) {
            await navigateByAjaxNav(response.url, { pushToHistory: true });
            return;
        }

        showToast('Your session has expired. Please log in again.', 'error');
        window.location.assign('/login');
        return;
    }

    const payload = await getJsonIfAny(response);
    if (payload) {
        await handleJsonPayload(payload, { form: formElement, submitter });
        return;
    }

    if (response.redirected) {
        await navigateByAjaxNav(response.url, { pushToHistory: true });
        return;
    }

    const html = await getTextIfAny(response);
    if (!response.ok) {
        if (formElement.closest(`#${AJAX_MODAL_ID}`)) {
            const { body } = getModalElements();
            if (body && html) {
                body.innerHTML = html;
            } else {
                showToast('Validation failed.', 'error');
            }
            return;
        }

        showToast('Request failed.', 'error');
        return;
    }

    await navigateByAjaxNav(response.url || window.location.href, { pushToHistory: true });
};

const shouldHandleLinkWithAjaxNav = (link) => {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    if (link.dataset.ajaxNav === 'off' || link.dataset.noAjax === 'true') {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    const href = link.getAttribute('href') || '';
    if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
        return false;
    }

    if (!isSameOriginUrl(link.href)) {
        return false;
    }

    if (link.closest(`#${AJAX_MODAL_ID}`)) {
        return false;
    }

    if (link.dataset.ajaxNav === 'true') {
        return true;
    }

    if (link.closest('.pagination') || href.includes('page=')) {
        return true;
    }

    return false;
};

const initAjaxModalBindings = () => {
    const { modal } = getModalElements();
    if (!modal) {
        return;
    }

    document.addEventListener('click', (event) => {
        const closeTrigger = event.target.closest('#ajaxModalClose, [data-ajax-modal-backdrop], [data-ajax-modal-close="true"]');
        if (!closeTrigger) {
            return;
        }

        event.preventDefault();
        closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
};

const initDelegatedEvents = () => {
    document.addEventListener('click', async (event) => {
        if (
            event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
        ) {
            return;
        }

        const modalTrigger = event.target.closest('[data-ajax-modal="true"]');
        if (modalTrigger) {
            const url = modalTrigger.getAttribute('data-url') || modalTrigger.getAttribute('href');
            if (!url || !isSameOriginUrl(url)) {
                return;
            }

            event.preventDefault();

            try {
                const title = modalTrigger.getAttribute('data-modal-title') || 'Form';
                const html = await ajaxFetchHTML(url);
                openModal(title, html);
            } catch (error) {
                showToast('Unable to open form.', 'error');
            }

            return;
        }

        const actionTrigger = event.target.closest('[data-ajax-action="true"]');
        if (actionTrigger) {
            const url = actionTrigger.getAttribute('data-url') || actionTrigger.getAttribute('href');
            if (!url || !isSameOriginUrl(url)) {
                return;
            }

            const confirmMessage = actionTrigger.getAttribute('data-confirm');
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            event.preventDefault();

            let payload = {};
            const payloadRaw = actionTrigger.getAttribute('data-payload');
            if (payloadRaw) {
                try {
                    payload = JSON.parse(payloadRaw);
                } catch (error) {
                    payload = {};
                }
            }

            const status = actionTrigger.getAttribute('data-status');
            if (status) {
                payload.status = status;
            }

            await submitActionRequest({
                url,
                method: actionTrigger.getAttribute('data-method') || 'POST',
                payload,
                context: { trigger: actionTrigger },
            });
            return;
        }

        const link = event.target.closest('#appContent a[href]');
        if (!link || !shouldHandleLinkWithAjaxNav(link)) {
            return;
        }

        event.preventDefault();
        await navigateByAjaxNav(link.href, { pushToHistory: true });
    });

    document.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) {
            return;
        }

        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const isAjaxForm = form.getAttribute('data-ajax-form') === 'true';
        const isGetFilterForm = form.closest('#appContent') && (form.method || 'GET').toUpperCase() === 'GET';
        if (!isAjaxForm && !isGetFilterForm) {
            return;
        }

        if (form.getAttribute('target') && form.getAttribute('target') !== '_self') {
            return;
        }

        if (!isSameOriginUrl(form.action || window.location.href)) {
            return;
        }

        event.preventDefault();
        await ajaxSubmitForm(form, event.submitter || null);
    });
};

const init = () => {
    if (window.__ajaxEngineInit) {
        return;
    }

    window.__ajaxEngineInit = true;

    initAjaxModalBindings();
    initDelegatedEvents();

    window.AjaxEngine = {
        ajaxFetchHTML,
        ajaxSubmitForm,
        applyPatches,
        openModal,
        closeModal,
    };
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

export { ajaxFetchHTML, ajaxSubmitForm, applyPatches };
