<template>
  <div class="space-y-6 lg:space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div>
        <h2 class="page-title">Geografía Electoral</h2>
        <p class="page-subtitle">Barrios por comuna y el estado de su elección. Crea o cierra elecciones una a una o en lote.</p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          v-can="'elections.create'"
          type="button"
          class="btn bg-white text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50"
          :disabled="loading || bulkCreateCount === 0"
          @click="openBulkModal('create')"
        >
          <CalendarPlus class="w-4 h-4" />
          Crear todas
          <span class="rounded-full bg-emerald-100 px-1.5 text-[11px] tabular-nums">{{ bulkCreateCount }}</span>
        </button>
        <button
          v-can="'elections.update'"
          type="button"
          class="btn bg-white text-amber-700 ring-1 ring-amber-200 hover:bg-amber-50"
          :disabled="loading || bulkCloseCount === 0"
          @click="openBulkModal('close')"
        >
          <CalendarX class="w-4 h-4" />
          Cerrar todas
          <span class="rounded-full bg-amber-100 px-1.5 text-[11px] tabular-nums">{{ bulkCloseCount }}</span>
        </button>
      </div>
    </div>

    <!-- Indicadores (según los filtros aplicados) -->
    <div class="stagger grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
      <article class="stat-card stat-card--blue">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Barrios</p>
          <span class="stat-icon"><MapPinned class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ totalNeighborhoods }}</p>
        <p class="mt-1 text-xs font-semibold text-sky-700">{{ selectedCommuneName || 'Todas las comunas' }}</p>
      </article>
      <article class="stat-card stat-card--green">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Con elección activa</p>
          <span class="stat-icon"><CalendarCheck class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ bulkCloseCount }}</p>
        <div class="mt-2 h-1.5 w-full rounded-full bg-white/80 overflow-hidden">
          <div class="h-full rounded-full bg-aso-primary transition-[width] duration-700" :style="{ width: `${activePercent}%` }"></div>
        </div>
      </article>
      <article class="stat-card stat-card--amber">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Sin elección</p>
          <span class="stat-icon"><CalendarX class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ bulkCreateCount }}</p>
        <p class="mt-1 text-xs font-semibold text-amber-700">Pendientes de crear</p>
      </article>
    </div>

    <section class="card overflow-hidden">
      <!-- Filtros -->
      <div class="card-header">
        <div class="flex flex-col sm:flex-row gap-3 w-full">
          <div class="relative flex-1">
            <Search class="field-icon" />
            <input
              v-model.trim="search"
              type="search"
              placeholder="Buscar barrio por nombre o código"
              class="field field-search"
              @input="queueSearch"
              @keydown.enter.prevent="fetchFirstPage"
            >
          </div>
          <div class="relative sm:w-60">
            <Filter class="field-icon z-10" />
            <select v-model="selectedCommuneId" class="field pl-10 cursor-pointer">
              <option value="">Todas las comunas</option>
              <option v-for="commune in communes" :key="commune.id" :value="String(commune.id)">
                {{ commune.name }}
              </option>
            </select>
          </div>
          <button
            v-if="search || selectedCommuneId"
            type="button"
            class="btn px-4 text-gray-500 hover:text-gray-800 hover:bg-gray-100"
            :disabled="loading"
            @click="clearFilters"
          >
            <X class="w-4 h-4" />
            Limpiar
          </button>
        </div>
      </div>

      <div v-if="error" class="mx-5 sm:mx-6 mt-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ error }}</div>

      <div class="px-5 sm:px-6 py-2.5 border-b border-gray-100 bg-gray-50/60">
        <ActionLegend v-if="legendItems.length" :items="legendItems" />
      </div>

      <div class="table-wrap rounded-none" :class="{ 'opacity-60 transition-opacity': loading && neighborhoods.length }">
        <table class="data-table">
          <thead>
            <tr>
              <th>Barrio</th>
              <th>Comuna</th>
              <th>Elección</th>
              <th>Presidente</th>
              <th>Vicepresidente</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading && !neighborhoods.length">
              <tr v-for="n in 5" :key="`sk-${n}`">
                <td colspan="6"><div class="h-4 w-full max-w-md rounded bg-gray-100 animate-pulse"></div></td>
              </tr>
            </template>

            <tr v-else-if="!neighborhoods.length">
              <td colspan="6" class="py-10 text-center text-sm text-gray-500">No hay barrios para los filtros seleccionados.</td>
            </tr>

            <tr v-for="neighborhood in neighborhoods" :key="neighborhood.id" class="row-enter">
              <td>
                <div class="flex items-center gap-3 min-w-0">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-aso-primary"><MapPin class="w-4 h-4" /></span>
                  <div class="min-w-0">
                    <p class="font-semibold text-gray-900 truncate">{{ neighborhood.name }}</p>
                    <p class="text-xs text-gray-400">{{ neighborhood.code }}</p>
                  </div>
                </div>
              </td>
              <td class="text-gray-700 whitespace-nowrap">{{ neighborhood.commune?.name || 'Sin comuna' }}</td>
              <td>
                <span :class="neighborhood.has_active_election ? 'badge-green' : 'badge-gray'">
                  <span class="badge-dot"></span>
                  {{ neighborhood.has_active_election ? 'Activa' : 'Sin elección' }}
                </span>
              </td>
              <td>
                <span v-if="neighborhood.president_name" class="text-gray-800">{{ neighborhood.president_name }}</span>
                <span v-else class="text-gray-400 italic">Pendiente</span>
              </td>
              <td>
                <span v-if="neighborhood.vicepresident_name" class="text-gray-800">{{ neighborhood.vicepresident_name }}</span>
                <span v-else class="text-gray-400 italic">Pendiente</span>
              </td>
              <td>
                <div class="flex justify-end gap-2">
                  <button
                    v-if="!neighborhood.has_active_election"
                    v-can="'elections.create'"
                    type="button"
                    class="icon-btn-green"
                    data-tooltip="Crear elección"
                    aria-label="Crear elección"
                    :disabled="isRowBusy(neighborhood.id)"
                    @click="createElection(neighborhood)"
                  >
                    <Loader2 v-if="isRowBusy(neighborhood.id)" class="w-4 h-4 animate-spin" />
                    <CalendarPlus v-else class="w-4 h-4" />
                  </button>
                  <button
                    v-else
                    v-can="'elections.update'"
                    type="button"
                    class="icon-btn-amber"
                    data-tooltip="Cerrar elección"
                    aria-label="Cerrar elección"
                    :disabled="isRowBusy(neighborhood.id)"
                    @click="closeElection(neighborhood)"
                  >
                    <Loader2 v-if="isRowBusy(neighborhood.id)" class="w-4 h-4 animate-spin" />
                    <CalendarX v-else class="w-4 h-4" />
                  </button>
                  <RouterLink
                    :to="`/admin/neighborhood/${neighborhood.id}/results`"
                    v-can="'candidates.view'"
                    class="icon-btn-blue"
                    data-tooltip="Ver resultados"
                    aria-label="Ver resultados"
                  >
                    <BarChart3 class="w-4 h-4" />
                  </RouterLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar
        :current="pagination.current_page"
        :last="pagination.last_page"
        :total="pagination.total"
        :from="pagination.from"
        :to="pagination.to"
        :loading="loading"
        label="barrios"
        @change="changePage"
      />
    </section>

    <Teleport to="body">
      <div v-if="confirmState.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden animate-rise">
          <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Confirmación</p>
              <h2 class="mt-1 font-display text-xl font-bold text-gray-900">{{ confirmState.title }}</h2>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700" @click="closeConfirmModal">
              <X class="w-5 h-5" />
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-600">{{ confirmState.message }}</p>
            <div class="grid grid-cols-3 gap-3 text-center">
              <div class="rounded-2xl bg-gray-50 px-3 py-3">
                <p class="text-[10px] font-bold uppercase text-gray-400">Total</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ confirmState.stats.total }}</p>
              </div>
              <div class="rounded-2xl bg-green-50 px-3 py-3">
                <p class="text-[10px] font-bold uppercase text-green-500">Afectados</p>
                <p class="mt-1 text-lg font-bold text-green-700">{{ confirmState.stats.targeted }}</p>
              </div>
              <div class="rounded-2xl bg-amber-50 px-3 py-3">
                <p class="text-[10px] font-bold uppercase text-amber-500">Omitidos</p>
                <p class="mt-1 text-lg font-bold text-amber-700">{{ confirmState.stats.skipped }}</p>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
            <button
              type="button"
              class="btn-secondary"
              @click="closeConfirmModal"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-xl text-sm font-semibold text-white transition-colors disabled:opacity-60"
              :class="confirmState.action === 'create' ? 'bg-green-600 hover:bg-green-700' : 'bg-amber-600 hover:bg-amber-700'"
              :disabled="confirmState.busy"
              @click="runConfirmedAction"
            >
              {{ confirmState.busy ? 'Procesando...' : confirmState.actionLabel }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch, Teleport } from 'vue';
