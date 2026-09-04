import axios from 'axios';
import { startRequestLoading, stopRequestLoading } from '@/state/loading';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api';

// El backend concede hasta EXTRACTOR_REQUEST_TIMEOUT_SECONDS (180s por defecto)
// a los procesos de OCR. Este margen debe superarlo para no abortar una
// extracción que el servidor sigue ejecutando.
export const EXTRACTOR_TIMEOUT_MS = 240000;

const defaultHeaders = {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

const instance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    timeout: 30000,
    headers: { ...defaultHeaders }
});

// Instancia para los endpoints que invocan al extractor OCR.
export const extractorInstance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    timeout: EXTRACTOR_TIMEOUT_MS,
    headers: { ...defaultHeaders }
});

// Endpoints donde un 401 es una respuesta esperada y la vista debe mostrarlo.
const AUTH_ENDPOINTS = ['/login', '/logout', '/check', '/user'];

function isAuthEndpoint(url) {
    if (!url) return false;
    return AUTH_ENDPOINTS.some((endpoint) => url === endpoint || url.endsWith(endpoint));
}

let redirectingToLogin = false;

async function handleExpiredSession() {
    if (redirectingToLogin || window.location.pathname === '/login') {
        return;
    }
    redirectingToLogin = true;

    // Importación diferida: el store importa este módulo y crearía un ciclo.
    const { useAuthStore } = await import('@/stores/auth');
    useAuthStore().$reset();

    window.location.href = '/login';
}

function attachInterceptors(client) {
    client.interceptors.request.use(
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

    client.interceptors.response.use(
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

            const status = Number(error?.response?.status || 0);
            // 419 = token CSRF expirado; para el usuario es lo mismo que una sesión caída.
            if ((status === 401 || status === 419) && !isAuthEndpoint(error?.config?.url)) {
                handleExpiredSession();
            }

            return Promise.reject(error);
        }
    );

    return client;
}

attachInterceptors(instance);
attachInterceptors(extractorInstance);

export default instance;