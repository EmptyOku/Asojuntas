<template>
  <div class="space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Auditoría de Actas</h1>
        <p class="text-sm text-gray-500 mt-1">Bandeja de entrada de resultados transmitidos por los Tribunales de Garantías.</p>
      </div>
      
      <div class="flex items-center gap-3">
        <div class="px-4 py-2 bg-green-50 border border-green-100 rounded-xl">
          <p class="text-[10px] font-bold text-green-600 uppercase">Procesadas (IA OK)</p>
          <p class="text-lg font-bold text-green-700">{{ stats.processed_count }}</p>
        </div>
        <div class="px-4 py-2 bg-orange-50 border border-orange-100 rounded-xl">
          <p class="text-[10px] font-bold text-orange-600 uppercase">Requieren Revisión</p>
          <p class="text-lg font-bold text-orange-700">{{ stats.review_count }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden">
      
      <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gray-50/30">
        <div class="relative w-full sm:max-w-md">
          <Search class="w-4 h-4 absolute left-3.5 top-1/2 transform -translate-y-1/2 text-gray-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Buscar por mesa, comuna o jurado..."
            class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary"
          >
        </div>
        <div class="flex gap-2">
          <select v-model="filter" class="border border-gray-200 rounded-lg text-sm text-gray-600 py-2 px-3 focus:outline-none">
            <option value="all">Todas las actas</option>
            <option value="review">Requieren revision</option>
            <option value="processed">Procesadas</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
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
                <span
                  :class="row.status_tag?.kind === 'ok' ? 'bg-green-50 text-green-700 border-green-100' : 'bg-orange-50 text-orange-700 border-orange-100'"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border"
                >
                  <span :class="row.status_tag?.kind === 'ok' ? 'bg-green-500' : 'bg-orange-500 animate-pulse'" class="w-1.5 h-1.5 rounded-full"></span>
                  {{ row.status_tag?.text || 'Sin estado' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <router-link :to="`/admin/audit/${row.id}`" class="inline-block bg-white border border-gray-200 hover:border-aso-primary hover:text-aso-primary text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors shadow-sm">
                  Corroborar
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
import { Search } from 'lucide-vue-next';
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