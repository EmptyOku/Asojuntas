import { defineStore } from 'pinia';
import axios from '@/services/axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    roles: [],
    permissions: [],
    isAuthenticated: false,
    loading: false
  }),
  
  actions: {
    isAuthError(error) {
      const status = Number(error?.response?.status || 0);
      return status === 401 || status === 419;
    },

    async login(credentials) {
      this.loading = true;
      try {
        const response = await axios.post('/login', credentials);
        if (response.data.token) {
          window.localStorage.setItem('sanctum_token', response.data.token);
        }
        this.user = response.data.user ?? null;
        this.roles = response.data.roles ?? [];
        this.permissions = response.data.permissions ?? [];
        this.isAuthenticated = true;
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
        this.user = response.data.user ?? null;
        this.roles = response.data.roles ?? [];
        this.permissions = response.data.permissions ?? [];
        this.isAuthenticated = true;
      } catch (error) {
        if (this.isAuthError(error)) {
          this.$reset();
        }
        throw error;
      }
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