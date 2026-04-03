import axios from 'axios';
import { startRequestLoading, stopRequestLoading } from '@/state/loading';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api';

const instance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

instance.interceptors.request.use(
    (config) => {
        startRequestLoading();
        return config;
    },
    (error) => {
        stopRequestLoading();
        return Promise.reject(error);
    }
);

instance.interceptors.response.use(
    (response) => {
        stopRequestLoading();
        return response;
    },
    (error) => {
        stopRequestLoading();
        return Promise.reject(error);
    }
);

export default instance;