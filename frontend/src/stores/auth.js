import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    permissions: [], // Aquí guardaremos los slugs de los permisos del UML
    isAuthenticated: false,
    loading: false
  }),
  
  actions: {
    async login(credentials) {
      this.loading = true;
      try {
        // Primero obtenemos la cookie CSRF de Laravel Sanctum
        await axios.get('/sanctum/csrf-cookie');
        // Enviamos el login
        const response = await axios.post('/login', credentials);
        await this.fetchUser();
        return true;
      } catch (error) {
        console.error("Error en login:", error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async fetchUser() {
      try {
        const response = await axios.get('/api/user'); // Laravel debe devolver user + roles + permissions
        this.user = response.data.user;
        this.permissions = response.data.permissions; // Ej: ['crear_acta', 'auditar_votos']
        this.isAuthenticated = true;
      } catch (error) {
        this.user = null;
        this.permissions = [];
        this.isAuthenticated = false;
      }
    },

    async logout() {
      await axios.post('/logout');
      this.$reset(); // Limpia el store
      window.location.href = '/login';
    }
  }
});