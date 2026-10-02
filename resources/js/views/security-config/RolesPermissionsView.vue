<template>
  <div class="space-y-6">
    <nav class="flex items-center gap-1 p-1 rounded-2xl bg-white border border-gray-200/70 overflow-x-auto w-fit max-w-full shadow-sm" aria-label="Administración de usuarios">
      <button
        v-for="tab in visibleTabs"
        :key="tab.key"
        type="button"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200"
        :class="activeTab === tab.key ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/25' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100'"
        :aria-current="activeTab === tab.key ? 'page' : undefined"
        @click="activeTab = tab.key"
      >
        <component :is="tab.icon" class="w-4 h-4" />
        {{ tab.label }}
      </button>
    </nav>

    <div v-if="errorMessage" class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
      {{ errorMessage }}
    </div>

    <UserCreationWizard
      v-if="authStore.can('users.create')"
      v-show="activeTab === 'create'"
      :communes="communes"
      :roles="roles"
      @reload="markListsStale"
      @show-result="showResult"
      @created="activeTab = 'users'"
    />

    <template v-if="activeTab === 'roles'">
      <RoleCatalogEditor
        :permissions="permissions"
        @reload="loadRoles"
        @reload-users="loadUsers"
        @show-result="showResult"
      />
    </template>

    <template v-if="activeTab === 'persons'">
      <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="page-title">Gestión de Personas</h1>
            <p class="page-subtitle">Consulta y edita las personas registradas.</p>
          </div>
          <button
            v-can="'users.create'"
            type="button"
            class="btn-primary px-3"
            @click="creatingPersonOpen = true"
          >
            Crear Persona
          </button>
        </div>
      </section>

      <PersonsTable
        :persons="persons"
        :persons-per-page="personsPerPage"
        :persons-current-page="personsCurrentPage"
        :persons-last-page="personsLastPage"
        :persons-total="personsTotal"
        :persons-from="personsFrom"
        :persons-to="personsTo"
        @search-change="onPersonsSearchChange"
        @per-page-change="onPersonsPerPageChange"
        @page-change="onPersonsPageChange"
        @edit-person="(person) => (editingPerson = person)"
      />
    </template>

    <template v-if="activeTab === 'users'">
      <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="page-title">Gestión de Usuarios</h1>
            <p class="page-subtitle">Administra cuentas, roles y estado de acceso.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="btn-secondary px-3"
              @click="reloadActiveTab"
              :disabled="loading"
            >
              <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
              Recargar
            </button>
            <button
              v-can="'users.create'"
              type="button"
              class="btn-primary px-3"
              @click="creatingUserOpen = true"
            >
              Crear Usuario
            </button>
          </div>
        </div>
      </section>

      <UsersTable
        :users="users"
        :users-per-page="usersPerPage"
        :users-current-page="usersCurrentPage"
        :users-last-page="usersLastPage"
        :users-total="usersTotal"
        :users-from="usersFrom"
        :users-to="usersTo"
        :assignment-context="assignmentContext"
        @search-change="onUsersSearchChange"
        @per-page-change="onUsersPerPageChange"
        @page-change="onUsersPageChange"
        @edit-roles="(user) => (editingUser = user)"
        @edit-user="(user) => (editingUserData = user)"
        @reset-password="(user) => (resettingUser = user)"
        @reload="loadUsers"
        @show-result="showResult"
      />
    </template>

    <RoleEditorModal :user="editingUser" :roles="roles" @close="editingUser = null" @reload="loadUsers" @show-result="showResult" />

    <UserEditorModal :user="editingUserData" :communes="communes" @close="editingUserData = null" @reload="loadUsers" @show-result="showResult" />

    <PasswordResetModal :user="resettingUser" @close="resettingUser = null" @show-result="showResult" />

    <PersonEditorModal :person="editingPerson" :communes="communes" @close="editingPerson = null" @reload="loadPersons" @show-result="showResult" />

    <PersonCreateModal :open="creatingPersonOpen" :communes="communes" @close="creatingPersonOpen = false" @reload="loadPersons" @show-result="showResult" />

    <UserCreateModal :open="creatingUserOpen" :roles="roles" @close="creatingUserOpen = false" @reload="loadUsers" @show-result="showResult" />

    <ResultModal
      :open="resultModal.open"
      :success="resultModal.success"
      :title="resultModal.title"
      :message="resultModal.message"
      @close="resultModal.open = false"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { IdCard, RefreshCw, ShieldCheck, UserPlus, Users } from 'lucide-vue-next';
