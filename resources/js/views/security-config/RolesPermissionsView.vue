<template>
  <div class="space-y-6">
    <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
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
      </div>

      <div v-if="errorMessage" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
        {{ errorMessage }}
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
          <h2 class="text-base font-semibold text-gray-900 mb-3">Crear Usuario</h2>
          <form class="space-y-3" @submit.prevent="createUser">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Usuario</label>
              <input v-model="createForm.username" required class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white" />
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Correo</label>
              <input v-model="createForm.email" required type="email" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white" />
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Contraseña</label>
              <input v-model="createForm.password" required type="password" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white" />
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Roles iniciales</label>
              <select v-model="createForm.roles" multiple class="w-full min-h-28 px-3 py-2 rounded-lg border border-gray-200 bg-white">
                <option v-for="role in roles" :key="role.id" :value="role.id">
                  {{ role.display_name }} ({{ role.name }})
                </option>
              </select>
            </div>

            <button
              type="submit"
              class="w-full py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-60"
              :disabled="loading || createForm.roles.length === 0"
            >
              Crear cuenta
            </button>
          </form>
        </div>

        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
          <h2 class="text-base font-semibold text-gray-900 mb-3">Catálogo de Roles</h2>
          <div class="max-h-80 overflow-auto space-y-3 pr-1">
            <div v-for="role in roles" :key="role.id" class="rounded-lg border border-gray-200 bg-white p-3">
              <p class="font-medium text-gray-900">{{ role.display_name }}</p>
              <p class="text-xs text-gray-500">{{ role.name }}</p>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <span
                  v-for="perm in role.permissions || []"
                  :key="perm.id"
                  class="text-xs px-2 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  {{ perm.name }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <h2 class="text-base sm:text-lg font-semibold text-gray-900">Listado de Usuarios</h2>
        <input
          v-model.trim="search"
          class="w-full sm:w-72 px-3 py-2 rounded-lg border border-gray-200"
          placeholder="Buscar por usuario/correo"
        />
      </div>

      <div class="overflow-auto border border-gray-100 rounded-xl">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600">
            <tr>
              <th class="text-left font-medium px-3 py-2.5">Usuario</th>
              <th class="text-left font-medium px-3 py-2.5">Correo</th>
              <th class="text-left font-medium px-3 py-2.5">Barrio</th>
              <th class="text-left font-medium px-3 py-2.5">Mesa Sugerida</th>
              <th class="text-left font-medium px-3 py-2.5">Estado</th>
              <th class="text-left font-medium px-3 py-2.5">Roles</th>
              <th class="text-left font-medium px-3 py-2.5">Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id" class="border-t border-gray-100">
              <td class="px-3 py-2.5 text-gray-900">{{ user.username }}</td>
              <td class="px-3 py-2.5 text-gray-700">{{ user.email }}</td>
              <td class="px-3 py-2.5 text-gray-700">
                <span v-if="user.person?.neighborhood">{{ user.person.neighborhood.name }}</span>
                <span v-else class="text-gray-400">Sin asignar</span>
              </td>
              <td class="px-3 py-2.5 text-gray-700">
                <template v-if="suggestedTableForUser(user)">
                  {{ suggestedTableForUser(user).name }} ({{ suggestedTableForUser(user).code }})
                </template>
                <span v-else class="text-gray-400">Sin mesa activa</span>
              </td>
              <td class="px-3 py-2.5">
                <span class="text-xs px-2 py-1 rounded-md" :class="user.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'">
                  {{ user.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="px-3 py-2.5">
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="role in user.roles || []" :key="role.id" class="text-xs px-2 py-1 rounded-md bg-blue-50 text-blue-700 border border-blue-200">
                    {{ role.display_name }}
                  </span>
                </div>
              </td>
              <td class="px-3 py-2.5">
                <button
                  type="button"
                  class="text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-black"
                  @click="openRoleEditor(user)"
                >
                  Editar Roles
                </button>
                <button
                  type="button"
                  class="text-xs px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50 ml-2"
                  @click="openNeighborhoodEditor(user)"
                >
                  Asignar Barrio
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="editingUser" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/40">
      <div class="w-full max-w-lg bg-white rounded-2xl border border-gray-100 shadow-xl p-5">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Editar roles de {{ editingUser.username }}</h3>
        <p class="text-sm text-gray-500 mb-4">Selecciona uno o más roles y guarda.</p>

        <select v-model="editingRoles" multiple class="w-full min-h-36 px-3 py-2 rounded-lg border border-gray-200 bg-white">
          <option v-for="role in roles" :key="role.id" :value="role.id">
            {{ role.display_name }} ({{ role.name }})
          </option>
        </select>

        <div class="flex items-center justify-end gap-2 mt-4">
          <button type="button" class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700" @click="closeRoleEditor">Cancelar</button>
          <button
            type="button"
            class="px-3 py-2 rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark disabled:opacity-60"
            :disabled="editingRoles.length === 0 || loading"
            @click="saveRoles"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>

    <div v-if="editingNeighborhoodUser" class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/40">
      <div class="w-full max-w-lg bg-white rounded-2xl border border-gray-100 shadow-xl p-5">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Asignar barrio a {{ editingNeighborhoodUser.username }}</h3>
        <p class="text-sm text-gray-500 mb-4">La mesa se sugiere automáticamente según la elección activa del barrio.</p>

        <label class="block text-sm text-gray-700 mb-1">Barrio</label>
        <select v-model="editingNeighborhoodId" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white">
          <option value="">Sin barrio asignado</option>
          <option v-for="item in assignmentContext" :key="item.id" :value="String(item.id)">
            {{ item.name }}
          </option>
        </select>

        <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
          <p class="font-medium">Mesa sugerida:</p>
          <p v-if="editingSuggestedTable">{{ editingSuggestedTable.name }} ({{ editingSuggestedTable.code }})</p>
          <p v-else class="text-gray-500">No hay mesa activa disponible para el barrio seleccionado.</p>
        </div>

        <div class="flex items-center justify-end gap-2 mt-4">
          <button type="button" class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700" @click="closeNeighborhoodEditor">Cancelar</button>
          <button
            type="button"
            class="px-3 py-2 rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark disabled:opacity-60"
            :disabled="loading"
            @click="saveNeighborhood"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from '@/services/axios';

const loading = ref(false);
const errorMessage = ref('');

const users = ref([]);
const roles = ref([]);
const search = ref('');
const assignmentContext = ref([]);

const createForm = ref({
  username: '',
  email: '',
  password: '',
  roles: [],
});

const editingUser = ref(null);
const editingRoles = ref([]);
const editingNeighborhoodUser = ref(null);
const editingNeighborhoodId = ref('');

const filteredUsers = computed(() => {
  if (!search.value) return users.value;
  const term = search.value.toLowerCase();
  return users.value.filter((u) =>
    (u.username || '').toLowerCase().includes(term) ||
    (u.email || '').toLowerCase().includes(term)
  );
});

const loadRoles = async () => {
  const { data } = await axios.get('/admin/roles');
  roles.value = data.data ?? [];
};

const loadUsers = async () => {
  const { data } = await axios.get('/admin/users');
  users.value = data.data?.data ?? data.data ?? [];
};

const loadAssignmentContext = async () => {
  const { data } = await axios.get('/admin/users/assignment-context');
  assignmentContext.value = Array.isArray(data.data) ? data.data : [];
};

const getSuggestedTableByNeighborhood = (neighborhoodId) => {
  const targetId = Number(neighborhoodId || 0);
  if (!targetId) {
    return null;
  }

  const item = assignmentContext.value.find((row) => Number(row.id) === targetId);
  return item?.suggested_polling_table || null;
};

const suggestedTableForUser = (user) => {
  const neighborhoodId = user?.person?.neighborhood_id;
  return getSuggestedTableByNeighborhood(neighborhoodId);
};

const editingSuggestedTable = computed(() => getSuggestedTableByNeighborhood(editingNeighborhoodId.value));

const loadAll = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    await Promise.all([loadRoles(), loadUsers(), loadAssignmentContext()]);
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'No fue posible cargar usuarios y roles.';
  } finally {
    loading.value = false;
  }
};

