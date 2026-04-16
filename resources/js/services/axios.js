import axios from 'axios';
import { startRequestLoading, stopRequestLoading } from '@/state/loading';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api';

const instance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    timeout: 30000, // Aumentado de 12s a 30s para endpoints lentos
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

instance.interceptors.request.use(
    (config) => {
        const skipGlobalLoading = config?.skipGlobalLoading === true;
        config.__loadingTracked = !skipGlobalLoading;

        if (config.__loadingTracked) {
            startRequestLoading();
        }

        return config;
    },
    (error) => {
        stopRequestLoading();
        return Promise.reject(error);
    }
);

instance.interceptors.response.use(
    (response) => {
        if (response?.config?.__loadingTracked) {
            stopRequestLoading();
        }

        return response;
    },
    (error) => {
        if (error?.config?.__loadingTracked) {
            stopRequestLoading();
        }

        return Promise.reject(error);
    }
);

export default instance;