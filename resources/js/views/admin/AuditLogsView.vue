<template>
  <div class="space-y-6">
    <!-- Encabezado -->
    <section class="card p-5 sm:p-6 flex flex-col lg:flex-row lg:items-center gap-4">
      <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-aso-primary">
          <ClipboardList class="w-3.5 h-3.5" />
          Bitácora del sistema
        </p>
        <h2 class="page-title mt-1">Quién hizo qué, y cuándo</h2>
        <p class="page-subtitle">El historial de lo que cada usuario hace en el sistema: registros, cambios, decisiones y seguridad.</p>
      </div>

      <button
        type="button"
        class="btn-secondary shrink-0 self-start lg:self-center"
        :disabled="exporting || !pagination.total"
        data-tooltip="Descarga lo que estás viendo, con los filtros elegidos"
        @click="exportCsv"
      >
        <Loader2 v-if="exporting" class="w-4 h-4 animate-spin" />
        <Download v-else class="w-4 h-4 text-aso-primary" />
        {{ exporting ? 'Generando archivo…' : 'Exportar a CSV' }}
      </button>
    </section>

    <section class="card overflow-hidden">
      <!-- Filtros -->
      <div class="p-5 border-b border-gray-100 bg-gray-50/40 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
          <label class="block">
            <span class="field-label">Mostrar</span>
            <select v-model="filters.detail" class="field">
              <option v-for="option in DETAIL_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label class="block">
            <span class="field-label">Qué pasó</span>
            <select v-model="filters.group" class="field">
              <option value="">Todo</option>
              <option v-for="option in filterOptions.groups" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label class="block">
            <span class="field-label">Sobre qué</span>
            <select v-model="filters.auditable_type" class="field">
              <option value="">Cualquier cosa</option>
              <option v-for="option in filterOptions.entities" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label class="block">
            <span class="field-label">Quién</span>
            <input v-model.trim="filters.user" type="search" class="field" placeholder="Nombre de usuario">
          </label>
          <label class="block">
            <span class="field-label">Desde</span>
            <input v-model="filters.from_date" type="date" class="field">
          </label>
          <label class="block">
            <span class="field-label">Hasta</span>
            <input v-model="filters.to_date" type="date" class="field">
          </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="flex items-center gap-2 text-sm text-gray-500">
            <Info class="w-4 h-4 shrink-0 text-gray-400" />
            {{ DETAIL_OPTIONS.find((option) => option.value === filters.detail)?.hint }}
          </p>
          <button v-if="hasFilters" type="button" class="btn-secondary" @click="clearFilters">
            <X class="w-4 h-4" /> Limpiar filtros
          </button>
        </div>
      </div>

      <!-- Lista -->
      <div v-if="isLoading && !records.length" class="divide-y divide-gray-100">
        <div v-for="n in 6" :key="n" class="flex items-center gap-4 px-5 sm:px-6 py-4">
          <span class="h-10 w-10 rounded-xl bg-gray-100 animate-pulse"></span>
          <span class="flex-1 space-y-2">
            <span class="block h-3.5 w-2/3 rounded bg-gray-100 animate-pulse"></span>
            <span class="block h-3 w-1/3 rounded bg-gray-100 animate-pulse"></span>
          </span>
        </div>
      </div>

      <div v-else-if="loadError" class="px-6 py-12 text-center">
        <p class="font-display text-lg font-semibold text-gray-900">No se pudo cargar la bitácora</p>
        <p class="text-sm text-gray-500">{{ loadError }}</p>
        <button type="button" class="btn-primary mt-3" @click="fetchLogs">Reintentar</button>
      </div>

      <div v-else-if="!records.length" class="px-6 py-12 flex flex-col items-center text-center gap-2">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><ClipboardList class="w-6 h-6" /></span>
        <p class="font-display text-lg font-semibold text-gray-900">Sin actividad</p>
        <p class="text-sm text-gray-500">{{ hasFilters ? 'Nada coincide con los filtros elegidos.' : 'Todavía no hay actividad registrada.' }}</p>
      </div>

      <ul v-else class="divide-y divide-gray-100" :class="{ 'opacity-60 transition-opacity': isLoading }">
        <li v-for="log in records" :key="log.id">
          <component
            :is="hasDetail(log) ? 'button' : 'div'"
            :type="hasDetail(log) ? 'button' : undefined"
            class="w-full flex items-start gap-3 sm:gap-4 px-5 sm:px-6 py-4 text-left transition-colors"
            :class="hasDetail(log) ? 'hover:bg-gray-50/70 cursor-pointer' : ''"
            :aria-expanded="hasDetail(log) ? isExpanded(log.id) : undefined"
            @click="hasDetail(log) && toggle(log.id)"
          >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="STYLE[log.category]?.box ?? STYLE.update.box">
              <component :is="STYLE[log.category]?.icon ?? STYLE.update.icon" class="w-[18px] h-[18px]" />
            </span>

            <span class="min-w-0 flex-1">
              <span class="block text-sm sm:text-[15px] text-gray-900">
                <strong class="font-semibold">{{ log.user?.name }}</strong>
                {{ lowerFirst(log.summary) }}
              </span>
              <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500">
                <span :data-tooltip="log.created_at_exact">{{ log.created_at_human }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ log.created_at_exact }}</span>
              </span>
            </span>

            <span class="hidden sm:inline-flex shrink-0" :class="STYLE[log.category]?.badge ?? 'badge-blue'">{{ STYLE[log.category]?.label ?? 'Cambio' }}</span>
            <ChevronDown
              v-if="hasDetail(log)"
              class="w-4 h-4 mt-1 shrink-0 text-gray-400 transition-transform"
              :class="{ 'rotate-180 text-aso-primary': isExpanded(log.id) }"
            />
            <span v-else class="w-4 shrink-0"></span>
          </component>

          <!-- Detalle -->
          <div v-if="hasDetail(log) && isExpanded(log.id)" class="px-5 sm:px-6 pb-4 sm:pl-[4.75rem] animate-rise">
            <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
              <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ detailTitle(log) }}</p>
              <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                <div v-for="change in log.changes" :key="change.field" class="flex flex-wrap items-baseline gap-x-2">
                  <dt class="text-gray-500">{{ change.label }}:</dt>
                  <dd class="font-medium text-gray-900">
                    <template v-if="'from' in change && 'to' in change">
                      <span class="font-normal text-gray-500 line-through decoration-gray-300">{{ change.from }}</span>
                      <ArrowRight class="w-3.5 h-3.5 inline mx-1 text-gray-400" />
                      {{ change.to }}
                    </template>
                    <template v-else>{{ change.to ?? change.from }}</template>
                  </dd>
                </div>
              </dl>
              <p v-if="log.ip_address" class="mt-2 text-xs text-gray-400">Desde el equipo con dirección {{ log.ip_address }}</p>
            </div>
          </div>
        </li>
      </ul>

      <PaginationBar
        :current="pagination.current_page"
        :last="pagination.last_page"
        :total="pagination.total"
        :from="pagination.from"
        :to="pagination.to"
        :loading="isLoading"
        label="registros"
        @change="changePage"
      />
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
  ArrowRight, BadgeCheck, Bell, ChevronDown, ClipboardList, Download, Info, Loader2, LogIn, Pencil, Plus, ShieldCheck, Trash2, X
} from 'lucide-vue-next';
import axios from '@/services/axios';
import PaginationBar from '@/components/ui/PaginationBar.vue';

