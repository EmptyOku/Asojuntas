<template>
  <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
    <div class="grid grid-cols-1 lg:grid-cols-[340px_1fr] gap-6 items-start">
      <div class="lg:sticky lg:top-4">
        <div class="mb-4">
          <h1 class="text-xl font-semibold text-gray-900">Roles existentes</h1>
          <p class="text-sm text-gray-500">Elige un rol para editarlo o desactivarlo.</p>
        </div>
        <div class="space-y-2 max-h-[34rem] overflow-y-auto pr-1">
          <div
            v-for="role in roleCatalog"
            :key="role.id"
            class="rounded-lg border bg-white px-3 py-2.5 transition-colors"
            :class="editingRoleId === role.id ? 'border-aso-primary ring-1 ring-aso-primary' : 'border-gray-200'"
          >
            <span class="block font-medium text-gray-900">
              {{ role.display_name }}
              <span class="ml-2 text-xs px-2 py-0.5 rounded-md" :class="role.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'">
                {{ role.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </span>
            <span class="block text-xs text-gray-500 mb-2">{{ role.name }} · {{ (role.permissions || []).length }} permiso(s) · {{ role.users_count ?? 0 }} usuario(s)</span>
            <div class="flex items-center gap-2">
              <button type="button" class="text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-black" @click="editRole(role)">Editar</button>
              <button
                type="button"
                class="text-xs px-3 py-1.5 rounded-md border"
                :class="role.is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                @click="toggleRoleActive(role)"
              >
                {{ role.is_active ? 'Desactivar' : 'Activar' }}
              </button>
            </div>
          </div>
          <p v-if="!roleCatalog.length" class="text-sm text-gray-400 italic">No hay roles registrados.</p>
        </div>
      </div>

      <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3 mb-5">
          <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ editingRoleId ? 'Editar rol' : 'Crear rol' }}</h1>
            <p class="text-sm text-gray-500">Agrupa permisos por módulo y publícalos en un solo paso.</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button v-if="editingRoleId" type="button" class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-white" @click="cancelEdit">Cancelar edición</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-lg bg-gray-900 text-white hover:bg-black" @click="saveRole">{{ editingRoleId ? 'Guardar cambios' : 'Crear rol' }}</button>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <label class="block text-sm text-gray-700">Nombre técnico<input v-model="roleForm.name" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="records.manager" /></label>
          <label class="block text-sm text-gray-700">Nombre visible<input v-model="roleForm.display_name" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" /></label>
          <label class="block text-sm text-gray-700">Descripción<input v-model="roleForm.description" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" /></label>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
          <fieldset v-for="(items, module) in permissionsByModule" :key="module" class="rounded-xl border border-gray-200 bg-white p-3">
            <legend class="px-1 text-sm font-semibold text-gray-900 capitalize">{{ module }}</legend>
            <label v-for="permission in items" :key="permission.id" class="flex items-center gap-2 mt-2 text-sm text-gray-700"><input v-model="roleForm.permissions" type="checkbox" :value="permission.id" />{{ permission.description || permission.display_name || permission.name }}</label>
          </fieldset>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from '@/services/axios';

const props = defineProps({
  permissions: { type: Array, default: () => [] },
});

const emit = defineEmits(['reload', 'reload-users', 'show-result']);

const loading = ref(false);
const roleCatalog = ref([]);
const editingRoleId = ref(null);
const emptyRoleForm = () => ({ name: '', display_name: '', description: '', permissions: [] });
const roleForm = ref(emptyRoleForm());

const permissionsByModule = computed(() => props.permissions.reduce((groups, permission) => {
  const module = permission.name.split('.')[0] || 'general';
  (groups[module] ||= []).push(permission);
  return groups;
}, {}));

// Se carga aparte del `roles` compartido del shell (que solo trae activos, para no romper
// los selectores de asignación de rol): este catálogo necesita ver también los inactivos
// para poder reactivarlos.
const loadRoleCatalog = async () => {
  const { data } = await axios.get('/admin/roles', {
    params: { include_inactive: 1 },
    skipGlobalLoading: true,
  });
  roleCatalog.value = data.data ?? [];
};

const editRole = (role) => {
  editingRoleId.value = role.id;
  roleForm.value = {
    name: role.name,
    display_name: role.display_name,
    description: role.description ?? '',
    permissions: (role.permissions || []).map((permission) => permission.id),
  };
};

const cancelEdit = () => {
  editingRoleId.value = null;
  roleForm.value = emptyRoleForm();
};

const saveRole = async () => {
  if (!roleForm.value.name || !roleForm.value.display_name) return;
  loading.value = true;
  try {
    if (editingRoleId.value) {
      await axios.put(`/admin/roles/${editingRoleId.value}`, roleForm.value);
    } else {
      await axios.post('/admin/roles', roleForm.value);
    }
    cancelEdit();
    await loadRoleCatalog();
    emit('reload');
  } catch (error) {
    const action = editingRoleId.value ? 'actualizar' : 'crear';
    emit('show-result', false, `No se pudo ${action} el rol`, error?.response?.data?.message || `No fue posible ${action} el rol.`);
  } finally {
    loading.value = false;
  }
};

const toggleRoleActive = async (role) => {
  const message = role.is_active
    ? `¿Seguro que deseas desactivar el rol "${role.display_name}"? Se quitará de ${role.users_count ?? 0} usuario(s) que lo tienen asignado.`
    : `¿Seguro que deseas activar el rol "${role.display_name}"?`;
  if (!confirm(message)) return;

  loading.value = true;
  try {
    const { data } = await axios.patch(`/admin/roles/${role.id}/toggle-active`);
    await loadRoleCatalog();
    emit('reload');
    if (data?.affected_users_count > 0) {
      emit('reload-users');
    }
  } catch (error) {
    emit('show-result', false, 'Error', error?.response?.data?.message || 'No fue posible cambiar el estado del rol.');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadRoleCatalog();
});
</script>