import axios from '@/services/axios';
import { useAuthStore } from '@/stores/auth';
import UserCreationWizard from '@/components/security-config/UserCreationWizard.vue';
import PersonsTable from '@/components/security-config/PersonsTable.vue';
import PersonEditorModal from '@/components/security-config/PersonEditorModal.vue';
import PersonCreateModal from '@/components/security-config/PersonCreateModal.vue';
import UserCreateModal from '@/components/security-config/UserCreateModal.vue';
import UsersTable from '@/components/security-config/UsersTable.vue';
import RoleCatalogEditor from '@/components/security-config/RoleCatalogEditor.vue';
import RoleEditorModal from '@/components/security-config/RoleEditorModal.vue';
import UserEditorModal from '@/components/security-config/UserEditorModal.vue';
import PasswordResetModal from '@/components/security-config/PasswordResetModal.vue';
import ResultModal from '@/components/ResultModal.vue';

const authStore = useAuthStore();

// Cada pestaña se muestra solo con el permiso que exige su API.
// Orden: primero crear y consultar cuentas (lo más usado), al final la configuración de roles.
const TABS = [
  { key: 'create', label: 'Nueva cuenta', permission: 'users.create', icon: UserPlus },
  { key: 'users', label: 'Usuarios', permission: 'users.view', icon: Users },
  { key: 'persons', label: 'Personas', permission: 'users.view', icon: IdCard },
  { key: 'roles', label: 'Roles y permisos', permission: 'roles.view', icon: ShieldCheck },
];

// Registros por página de los listados de personas y usuarios.
const DEFAULT_PER_PAGE = 10;
const visibleTabs = computed(() => TABS.filter((tab) => authStore.can(tab.permission)));

const loading = ref(false);
const activeTab = ref(visibleTabs.value[0]?.key ?? 'users');
const errorMessage = ref('');

const users = ref([]);
const persons = ref([]);
const roles = ref([]);
const permissions = ref([]);
const communes = ref([]);
const assignmentContext = ref([]);

const search = ref('');
const usersPerPage = ref(DEFAULT_PER_PAGE);
const usersCurrentPage = ref(1);
const usersLastPage = ref(1);
const usersTotal = ref(0);
const usersFrom = ref(0);
const usersTo = ref(0);

const personsSearch = ref('');
const personsPerPage = ref(DEFAULT_PER_PAGE);
const personsCurrentPage = ref(1);
const personsLastPage = ref(1);
const personsTotal = ref(0);
const personsFrom = ref(0);
const personsTo = ref(0);

const editingUser = ref(null);
const editingUserData = ref(null);
const resettingUser = ref(null);
const editingPerson = ref(null);
const creatingPersonOpen = ref(false);
const creatingUserOpen = ref(false);

const resultModal = ref({ open: false, success: true, title: '', message: '' });
const showResult = (success, title, message) => {
  resultModal.value = { open: true, success, title, message };
};

const loadRoles = async () => {
  const { data } = await axios.get('/admin/roles', { skipGlobalLoading: true });
  // Solo los roles que la cuenta actual puede asignar (no puede otorgar
  // permisos que no tiene); el backend lo exige igual con un 403.
  roles.value = (data.data ?? []).filter((role) => role.grantable !== false);
};

const loadPermissions = async () => {
  const { data } = await axios.get('/admin/permissions', { skipGlobalLoading: true });
  permissions.value = data.data ?? [];
};

// El listado se pagina y se filtra en el backend (usuario/correo/documento/nombre/barrio/comuna),
// para no traer de golpe todos los registros contra una DB que puede tener latencia alta.
const loadUsers = async () => {
  const { data } = await axios.get('/admin/users', {
    params: {
      search: search.value || undefined,
      per_page: usersPerPage.value,
      page: usersCurrentPage.value,
    },
    skipGlobalLoading: true,
  });
  const payload = data.data;
  users.value = payload?.data ?? [];
  usersCurrentPage.value = payload?.current_page ?? 1;
  usersLastPage.value = payload?.last_page ?? 1;
  usersTotal.value = payload?.total ?? users.value.length;
  usersFrom.value = payload?.from ?? 0;
  usersTo.value = payload?.to ?? 0;
};

