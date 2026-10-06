<template>
  <div class="space-y-6">
    <!-- Encabezado -->
    <section class="card p-5 sm:p-6 flex flex-col lg:flex-row lg:items-center gap-4">
      <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-aso-primary">
          <Database class="w-3.5 h-3.5" />
          Panel de datos
        </p>
        <h2 class="page-title mt-1">Administración por tabla</h2>
        <p class="page-subtitle">Consulta, crea, edita y elimina los datos de cada tabla. Solo ves las tablas y los botones que tu rol permite.</p>
      </div>

      <!-- Copia de seguridad de toda la base: permiso propio (database.backup). -->
      <button v-if="authStore.can('database.backup')" type="button" class="btn-secondary shrink-0 self-start lg:self-center" :disabled="backingUp" @click="confirmBackup = true">
        <Loader2 v-if="backingUp" class="w-4 h-4 animate-spin" />
        <DatabaseBackup v-else class="w-4 h-4 text-aso-primary" />
        {{ backingUp ? 'Generando copia…' : 'Copia de seguridad' }}
      </button>
    </section>

    <div v-if="loadingResources" class="card h-64 animate-pulse"></div>

    <div v-else-if="!resources.length" class="card p-10 flex flex-col items-center text-center gap-3">
      <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><Database class="w-7 h-7" /></span>
      <p class="font-display text-lg font-semibold text-gray-900">Sin tablas disponibles</p>
      <p class="text-sm text-gray-500 max-w-sm">Tu rol no tiene permiso de ver ninguna tabla del Panel.</p>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-[15rem_minmax(0,1fr)] gap-6 items-start">
      <!-- Tablas (lista en escritorio, selector en celular) -->
      <aside class="card p-3 lg:sticky lg:top-4">
        <label class="lg:hidden block">
          <span class="field-label">Tabla</span>
          <select :value="activeKey" class="field" @change="selectResource($event.target.value)">
            <optgroup v-for="group in groups" :key="group.key" :label="group.label">
              <option v-for="item in group.items" :key="item.key" :value="item.key">{{ item.label }}</option>
            </optgroup>
          </select>
        </label>

        <nav class="hidden lg:block space-y-3" aria-label="Tablas">
          <div v-for="group in groups" :key="group.key">
            <p class="px-2 mb-1 text-[10.5px] font-bold uppercase tracking-[0.12em] text-gray-400">{{ group.label }}</p>
            <button
              v-for="item in group.items"
              :key="item.key"
              type="button"
              class="w-full flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-left text-sm font-semibold transition-colors"
              :class="activeKey === item.key ? 'bg-aso-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
              :aria-current="activeKey === item.key ? 'page' : undefined"
              @click="selectResource(item.key)"
            >
              <span class="truncate">{{ item.label }}</span>
              <!-- Solo lectura: el rol (o la tabla) no permite cambios aquí. -->
              <Eye v-if="!item.can.create && !item.can.update && !item.can.delete" class="w-3.5 h-3.5 shrink-0 opacity-60" aria-label="Solo lectura" />
            </button>
          </div>
        </nav>
      </aside>

      <!-- Tabla activa -->
      <section v-if="active" class="card overflow-hidden min-w-0">
        <div class="card-header">
          <div class="min-w-0">
            <h3 class="card-title flex items-center gap-2">
              {{ active.label }}
              <span v-if="showTrash" class="badge-red"><Trash2 class="w-3 h-3" /> Papelera</span>
            </h3>
            <p class="card-subtitle">
              {{ meta.total.toLocaleString('es-CO') }} {{ meta.total === 1 ? 'registro' : 'registros' }}
              <template v-if="active.why"> · {{ active.why }}</template>
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full sm:w-64">
              <Search class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              <input v-model.trim="search" type="search" class="field field-search" :placeholder="`Buscar en ${active.label.toLowerCase()}`" @input="queueSearch">
            </div>
            <button
              v-if="active.can.delete && active.soft_deletes"
              type="button"
              class="btn-secondary"
              :class="{ '!border-red-200 !text-red-600 !bg-red-50': showTrash }"
              :aria-pressed="showTrash"
              @click="toggleTrash"
            >
              <Trash2 class="w-4 h-4" />
              {{ showTrash ? 'Salir de la papelera' : `Papelera (${meta.trashed_total})` }}
            </button>
            <button v-if="active.can.create && !showTrash" type="button" class="btn-primary" @click="openCreate">
              <Plus class="w-4 h-4" /> Nuevo
            </button>
          </div>
        </div>

        <!-- Operaciones que se hacen en otra pantalla -->
        <div v-if="!showTrash && active.elsewhere.length" class="px-5 sm:px-6 py-3 border-b border-gray-100 bg-sky-50/50 space-y-1.5">
          <p v-for="note in active.elsewhere" :key="note.op" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-sky-900">
            <Info class="w-4 h-4 shrink-0 text-sky-600" />
            <span>{{ note.text }}</span>
            <router-link v-if="note.allowed" :to="{ name: note.route }" class="font-semibold underline underline-offset-2 hover:text-sky-700">Ir allá</router-link>
          </p>
        </div>

        <div v-if="legendItems.length" class="px-5 sm:px-6 py-2.5 border-b border-gray-100 bg-gray-50/60">
          <ActionLegend :items="legendItems" />
        </div>

        <p v-if="listError" class="mx-5 sm:mx-6 mt-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm" role="alert">{{ listError }}</p>

        <div class="table-wrap rounded-none" :class="{ 'opacity-60 transition-opacity': loadingRows && rows.length }">
          <table class="data-table">
            <thead>
              <tr>
                <th v-for="field in listFields" :key="field.name">{{ field.label }}</th>
                <th v-if="showTrash">Eliminado</th>
                <th v-if="hasRowActions" class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="loadingRows && !rows.length">
                <tr v-for="n in 5" :key="`sk-${n}`">
                  <td :colspan="columnCount"><div class="h-4 w-full max-w-md rounded bg-gray-100 animate-pulse"></div></td>
                </tr>
              </template>

              <tr v-else-if="!rows.length">
                <td :colspan="columnCount" class="py-10 text-center text-sm text-gray-500">
                  {{ showTrash ? 'La papelera está vacía.' : (search ? 'Nada coincide con la búsqueda.' : 'No hay registros todavía.') }}
                </td>
              </tr>

              <tr v-for="row in rows" :key="row.id" class="row-enter">
                <td v-for="field in listFields" :key="field.name">
                  <template v-if="field.type === 'boolean'">
                    <span v-if="row[field.name] === null" class="text-gray-400">—</span>
                    <span v-else :class="row[field.name] ? 'badge-green' : 'badge-gray'">
                      <span class="badge-dot"></span>{{ booleanLabel(field, row[field.name]) }}
                    </span>
                  </template>
                  <span v-else-if="display(row, field) === null" class="text-gray-400">—</span>
                  <span v-else class="text-gray-800" :class="{ 'font-semibold text-gray-900': field === listFields[0] }">{{ display(row, field) }}</span>
                </td>
                <td v-if="showTrash" class="text-gray-500 whitespace-nowrap">{{ row.deleted_at }}</td>
                <td v-if="hasRowActions">
                  <div class="flex justify-end gap-2">
                    <template v-if="showTrash">
                      <button type="button" class="icon-btn-green" data-tooltip="Restaurar" :aria-label="`Restaurar el registro ${row.id}`" :disabled="busyId === row.id" @click="restore(row)">
                        <Loader2 v-if="busyId === row.id" class="w-4 h-4 animate-spin" />
                        <RotateCcw v-else class="w-4 h-4" />
                      </button>
                    </template>
                    <template v-else>
                      <button v-if="active.can.update" type="button" class="icon-btn-blue" data-tooltip="Editar" :aria-label="`Editar el registro ${row.id}`" @click="openEdit(row)">
                        <Pencil class="w-4 h-4" />
                      </button>
                      <button v-if="active.can.delete" type="button" class="icon-btn-red" data-tooltip="Eliminar" :aria-label="`Eliminar el registro ${row.id}`" @click="rowToDelete = row">
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <PaginationBar
          :current="meta.current_page"
          :last="meta.last_page"
          :total="meta.total"
          :from="meta.from"
          :to="meta.to"
          :loading="loadingRows"
          label="registros"
          @change="loadRows"
        />
      </section>
    </div>

    <!-- Formulario de crear / editar -->
    <Teleport to="body">
      <div v-if="form.open" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" role="dialog" aria-modal="true" :aria-label="formTitle" @keydown.esc="closeForm">
        <form class="w-full max-w-2xl max-h-[90vh] flex flex-col rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden animate-rise" @submit.prevent="saveForm">
          <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">{{ active?.label }}</p>
              <h2 class="mt-1 font-display text-xl font-bold text-gray-900">{{ formTitle }}</h2>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700" aria-label="Cerrar" @click="closeForm"><X class="w-5 h-5" /></button>
          </div>

          <div class="px-6 py-5 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-4">
            <p v-if="form.error" class="sm:col-span-2 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm" role="alert">{{ form.error }}</p>

            <div v-for="field in formFields" :key="field.name" :class="{ 'sm:col-span-2': field.type === 'textarea' }">
              <label v-if="field.type === 'boolean'" class="flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100 cursor-pointer">
                <input v-model="form.values[field.name]" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-aso-primary focus:ring-aso-primary" :disabled="isLocked(field)">
                <span class="text-sm font-semibold text-gray-900">{{ field.label }}</span>
              </label>

              <template v-else>
                <label :for="`panel-${field.name}`" class="field-label">
                  {{ field.label }}
                  <span v-if="!field.required" class="field-optional">opcional</span>
                </label>

                <select
                  v-if="field.type === 'select'"
                  :id="`panel-${field.name}`"
                  v-model="form.values[field.name]"
                  class="field"
                  :class="{ 'field-error': form.errors[field.name] }"
                  :required="field.required"
                  :disabled="isLocked(field)"
                >
                  <option :value="null">{{ field.required ? 'Selecciona…' : 'Ninguno' }}</option>
                  <option v-for="option in optionsFor(field)" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>

                <textarea
                  v-else-if="field.type === 'textarea'"
                  :id="`panel-${field.name}`"
                  v-model="form.values[field.name]"
                  rows="3"
                  class="field"
                  :class="{ 'field-error': form.errors[field.name] }"
                  :required="field.required"
                ></textarea>

                <input
                  v-else
                  :id="`panel-${field.name}`"
                  v-model="form.values[field.name]"
                  :type="INPUT_TYPES[field.type] ?? 'text'"
                  class="field"
                  :class="{ 'field-error': form.errors[field.name] }"
                  :required="field.required"
                  :disabled="isLocked(field)"
                >

                <p v-if="form.errors[field.name]" class="mt-1 text-xs text-red-600">{{ form.errors[field.name] }}</p>
                <p v-else-if="isLocked(field)" class="mt-1 text-xs text-gray-400">No se puede cambiar después de creado.</p>
                <p v-else-if="field.hint" class="mt-1 text-xs text-gray-400">{{ field.hint }}</p>
              </template>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button type="button" class="btn-secondary" :disabled="form.saving" @click="closeForm">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="form.saving">
              <Loader2 v-if="form.saving" class="w-4 h-4 animate-spin" />
              {{ form.saving ? 'Guardando…' : (form.id ? 'Guardar cambios' : 'Crear') }}
            </button>
          </div>
        </form>
      </div>
    </Teleport>

    <ConfirmModal
      :open="Boolean(rowToDelete)"
      :title="`¿Eliminar este ${active?.singular ?? 'registro'}?`"
      :message="rowToDelete ? `“${rowTitle(rowToDelete)}” pasará a la papelera. No se borra de forma definitiva: se puede restaurar.` : ''"
      confirm-text="Eliminar"
      danger
      :loading="busyId !== null"
      @confirm="destroy"
      @cancel="rowToDelete = null"
    />

    <ConfirmModal
      :open="confirmBackup"
      title="¿Descargar una copia de la base de datos?"
      message="Se generará un archivo con todos los datos del sistema, para guardarlo como respaldo."
      confirm-text="Descargar copia"
      @confirm="downloadBackup"
      @cancel="confirmBackup = false"
    >
      <ul class="space-y-1.5 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-100 text-amber-900 text-sm">
        <li>Incluye datos personales (nombres, cédulas, celulares) y las contraseñas cifradas: guárdalo en un lugar seguro y no lo compartas.</li>
        <li>No incluye las sesiones abiertas ni las fotos de actas y planchas (esas son archivos, no están en la base).</li>
        <li>La descarga queda registrada en la bitácora.</li>
      </ul>
    </ConfirmModal>

    <ResultModal :open="result.open" :success="result.success" :title="result.title" :message="result.message" @close="result.open = false" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Database, DatabaseBackup, Eye, Info, Loader2, Pencil, Plus, RotateCcw, Search, Trash2, X
} from 'lucide-vue-next';
import axios from '@/services/axios';
import { useAuthStore } from '@/stores/auth';
import ActionLegend from '@/components/ui/ActionLegend.vue';
import PaginationBar from '@/components/ui/PaginationBar.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import ResultModal from '@/components/ResultModal.vue';

