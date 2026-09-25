<template>
  <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <h2 class="text-base sm:text-lg font-semibold text-gray-900">Listado de Usuarios</h2>
      <input
        v-model.trim="search"
        @input="handleUsersSearchInput"
        class="w-full sm:w-80 px-3 py-2 rounded-lg border border-gray-200 text-sm"
        placeholder="Buscar por usuario, correo, documento, barrio o comuna..."
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
          <tr v-for="user in users" :key="user.id" class="border-t border-gray-100">
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
              <div class="flex flex-wrap gap-2">
                <button v-can="'roles.assign'" type="button" class="text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-black" @click="$emit('edit-roles', user)">Editar Roles</button>
                <button v-can="'users.update'" type="button" class="text-xs px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50" @click="$emit('edit-user', user)">Editar Usuario</button>
                <button v-can="'users.update'" type="button" class="text-xs px-3 py-1.5 rounded-md border border-amber-200 text-amber-700 hover:bg-amber-50" @click="$emit('reset-password', user)">Restablecer Contraseña</button>
                <button
                  v-can="'users.update'"
                  type="button"
                  class="text-xs px-3 py-1.5 rounded-md border"
                  :class="user.is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                  @click="askToggleUser(user)"
                >
                  {{ user.is_active ? 'Deshabilitar' : 'Habilitar' }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-400">
              No se encontraron usuarios{{ search ? ' para "' + search + '"' : '' }}.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4">
      <p class="text-sm text-gray-500">
        <template v-if="usersTotal > 0">Mostrando {{ usersFrom }}–{{ usersTo }} de {{ usersTotal }}</template>
        <template v-else>Sin resultados</template>
      </p>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-gray-600">
          Mostrar
          <select v-model.number="perPageModel" class="px-2 py-1.5 rounded-lg border border-gray-200 bg-white text-sm">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
        </label>
        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="$emit('page-change', usersCurrentPage - 1)"
            :disabled="usersCurrentPage <= 1"
            class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
          >
            Anterior
          </button>
          <span class="px-2 text-sm text-gray-600 whitespace-nowrap">Página {{ usersCurrentPage }} de {{ usersLastPage }}</span>
          <button
            type="button"
            @click="$emit('page-change', usersCurrentPage + 1)"
            :disabled="usersCurrentPage >= usersLastPage"
            class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <ConfirmModal
      :open="Boolean(userToToggle)"
      :title="userToToggle?.is_active ? `¿Deshabilitar a “${userToToggle?.username}”?` : `¿Habilitar a “${userToToggle?.username}”?`"
      :message="userToToggle?.is_active ? 'No podrá iniciar sesión hasta que lo vuelvas a habilitar.' : 'Podrá volver a iniciar sesión con sus roles actuales.'"
      :confirm-text="userToToggle?.is_active ? 'Deshabilitar' : 'Habilitar'"
      :danger="Boolean(userToToggle?.is_active)"
      :loading="togglingUser"
      @confirm="toggleUserStatus"
      @cancel="userToToggle = null"
    />
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from '@/services/axios';
import ConfirmModal from '@/components/ConfirmModal.vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  usersPerPage: { type: Number, default: 20 },
  usersCurrentPage: { type: Number, default: 1 },
  usersLastPage: { type: Number, default: 1 },
  usersTotal: { type: Number, default: 0 },
  usersFrom: { type: Number, default: 0 },
  usersTo: { type: Number, default: 0 },
  assignmentContext: { type: Array, default: () => [] },
});

const emit = defineEmits([
  'search-change',
  'per-page-change',
  'page-change',
  'edit-roles',
  'edit-user',
  'reset-password',
  'reload',
  'show-result',
]);

const search = ref('');
const perPageModel = computed({
  get: () => props.usersPerPage,
  set: (value) => emit('per-page-change', value),
});
let usersSearchTimeout = null;

const handleUsersSearchInput = () => {
  clearTimeout(usersSearchTimeout);
  usersSearchTimeout = setTimeout(() => {
    emit('search-change', search.value);
  }, 400);
};

const getSuggestedTableByNeighborhood = (neighborhoodId) => {
  const targetId = Number(neighborhoodId || 0);
  if (!targetId) return null;
  const item = props.assignmentContext.find((row) => Number(row.id) === targetId);
  return item?.suggested_polling_table || null;
};

const suggestedTableForUser = (user) => {
  const neighborhoodId = user?.person?.neighborhood_id;
  return getSuggestedTableByNeighborhood(neighborhoodId);
};

// El botón abre el modal; toggleUserStatus() aplica el cambio al confirmar.
const userToToggle = ref(null);
const togglingUser = ref(false);
const askToggleUser = (user) => {
  userToToggle.value = user;
};

const toggleUserStatus = async () => {
  const user = userToToggle.value;
  if (!user) return;

  togglingUser.value = true;
  try {
    await axios.patch(`/admin/users/${user.id}/toggle-active`);
    emit('reload');
  } catch (error) {
    emit('show-result', false, 'Error', error?.response?.data?.message || 'Error al cambiar el estado del usuario.');
  } finally {
    togglingUser.value = false;
    userToToggle.value = null;
  }
};
</script>