import {
  BarChart3, CalendarCheck, CalendarPlus, CalendarX, Filter, Loader2, MapPin, MapPinned, Search, X
} from 'lucide-vue-next';
import axios from '@/services/axios';
import PaginationBar from '@/components/ui/PaginationBar.vue';
import ActionLegend from '@/components/ui/ActionLegend.vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

// Qué hace cada botón de la columna Acciones.
const ALL_LEGEND_ITEMS = [
  { icon: CalendarPlus, label: 'Crear elección', tone: 'green', permission: 'elections.create' },
  { icon: CalendarX, label: 'Cerrar elección', tone: 'amber', permission: 'elections.update' },
  { icon: BarChart3, label: 'Ver resultados', tone: 'blue', permission: 'candidates.view' },
];
// La leyenda solo explica los botones que este usuario ve.
const legendItems = computed(() => ALL_LEGEND_ITEMS.filter((item) => authStore.can(item.permission)));

const neighborhoods = ref([]);
const communes = ref([]);
const search = ref('');
const selectedCommuneId = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
});
const bulkCounts = ref({
  create: 0,
  close: 0,
});
const loading = ref(false);
const error = ref('');
const rowBusy = ref({});
const confirmState = reactive({
  open: false,
  action: null,
  target: null,
  title: '',
  message: '',
  actionLabel: '',
  busy: false,
  stats: {
    total: 0,
    targeted: 0,
    skipped: 0,
  },
});

