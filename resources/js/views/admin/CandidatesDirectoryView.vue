<template>
  <div class="space-y-6 lg:space-y-8">

    <!-- Encabezado -->
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div>
        <h2 class="page-title">Directorio de Juntas de Acción Comunal</h2>
        <p class="page-subtitle">
          <span class="font-semibold text-aso-primary">{{ pagination.total.toLocaleString('es-CO') }}</span>
          juntas registradas · dignatarios y resultados por barrio.
        </p>
      </div>
      <button
        type="button"
        class="btn-secondary self-start lg:self-auto"
        :disabled="loading || reportLoading"
        @click="generateReport"
      >
        <Loader2 v-if="reportLoading" class="w-4 h-4 animate-spin" />
        <FileBarChart v-else class="w-4 h-4" />
        {{ reportLoading ? 'Generando…' : 'Generar reporte' }}
      </button>
    </div>

    <!-- Filtros -->
    <section class="card p-4 sm:p-5 flex flex-col md:flex-row gap-3">
      <div class="relative flex-1">
        <Search class="field-icon" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Buscar por nombre o código del barrio"
          class="field field-search"
          @input="queueSearch"
          @keydown.enter.prevent="fetchBarrios(1)"
        >
      </div>
      <div class="relative md:w-64">
        <Filter class="field-icon z-10" />
        <select v-model="selectedCommune" class="field pl-10 cursor-pointer">
          <option value="">Todas las comunas</option>
          <option v-for="comuna in availableCommunes" :key="comuna.id" :value="comuna.id">
            {{ comuna.name }}
          </option>
        </select>
      </div>
      <button
        v-if="searchQuery || selectedCommune"
        type="button"
        class="btn px-4 text-gray-500 hover:text-gray-800 hover:bg-gray-100"
        :disabled="loading"
        @click="resetFilters"
      >
        <X class="w-4 h-4" />
        Limpiar
      </button>
    </section>

    <!-- Reporte -->
    <div v-if="reportError" class="rounded-2xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-center gap-2 animate-rise">
      <AlertCircle class="w-4 h-4 shrink-0" />
      {{ reportError }}
    </div>

    <section v-if="reportData" class="card overflow-hidden animate-rise">
      <div class="card-header">
        <div>
          <h3 class="card-title">Reporte de planchas y cuocientes</h3>
          <p class="card-subtitle">
            {{ reportData.summary?.total_neighborhoods || 0 }} barrios · generado {{ reportData.generated_at || 'sin fecha' }}
          </p>
        </div>
        <button type="button" class="btn px-3 text-gray-500 hover:text-gray-800 hover:bg-gray-100" @click="reportData = null">
          <X class="w-4 h-4" />
          Cerrar
        </button>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 p-5 sm:p-6 border-b border-gray-100">
        <div v-for="item in reportSummary" :key="item.label" class="rounded-2xl px-4 py-3" :class="item.bg">
          <p class="text-[11px] font-bold uppercase tracking-wide" :class="item.text">{{ item.label }}</p>
          <p class="font-display text-2xl font-bold text-gray-900 tabular-nums">{{ item.value }}</p>
        </div>
      </div>

      <div class="divide-y divide-gray-100 max-h-[32rem] overflow-y-auto">
        <details v-for="row in reportData.rows || []" :key="row.neighborhood_id" class="group">
          <summary class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 sm:px-6 py-3.5 cursor-pointer list-none hover:bg-gray-50/70">
            <ChevronRight class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-90" />
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-gray-900 truncate">{{ row.neighborhood_name }}</p>
              <p class="text-xs text-gray-500">{{ row.commune_name || 'Sin comuna' }}</p>
            </div>
            <div class="flex flex-wrap gap-1.5">
              <span :class="row.has_active_election ? 'badge-green' : 'badge-gray'">Elección</span>
              <span :class="row.has_registered_slate ? 'badge-green' : 'badge-amber'">Planchas</span>
              <span :class="row.has_scrutiny ? 'badge-green' : 'badge-gray'">Escrutinio</span>
            </div>
          </summary>

          <div class="px-5 sm:px-6 pb-5 pl-12 space-y-4">
            <ul v-if="(row.warnings || []).length" class="space-y-1.5">
              <li
                v-for="(warning, index) in row.warnings"
                :key="`${row.neighborhood_id}-w-${index}`"
                class="flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800"
              >
                <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0" />
                {{ warning }}
              </li>
            </ul>

            <div>
              <p class="field-label">Planchas</p>
              <div v-if="(row.slates || []).length" class="flex flex-wrap gap-2">
                <span
                  v-for="slate in row.slates"
                  :key="slate.id"
                  class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm ring-1"
                  :class="slate.registered ? 'bg-emerald-50 ring-emerald-100 text-emerald-800' : 'bg-gray-50 ring-gray-200 text-gray-600'"
                >
                  <span class="font-semibold">{{ slate.name }}</span>
                  <span class="text-xs">{{ slate.total_candidates }} candidatos</span>
                </span>
              </div>
              <p v-else class="text-sm text-gray-400">Sin planchas disponibles.</p>
            </div>

            <div v-if="(row.cuocientes || []).length">
              <p class="field-label">Cuocientes por bloque</p>
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div
                  v-for="(bloque, blockIndex) in row.cuocientes"
                  :key="`${row.neighborhood_id}-b-${blockIndex}`"
                  class="rounded-2xl ring-1 ring-gray-100 overflow-hidden"
                >
                  <div class="flex items-center justify-between gap-2 bg-gray-50 px-4 py-2.5">
                    <p class="font-semibold text-gray-900 text-sm">{{ bloque.block_name }}</p>
                    <p class="text-xs text-gray-500">Cuociente <span class="font-semibold text-gray-700">{{ formatQuota(bloque.cuociente_electoral) }}</span></p>
                  </div>
                  <table class="data-table data-table--compact">
                    <thead>
                      <tr><th>Plancha</th><th class="text-right">Votos</th><th class="text-right">Curules</th><th class="text-right">Residuo</th></tr>
                    </thead>
                    <tbody>
                      <tr v-for="(plancha, slateIndex) in bloque.planchas || []" :key="slateIndex">
                        <td class="font-medium text-gray-800">{{ plancha.plancha }}</td>
                        <td class="text-right tabular-nums">{{ plancha.votos }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ plancha.curules }}</td>
                        <td class="text-right tabular-nums text-gray-500">{{ formatQuota(plancha.residuo) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </details>
      </div>
    </section>

    <!-- Error -->
    <div v-if="error" class="card p-6 flex flex-col sm:flex-row items-center gap-3 text-sm">
      <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-500"><AlertCircle class="w-5 h-5" /></span>
      <p class="flex-1 text-gray-700">{{ error }}</p>
      <button type="button" class="btn-primary" @click="fetchBarrios(1)">Reintentar</button>
    </div>

    <!-- Tarjetas de juntas -->
    <template v-else>
      <div v-if="loading && barrios.length === 0" class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4 lg:gap-5">
        <div v-for="n in 6" :key="n" class="card h-52 animate-pulse"></div>
      </div>

      <div v-else-if="barrios.length === 0" class="card p-10 flex flex-col items-center text-center gap-3">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><Building2 class="w-7 h-7" /></span>
        <p class="font-display text-lg font-semibold text-gray-900">No se encontraron juntas</p>
        <p class="text-sm text-gray-500">Prueba con otro nombre o comuna.</p>
        <button v-if="searchQuery || selectedCommune" type="button" class="btn-secondary" @click="resetFilters">Limpiar filtros</button>
      </div>

      <div
        v-else
        class="stagger grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4 lg:gap-5 transition-opacity duration-200"
        :class="{ 'opacity-50 pointer-events-none': loading }"
      >
        <article
          v-for="barrio in barrios"
          :key="barrio.id"
          class="card flex flex-col overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-lift)]"
        >
          <div class="flex items-start gap-3 p-5 pb-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-aso-primary">
              <MapPin class="w-5 h-5" />
            </span>
            <div class="min-w-0 flex-1">
              <h3 class="font-display text-lg font-bold text-gray-900 truncate" :title="barrio.name">{{ barrio.name }}</h3>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 truncate">
                {{ barrio.commune?.name || 'Sin comuna' }} · {{ barrio.code }}
              </p>
            </div>
            <span :class="barrio.has_active_election ? 'badge-green' : 'badge-gray'" class="shrink-0">
              <span class="badge-dot"></span>
              {{ barrio.has_active_election ? 'Activa' : 'Sin elección' }}
            </span>
          </div>

          <dl class="mx-5 grid grid-cols-2 gap-3 rounded-2xl bg-gray-50/80 p-3.5">
            <div v-for="role in dignitaries(barrio)" :key="role.label" class="min-w-0">
              <dt class="text-[10.5px] font-bold uppercase tracking-wider text-gray-400">{{ role.label }}</dt>
              <dd class="mt-1 flex items-center gap-2 min-w-0">
                <span v-if="role.name" class="avatar h-7 w-7 rounded-lg text-[10px]">{{ initials(role.name) }}</span>
                <span v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-gray-300 ring-1 ring-gray-200"><User class="w-3.5 h-3.5" /></span>
                <span class="text-sm truncate" :class="role.name ? 'font-semibold text-gray-900' : 'text-gray-400 italic'">
                  {{ role.name || 'Pendiente' }}
                </span>
              </dd>
            </div>
          </dl>

          <div class="mt-auto flex items-center justify-between gap-3 p-5 pt-4">
            <p class="text-xs text-gray-400 truncate">
              <template v-if="barrio.active_election?.election_date">
                <CalendarDays class="w-3.5 h-3.5 inline -mt-0.5 mr-1" />{{ formatDate(barrio.active_election.election_date) }}
              </template>
            </p>
            <router-link
              :to="{ name: 'admin.neighborhood.results', params: { id: barrio.id } }"
              class="inline-flex items-center gap-1.5 text-sm font-semibold text-aso-primary hover:text-aso-primary-dark group/link"
            >
              Ver resultados
              <ArrowRight class="w-4 h-4 transition-transform group-hover/link:translate-x-0.5" />
            </router-link>
          </div>
        </article>
      </div>

      <div v-if="barrios.length" class="card overflow-hidden">
        <PaginationBar
          :current="pagination.current_page"
          :last="pagination.last_page"
          :total="pagination.total"
          :from="pagination.from"
          :to="pagination.to"
          :loading="loading"
          label="juntas"
          @change="changePage"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from '@/services/axios';
import {
  AlertCircle, AlertTriangle, ArrowRight, Building2, CalendarDays, ChevronRight, FileBarChart,
  Filter, Loader2, MapPin, Search, User, X
} from 'lucide-vue-next';
import PaginationBar from '@/components/ui/PaginationBar.vue';

const router = useRouter();
const barrios = ref([]);
const availableCommunes = ref([]);
const loading = ref(false);
const error = ref(null);
const reportLoading = ref(false);
const reportError = ref(null);
const reportData = ref(null);

const searchQuery = ref('');
const selectedCommune = ref('');
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0, from: 0, to: 0 });

