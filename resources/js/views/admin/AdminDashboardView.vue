<template>
  <div class="space-y-8">
    
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Dashboard General</h1>
      <p class="text-gray-500 mt-1">Resumen en tiempo real del escrutinio electoral.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      
      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
          <FileText class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Actas Escrutadas</p>
          <h3 class="text-3xl font-bold text-gray-900">{{ stats.processed_count }} <span class="text-sm font-medium text-gray-400">/ {{ stats.total_count }}</span></h3>
          <p class="text-xs text-blue-600 font-medium mt-1">{{ processedPercent }} del total</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-aso-primary/10 text-aso-primary rounded-xl">
          <CheckCircle class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Votos Procesados</p>
          <h3 class="text-3xl font-bold text-gray-900">{{ formattedValidVotes }}</h3>
          <p class="text-xs text-aso-primary font-medium mt-1">Sumatoria de votos válidos</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
          <Users class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Jurados Activos</p>
          <h3 class="text-3xl font-bold text-gray-900">{{ stats.active_juries_count }}</h3>
          <p class="text-xs text-purple-600 font-medium mt-1">Con actas pendientes</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-red-50 text-red-600 rounded-xl">
          <AlertTriangle class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Requieren Revisión</p>
          <h3 class="text-3xl font-bold text-gray-900">{{ stats.review_count }}</h3>
          <p class="text-xs text-red-600 font-medium mt-1">Auditoría manual</p>
        </div>
      </div>

    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden">
      <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Últimas Actas Recibidas</h3>
        <router-link to="/admin/audit" class="text-sm font-medium text-aso-primary hover:text-aso-primary-dark">Ver todas</router-link>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50/50 text-gray-500 font-medium border-b border-gray-100">
            <tr>
              <th class="px-6 py-4 font-medium">Mesa</th>
              <th class="px-6 py-4 font-medium">Jurado Remitente</th>
              <th class="px-6 py-4 font-medium">Hora de Recepción</th>
              <th class="px-6 py-4 font-medium">Estado IA</th>
              <th class="px-6 py-4 font-medium text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="isLoading">
              <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Cargando actas...</td>
            </tr>
            <tr v-else-if="rows.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No hay actas pendientes de revisión.</td>
            </tr>
            <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-4 font-medium text-gray-900">
                <div class="space-y-1">
                  <p>{{ row.polling_table?.name || row.polling_table?.code || ('Acta '+row.id) }}</p>
                  <p class="text-xs text-gray-500">{{ row.polling_table?.location || 'Dirección no registrada' }}</p>
                </div>
              </td>
              <td class="px-6 py-4">{{ row.jury_name }}</td>
              <td class="px-6 py-4 text-gray-500">{{ row.transmitted_at_human || 'Sin fecha' }}</td>
              <td class="px-6 py-4">
                <span
                  :class="row.status_tag?.kind === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
                >
                  <span :class="row.status_tag?.kind === 'ok' ? 'bg-green-500' : 'bg-red-500'" class="w-1.5 h-1.5 rounded-full"></span>
                  {{ row.status_tag?.text || 'Sin estado' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <router-link :to="`/admin/audit/${row.id}`" class="text-aso-primary font-medium hover:text-aso-primary-dark transition-colors">Revisar</router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { FileText, CheckCircle, Users, AlertTriangle } from 'lucide-vue-next';
import axios from '@/services/axios';

const isLoading = ref(false);
const rows = ref([]);

const stats = ref({
  total_count: 0,
  processed_count: 0,
  review_count: 0,
  active_juries_count: 0,
  valid_votes_total: 0,
});

const processedPercent = computed(() => {
  if (!stats.value.total_count) {
    return '0%';
  }

  const percent = (stats.value.processed_count / stats.value.total_count) * 100;
  return `${percent.toFixed(1)}%`;
});

const formattedValidVotes = computed(() => {
  const value = Number(stats.value.valid_votes_total || 0);
  return value.toLocaleString('es-CO');
});

const fetchDashboard = async () => {
  isLoading.value = true;

  try {
    const { data } = await axios.get('/admin/audit-records', {
      params: {
        filter: 'review',
        per_page: 5,
      },
    });

    const payload = data?.data || {};
    stats.value = {
      total_count: Number(payload?.stats?.total_count || 0),
      processed_count: Number(payload?.stats?.processed_count || 0),
      review_count: Number(payload?.stats?.review_count || 0),
      active_juries_count: Number(payload?.stats?.active_juries_count || 0),
      valid_votes_total: Number(payload?.stats?.valid_votes_total || 0),
    };

    rows.value = Array.isArray(payload?.records?.data) ? payload.records.data : [];
  } catch (error) {
    console.error('No fue posible cargar el dashboard:', error);
    rows.value = [];
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchDashboard();
});
</script>