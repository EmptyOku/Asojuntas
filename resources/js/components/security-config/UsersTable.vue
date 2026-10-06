<template>
  <section class="card overflow-hidden">
    <div class="card-header">
      <div>
        <h2 class="card-title">Listado de usuarios</h2>
        <p class="card-subtitle">{{ usersTotal }} {{ usersTotal === 1 ? 'registro' : 'registros' }}</p>
      </div>
      <div class="relative w-full sm:w-80">
        <Search class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          v-model.trim="search"
          @input="handleUsersSearchInput"
          class="field field-search"
          placeholder="Buscar usuario, correo o barrio"
        />
      </div>
    </div>

    <div v-if="legendItems.length" class="px-5 sm:px-6 py-2.5 border-b border-gray-100 bg-gray-50/60">
      <ActionLegend :items="legendItems" />
    </div>

    <div class="table-wrap rounded-none">
      <table class="data-table">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Barrio / Sector</th>
            <th>Mesa sugerida</th>
            <th>Estado</th>
            <th>Rol</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="row-enter">
            <td>
              <div class="flex items-center gap-3 min-w-0">
                <div class="avatar" :class="{ 'grayscale opacity-60': !user.is_active }">{{ initialsOf(user) }}</div>
                <div class="min-w-0">
                  <p class="font-semibold text-gray-900 truncate">{{ user.username }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
                </div>
              </div>
            </td>
            <td>
              <span v-if="user.person?.neighborhood" class="text-gray-800">{{ user.person.neighborhood.name }}</span>
              <span v-else class="text-gray-400">Sin asignar</span>
            </td>
            <td>
              <template v-if="suggestedTableForUser(user)">
                <span class="text-gray-800">{{ suggestedTableForUser(user).name }}</span>
                <span class="text-xs text-gray-400"> · {{ suggestedTableForUser(user).code }}</span>
              </template>
              <span v-else class="text-gray-400">Sin mesa activa</span>
            </td>
            <td>
              <span :class="user.is_active ? 'badge-green' : 'badge-gray'">
                <span class="badge-dot"></span>
                {{ user.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td>
              <span v-if="(user.roles || []).length" class="text-gray-700">
                {{ user.roles.map((role) => role.display_name).join(', ') }}
              </span>
              <span v-else class="text-gray-400">Sin rol</span>
            </td>
            <td>
              <div class="flex justify-end gap-2">
                <button v-can="'users.update'" type="button" class="icon-btn-blue" data-tooltip="Editar usuario" aria-label="Editar usuario" @click="$emit('edit-user', user)">
                  <Pencil class="w-4 h-4" />
                </button>
                <button v-can="'users.assign_role'" type="button" class="icon-btn-amber" data-tooltip="Cambiar rol" aria-label="Cambiar rol" @click="$emit('edit-roles', user)">
                  <ShieldCheck class="w-4 h-4" />
                </button>
                <button v-can="'users.reset_password'" type="button" class="icon-btn-gray" data-tooltip="Restablecer contraseña" aria-label="Restablecer contraseña" @click="$emit('reset-password', user)">
                  <KeyRound class="w-4 h-4" />
                </button>
                <button
                  v-can="'users.update'"
                  type="button"
                  :class="user.is_active ? 'icon-btn-red' : 'icon-btn-green'"
                  :data-tooltip="user.is_active ? 'Deshabilitar' : 'Habilitar'"
                  :aria-label="user.is_active ? 'Deshabilitar usuario' : 'Habilitar usuario'"
                  @click="askToggleUser(user)"
                >
                  <Power class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="6" class="py-10 text-center text-sm text-gray-400">
              No se encontraron usuarios{{ search ? ' para "' + search + '"' : '' }}.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 sm:px-6 py-4 border-t border-gray-100 bg-gray-50/50">
      <p class="text-sm text-gray-500">
        <template v-if="usersTotal > 0">Mostrando <span class="font-semibold text-gray-700">{{ usersFrom }}–{{ usersTo }}</span> de {{ usersTotal }}</template>
        <template v-else>Sin resultados</template>
      </p>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-gray-600">
          Mostrar
          <select v-model.number="perPageModel" class="field w-20 py-1.5">
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </label>
        <div class="flex items-center gap-1.5">
          <button
            type="button"
            class="pager-btn"
            aria-label="Página anterior"
            :disabled="usersCurrentPage <= 1"
            @click="$emit('page-change', usersCurrentPage - 1)"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <span class="px-2 text-sm text-gray-600 whitespace-nowrap">{{ usersCurrentPage }} / {{ usersLastPage }}</span>
          <button
            type="button"
            class="pager-btn"
            aria-label="Página siguiente"
            :disabled="usersCurrentPage >= usersLastPage"
            @click="$emit('page-change', usersCurrentPage + 1)"
          >
            <ChevronRight class="w-4 h-4" />
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
import { ChevronLeft, ChevronRight, KeyRound, Pencil, Power, Search, ShieldCheck } from 'lucide-vue-next';
import axios from '@/services/axios';
import ConfirmModal from '@/components/ConfirmModal.vue';
import ActionLegend from '@/components/ui/ActionLegend.vue';
import { useAuthStore } from '@/stores/auth';

// Iniciales para el avatar: nombre de la persona si existe, si no el usuario.
const initialsOf = (user) => {
  const person = user?.person;
  const source = [person?.first_name, person?.last_name].filter(Boolean).join(' ') || user?.username || '?';
  return source
    .split(/[\s._-]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
};

const authStore = useAuthStore();

// Leyenda de la columna Acciones: solo lo que este usuario puede hacer.
const legendItems = computed(() => [
  { icon: Pencil, label: 'Editar datos', tone: 'blue', permission: 'users.update' },
  { icon: ShieldCheck, label: 'Cambiar rol', tone: 'amber', permission: 'users.assign_role' },
  { icon: KeyRound, label: 'Restablecer contraseña', tone: 'gray', permission: 'users.reset_password' },
  { icon: Power, label: 'Deshabilitar', tone: 'red', permission: 'users.update' },
  { icon: Power, label: 'Habilitar', tone: 'green', permission: 'users.update' },
].filter((item) => authStore.can(item.permission)));

const props = defineProps({
  users: { type: Array, default: () => [] },
  usersPerPage: { type: Number, default: 10 },
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
