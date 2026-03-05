import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

const readCsrfToken = () => {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    const token = metaTag?.getAttribute('content') || '';

    return token.trim();
};

const applyCsrfToken = (config) => {
    const token = readCsrfToken();
    if (token !== '') {
        config.headers = config.headers || {};
        config.headers['X-CSRF-TOKEN'] = token;
    }

    return config;
};

window.axios.interceptors.request.use((config) => applyCsrfToken(config));
