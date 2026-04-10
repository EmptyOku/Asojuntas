import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { startRouteLoading, stopRouteLoading } from '@/state/loading';

// ==========================================
// Importación de Layouts (Estáticos)
// ==========================================
import AdminLayout from '@/layouts/AdminLayout.vue';
import JuryLayout from '@/layouts/JuryLayout.vue';
import SecretaryLayout from '@/layouts/SecretaryLayout.vue';

// Importación de vistas estáticas
import SecretaryDashboardView from '@/views/secretary/SecretaryDashboardView.vue';
import SecretaryPlanchasList from '@/views/secretary/SecretaryPlanchasList.vue';
import SecretaryPlanchaDetailView from '@/views/secretary/SecretaryPlanchaDetailView.vue';
import CandidatesDirectoryView from '@/views/admin/CandidatesDirectoryView.vue';
import NeighborhoodResultsView from '@/views/admin/NeighborhoodResultsView.vue';
import RegistrationPlatesView from '@/views/admin/RegistrationPlatesView.vue';

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
  // Módulo de Jurados (Solo Escrutinio)
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
  // Módulo de Secretaría 
  // ==========================================
  {
    path: '/secretary',
    component: SecretaryLayout,
    // TODO: Ajusta el 'permission' según lo que tengas en tu base de datos para la secretaria
    meta: { requiresAuth: true, permission: 'records.upload' }, 
    children: [
      { path: 'dashboard', name: 'secretary-dashboard', component: SecretaryDashboardView },
      { path: 'capture', name: 'secretary-capture', component: () => import('@/views/jury/CaptureSlatesView.vue') },
      { path: 'planchas', name: 'secretary-planchas', component: SecretaryPlanchasList },
      { path: 'planchas/:id', name: 'secretary-plancha-detail', component: SecretaryPlanchaDetailView }
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
      { path: 'audit-logs', name: 'admin-audit-logs', component: () => import('@/views/admin/AuditLogsView.vue'), meta: { permission: 'audit.view' } },
      { path: 'geography', name: 'admin-geography', component: () => import('@/views/admin/GeographyView.vue') },
      { path: 'audit/:id', name: 'admin-audit-detail', component: () => import('@/views/admin/VoteValidationView.vue') },
      { path: 'roles', name: 'admin-roles', component: () => import('@/views/security-config/RolesPermissionsView.vue') },
      { path: 'candidates', name: 'admin.candidates', component: CandidatesDirectoryView },
      { path: 'neighborhood/:id/results', name: 'admin.neighborhood.results', component: NeighborhoodResultsView },
      { path: 'registration', name: 'admin.registration', component: RegistrationPlatesView}
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
  startRouteLoading();

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

router.afterEach(() => {
  stopRouteLoading();
});

export default router;