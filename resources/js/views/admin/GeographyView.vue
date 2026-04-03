<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Geografía Electoral</h1>
        <p class="text-sm text-gray-500 mt-1">Filtra por comuna y busca barrios directamente en base de datos.</p>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold bg-white border border-green-200 text-green-700 px-3.5 py-2 rounded-xl hover:bg-green-50 transition-colors disabled:opacity-60"
          :disabled="loading || bulkCreateCount === 0"
          @click="openBulkModal('create')"
        >
          Crear todas
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold bg-white border border-amber-200 text-amber-700 px-3.5 py-2 rounded-xl hover:bg-amber-50 transition-colors disabled:opacity-60"
          :disabled="loading || bulkCloseCount === 0"
          @click="openBulkModal('close')"
        >
          Cerrar todas
        </button>

        <div class="inline-flex items-center gap-2 text-xs sm:text-sm text-gray-500 bg-white border border-gray-200 px-3.5 py-2 rounded-xl">
          <MapPinned class="w-4 h-4 text-aso-primary" />
          {{ totalNeighborhoods }} barrios encontrados
        </div>
      </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gray-50/30">

        <div class="relative w-full sm:max-w-lg">
          <Search class="w-4 h-4 absolute left-3.5 top-1/2 transform -translate-y-1/2 text-gray-400" />
          <input
            v-model.trim="search"
            type="text" 
            placeholder="Buscar barrio por nombre, código o comuna..."
            class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary transition-colors"
          >
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <div class="relative w-full sm:w-64">
            <Filter class="w-4 h-4 text-gray-400" />
            <select
              v-model="selectedCommuneId"
              class="w-full py-2.5 pl-9 pr-9 border border-gray-200 rounded-xl text-sm bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary transition-colors appearance-none"
            >
              <option value="">Todas las comunas</option>
              <option
                v-for="commune in communes"
                :key="commune.id"
                :value="String(commune.id)"
              >
                {{ commune.name }}
              </option>
            </select>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm"
            @click="clearFilters"
          >
            Limpiar
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50/80 text-gray-500 font-semibold border-b border-gray-100">
            <tr>
              <th class="px-6 py-4 whitespace-nowrap">Comuna</th>
              <th class="px-6 py-4 whitespace-nowrap">Barrio</th>
              <th class="px-6 py-4 whitespace-nowrap">Código</th>
              <th class="px-6 py-4 whitespace-nowrap">Presidente</th>
              <th class="px-6 py-4 whitespace-nowrap">Vicepresidente</th>
              <th class="px-6 py-4 whitespace-nowrap text-right">Acciones</th>
            </tr>
          </thead>
          <tbody v-if="loading" class="divide-y divide-gray-50">
            <tr>
              <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                Cargando barrios...
              </td>
            </tr>
          </tbody>

          <tbody v-else-if="error" class="divide-y divide-gray-50">
            <tr>
              <td colspan="6" class="px-6 py-10 text-center text-red-600">
                {{ error }}
              </td>
            </tr>
          </tbody>

          <tbody v-else-if="neighborhoods.length === 0" class="divide-y divide-gray-50">
            <tr>
              <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                No hay barrios para los filtros seleccionados.
              </td>
            </tr>
          </tbody>

          <tbody v-else class="divide-y divide-gray-50">
            <tr
              v-for="neighborhood in neighborhoods"
              :key="neighborhood.id"
              class="hover:bg-gray-50/50 transition-colors group"
            >
              <td class="px-6 py-4">
                <p class="font-medium text-gray-800">{{ neighborhood.commune?.name || 'Sin comuna' }}</p>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <MapPin class="w-4 h-4 text-aso-primary" />
                  <span class="font-semibold text-gray-900">{{ neighborhood.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-gray-700">{{ neighborhood.code }}</td>
              <td class="px-6 py-4 text-gray-700">{{ neighborhood.president_name || 'Sin datos' }}</td>
              <td class="px-6 py-4 text-gray-700">{{ neighborhood.vicepresident_name || 'Sin datos' }}</td>
              <td class="px-6 py-4 text-right">
                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                  <button
                    v-if="!neighborhood.has_active_election"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold border border-green-200 text-green-700 rounded-lg hover:bg-green-50 transition-colors disabled:opacity-60"
                    :disabled="isRowBusy(neighborhood.id)"
                    @click="createElection(neighborhood)"
                  >
                    {{ isRowBusy(neighborhood.id) ? 'Creando...' : 'Crear eleccion' }}
                  </button>

                  <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold border border-amber-200 text-amber-700 rounded-lg hover:bg-amber-50 transition-colors disabled:opacity-60"
                    :disabled="isRowBusy(neighborhood.id)"
                    @click="closeElection(neighborhood)"
                  >
                    {{ isRowBusy(neighborhood.id) ? 'Cerrando...' : 'Cerrar eleccion' }}
                  </button>

                  <RouterLink
                    :to="`/admin/neighborhood/${neighborhood.id}/results`"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    Ver resultados
                    <ChevronRight class="w-3.5 h-3.5" />
                  </RouterLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm">
        <p class="text-gray-500">
          Mostrando <span class="font-semibold text-gray-900">{{ pagination.from || 0 }}</span>
          a <span class="font-semibold text-gray-900">{{ pagination.to || 0 }}</span>
          de <span class="font-semibold text-gray-900">{{ pagination.total }}</span> barrios
        </p>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 disabled:opacity-50 transition-colors"
            :disabled="loading || pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >
            Anterior
          </button>

          <span class="px-3 py-1.5 rounded-lg bg-gray-50 text-gray-600 text-xs font-semibold border border-gray-200">
            Página {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>

          <button
            type="button"
            class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 disabled:opacity-50 transition-colors"
            :disabled="loading || pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>
    <Teleport to="body">
      <div v-if="confirmState.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Confirmación</p>
              <h2 class="mt-1 text-lg font-bold text-gray-900">{{ confirmState.title }}</h2>
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
              class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors"
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
import { ChevronRight, Filter, MapPin, MapPinned, Search } from 'lucide-vue-next';
import { X } from 'lucide-vue-next';
import axios from '@/services/axios';

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

let searchTimer = null;

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
    });

    const payload = data?.data || {};
    if (!data?.success || !Array.isArray(payload?.neighborhoods)) {
      throw new Error('Respuesta inválida del servidor');
    }

    neighborhoods.value = payload.neighborhoods;
    communes.value = Array.isArray(payload.communes) ? payload.communes : [];
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
    communes.value = [];
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

const clearFilters = () => {
  search.value = '';
  selectedCommuneId.value = '';
  currentPage.value = 1;
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

      await axios.post(endpoint, buildParams());
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

watch(search, () => {
  if (searchTimer) {
    clearTimeout(searchTimer);
  }

  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchNeighborhoods();
  }, 300);
});

onMounted(async () => {
  await fetchNeighborhoods();
});
</script>