// El Panel es un motor genérico: el servidor describe cada tabla (campos,
// tipos, qué puede hacer este usuario) y esta pantalla solo lo pinta. No hay
// una pantalla por tabla. Ver app/Support/CrudCatalog.php.

const INPUT_TYPES = { text: 'text', email: 'email', number: 'number', decimal: 'number', date: 'date' };

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const loadingResources = ref(true);
const resources = ref([]);
const activeKey = ref('');
const active = computed(() => resources.value.find((item) => item.key === activeKey.value) ?? null);

const groups = computed(() => resources.value.reduce((list, item) => {
  let group = list.find((entry) => entry.key === item.group);
  if (!group) {
    group = { key: item.group, label: item.group_label, items: [] };
    list.push(group);
  }
  group.items.push(item);
  return list;
}, []));

const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, trashed_total: 0 });
const loadingRows = ref(false);
const listError = ref('');
const search = ref('');
const showTrash = ref(false);
const busyId = ref(null);
const rowToDelete = ref(null);
const result = reactive({ open: false, success: true, title: '', message: '' });
const showResult = (success, title, message) => Object.assign(result, { open: true, success, title, message });

// Opciones de los campos de selección, por tabla (se piden una vez).
const optionsCache = reactive({});

const listFields = computed(() => (active.value?.fields ?? []).filter((field) => field.list));
const hasRowActions = computed(() => Boolean(active.value) && (showTrash.value ? active.value.can.delete : (active.value.can.update || active.value.can.delete)));
const columnCount = computed(() => listFields.value.length + (showTrash.value ? 1 : 0) + (hasRowActions.value ? 1 : 0));