let communesCached = false;
let searchTimer = null;

// Solo vale la respuesta de la última búsqueda: si se escribe de nuevo, la
// petición anterior se cancela y, si igual llega, se descarta.
let activeController = null;
let requestSeq = 0;
let lastQueryKey = '';

const fetchBarrios = async (page = 1) => {
  clearTimeout(searchTimer);
  activeController?.abort();
  activeController = new AbortController();
  const seq = ++requestSeq;
  lastQueryKey = `${searchQuery.value.trim()}|${selectedCommune.value}|${page}`;

  loading.value = true;
  error.value = null;

  try {
    const response = await axios.get('/admin/neighborhoods', {
      params: {
        page,
        search: searchQuery.value.trim() || undefined,
        commune_id: selectedCommune.value || undefined,
        // El directorio no usa los conteos de "crear/cerrar todas" y las
        // comunas solo se piden una vez: dos consultas menos por búsqueda.
        with_bulk_counts: 0,
        with_communes: communesCached ? 0 : 1,
      },
      signal: activeController.signal,
      skipGlobalLoading: true,
    });

    if (seq !== requestSeq) return;

    if (response.data.success) {
      barrios.value = response.data.data.neighborhoods;
      pagination.value = { from: 0, to: 0, ...response.data.data.pagination };

      if (!communesCached && response.data.data.communes) {
        availableCommunes.value = response.data.data.communes;
        communesCached = true;
      }
    }
  } catch (err) {
    // Cancelada a propósito por una búsqueda más nueva: no es un error.
    if (axios.isCancel?.(err) || err?.name === 'CanceledError' || seq !== requestSeq) return;

    console.error('Error en Directorio:', err);
    if (err?.response?.status === 401) {
      router.push({ name: 'login' });
    } else {
      error.value = 'Error al conectar con el servidor.';
    }
  } finally {
    if (seq === requestSeq) loading.value = false;
  }
};

