import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
  // Rutas Públicas
  { path: '/login', name: 'login', component: () => import('@/views/auth/LoginView.vue') },
  
  // Rutas de Jurado (Mobile)
  { 
    path: '/jury',
    meta: { requiresAuth: true, permission: 'capturar_actas' },
    children: [
      { path: 'dashboard', component: () => import('@/views/jury/JuryDashboardView.vue') },
      { path: 'capture', component: () => import('@/views/jury/CaptureSlatesView.vue') },
    ]
  },

  // Rutas de Administrador (Desktop)
  {
    path: '/admin',
    meta: { requiresAuth: true, permission: 'acceso_admin' },
    children: [
      { path: 'dashboard', component: () => import('@/views/security-config/AdminDashboardView.vue') },
      { path: 'audit', component: () => import('@/views/admin/AuditView.vue') },
      { path: 'roles', component: () => import('@/views/security-config/RolesPermissionsView.vue') },
    ]
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

// Guardia de Seguridad Global
router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore();
  
  // 1. Si la ruta requiere autenticación
  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated) {
      await auth.fetchUser(); // Intentar recuperar sesión si refrescó la página
      if (!auth.isAuthenticated) return next({ name: 'login' });
    }

    // 2. Si la ruta requiere un permiso específico (Escalabilidad RBAC)
    if (to.meta.permission && !auth.permissions.includes(to.meta.permission)) {
      return next({ name: 'unauthorized' }); // Redirigir a tu Error 403
    }
  }
  
  next();
});

export default router;