// La leyenda explica solo los botones que este usuario ve.
const legendItems = computed(() => {
  if (!active.value) return [];
  if (showTrash.value) return active.value.can.delete ? [{ icon: RotateCcw, label: 'Restaurar', tone: 'green' }] : [];
  return [
    active.value.can.update && { icon: Pencil, label: 'Editar', tone: 'blue' },
    active.value.can.delete && { icon: Trash2, label: 'Eliminar (va a la papelera)', tone: 'red' },
  ].filter(Boolean);
});

const booleanLabel = (field, value) => (field.name === 'is_active'
  ? (value ? 'Activo' : 'Inactivo')
  : (value ? 'Sí' : 'No'));

// Texto de una celda: el nombre del registro relacionado o el valor traducido.
const display = (row, field) => {
  const label = row._labels?.[field.name];
  const value = label !== undefined ? label : row[field.name];
  return value === null || value === undefined || value === '' ? null : value;
};

const rowTitle = (row) => listFields.value.slice(0, 2).map((field) => display(row, field)).filter(Boolean).join(' · ') || `#${row.id}`;

const errorMessage = (error, fallback) => {
  const errors = error?.response?.data?.errors;
  return (errors && Object.values(errors).flat()[0]) || error?.response?.data?.message || fallback;
};

const loadRows = async (page = 1) => {
  if (!active.value) return;
  loadingRows.value = true;
  listError.value = '';
  try {
    const { data } = await axios.get(`/admin/panel/${activeKey.value}`, {
      params: { page, per_page: 15, search: search.value || undefined, trashed: showTrash.value ? 1 : undefined },
      skipGlobalLoading: true,
    });
    rows.value = data?.data ?? [];
    meta.value = { ...meta.value, ...(data?.meta ?? {}) };
  } catch (error) {
    rows.value = [];
    listError.value = errorMessage(error, 'No se pudieron cargar los registros.');
  } finally {
    loadingRows.value = false;
  }
};

