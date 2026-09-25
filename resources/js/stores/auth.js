import { defineStore } from 'pinia';
import axios from '@/services/axios';

// Evita refrescos en ráfaga cuando varias peticiones fallan con 403 a la vez.
const PERMISSION_REFRESH_MIN_INTERVAL_MS = 5000;
let refreshInFlight = null;
let lastRefreshAt = 0;

const toList = (value) => (Array.isArray(value) ? value : [value]).filter(Boolean);

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
    permissions: [],
    isAuthenticated: false,
    loading: false
  }),

  getters: {
    // El SPA decide por permisos, nunca por nombre de rol: los roles se crean desde la UI.
    can: (state) => (permission) => state.permissions.includes(permission),
    canAny: (state) => (permissions) => toList(permissions).some((permission) => state.permissions.includes(permission)),
    canAll: (state) => (permissions) => toList(permissions).every((permission) => state.permissions.includes(permission))
  },

  actions: {
    isAuthError(error) {
      const status = Number(error?.response?.status || 0);
      return status === 401 || status === 419;
    },

    applySession(data) {
      this.user = data.user ?? null;
      this.roles = data.roles ?? [];
      this.permissions = data.permissions ?? [];
      this.isAuthenticated = true;
    },

    async login(credentials) {
      this.loading = true;
      try {
        const response = await axios.post('/login', credentials);
        if (response.data.token) {
          window.localStorage.setItem('sanctum_token', response.data.token);
        }
        this.applySession(response.data);
        return true;
      } catch (error) {
        if (this.isAuthError(error)) {
          this.$reset();
        }
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchUser() {
      try {
        const response = await axios.get('/user');
        this.applySession(response.data);
      } catch (error) {
        if (this.isAuthError(error)) {
          this.$reset();
        }
        throw error;
      }
    },

    /**
     * Vuelve a pedir los permisos (p. ej. tras un 403: un admin pudo cambiar el
     * rol mientras la sesión estaba abierta). Devuelve true si cambiaron.
     */
    async refreshPermissions() {
      if (!this.isAuthenticated) return false;
      if (refreshInFlight) return refreshInFlight;
      if (Date.now() - lastRefreshAt < PERMISSION_REFRESH_MIN_INTERVAL_MS) return false;

      const before = [...this.permissions].sort().join('|');

      refreshInFlight = axios.get('/user', { skipGlobalLoading: true })
        .then((response) => {
          this.applySession(response.data);
          return [...this.permissions].sort().join('|') !== before;
        })
        .catch(() => false)
        .finally(() => {
          lastRefreshAt = Date.now();
          refreshInFlight = null;
        });

      return refreshInFlight;
    },

    async logout() {
      try {
        await axios.post('/logout');
      } catch (error) {
        // Ignore logout network errors and force local cleanup.
      }
      window.localStorage.removeItem('sanctum_token');
      this.$reset();
      window.location.href = '/login';
    }
  }
});