const totalNeighborhoods = computed(() => pagination.value.total || neighborhoods.value.length);
const bulkCreateCount = computed(() => bulkCounts.value.create);
const bulkCloseCount = computed(() => bulkCounts.value.close);
const activePercent = computed(() => (totalNeighborhoods.value
  ? Math.round((bulkCloseCount.value / totalNeighborhoods.value) * 100)
  : 0));
const selectedCommuneName = computed(() => communes.value
  .find((commune) => String(commune.id) === selectedCommuneId.value)?.name ?? '');

let searchTimer = null;
let communesCached = false; // Flag para cachear comunas

const fetchFirstPage = () => {
  clearTimeout(searchTimer);
  currentPage.value = 1;
  fetchNeighborhoods();
};

// Busca al dejar de escribir, no en cada tecla.
const queueSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(fetchFirstPage, 450);
};

const buildParams = () => {
  const params = {};

  params.page = currentPage.value;
  params.per_page = perPage.value;

  if (search.value !== '') {
    params.search = search.value;
  }

  if (selectedCommuneId.value !== '') {
    params.commune_id = selectedCommuneId.value;
  }

  return params;
};

const fetchNeighborhoods = async () => {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await axios.get('/admin/neighborhoods', {
      params: buildParams(),
      skipGlobalLoading: true,
    });

    const payload = data?.data || {};
    if (!data?.success || !Array.isArray(payload?.neighborhoods)) {
      throw new Error('Respuesta inválida del servidor');
    }

    neighborhoods.value = payload.neighborhoods;
    
    // ✅ OPTIMIZACIÓN: Solo cargar comunas una sola vez
    if (!communesCached && Array.isArray(payload?.communes)) {
      communes.value = payload.communes;
      communesCached = true;
    }
    
    bulkCounts.value = {
      create: Number(payload?.bulk_counts?.create || 0),
      close: Number(payload?.bulk_counts?.close || 0),
    };
    pagination.value = {
      current_page: Number(payload?.pagination?.current_page || 1),
      last_page: Number(payload?.pagination?.last_page || 1),
      per_page: Number(payload?.pagination?.per_page || perPage.value),
      total: Number(payload?.pagination?.total || 0),
      from: Number(payload?.pagination?.from || 0),
      to: Number(payload?.pagination?.to || 0),
    };
  } catch (err) {
    console.error(err);
    neighborhoods.value = [];
    bulkCounts.value = { create: 0, close: 0 };
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: perPage.value,
      total: 0,
      from: 0,
      to: 0,
    };
    error.value = 'No fue posible cargar los barrios. Intenta nuevamente.';
  } finally {
    loading.value = false;
  }
};