const selectResource = (key) => {
  if (key === activeKey.value) return;
  activeKey.value = key;
  search.value = '';
  showTrash.value = false;
  rows.value = [];
  meta.value = { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, trashed_total: 0 };
  // La tabla abierta queda en la dirección: se puede recargar o compartir el enlace.
  router.replace({ query: { ...route.query, tabla: key } });
  loadRows(1);
};

let searchTimer = null;
const queueSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadRows(1), 400);
};

const toggleTrash = () => {
  showTrash.value = !showTrash.value;
  loadRows(1);
};

// ------------------------------------------------------------------ formulario

const form = reactive({ open: false, id: null, values: {}, errors: {}, error: '', saving: false });
const formTitle = computed(() => (form.id ? `Editar ${active.value?.singular ?? ''}` : `Nuevo ${active.value?.singular ?? ''}`));

// Al crear entran todos los campos editables; al editar, los de "solo al crear" se ven bloqueados.
const formFields = computed(() => (active.value?.fields ?? []).filter((field) => field.edit === true || field.edit === 'create'));
const isLocked = (field) => Boolean(form.id) && field.edit === 'create';

const optionsFor = (field) => {
  if (field.values) return Object.entries(field.values).map(([value, label]) => ({ value, label }));
  return optionsCache[activeKey.value]?.[field.name] ?? [];
};

