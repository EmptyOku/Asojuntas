import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { startRouteLoading, stopRouteLoading } from '@/state/loading';

// ==========================================
// Layout único: el menú se arma con navigationFor() según permisos
// ==========================================
import AppLayout from '@/layouts/AppLayout.vue';

// Importación de vistas estáticas
import SecretaryDashboardView from '@/views/secretary/SecretaryDashboardView.vue';
import SecretaryCaptureView from '@/views/secretary/SecretaryCaptureView.vue';
import SecretaryPlanchasList from '@/views/secretary/SecretaryPlanchasList.vue';
import SecretaryPlanchaDetailView from '@/views/secretary/SecretaryPlanchaDetailView.vue';
import SecretaryNeighborhoodSlatesView from '@/views/secretary/SecretaryNeighborhoodSlatesView.vue';
import CandidatesDirectoryView from '@/views/admin/CandidatesDirectoryView.vue';
import NeighborhoodResultsView from '@/views/admin/NeighborhoodResultsView.vue';
import RegistrationPlatesView from '@/views/admin/RegistrationPlatesView.vue';

// ==========================================
// Permisos por ruta (roles dinámicos)
// ==========================================
// Cada ruta hija declara lo que exige:
// Los permisos son por tabla y operación (persons.view, neighborhoods.update…)
// más unas pocas acciones especiales; ver app/Support/PermissionCatalog.php.
//   meta.permission: 'x.view'            -> exige ese permiso
//   meta.anyPermission: ['a', 'b']       -> basta con uno
// y, si aparece en el menú, meta.nav:
//   { label, icon (ver ICONS en AppSidebar.vue), section, order, query?, position?: 'bottom' }
// `order` también define el inicio tras el login: la primera entrada del menú
// que el usuario puede abrir (Admin -> Secretaría -> Jurado -> Configuración).
// Los módulos (padres) solo aportan meta.title (título del header) y, si hace
// falta, meta.layout: 'narrow' (columna angosta, pensada para celular).
// Los permisos se declaran en app/Support/PermissionCatalog.php.

