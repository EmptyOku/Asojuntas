<template>
  <div class="space-y-6">
    <nav class="flex items-center gap-2 border-b border-gray-200" aria-label="Administración">
      <button type="button" class="px-4 py-3 text-sm font-semibold border-b-2" :class="activeTab === 'users' ? 'border-aso-primary text-aso-primary' : 'border-transparent text-gray-500'" @click="activeTab = 'users'">Usuarios y Personas</button>
      <button type="button" class="px-4 py-3 text-sm font-semibold border-b-2" :class="activeTab === 'roles' ? 'border-aso-primary text-aso-primary' : 'border-transparent text-gray-500'" @click="activeTab = 'roles'">Roles y Permisos</button>
    </nav>

    <div v-if="errorMessage" v-show="activeTab === 'users'" class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
      {{ errorMessage }}
    </div>

    <PersonCreateForm
      v-show="activeTab === 'users'"
      :communes="communes"
      @reload="loadAll"
      @show-result="showResult"
      @assignment-context-change="(list) => (assignmentContext = list)"
    />

    <PersonsTable
      v-show="activeTab === 'users'"
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

    <section v-show="activeTab === 'users'" class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
      <div class="flex items-start justify-between gap-3 mb-4">
        <div>
          <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Usuarios y Roles</h1>
          <p class="text-sm text-gray-500 mt-1">Gestiona cuentas y asignaciones de rol desde la API.</p>
        </div>
        <button
          type="button"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark transition-colors"
          @click="loadAll"
          :disabled="loading"
        >
          Recargar
        </button>
        <button type="button" class="px-3 py-2 text-sm font-medium rounded-lg bg-gray-900 text-white hover:bg-black" @click="completeUserOpen = true">Nuevo Registro</button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
        <UserCreateForm :roles="roles" @reload="loadUsers" @show-result="showResult" />
        <RoleSummaryList :roles="roles" />
      </div>
    </section>

    <UsersTable
      v-show="activeTab === 'users'"
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

    <RoleCatalogEditor
      v-if="activeTab === 'roles'"
      :permissions="permissions"
      @reload="loadRoles"
      @reload-users="loadUsers"
      @show-result="showResult"
    />

    <CompleteUserModal
      :open="completeUserOpen"
      :communes="communes"
      :roles="roles"
      @close="completeUserOpen = false"
      @reload="loadAll"
      @show-result="showResult"
    />

    <RoleEditorModal :user="editingUser" :roles="roles" @close="editingUser = null" @reload="loadUsers" />

    <UserEditorModal :user="editingUserData" :communes="communes" @close="editingUserData = null" @reload="loadUsers" />

    <PasswordResetModal :user="resettingUser" @close="resettingUser = null" />

    <PersonEditorModal :person="editingPerson" :communes="communes" @close="editingPerson = null" @reload="loadPersons" />

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
import { onMounted, ref } from 'vue';
import axios from '@/services/axios';
import PersonCreateForm from '@/components/security-config/PersonCreateForm.vue';
import PersonsTable from '@/components/security-config/PersonsTable.vue';
import PersonEditorModal from '@/components/security-config/PersonEditorModal.vue';
import UserCreateForm from '@/components/security-config/UserCreateForm.vue';
import RoleSummaryList from '@/components/security-config/RoleSummaryList.vue';
import UsersTable from '@/components/security-config/UsersTable.vue';
import RoleCatalogEditor from '@/components/security-config/RoleCatalogEditor.vue';
import CompleteUserModal from '@/components/security-config/CompleteUserModal.vue';
import RoleEditorModal from '@/components/security-config/RoleEditorModal.vue';
import UserEditorModal from '@/components/security-config/UserEditorModal.vue';
import PasswordResetModal from '@/components/security-config/PasswordResetModal.vue';
import ResultModal from '@/components/ResultModal.vue';

const loading = ref(false);
const activeTab = ref('users');
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

const completeUserOpen = ref(false);
const editingUser = ref(null);
const editingUserData = ref(null);
const resettingUser = ref(null);
const editingPerson = ref(null);

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

const loadAll = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    await Promise.all([
      loadCommunes(),
      loadRoles().catch((e) => {
        console.error('Error loading roles:', e?.response?.status, e?.message);
        throw e;
      }),
      loadPermissions(),
      loadUsers().catch((e) => {
        console.error('Error loading users:', e?.response?.status, e?.message);
        throw e;
      }),
    ]);
  } catch (error) {
    console.error('Full error:', error);
    errorMessage.value = 'No fue posible cargar la información inicial.';
  }

  // Se carga después del bloque anterior (no dentro del mismo Promise.all) para no sumar
  // una quinta petición concurrente: en este entorno de desarrollo el servidor PHP es de
  // un solo hilo y eso ya provocaba timeouts de 30s incluso antes de agregar este listado.
  try {
    await loadPersons();
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