const ensureOptions = async () => {
  if (optionsCache[activeKey.value] || !formFields.value.some((field) => field.has_options)) return;
  const { data } = await axios.get(`/admin/panel/${activeKey.value}/options`, { skipGlobalLoading: true });
  optionsCache[activeKey.value] = data?.data ?? {};
};

const openForm = async (row) => {
  Object.assign(form, { open: true, id: row?.id ?? null, errors: {}, error: '', saving: false });
  form.values = Object.fromEntries(formFields.value.map((field) => {
    const fallback = field.type === 'boolean' ? Boolean(field.default) : (field.default ?? null);
    return [field.name, row ? row[field.name] ?? (field.type === 'boolean' ? false : null) : fallback];
  }));
  try {
    await ensureOptions();
  } catch (error) {
    form.error = errorMessage(error, 'No se pudieron cargar las opciones del formulario.');
  }
};

const openCreate = () => openForm(null);
const openEdit = (row) => openForm(row);
const closeForm = () => {
  if (!form.saving) form.open = false;
};

const saveForm = async () => {
  form.saving = true;
  form.errors = {};
  form.error = '';

  // Al editar no se envían los campos bloqueados.
  const payload = Object.fromEntries(formFields.value
    .filter((field) => !isLocked(field))
    .map((field) => [field.name, form.values[field.name] === '' ? null : form.values[field.name]]));

  try {
    if (form.id) {
      await axios.put(`/admin/panel/${activeKey.value}/${form.id}`, payload, { skipGlobalLoading: true });
    } else {
      await axios.post(`/admin/panel/${activeKey.value}`, payload, { skipGlobalLoading: true });
    }
    const created = !form.id;
    form.saving = false;
    form.open = false;
    // Otras tablas pueden usar esta como lista de opciones: se vuelven a pedir.
    Object.keys(optionsCache).forEach((key) => delete optionsCache[key]);
    await loadRows(created ? 1 : meta.value.current_page);
  } catch (error) {
    const errors = error?.response?.data?.errors ?? {};
    form.errors = Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, [].concat(messages)[0]]));
    // Errores que no son de un campo (por ejemplo, un registro bloqueado).
    form.error = errors.resource?.[0] || (Object.keys(form.errors).length ? '' : errorMessage(error, 'No se pudo guardar.'));
  } finally {
    form.saving = false;
  }
};

