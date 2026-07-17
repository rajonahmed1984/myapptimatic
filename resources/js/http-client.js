import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

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

axios.interceptors.request.use((config) => applyCsrfToken(config));

export default axios;