const onUsersSearchChange = (value) => {
  search.value = value;
  usersCurrentPage.value = 1;
  loadUsers();
};

const onUsersPerPageChange = (value) => {
  usersPerPage.value = value;
  usersCurrentPage.value = 1;
  loadUsers();
};

const onUsersPageChange = (page) => {
  if (page < 1 || page > usersLastPage.value || page === usersCurrentPage.value) return;
  usersCurrentPage.value = page;
  loadUsers();
};

// Igual que loadUsers: el listado se pagina y filtra en el backend.
const loadPersons = async () => {
  const { data } = await axios.get('/admin/persons', {
    params: {
      search: personsSearch.value || undefined,
      per_page: personsPerPage.value,
      page: personsCurrentPage.value,
    },
    skipGlobalLoading: true,
  });
  const payload = data.data;
  persons.value = payload?.data ?? [];
  personsCurrentPage.value = payload?.current_page ?? 1;
  personsLastPage.value = payload?.last_page ?? 1;
  personsTotal.value = payload?.total ?? persons.value.length;
  personsFrom.value = payload?.from ?? 0;
  personsTo.value = payload?.to ?? 0;
};

const onPersonsSearchChange = (value) => {
  personsSearch.value = value;
  personsCurrentPage.value = 1;
  loadPersons();
};

const onPersonsPerPageChange = (value) => {
  personsPerPage.value = value;
  personsCurrentPage.value = 1;
  loadPersons();
};

const onPersonsPageChange = (page) => {
  if (page < 1 || page > personsLastPage.value || page === personsCurrentPage.value) return;
  personsCurrentPage.value = page;
  loadPersons();
};

const loadCommunes = async () => {
  const { data } = await axios.get('/admin/neighborhoods/communes', { skipGlobalLoading: true });
  communes.value = data.data ?? [];
};

// Usada por UsersTable para la columna "Mesa Sugerida". Se carga aparte (sin comuna)
// para no depender de que se haya elegido una comuna en el wizard de creación.
const loadAssignmentContext = async () => {
  const { data } = await axios.get('/admin/users/assignment-context', { skipGlobalLoading: true });
  assignmentContext.value = Array.isArray(data.data) ? data.data : [];
};

// Cada pestaña pide solo sus datos, la primera vez que se abre (antes se pedían
// seis listados al entrar y otra vez tras crear un usuario). En desarrollo el
// servidor PHP atiende una petición a la vez, así que se piden en serie.
const LOADERS = {
  communes: { load: loadCommunes },
  roles: { load: loadRoles, permission: 'roles.view' },
  permissions: { load: loadPermissions, permission: 'roles.view' },
  users: { load: loadUsers, permission: 'users.view' },
  assignment: { load: loadAssignmentContext, permission: 'users.view' },
  persons: { load: loadPersons, permission: 'users.view' },
};

// RoleCatalogEditor carga sus propios roles; aquí solo necesita los permisos.
const TAB_DATA = {
  create: ['communes', 'roles'],
  roles: ['permissions'],
  persons: ['persons', 'communes'],
  users: ['users', 'assignment', 'roles', 'communes'],
};

const loaded = new Set();

const ensureTabData = async (tabKey, { force = false } = {}) => {
  // Solo se pide lo que el rol puede ver: evita 403 (y el refresco de permisos que disparan).
  const keys = (TAB_DATA[tabKey] ?? []).filter((key) => {
    const permission = LOADERS[key].permission;
    return (force || !loaded.has(key)) && (!permission || authStore.can(permission));
  });
  if (!keys.length) return;

  loading.value = true;
  errorMessage.value = '';
  for (const key of keys) {
    try {
      await LOADERS[key].load();
      loaded.add(key);
    } catch (error) {
      console.error(`Error cargando ${key}:`, error?.response?.status, error?.message);
      errorMessage.value = 'No fue posible cargar parte de la información. Intenta recargar.';
    }
  }
  loading.value = false;
};

const reloadActiveTab = () => ensureTabData(activeTab.value, { force: true });

// Tras crear una persona y su cuenta, los listados quedan viejos: se vuelven a
// pedir cuando se abra su pestaña (el asistente lleva directo a Usuarios).
const markListsStale = () => {
  ['users', 'persons', 'assignment'].forEach((key) => loaded.delete(key));
};

watch(activeTab, (tab) => ensureTabData(tab), { immediate: true });
</script>
