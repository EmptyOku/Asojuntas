<template>
  <div class="space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="page-title">Auditoría de Actas</h1>
        <p class="page-subtitle">Bandeja de entrada de resultados transmitidos por los Tribunales de Garantías.</p>
      </div>
      
      <div class="flex items-center gap-3">
        <div class="stat-card stat-card--green px-5 py-3 sm:px-5 sm:py-3">
          <p class="text-[10px] font-bold text-emerald-700 uppercase tracking-wide">Procesadas (IA OK)</p>
          <p class="font-display text-2xl font-bold text-gray-900 tabular-nums">{{ stats.processed_count }}</p>
        </div>
        <div class="stat-card stat-card--amber px-5 py-3 sm:px-5 sm:py-3">
          <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wide">Requieren revisión</p>
          <p class="font-display text-2xl font-bold text-gray-900 tabular-nums">{{ stats.review_count }}</p>
        </div>
      </div>
    </div>

    <div class="card overflow-hidden">
      
      <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gray-50/30">
        <div class="relative w-full sm:max-w-md">
          <Search class="w-4 h-4 absolute left-3.5 top-1/2 transform -translate-y-1/2 text-gray-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Buscar por mesa, comuna o jurado..."
            class="field field-search"
          >
        </div>
        <div class="flex gap-2">
          <select v-model="filter" class="field w-auto min-w-48">
            <option value="all">Todas las actas</option>
            <option value="review">Requieren revision</option>
            <option value="processed">Procesadas</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="data-table">
          <thead class="bg-gray-50/80 text-gray-500 font-semibold border-b border-gray-100">
            <tr>
              <th class="px-6 py-4">Mesa / Ubicación</th>
              <th class="px-6 py-4">Jurado Transmisor</th>
              <th class="px-6 py-4 text-center">Votos Válidos</th>
              <th class="px-6 py-4">Estado Textract (IA)</th>
              <th class="px-6 py-4 text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="isLoading">
              <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Cargando actas...</td>
            </tr>

            <tr v-else-if="records.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No hay actas para los filtros aplicados.</td>
            </tr>

            <tr v-for="row in records" :key="row.id" class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-4">
                <p class="font-bold text-gray-900">{{ row.polling_table?.name || row.polling_table?.code || ('Acta '+row.id) }}</p>
                <p class="text-xs text-gray-500">{{ buildLocation(row) }}</p>
              </td>
              <td class="px-6 py-4">{{ row.jury_name }}<br><span class="text-xs text-gray-400">{{ row.transmitted_at_human || 'Sin fecha' }}</span></td>
              <td class="px-6 py-4 text-center font-bold text-gray-700">{{ row.valid_votes }}</td>
              <td class="px-6 py-4">
                <span :class="row.status_tag?.kind === 'ok' ? 'badge-green' : 'badge-amber'">
                  <span class="badge-dot" :class="{ 'animate-pulse': row.status_tag?.kind !== 'ok' }"></span>
                  {{ row.status_tag?.text || 'Sin estado' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <router-link :to="`/admin/audit/${row.id}`" class="btn-secondary px-3 py-2 text-xs hover:border-aso-primary hover:text-aso-primary">
                  Corroborar
                  <ArrowRight class="w-3.5 h-3.5" />
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { ArrowRight, Search } from 'lucide-vue-next';
import axios from '@/services/axios';

const isLoading = ref(false);
const records = ref([]);
const stats = ref({
  processed_count: 0,
  review_count: 0,
});

const search = ref('');
const filter = ref('all');

let fetchTimer = null;

const buildLocation = (row) => {
  const parts = [];
  if (row.polling_table?.location) {
    parts.push(row.polling_table.location);
  }
  if (row.commune_name) {
    parts.push(row.commune_name);
  }

  return parts.length > 0 ? parts.join(' - ') : 'Ubicacion no registrada';
};

const fetchAuditRecords = async () => {
  isLoading.value = true;
  try {
    const { data } = await axios.get('/admin/audit-records', {
      params: {
        search: search.value || undefined,
        filter: filter.value,
      },
      skipGlobalLoading: true,
    });

    const payload = data?.data || {};
    const paginatedRecords = payload.records?.data || [];
    records.value = paginatedRecords;
    stats.value = payload.stats || { processed_count: 0, review_count: 0 };
  } catch (error) {
    records.value = [];
    stats.value = { processed_count: 0, review_count: 0 };
  } finally {
    isLoading.value = false;
  }
};

const queueFetch = () => {
  if (fetchTimer) {
    clearTimeout(fetchTimer);
  }

  fetchTimer = setTimeout(() => {
    fetchAuditRecords();
  }, 250);
};

watch([search, filter], () => {
  queueFetch();
});

onMounted(async () => {
  await fetchAuditRecords();
});
</script>