// Tablas que se administran desde el Panel (las mismas de app/Support/CrudCatalog.php).
const PANEL_TABLES = [
  'states', 'cities', 'communes', 'neighborhoods',
  'document_types', 'blocks', 'positions',
  'persons', 'users', 'roles', 'permissions',
  'elections', 'polling_tables', 'slates', 'candidate_drafts', 'candidates',
  'scrutiny_records', 'scrutiny_block_results', 'scrutiny_reviews',
];

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
  // Módulo de Administración
  // ==========================================
  {
    path: '/admin',
    component: AppLayout,
    meta: { requiresAuth: true, title: 'Panel de Administración', subtitle: 'Gestión centralizada de juntas y del proceso electoral' },
    children: [
      {
        path: 'dashboard', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboardView.vue'),
        meta: { permission: 'dashboard.view', nav: { label: 'Dashboard', icon: 'LayoutDashboard', section: 'Administración', order: 10 } }
      },
      {
        path: 'geography', name: 'admin-geography', component: () => import('@/views/admin/GeographyView.vue'),
        meta: { permission: 'neighborhoods.view', nav: { label: 'Geografía Electoral', icon: 'Map', section: 'Administración', order: 20 } }
      },
      {
        path: 'map', name: 'admin-map', component: () => import('@/views/admin/ElectoralMapView.vue'),
        meta: { permission: 'map.view', nav: { label: 'Mapa Interactivo', icon: 'MapPin', section: 'Administración', order: 30 } }
      },
      {
        path: 'candidates', name: 'admin.candidates', component: CandidatesDirectoryView,
        meta: { permission: 'candidates.view', nav: { label: 'Directorio JAC', icon: 'Users', section: 'Administración', order: 40 } }
      },
      {
        path: 'audit', name: 'admin-audit', component: () => import('@/views/admin/AuditView.vue'),
        meta: { permission: 'scrutiny_records.view', nav: { label: 'Auditoría de Actas', icon: 'FileCheck', section: 'Administración', order: 50 } }
      },
      {
        path: 'audit-logs', name: 'admin-audit-logs', component: () => import('@/views/admin/AuditLogsView.vue'),
        meta: { permission: 'audit_logs.view', nav: { label: 'Bitácora del Sistema', icon: 'ClipboardList', section: 'Administración', order: 60 } }
      },
      {
        path: 'registration', name: 'admin.registration', component: RegistrationPlatesView,
        meta: { permission: 'candidates.view', nav: { label: 'Revisión de planchas', icon: 'ShieldAlert', section: 'Administración', order: 70 } }
      },
      {
        path: 'audit/:id', name: 'admin-audit-detail', component: () => import('@/views/admin/VoteValidationView.vue'),
        meta: { permission: 'scrutiny_records.view' }
      },
      {
        path: 'neighborhood/:id/results', name: 'admin.neighborhood.results', component: NeighborhoodResultsView,
        meta: { anyPermission: ['candidates.view', 'neighborhoods.view', 'map.view'] }
      },
      {
        // Panel: CRUD de cada tabla. Aparece con que el rol pueda VER al menos
        // una; dentro, cada tabla y cada botón dependen de su propio permiso.
        path: 'panel', name: 'admin-panel', component: () => import('@/views/admin/PanelView.vue'),
        meta: {
          anyPermission: PANEL_TABLES.map((table) => `${table}.view`),
          title: 'Panel de datos',
          subtitle: 'Consulta y administración de cada tabla del sistema',
          nav: { label: 'Panel', icon: 'Database', section: 'Configuración', order: 890, position: 'bottom' },
        }
      },
      {
        path: 'roles', name: 'admin-roles', component: () => import('@/views/security-config/RolesPermissionsView.vue'),
        // Aquí se crean personas y cuentas, no solo roles: título propio en el header.
        meta: {
          anyPermission: ['users.view', 'persons.view', 'roles.view'],
          title: 'Administración de usuarios',
          subtitle: 'Personas, cuentas de acceso, roles y permisos',
          nav: { label: 'Usuarios y accesos', icon: 'UserCog', section: 'Configuración', order: 900, position: 'bottom' },
        }
      }
    ]
  },

  // ==========================================
  // Módulo de Secretaría (planchas)
  // ==========================================
  {
    path: '/secretary',
    component: AppLayout,
    meta: { requiresAuth: true, title: 'Secretaría Técnica', subtitle: 'Registro y revisión de planchas por barrio' },
    children: [
      {
        path: 'dashboard', name: 'secretary-dashboard', component: SecretaryDashboardView,
        meta: { permission: 'candidate_drafts.create', nav: { label: 'Dashboard', icon: 'LayoutDashboard', section: 'Secretaría', order: 110 } }
      },
      {
        path: 'capture', name: 'secretary-capture', component: SecretaryCaptureView,
        meta: { permission: 'candidate_drafts.create', nav: { label: 'Escanear Planchas', icon: 'Camera', section: 'Secretaría', order: 120, query: { doc: 'plancha' } } }
      },
      {
        path: 'planchas', name: 'secretary-planchas', component: SecretaryPlanchasList,
        meta: { permission: 'candidate_drafts.view', nav: { label: 'Auditoría de Planchas', icon: 'Files', section: 'Secretaría', order: 130 } }
      },
      {
        path: 'planchas-por-barrio', name: 'secretary-neighborhood-slates', component: SecretaryNeighborhoodSlatesView,
        meta: { permission: 'candidates.view', nav: { label: 'Planchas por Barrio', icon: 'MapPinned', section: 'Secretaría', order: 140 } }
      },
      {
        path: 'planchas/:id', name: 'secretary-plancha-detail', component: SecretaryPlanchaDetailView,
        meta: { permission: 'candidate_drafts.view' }
      }
    ]
  },

  // ==========================================
  // Módulo de Jurados (Solo Escrutinio)
  // ==========================================
  {
    path: '/jury',
    component: AppLayout,
    meta: { requiresAuth: true, title: 'Módulo de Jurados', subtitle: 'Carga de actas de mesa', layout: 'narrow' },
    children: [
      {
        path: 'dashboard', name: 'jury-dashboard', component: () => import('@/views/jury/JuryDashboardView.vue'),
        meta: { permission: 'scrutiny_records.create', nav: { label: 'Actas de mesa', icon: 'Camera', section: 'Jurado', order: 210 } }
      },
      { path: 'capture', name: 'jury-capture', component: () => import('@/views/jury/CaptureSlatesView.vue'), meta: { permission: 'scrutiny_records.create' } },
      { path: 'review', name: 'jury-review', component: () => import('@/views/jury/PreviousReviewView.vue'), meta: { permission: 'scrutiny_records.create' } }
    ]
  },

  // ==========================================
  // Rutas de Error
  // ==========================================
  {
    path: '/sin-modulos',
    name: 'no-modules',
    component: () => import('@/views/errors/NoModulesView.vue'),
    meta: { requiresAuth: true }
  },
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

/** ¿Los permisos del usuario cubren lo que exige este registro de ruta? */
function recordAllows(record, auth) {
  const { permission, anyPermission } = record.meta ?? {};
  if (permission && !auth.can(permission)) return false;
  if (anyPermission && !auth.canAny(anyPermission)) return false;
  return true;
}

/** ¿El usuario puede abrir esta ruta resuelta? Revisa toda la cadena (padre + hijas). */
export function canAccessRoute(route, auth = useAuthStore()) {
  return (route.matched ?? []).every((record) => recordAllows(record, auth));
}

/** Entradas del menú que el usuario puede ver, ordenadas por meta.nav.order. */
export function navigationFor(auth = useAuthStore()) {
  return router.getRoutes()
    .filter((record) => record.meta?.nav && record.name)
    .map((record) => router.resolve({ name: record.name, query: record.meta.nav.query }))
    .filter((route) => canAccessRoute(route, auth))
    .sort((a, b) => a.meta.nav.order - b.meta.nav.order)
    .map((route) => ({ ...route.meta.nav, name: route.name, to: route.fullPath }));
}

/** Inicio del usuario: la primera entrada del menú que puede abrir. */
export function firstAllowedRoute(auth = useAuthStore()) {
  const [first] = navigationFor(auth);
  return first ? first.to : { name: 'no-modules' };
}

// Guardia de Seguridad Global
router.beforeEach(async (to) => {
  startRouteLoading();

  const auth = useAuthStore();

  if (to.matched.some((record) => record.meta.requiresAuth)) {
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

    // Verificación de permisos (RBAC): la ruta y todos sus padres.
    if (!canAccessRoute(to, auth)) {
      return { name: 'unauthorized' };
    }
  }

  return true;
});

router.afterEach(() => {
  stopRouteLoading();
});

export default router;