// Color e ícono por tipo de actividad (la categoría la decide el servidor).
const STYLE = {
  create: { label: 'Registro', badge: 'badge-green', box: 'bg-emerald-50 text-aso-primary', icon: Plus },
  update: { label: 'Cambio', badge: 'badge-blue', box: 'bg-sky-50 text-sky-600', icon: Pencil },
  delete: { label: 'Eliminación', badge: 'badge-red', box: 'bg-red-50 text-red-500', icon: Trash2 },
  access: { label: 'Acceso', badge: 'badge-gray', box: 'bg-gray-100 text-gray-500', icon: LogIn },
  decision: { label: 'Decisión', badge: 'badge-amber', box: 'bg-amber-50 text-amber-600', icon: BadgeCheck },
  security: { label: 'Seguridad', badge: 'badge-amber', box: 'bg-amber-50 text-amber-600', icon: ShieldCheck },
  notice: { label: 'Aviso', badge: 'badge-blue', box: 'bg-sky-50 text-sky-600', icon: Bell },
};

// Qué tanto detalle se muestra (lo decide el servidor con el parámetro "detail").
const DETAIL_OPTIONS = [
  { value: 'important', label: 'Solo lo importante', hint: 'Decisiones, registros, cambios, eliminaciones y seguridad. Sin ingresos ni el paso a paso de cada plancha.' },
  { value: 'all', label: 'Toda la actividad', hint: 'También los ingresos y salidas, y cada candidato de una plancha por separado.' },
  { value: 'internal', label: 'Todo, con pasos internos', hint: 'Incluye lo que el sistema crea solo, como los cargos y bloques al abrir una elección.' },
];

