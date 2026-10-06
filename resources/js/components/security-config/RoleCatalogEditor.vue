<template>
  <section class="bg-white border border-gray-100 rounded-2xl p-4 sm:p-6 shadow-sm">
    <div class="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">
      <div class="min-w-0 xl:sticky xl:top-4">
        <div class="mb-4">
          <h1 class="text-xl font-semibold text-gray-900">Roles existentes</h1>
          <p class="text-sm text-gray-500">{{ canUpdate ? 'Elige un rol para editarlo o desactivarlo.' : 'Solo lectura: tu rol no incluye "Editar roles".' }}</p>
        </div>
        <div class="space-y-2 max-h-[34rem] overflow-y-auto pr-1">
          <div
            v-for="role in roleCatalog"
            :key="role.id"
            class="rounded-lg border bg-white px-3 py-2.5 transition-colors"
            :class="editingRoleId === role.id ? 'border-aso-primary ring-1 ring-aso-primary' : 'border-gray-200'"
          >
            <span class="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium text-gray-900">
              <span class="break-words">{{ role.display_name }}</span>
              <span class="text-xs px-2 py-0.5 rounded-md" :class="role.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'">
                {{ role.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </span>
            <span class="block text-xs text-gray-500 mb-2 break-all">{{ role.name }} · {{ (role.permissions || []).length }} permiso(s) · {{ role.users_count ?? 0 }} usuario(s)</span>
            <div v-if="canUpdate" class="flex flex-wrap items-center gap-2">
              <button type="button" class="text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-black" @click="editRole(role)">Editar</button>
              <button
                type="button"
                class="text-xs px-3 py-1.5 rounded-md border"
                :class="role.is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                @click="askToggleRole(role)"
              >
                {{ role.is_active ? 'Desactivar' : 'Activar' }}
              </button>
            </div>
          </div>
          <p v-if="!roleCatalog.length" class="text-sm text-gray-400 italic">No hay roles registrados.</p>
        </div>
      </div>

      <div v-if="canCreate || editingRoleId" ref="formPanel" class="min-w-0 rounded-xl border border-gray-100 bg-gray-50 p-4 sm:p-5">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-5">
          <div class="min-w-0">
            <h1 class="text-xl font-semibold text-gray-900">{{ editingRoleId ? 'Editar rol' : 'Crear rol' }}</h1>
            <p class="text-sm text-gray-500">Marca, tabla por tabla, qué puede ver, crear, editar y eliminar el rol. Lo que una operación necesita se marca solo (crear exige ver).</p>
          </div>
          <div class="flex flex-col-reverse sm:flex-row gap-2 md:shrink-0">
            <button v-if="editingRoleId" type="button" class="btn-secondary w-full sm:w-auto px-3" @click="cancelEdit">Cancelar edición</button>
            <button type="button" class="w-full sm:w-auto px-3 py-2 text-sm font-medium rounded-lg bg-gray-900 text-white hover:bg-black disabled:opacity-50" :disabled="loading" @click="askSaveRole">{{ editingRoleId ? 'Guardar cambios' : 'Crear rol' }}</button>
          </div>
        </div>
        <p v-if="formError" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm">{{ formError }}</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <label class="field-label">Nombre técnico<input v-model="roleForm.name" class="field mt-1" placeholder="digitador_catalogos" /></label>
          <label class="field-label">Nombre visible<input v-model="roleForm.display_name" class="field mt-1" /></label>
          <label class="field-label">Descripción<input v-model="roleForm.description" class="field mt-1" /></label>
        </div>
        <p class="mb-4 text-sm text-gray-600">
          <span class="font-medium text-gray-900">Pantallas en el menú:</span>
          {{ selectedScreens.length ? selectedScreens.join(', ') : 'ninguna (el usuario verá "Tu rol no tiene módulos asignados")' }}
        </p>
        <!-- Permisos por tabla: cada fila es una tabla y cada columna una operación. -->
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-100">
            <div>
              <h2 class="text-sm font-semibold text-gray-900">Permisos por tabla</h2>
              <p class="text-xs text-gray-500">Qué puede hacer el rol con los datos de cada tabla.</p>
            </div>
            <span class="badge-green">{{ selectedTableCount }} de {{ tablePermissionCount }}</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-gray-500 bg-gray-50">
                  <th scope="col" class="px-4 py-2.5">Tabla</th>
                  <th v-for="action in CRUD" :key="action.key" scope="col" class="px-2 py-2.5 text-center w-20">{{ action.label }}</th>
                  <th scope="col" class="px-3 py-2.5 text-center w-20">Todo</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="group in resourceGroups" :key="group.key">
                  <tr class="bg-gray-50/70">
                    <th scope="colgroup" :colspan="CRUD.length + 2" class="px-4 py-1.5 text-left text-[11px] font-bold uppercase tracking-[0.1em] text-aso-primary">{{ group.label }}</th>
                  </tr>
                  <tr v-for="resource in group.items" :key="resource.key" class="border-t border-gray-100 hover:bg-gray-50/60">
                    <th scope="row" class="px-4 py-2.5 text-left font-medium text-gray-900">
                      {{ resource.label }}
                      <span v-if="resource.why" class="ml-1 inline-flex align-middle text-gray-400" :data-tooltip="resource.why"><Info class="w-3.5 h-3.5" /></span>
                    </th>
                    <td v-for="action in CRUD" :key="action.key" class="px-2 py-2.5 text-center">
                      <label v-if="cell(resource, action.key)" class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg hover:bg-gray-100" :data-tooltip="cell(resource, action.key).scope_note || undefined">
                        <input
                          type="checkbox"
                          class="h-4 w-4 rounded border-gray-300 text-aso-primary focus:ring-aso-primary"
                          :checked="isChecked(cell(resource, action.key))"
                          :aria-label="`${action.label} ${resource.label.toLowerCase()}`"
                          @change="togglePermission(cell(resource, action.key), $event.target.checked)"
                        />
                      </label>
                      <!-- Esta operación no existe para la tabla (ver el motivo en el ícono de información). -->
                      <span v-else class="text-gray-300" aria-label="No aplica">—</span>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                      <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-semibold transition-colors"
                        :class="rowIsFull(resource) ? 'bg-aso-primary/10 text-aso-primary' : 'text-gray-500 hover:bg-gray-100'"
                        @click="toggleRow(resource)"
                      >{{ rowIsFull(resource) ? 'Quitar' : 'Todo' }}</button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Lo que no es crear, editar ni eliminar una fila. -->
        <fieldset class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
          <legend class="px-1 text-sm font-semibold text-gray-900">Acciones especiales</legend>
          <p class="text-xs text-gray-500">No son un CRUD de tabla: van aparte para poder separar, por ejemplo, a quien digita un acta de quien la aprueba.</p>
          <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
            <div v-for="permission in specialPermissions" :key="permission.id" class="mt-2">
              <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-aso-primary focus:ring-aso-primary" :checked="isChecked(permission)" @change="togglePermission(permission, $event.target.checked)" />
                <span>
                  <span class="font-medium text-gray-900">{{ permission.display_name }}</span>
                  <span v-if="permission.screen" class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 align-middle">Pantalla</span>
                  <span class="block text-xs text-gray-500">{{ permission.description }}</span>
                </span>
              </label>
              <p v-if="permission.depends_on?.length" class="ml-6 text-xs text-gray-400">Incluye: {{ permission.depends_on.map(labelOf).join(', ') }}</p>
            </div>
          </div>
        </fieldset>

        <!-- Avisos de alcance de lo que está marcado. -->
        <p v-for="note in activeScopeNotes" :key="note" class="mt-3 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ note }}</p>
      </div>
    </div>

    <ConfirmModal
      :open="confirmSave"
      :title="editingRoleId ? '¿Guardar los cambios del rol?' : '¿Crear este rol?'"
      :confirm-text="editingRoleId ? 'Guardar cambios' : 'Crear rol'"
      :loading="loading"
      @confirm="saveRole"
      @cancel="confirmSave = false"
    >
      <dl class="space-y-1.5 rounded-lg bg-gray-50 border border-gray-100 p-3">
        <div><dt class="inline font-medium text-gray-900">Rol:</dt> <dd class="inline break-words">{{ roleForm.display_name }} <span class="text-gray-400">({{ roleForm.name }})</span></dd></div>
        <div><dt class="inline font-medium text-gray-900">Permisos:</dt> <dd class="inline">{{ roleForm.permissions.length }} ({{ selectedTableCount }} de tabla)</dd></div>
        <div><dt class="inline font-medium text-gray-900">Pantallas en el menú:</dt> <dd class="inline">{{ selectedScreens.length ? selectedScreens.join(', ') : 'ninguna' }}</dd></div>
      </dl>
      <p v-if="editingRoleId && editingRoleUsers > 0" class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5">
        {{ editingRoleUsers }} usuario(s) tienen este rol: verán los cambios la próxima vez que recarguen la aplicación.
      </p>
    </ConfirmModal>

    <ConfirmModal
      :open="Boolean(roleToToggle)"
      :title="roleToToggle?.is_active ? `¿Desactivar el rol “${roleToToggle?.display_name}”?` : `¿Activar el rol “${roleToToggle?.display_name}”?`"
      :message="toggleMessage"
      :confirm-text="roleToToggle?.is_active ? 'Desactivar' : 'Activar'"
      :danger="Boolean(roleToToggle?.is_active)"
      :loading="loading"
      @confirm="toggleRoleActive"
      @cancel="roleToToggle = null"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from '@/services/axios';
import { useAuthStore } from '@/stores/auth';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { Info } from 'lucide-vue-next';

const props = defineProps({
  permissions: { type: Array, default: () => [] },
  // Tablas del catálogo: [{ key, label, group, group_label, actions, why }]
  resources: { type: Array, default: () => [] },
});

// Columnas de la matriz.
const CRUD = [
  { key: 'view', label: 'Ver' },
  { key: 'create', label: 'Crear' },
  { key: 'update', label: 'Editar' },
  { key: 'delete', label: 'Eliminar' },
];

const emit = defineEmits(['reload', 'reload-users', 'show-result']);

const loading = ref(false);
const roleCatalog = ref([]);
const editingRoleId = ref(null);
const emptyRoleForm = () => ({ name: '', display_name: '', description: '', permissions: [] });
const roleForm = ref(emptyRoleForm());

const authStore = useAuthStore();
// Crear y editar roles son permisos distintos.
const canCreate = computed(() => authStore.can('roles.create'));
const canUpdate = computed(() => authStore.can('roles.update'));

// El backend (/admin/permissions) trae cada permiso con su tabla (`module`) y
// su operación (`action`), o marcado como acción especial, desde PermissionCatalog.
const resourceGroups = computed(() => props.resources.reduce((groups, resource) => {
  let group = groups.find((item) => item.key === resource.group);
  if (!group) {
    group = { key: resource.group, label: resource.group_label, items: [] };
    groups.push(group);
  }
  group.items.push(resource);
  return groups;
}, []));

// Permiso de una celda (tabla + operación); undefined si esa operación no existe.
const cellIndex = computed(() => Object.fromEntries(
  props.permissions.filter((permission) => !permission.special).map((permission) => [`${permission.module}.${permission.action}`, permission]),
));
const cell = (resource, action) => cellIndex.value[`${resource.key}.${action}`];
const rowCells = (resource) => CRUD.map((action) => cell(resource, action.key)).filter(Boolean);

const specialPermissions = computed(() => props.permissions.filter((permission) => permission.special));
const tablePermissionCount = computed(() => props.permissions.filter((permission) => !permission.special).length);
const selectedTableCount = computed(() => roleForm.value.permissions.filter((id) => byId.value[id] && !byId.value[id].special).length);

const byName = computed(() => Object.fromEntries(props.permissions.map((permission) => [permission.name, permission])));
const byId = computed(() => Object.fromEntries(props.permissions.map((permission) => [permission.id, permission])));
const labelOf = (name) => byName.value[name]?.display_name || name;

const isChecked = (permission) => roleForm.value.permissions.includes(permission.id);

// Marcar un permiso marca (en cascada) lo que necesita; desmarcarlo desmarca lo
// que depende de él. El backend aplica la misma regla al guardar.
const togglePermission = (permission, checked) => {
  const selected = new Set(roleForm.value.permissions);

  if (checked) {
    const pending = [permission.name];
    while (pending.length) {
      const current = byName.value[pending.pop()];
      if (!current || selected.has(current.id)) continue;
      selected.add(current.id);
      pending.push(...(current.depends_on || []));
    }
  } else {
    const pending = [permission.name];
    while (pending.length) {
      const name = pending.pop();
      const current = byName.value[name];
      if (!current || !selected.has(current.id)) continue;
      selected.delete(current.id);
      props.permissions
        .filter((other) => (other.depends_on || []).includes(name))
        .forEach((other) => pending.push(other.name));
    }
  }

  roleForm.value.permissions = [...selected];
};

const rowIsFull = (resource) => rowCells(resource).every((permission) => isChecked(permission));

// "Todo" marca la fila completa; si ya lo está, la quita.
const toggleRow = (resource) => {
  const full = rowIsFull(resource);
  rowCells(resource).forEach((permission) => {
    if (isChecked(permission) === full) togglePermission(permission, !full);
  });
};

const activeScopeNotes = computed(() => [...new Set(roleForm.value.permissions
  .map((id) => byId.value[id]?.scope_note)
  .filter(Boolean))]);

const selectedScreens = computed(() => roleForm.value.permissions
  .map((id) => byId.value[id])
  .filter((permission) => permission?.screen)
  .map((permission) => permission.display_name || permission.name));

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

const formPanel = ref(null);
const formError = ref('');
const confirmSave = ref(false);
const roleToToggle = ref(null);

const editingRoleUsers = computed(() => roleCatalog.value.find((role) => role.id === editingRoleId.value)?.users_count ?? 0);

const toggleMessage = computed(() => {
  const role = roleToToggle.value;
  if (!role) return '';
  if (!role.is_active) return 'El rol volverá a estar disponible para asignarlo a usuarios.';
  const users = role.users_count ?? 0;
  return users > 0
    ? `Se quitará a ${users} usuario(s) que lo tienen asignado y perderán los permisos que les daba.`
    : 'Nadie tiene este rol asignado. Dejará de aparecer para asignarlo.';
});

const editRole = (role) => {
  formError.value = '';
  editingRoleId.value = role.id;
  roleForm.value = {
    name: role.name,
    display_name: role.display_name,
    description: role.description ?? '',
    permissions: (role.permissions || []).map((permission) => permission.id),
  };
  // En pantallas angostas el formulario queda debajo de la lista: llevarlo a la vista.
  // Se desplaza solo el contenedor del layout; scrollIntoView movería también el
  // documento y escondería el header.
  const container = document.querySelector('[data-app-scroll]');
  if (container && formPanel.value) {
    const offset = formPanel.value.getBoundingClientRect().top - container.getBoundingClientRect().top;
    container.scrollTo({ top: container.scrollTop + offset - 16, behavior: 'smooth' });
  }
};

const cancelEdit = () => {
  editingRoleId.value = null;
  roleForm.value = emptyRoleForm();
  formError.value = '';
};

// Valida lo mínimo y abre el modal de confirmación; saveRole() guarda de verdad.
const askSaveRole = () => {
  if (!roleForm.value.name?.trim() || !roleForm.value.display_name?.trim()) {
    formError.value = 'Completa el nombre técnico y el nombre visible del rol.';
    return;
  }
  formError.value = '';
  confirmSave.value = true;
};

const saveRole = async () => {
  const isEditing = Boolean(editingRoleId.value);
  const displayName = roleForm.value.display_name;
  loading.value = true;
  try {
    if (isEditing) {
      await axios.put(`/admin/roles/${editingRoleId.value}`, roleForm.value);
    } else {
      await axios.post('/admin/roles', roleForm.value);
    }
    confirmSave.value = false;
    cancelEdit();
    await loadRoleCatalog();
    emit('reload');
    // Si el rol editado es uno de los míos, el menú debe reflejarlo ya.
    await authStore.fetchUser().catch(() => {});
    emit('show-result', true, isEditing ? 'Rol actualizado' : 'Rol creado', `El rol "${displayName}" se guardó correctamente.`);
  } catch (error) {
    confirmSave.value = false;
    const action = isEditing ? 'actualizar' : 'crear';
    emit('show-result', false, `No se pudo ${action} el rol`, error?.response?.data?.message || `No fue posible ${action} el rol.`);
  } finally {
    loading.value = false;
  }
};

const askToggleRole = (role) => {
  roleToToggle.value = role;
};

const toggleRoleActive = async () => {
  const role = roleToToggle.value;
  if (!role) return;

  loading.value = true;
  try {
    const { data } = await axios.patch(`/admin/roles/${role.id}/toggle-active`);
    roleToToggle.value = null;
    await loadRoleCatalog();
    emit('reload');
    if (data?.affected_users_count > 0) {
      emit('reload-users');
    }
    emit('show-result', true, role.is_active ? 'Rol desactivado' : 'Rol activado', `El rol "${role.display_name}" quedó ${role.is_active ? 'inactivo' : 'activo'}.`);
  } catch (error) {
    roleToToggle.value = null;
    emit('show-result', false, 'No se pudo cambiar el estado del rol', error?.response?.data?.message || 'No fue posible cambiar el estado del rol.');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadRoleCatalog();
});
</script>
