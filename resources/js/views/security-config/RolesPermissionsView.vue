<template>
  <div class="space-y-6">
    <nav class="flex items-center gap-2 border-b border-gray-200 overflow-x-auto" aria-label="Administración">
      <button
        v-for="tab in visibleTabs"
        :key="tab.key"
        type="button"
        class="px-4 py-3 text-sm font-semibold border-b-2 whitespace-nowrap"
        :class="activeTab === tab.key ? 'border-aso-primary text-aso-primary' : 'border-transparent text-gray-500'"
        @click="activeTab = tab.key"
      >
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
      @reload="loadAll"
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
      <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Gestión de Personas</h1>
            <p class="text-sm text-gray-500 mt-1">Consulta y edita las personas registradas.</p>
          </div>
          <button
            v-can="'users.create'"
            type="button"
            class="px-3 py-2 text-sm font-medium rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark transition-colors"
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
      <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Gestión de Usuarios</h1>
            <p class="text-sm text-gray-500 mt-1">Administra cuentas, roles y estado de acceso.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors"
              @click="loadAll"
              :disabled="loading"
            >
              Recargar
            </button>
            <button
              v-can="'users.create'"
              type="button"
              class="px-3 py-2 text-sm font-medium rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark transition-colors"
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
import { computed, onMounted, ref } from 'vue';
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
const TABS = [
  { key: 'create', label: 'Creación de usuarios', permission: 'users.create' },
  { key: 'roles', label: 'Gestión de roles y permisos', permission: 'roles.view' },
  { key: 'persons', label: 'Gestión de personas', permission: 'users.view' },
  { key: 'users', label: 'Gestión de usuarios', permission: 'users.view' },
];
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
const usersPerPage = ref(20);
const usersCurrentPage = ref(1);
const usersLastPage = ref(1);
const usersTotal = ref(0);
const usersFrom = ref(0);
const usersTo = ref(0);

const personsSearch = ref('');
const personsPerPage = ref(20);
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
  roles.value = data.data ?? [];
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

const loadAll = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    // Solo se pide lo que el rol puede ver: evita 403 (y el refresco de permisos que disparan).
    const canViewRoles = authStore.can('roles.view');
    const canViewUsers = authStore.can('users.view');
    await Promise.all([
      loadCommunes(),
      canViewRoles && loadRoles().catch((e) => {
        console.error('Error loading roles:', e?.response?.status, e?.message);
        throw e;
      }),
      canViewRoles && loadPermissions(),
      canViewUsers && loadUsers().catch((e) => {
        console.error('Error loading users:', e?.response?.status, e?.message);
        throw e;
      }),
      canViewUsers && loadAssignmentContext().catch((e) => {
        console.error('Error loading assignment context:', e?.response?.status, e?.message);
      }),
    ]);
  } catch (error) {
    console.error('Full error:', error);
    errorMessage.value = 'No fue posible cargar la información inicial.';
  }

  // Se carga después del bloque anterior (no dentro del mismo Promise.all) para no sumar
  // una petición concurrente adicional: en este entorno de desarrollo el servidor PHP es de
  // un solo hilo y eso ya provocaba timeouts de 30s incluso antes de agregar este listado.
  try {
    if (authStore.can('users.view')) await loadPersons();
  } catch (error) {
    console.error('Error loading persons:', error?.response?.status, error?.message);
    errorMessage.value = errorMessage.value || 'No fue posible cargar el listado de personas.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadAll();
});
</script>