const resetCreateForm = () => {
  createForm.value = {
    username: '',
    email: '',
    password: '',
    roles: [],
  };
};

const createUser = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    await axios.post('/admin/users', createForm.value);
    resetCreateForm();
    await loadUsers();
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'No se pudo crear el usuario.';
  } finally {
    loading.value = false;
  }
};

const openRoleEditor = (user) => {
  editingUser.value = user;
  editingRoles.value = (user.roles || []).map((r) => r.id);
};

const closeRoleEditor = () => {
  editingUser.value = null;
  editingRoles.value = [];
};

const openNeighborhoodEditor = (user) => {
  editingNeighborhoodUser.value = user;
  editingNeighborhoodId.value = user?.person?.neighborhood_id ? String(user.person.neighborhood_id) : '';
};

const closeNeighborhoodEditor = () => {
  editingNeighborhoodUser.value = null;
  editingNeighborhoodId.value = '';
};

const saveRoles = async () => {
  if (!editingUser.value) return;

  loading.value = true;
  errorMessage.value = '';
  try {
    await axios.put(`/admin/users/${editingUser.value.id}/roles`, {
      roles: editingRoles.value,
    });

    await loadUsers();
    closeRoleEditor();
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'No se pudieron actualizar los roles.';
  } finally {
    loading.value = false;
  }
};

const saveNeighborhood = async () => {
  if (!editingNeighborhoodUser.value) {
    return;
  }

  loading.value = true;
  errorMessage.value = '';

  try {
    await axios.put(`/admin/users/${editingNeighborhoodUser.value.id}/neighborhood`, {
      neighborhood_id: editingNeighborhoodId.value ? Number(editingNeighborhoodId.value) : null,
    });

    await loadUsers();
    closeNeighborhoodEditor();
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'No se pudo actualizar el barrio del usuario.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadAll);
</script>