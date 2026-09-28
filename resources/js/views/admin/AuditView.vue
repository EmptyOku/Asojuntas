<template>
  <div class="space-y-6 lg:space-y-8">

    <div>
      <h2 class="page-title">Auditoría de Actas</h2>
      <p class="page-subtitle">Bandeja de actas transmitidas por los jurados, con la lectura automática (IA) de cada una.</p>
    </div>

    <!-- Indicadores -->
    <div class="stagger grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
      <article class="stat-card stat-card--blue">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Actas recibidas</p>
          <span class="stat-icon"><Inbox class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ stats.total_count }}</p>
        <p class="mt-1 text-xs font-semibold text-sky-700">Total transmitidas</p>
      </article>
      <article class="stat-card stat-card--rose">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Requieren revisión</p>
          <span class="stat-icon"><AlertTriangle class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ stats.review_count }}</p>
        <p class="mt-1 text-xs font-semibold text-red-700">Pendientes de corroborar</p>
      </article>
      <article class="stat-card stat-card--green">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Procesadas</p>
          <span class="stat-icon"><CheckCircle2 class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ stats.processed_count }}</p>
        <p class="mt-1 text-xs font-semibold text-emerald-700">{{ processedPercent }}% del total</p>
      </article>
    </div>

    <section class="card overflow-hidden">
      <!-- Filtros -->
      <div class="card-header">
        <div class="flex items-center gap-1 p-1 rounded-xl bg-gray-100 w-fit" role="tablist" aria-label="Filtrar actas">
          <button
            v-for="option in FILTERS"
            :key="option.value"
            type="button"
            role="tab"
            class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-semibold whitespace-nowrap transition-all duration-200"
            :class="filter === option.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
            :aria-selected="filter === option.value"
            @click="filter = option.value"
          >
            {{ option.label }}
            <span v-if="option.count() !== null" class="rounded-full bg-gray-200/80 px-1.5 text-[11px] tabular-nums text-gray-600">{{ option.count() }}</span>
          </button>
        </div>

        <div class="relative w-full sm:w-80">
          <Search class="field-icon" />
          <input v-model="search" type="search" placeholder="Buscar mesa, comuna o jurado" class="field field-search">
        </div>
      </div>

      <div class="table-wrap rounded-none" :class="{ 'opacity-60 transition-opacity': isLoading && records.length }">
        <table class="data-table">
          <thead>
            <tr>
              <th>Mesa / Ubicación</th>
              <th>Jurado</th>
              <th class="text-right">Votos válidos</th>
              <th>Lectura IA</th>
              <th class="text-right">Acción</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="isLoading && !records.length">
              <tr v-for="n in 4" :key="`sk-${n}`">
                <td colspan="5">
                  <div class="flex items-center gap-3 animate-pulse">
                    <div class="h-9 w-9 rounded-xl bg-gray-100"></div>
                    <div class="flex-1 space-y-2">
                      <div class="h-3 w-1/4 rounded bg-gray-100"></div>
                      <div class="h-3 w-1/3 rounded bg-gray-100"></div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>

            <tr v-else-if="!records.length">
              <td colspan="5">
                <div class="flex flex-col items-center py-8 text-center">
                  <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 mb-3"><CheckCircle2 class="w-6 h-6" /></span>
                  <p class="font-semibold text-gray-800">Nada por aquí</p>
                  <p class="text-sm text-gray-500">No hay actas para los filtros aplicados.</p>
                </div>
              </td>
            </tr>

            <tr v-for="row in records" :key="row.id" class="row-enter">
              <td>
                <div class="flex items-center gap-3">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><FileText class="w-4 h-4" /></span>
                  <div class="min-w-0">
                    <p class="font-semibold text-gray-900 truncate">{{ row.polling_table?.name || row.polling_table?.code || ('Acta ' + row.id) }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ buildLocation(row) }}</p>
                  </div>
                </div>
              </td>
              <td>
                <div class="flex items-center gap-3 min-w-0">
                  <div class="avatar h-8 w-8 rounded-lg text-[11px]">{{ initials(row.jury_name) }}</div>
                  <div class="min-w-0">
                    <p class="font-medium text-gray-800 truncate">{{ row.jury_name }}</p>
                    <p class="text-xs text-gray-400">{{ row.transmitted_at_human || 'Sin fecha' }}</p>
                  </div>
                </div>
              </td>
              <td class="text-right font-display font-bold text-gray-900 tabular-nums">{{ row.valid_votes ?? '—' }}</td>
              <td>
                <div class="flex flex-col gap-1">
                  <span :class="row.status_tag?.kind === 'ok' ? 'badge-green' : 'badge-amber'" class="w-fit">
                    <span class="badge-dot" :class="{ 'animate-pulse': row.status_tag?.kind !== 'ok' }"></span>
                    {{ row.status_tag?.text || 'Sin estado' }}
                  </span>
                  <div v-if="row.ai_confidence !== null && row.ai_confidence !== undefined" class="flex items-center gap-2" :title="`Confianza de la lectura: ${confidencePercent(row)}%`">
                    <div class="h-1.5 w-16 rounded-full bg-gray-100 overflow-hidden">
                      <div class="h-full rounded-full" :class="confidenceColor(row)" :style="{ width: `${confidencePercent(row)}%` }"></div>
                    </div>
                    <span class="text-[11px] text-gray-400 tabular-nums">{{ confidencePercent(row) }}%</span>
                  </div>
                </div>
              </td>
              <td class="text-right">
                <router-link
                  :to="`/admin/audit/${row.id}`"
                  class="btn-secondary px-3 py-2 text-xs hover:border-aso-primary hover:text-aso-primary"
                >
                  Corroborar
                  <ArrowRight class="w-3.5 h-3.5" />
                </router-link>
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
        :loading="isLoading"
        label="actas"
        @change="changePage"
      />
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { AlertTriangle, ArrowRight, CheckCircle2, FileText, Inbox, Search } from 'lucide-vue-next';
import axios from '@/services/axios';
import PaginationBar from '@/components/ui/PaginationBar.vue';

