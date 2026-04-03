import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// Los Layouts se importan de forma estática
import AdminLayout from '@/layouts/AdminLayout.vue';
import JuryLayout from '@/layouts/JuryLayout.vue';

// Vistas para el módulo de candidatos y resultados
import CandidatesDirectoryView from '@/views/admin/CandidatesDirectoryView.vue';
import NeighborhoodResultsView from '@/views/admin/NeighborhoodResultsView.vue';

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue')
  },

  // ==========================================
  // Módulo de Jurados
  // ==========================================
  {
    path: '/jury',
    component: JuryLayout,
    meta: { requiresAuth: true, permission: 'records.upload' },
    children: [
      { path: 'dashboard', name: 'jury-dashboard', component: () => import('@/views/jury/JuryDashboardView.vue') },
      { path: 'capture', name: 'jury-capture', component: () => import('@/views/jury/CaptureSlatesView.vue') },
      { path: 'review', name: 'jury-review', component: () => import('@/views/jury/PreviousReviewView.vue') }
    ]
  },

  // ==========================================
  // Módulo de Administración
  // ==========================================
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, permission: 'users.view' },
    children: [
      { path: 'dashboard', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboardView.vue') },
      { path: 'audit', name: 'admin-audit', component: () => import('@/views/admin/AuditView.vue') },
      { path: 'geography', name: 'admin-geography', component: () => import('@/views/admin/GeographyView.vue') },
      { path: 'audit/:id', name: 'admin-audit-detail', component: () => import('@/views/admin/VoteValidationView.vue') },
      { path: 'roles', name: 'admin-roles', component: () => import('@/views/security-config/RolesPermissionsView.vue') },
      { path: 'neighborhood/:id/results', name: 'admin-neighborhood-results', component: () => import('@/views/admin/NeighborhoodResultsView.vue') },

      // RUTAS DE CANDIDATOS Y RESULTADOS
      {
        path: 'candidates',
        name: 'admin.candidates',
        component: CandidatesDirectoryView
      },
      {
        path: 'neighborhood/:id/results', // URL específica para resultados
        name: 'admin.neighborhood.results',
        component: NeighborhoodResultsView
      }
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
router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated) {
      if (typeof auth.fetchUser === 'function') {
        try {
          await auth.fetchUser();
        } catch {
          // Fallo silencioso
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