const isLoading = ref(false);
const exporting = ref(false);
const loadError = ref('');
const records = ref([]);
const filterOptions = ref({ groups: [], entities: [] });
const pagination = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });

const filters = reactive({
  group: '',
  auditable_type: '',
  user: '',
  from_date: '',
  to_date: '',
  detail: 'important',
  page: 1,
  per_page: 20,
});

const hasFilters = computed(() => Boolean(
  filters.group || filters.auditable_type || filters.user || filters.from_date || filters.to_date || filters.detail !== 'important',
));

// Los filtros como parámetros de la petición (los comparten la lista y la exportación).
const filterParams = () => {
  const params = { detail: filters.detail };
  ['group', 'auditable_type', 'user', 'from_date', 'to_date'].forEach((key) => {
    if (filters[key]) params[key] = filters[key];
  });
  return params;
};

// El nombre va primero, así que la frase sigue en minúscula: "Ana Pérez aprobó el acta…".
const lowerFirst = (text) => (text ? text.charAt(0).toLowerCase() + text.slice(1) : '');

const hasDetail = (log) => (log.changes?.length ?? 0) > 0;
const detailTitle = (log) => ({ create: 'Datos registrados', delete: 'Datos eliminados' }[log.category] ?? 'Qué cambió');

const expanded = ref(new Set());
const isExpanded = (id) => expanded.value.has(id);
const toggle = (id) => {
  const next = new Set(expanded.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  expanded.value = next;
};

const fetchLogs = async () => {
  isLoading.value = true;
  loadError.value = '';
  try {
    const params = { ...filterParams(), page: filters.page, per_page: filters.per_page };

    const { data } = await axios.get('/admin/audit-logs', { params, skipGlobalLoading: true });
    const payload = data?.data ?? {};
    const page = payload.records ?? {};

    records.value = Array.isArray(page.data) ? page.data : [];
    if (payload.filters) filterOptions.value = payload.filters;
    pagination.value = {
      current_page: Number(page.current_page || 1),
      last_page: Number(page.last_page || 1),
      total: Number(page.total || 0),
      from: Number(page.from || 0),
      to: Number(page.to || 0),
    };
  } catch (error) {
    records.value = [];
    loadError.value = error?.response?.data?.message || 'Error de conexión.';
  } finally {
    isLoading.value = false;
  }
};

// Descarga la bitácora filtrada como CSV.
const exportCsv = async () => {
  exporting.value = true;
  loadError.value = '';
  try {
    const response = await axios.get('/admin/audit-logs/export', {
      params: filterParams(),
      responseType: 'blob',
      timeout: 300000,
      skipGlobalLoading: true,
    });

    const disposition = response.headers?.['content-disposition'] ?? '';
    const filename = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? 'bitacora-asojuntas.csv';

    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch {
    loadError.value = 'No se pudo generar el archivo de la bitácora.';
  } finally {
    exporting.value = false;
  }
};

const changePage = (page) => {
  filters.page = page;
  fetchLogs();
};

const clearFilters = () => {
  Object.assign(filters, { group: '', auditable_type: '', user: '', from_date: '', to_date: '', detail: 'important' });
};

// Cualquier filtro vuelve a la página 1 (con una pequeña espera mientras se escribe).
let timer = null;
watch(
  () => [filters.group, filters.auditable_type, filters.user, filters.from_date, filters.to_date, filters.detail],
  () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      filters.page = 1;
      fetchLogs();
    }, 300);
  },
);

onMounted(fetchLogs);
</script>