// ------------------------------------------------------------------ eliminar y restaurar

const destroy = async () => {
  const row = rowToDelete.value;
  if (!row) return;
  busyId.value = row.id;
  try {
    await axios.delete(`/admin/panel/${activeKey.value}/${row.id}`, { skipGlobalLoading: true });
    rowToDelete.value = null;
    await loadRows(rows.value.length === 1 && meta.value.current_page > 1 ? meta.value.current_page - 1 : meta.value.current_page);
  } catch (error) {
    rowToDelete.value = null;
    showResult(false, 'No se pudo eliminar', errorMessage(error, 'No fue posible eliminar el registro.'));
  } finally {
    busyId.value = null;
  }
};

const restore = async (row) => {
  busyId.value = row.id;
  try {
    const { data } = await axios.post(`/admin/panel/${activeKey.value}/${row.id}/restore`, {}, { skipGlobalLoading: true });
    showResult(true, 'Registro restaurado', data?.message ?? 'El registro volvió a la tabla.');
    await loadRows(1);
  } catch (error) {
    showResult(false, 'No se pudo restaurar', errorMessage(error, 'No fue posible restaurar el registro.'));
  } finally {
    busyId.value = null;
  }
};

// ------------------------------------------------------------------ copia de seguridad

const confirmBackup = ref(false);
const backingUp = ref(false);

const downloadBackup = async () => {
  confirmBackup.value = false;
  backingUp.value = true;
  try {
    const response = await axios.get('/admin/panel/backup', {
      responseType: 'blob',
      timeout: 300000, // una base grande tarda en generarse
      skipGlobalLoading: true,
    });

    const disposition = response.headers?.['content-disposition'] ?? '';
    const filename = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? 'asojuntas-backup';

    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);

    const megabytes = (response.data.size / 1024 / 1024).toFixed(1);
    showResult(true, 'Copia descargada', `Se descargó "${filename}" (${megabytes} MB). Guárdala en un lugar seguro.`);
  } catch (error) {
    // Con responseType "blob" el mensaje de error del servidor también llega como archivo.
    let message = 'No se pudo generar la copia de seguridad.';
    try {
      const text = await error?.response?.data?.text?.();
      message = (text && JSON.parse(text).message) || message;
    } catch {
      // se deja el mensaje genérico
    }
    showResult(false, 'No se pudo descargar la copia', message);
  } finally {
    backingUp.value = false;
  }
};

onMounted(async () => {
  try {
    const { data } = await axios.get('/admin/panel/resources', { skipGlobalLoading: true });
    resources.value = data?.data ?? [];
  } catch (error) {
    listError.value = errorMessage(error, 'No se pudo cargar el Panel.');
  } finally {
    loadingResources.value = false;
  }

  if (resources.value.length) {
    const wanted = String(route.query.tabla ?? '');
    activeKey.value = resources.value.some((item) => item.key === wanted) ? wanted : resources.value[0].key;
    loadRows(1);
  }
});
</script>