// Busca solo al dejar de escribir (no una petición por tecla) y no repite
// una búsqueda idéntica a la última.
const queueSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    if (`${searchQuery.value.trim()}|${selectedCommune.value}|1` === lastQueryKey) return;
    fetchBarrios(1);
  }, 600);
};

const changePage = (page) => {
  fetchBarrios(page);
  // El scroll vive en el contenedor del layout (AppLayout), no en window.
  document.querySelector('[data-app-scroll]')?.scrollTo({ top: 0, behavior: 'smooth' });
};

// Antes solo recargaba si cambiaba la comuna: con solo texto no hacía nada.
const resetFilters = () => {
  const communeChanged = selectedCommune.value !== '';
  searchQuery.value = '';
  selectedCommune.value = '';
  if (!communeChanged) fetchBarrios(1);
};

const dignitaries = (barrio) => [
  { label: 'Presidente', name: barrio.president_name },
  { label: 'Vicepresidente', name: barrio.vicepresident_name },
];

const initials = (name) => String(name || '?')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part.charAt(0).toUpperCase())
  .join('');

const formatDate = (value) => {
  const date = new Date(`${value}T00:00:00`);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
};

const reportSummary = computed(() => {
  const summary = reportData.value?.summary ?? {};
  return [
    { label: 'Con planchas', value: summary.with_registered_slates || 0, bg: 'bg-emerald-50', text: 'text-emerald-700' },
    { label: 'Sin planchas', value: summary.without_registered_slates || 0, bg: 'bg-amber-50', text: 'text-amber-700' },
    { label: 'Con escrutinio', value: summary.with_scrutiny || 0, bg: 'bg-sky-50', text: 'text-sky-700' },
    { label: 'Sin escrutinio', value: summary.without_scrutiny || 0, bg: 'bg-gray-100', text: 'text-gray-600' },
  ];
});

const generateReport = async () => {
  reportLoading.value = true;
  reportError.value = null;

  try {
    const params = {};
    if (searchQuery.value?.trim()) params.search = searchQuery.value.trim();
    if (selectedCommune.value) params.commune_id = selectedCommune.value;

    const response = await axios.get('/admin/neighborhoods/report', {
      params,
      timeout: 120000,
      skipGlobalLoading: true,
    });

    if (response.data?.success) {
      reportData.value = response.data.data;
    } else {
      reportData.value = null;
      reportError.value = 'No fue posible generar el reporte.';
    }
  } catch (err) {
    if (err?.response?.status === 401) {
      router.push({ name: 'login' });
      return;
    }

    console.error('Error generando reporte del directorio de candidatos:', err);
    reportData.value = null;
    reportError.value = 'Error al generar el reporte. Intenta de nuevo.';
  } finally {
    reportLoading.value = false;
  }
};

const formatQuota = (value) => {
  const numeric = Number(value || 0);
  return Number.isFinite(numeric) ? numeric.toFixed(2) : '0.00';
};

watch(selectedCommune, () => fetchBarrios(1));

onMounted(() => fetchBarrios(1));
</script>