// Antes solo recargaba si cambiaba la comuna (vía watch): con solo texto
// escrito, "Limpiar" borraba el campo pero dejaba la lista filtrada.
const clearFilters = () => {
  const communeChanged = selectedCommuneId.value !== '';
  search.value = '';
  selectedCommuneId.value = '';
  if (!communeChanged) fetchFirstPage();
};

const closeConfirmModal = () => {
  confirmState.open = false;
  confirmState.action = null;
  confirmState.target = null;
  confirmState.title = '';
  confirmState.message = '';
  confirmState.actionLabel = '';
  confirmState.busy = false;
  confirmState.stats = { total: 0, targeted: 0, skipped: 0 };
};

const openBulkModal = (action) => {
  const targeted = action === 'create' ? bulkCreateCount.value : bulkCloseCount.value;
  if (!targeted) {
    return;
  }

  confirmState.open = true;
  confirmState.action = action;
  confirmState.target = 'bulk';
  confirmState.title = action === 'create' ? 'Crear elecciones en lote' : 'Cerrar elecciones en lote';
  confirmState.message = action === 'create'
    ? 'Se crearan elecciones solo para los barrios visibles que aun no tienen una eleccion activa.'
    : 'Se cerraran solo las elecciones activas de los barrios visibles.';
  confirmState.actionLabel = action === 'create' ? 'Crear todas' : 'Cerrar todas';
  confirmState.stats = {
    total: pagination.value.total,
    targeted,
    skipped: pagination.value.total - targeted,
  };
};

const openSingleModal = (action, neighborhood) => {
  confirmState.open = true;
  confirmState.action = action;
  confirmState.target = neighborhood;
  confirmState.title = action === 'create' ? 'Crear elección' : 'Cerrar elección';
  confirmState.message = action === 'create'
    ? `Se creara una eleccion activa para ${neighborhood.name}.`
    : `Se cerrara la eleccion activa de ${neighborhood.name}.`;
  confirmState.actionLabel = action === 'create' ? 'Crear elección' : 'Cerrar elección';
  confirmState.stats = {
    total: 1,
    targeted: 1,
    skipped: 0,
  };
};

const isRowBusy = (id) => Boolean(rowBusy.value[id]);

const setRowBusy = (id, value) => {
  rowBusy.value = {
    ...rowBusy.value,
    [id]: value,
  };
};

const createElection = async (neighborhood) => {
  openSingleModal('create', neighborhood);
};

const closeElection = async (neighborhood) => {
  openSingleModal('close', neighborhood);
};

const runConfirmedAction = async () => {
  if (!confirmState.action) {
    return;
  }

  confirmState.busy = true;

  try {
    if (confirmState.target === 'bulk') {
      const endpoint = confirmState.action === 'create'
        ? '/admin/neighborhoods/elections/create-all'
        : '/admin/neighborhoods/elections/close-all';

      await axios.post(endpoint, buildParams(), {
        // Bulk operations can take longer than the default Axios timeout.
        timeout: 0,
      });
    } else if (confirmState.target?.id) {
      const endpoint = confirmState.action === 'create'
        ? `/admin/neighborhoods/${confirmState.target.id}/elections`
        : `/admin/neighborhoods/${confirmState.target.id}/elections/close`;

      await axios.post(endpoint);
    }

    await fetchNeighborhoods();
    closeConfirmModal();
  } catch (err) {
    console.error(err);
    error.value = err?.response?.data?.message || 'No fue posible completar la accion solicitada.';
    closeConfirmModal();
  }
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) {
    return;
  }

  currentPage.value = page;
  fetchNeighborhoods();
};

watch(selectedCommuneId, () => {
  currentPage.value = 1;
  fetchNeighborhoods();
});

onMounted(async () => {
  await fetchNeighborhoods();
});
</script>