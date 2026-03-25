import { defineStore } from 'pinia';
// import axios from 'axios'; // Comentado temporalmente

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    permissions: [],
    isAuthenticated: false,
    loading: false
  }),
  
  actions: {
    // =========================================================
    // 1. MODO DESARROLLO: SIMULADOR (MOCK) - ACTIVO
    // Usa esto para diseñar las pantallas sin depender del backend
    // =========================================================
    async login(credentials) {
      this.loading = true;
      return new Promise((resolve, reject) => {
        setTimeout(() => {
          this.loading = false;
          // Simulamos un Jurado
          if (credentials.identity.toLowerCase().includes('jurado')) {
            this.user = { id: 1, name: 'Juan Jurado', role: 'jurado' };
            this.permissions = ['capturar_actas'];
            this.isAuthenticated = true;
            resolve(true);
          }
          // Simulamos al Administrador
          else if (credentials.identity.toLowerCase().includes('admin')) {
            this.user = { id: 2, name: 'Director Asojuntas', role: 'admin' };
            this.permissions = ['acceso_admin', 'auditar_votos'];
            this.isAuthenticated = true;
            resolve(true);
          } else {
            reject(new Error('Credenciales inválidas'));
          }
        }, 1500); 
      });
    },

    logout() {
      this.user = null;
      this.permissions = [];
      this.isAuthenticated = false;
      window.location.href = '/login';
    }

    // =========================================================
    // 2. MODO PRODUCCIÓN: LARAVEL SANCTUM REAL - INACTIVO
    // Descomenta este bloque y borra el simulador cuando la BD exista
    // =========================================================
    /*
    async login(credentials) {
      this.loading = true;
      try {
        await axios.get('/sanctum/csrf-cookie');
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
        const response = await axios.get('/api/user');
        this.user = response.data.user;
        this.permissions = response.data.permissions;
        this.isAuthenticated = true;
      } catch (error) {
        this.user = null;
        this.permissions = [];
        this.isAuthenticated = false;
      }
    },

    async logout() {
      await axios.post('/logout');
      this.$reset();
      window.location.href = '/login';
    }
    */
  }
});