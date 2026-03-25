import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// Los Layouts se importan de forma estática (cargan inmediatamente con la app)
import AdminLayout from '@/layouts/AdminLayout.vue';
import JuryLayout from '@/layouts/JuryLayout.vue'
import path from 'node:path';
import GeographyView from '../views/admin/GeographyView.vue';

const routes = [
  // Redirección inicial: Si entran a "/", mandarlos a login
  { 
    path: '/', 
    redirect: '/login' 
  },
  
  { 
    path: '/login', 
    name: 'login', 
    // Usamos Lazy Loading (Carga perezosa) para las vistas
    component: () => import('@/views/auth/LoginView.vue') 
  },

  // ==========================================
  // Módulo de Jurados
  // ==========================================
  { 
    path: '/jury',
    component: JuryLayout, // <--- Conecta el layout aquí
    meta: { requiresAuth: true, permission: 'capturar_actas' },
    children: [
      { path: 'dashboard', name: 'jury-dashboard', component: () => import('@/views/jury/JuryDashboardView.vue') },
      { path: 'capture', name: 'jury-capture', component: () => import('@/views/jury/CaptureSlatesView.vue') },
    ]
  },

  // ==========================================
  // Módulo de Administración
  // ==========================================
  {
    path: '/admin',
    component: AdminLayout, // <--- CORRECCIÓN CLAVE: Esto envuelve a todos los 'children' en tu menú lateral
    meta: { requiresAuth: true, permission: 'acceso_admin' },
    children: [
      { path: 'dashboard', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboardView.vue') },
      { path: 'audit', name: 'admin-audit', component: () => import('@/views/admin/AuditView.vue') },
      {path: 'geography', name: 'admin-geagraphy', component: () => import('@/views/admin/GeographyView.vue')},
      { path: 'audit/:id', name: 'admin-audit-detail', component: () => import('@/views/admin/VoteValidationView.vue') },
      { path: 'roles', name: 'admin-roles', component: () => import('@/views/security-config/RolesPermissionsView.vue') },
    ]
  },

  // ==========================================
  // Rutas de Error
  // ==========================================
  { 
    path: '/unauthorized', 
    name: 'unauthorized', 
    component: () => import('@/views/errors/UnauthorizedView.vue') 
  },
  
  // Catch-all para Vue (404 dentro de la App)
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/errors/NotFoundView.vue')
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

// Guardia de Seguridad Global
router.beforeEach(async (to, from) => {
  const auth = useAuthStore();
  
  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated) {
      // CORRECCIÓN PARA EL MOCK: Validamos si la función existe antes de llamarla.
      // Como estamos usando un simulador temporal, fetchUser no existe activo. 
      // Cuando descomentes tu código de Sanctum, esto funcionará perfecto con tu backend.
      if (typeof auth.fetchUser === 'function') {
        try {
          await auth.fetchUser();
        } catch (error) {
          // Fallo silencioso en caso de error de red
        }
      }
      
      if (!auth.isAuthenticated) return { name: 'login' }; 
    }

    // Verificación de permisos (RBAC)
    if (to.meta.permission && !auth.permissions.includes(to.meta.permission)) {
      return { name: 'unauthorized' }; 
    }
  }
  
  return true; 
});

export default router;