const PER_PAGE = 15;

const isLoading = ref(false);
const records = ref([]);
const stats = ref({ total_count: 0, processed_count: 0, review_count: 0 });
const pagination = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });

const search = ref('');
const filter = ref('all');
const page = ref(1);

const FILTERS = [
  { value: 'all', label: 'Todas', count: () => stats.value.total_count },
  { value: 'review', label: 'Requieren revisión', count: () => stats.value.review_count },
  { value: 'processed', label: 'Procesadas', count: () => stats.value.processed_count },
];

const processedPercent = computed(() => (stats.value.total_count
  ? Math.round((stats.value.processed_count / stats.value.total_count) * 100)
  : 0));

let fetchTimer = null;

const buildLocation = (row) => {
  const parts = [row.polling_table?.location, row.commune_name].filter(Boolean);
  return parts.length > 0 ? parts.join(' · ') : 'Ubicación no registrada';
};

const initials = (name) => String(name || '?')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part.charAt(0).toUpperCase())
  .join('');

// La confianza puede venir como 0-1 o 0-100 según el extractor.
const confidencePercent = (row) => {
  const value = Number(row.ai_confidence);
  if (!Number.isFinite(value)) return 0;
  return Math.round(value <= 1 ? value * 100 : value);
};

const confidenceColor = (row) => {
  const pct = confidencePercent(row);
  if (pct >= 85) return 'bg-emerald-500';
  if (pct >= 60) return 'bg-amber-400';
  return 'bg-red-400';
};

const fetchAuditRecords = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get('/admin/audit-records', {
      params: {
        search: search.value || undefined,
        filter: filter.value,
        page: page.value,
        per_page: PER_PAGE,
      },
      skipGlobalLoading: true,
    });

    const payload = data?.data || {};
    const paginated = payload.records || {};
    records.value = paginated.data || [];
    pagination.value = {
      current_page: Number(paginated.current_page || 1),
      last_page: Number(paginated.last_page || 1),
      total: Number(paginated.total || 0),
      from: Number(paginated.from || 0),
      to: Number(paginated.to || 0),
    };
    stats.value = { total_count: 0, processed_count: 0, review_count: 0, ...(payload.stats || {}) };
  } catch (error) {
    records.value = [];
  } finally {
    isLoading.value = false;
  }
};

const changePage = (target) => {
  page.value = target;
  fetchAuditRecords();
};

// Al cambiar filtro o búsqueda se vuelve a la primera página.
watch([search, filter], () => {
  clearTimeout(fetchTimer);
  fetchTimer = setTimeout(() => {
    page.value = 1;
    fetchAuditRecords();
  }, 300);
});

onMounted(fetchAuditRecords);
</script>
