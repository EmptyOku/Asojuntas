<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Bitácora del Sistema</h1>
        <p class="text-sm text-gray-500 mt-1">Consulta de todo lo que hace el programa: usuarios, acciones, entidades y fechas.</p>
      </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden">
      <div class="p-5 border-b border-gray-100 grid grid-cols-1 lg:grid-cols-5 gap-3 bg-gray-50/30">
        <input v-model.trim="filters.action" type="text" placeholder="Acción" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary">
        <input v-model.trim="filters.auditable_type" type="text" placeholder="Entidad" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary">
        <input v-model.trim="filters.auditable_id" type="number" min="1" placeholder="ID entidad" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary">
        <input v-model="filters.from_date" type="date" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary">
        <div class="flex gap-2">
          <button type="button" class="px-4 py-2 rounded-lg bg-aso-primary text-white text-sm font-semibold" @click="fetchLogs">Filtrar</button>
          <button type="button" class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700" @click="clearFilters">Limpiar</button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50/80 text-gray-500 font-semibold border-b border-gray-100">
            <tr>
              <th class="px-6 py-4">Fecha</th>
              <th class="px-6 py-4">Usuario</th>
              <th class="px-6 py-4">Acción</th>
              <th class="px-6 py-4">Entidad</th>
              <th class="px-6 py-4">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="isLoading">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500">Cargando bitácora...</td>
            </tr>
            <tr v-else-if="records.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500">No hay registros para los filtros aplicados.</td>
            </tr>
            <tr v-for="log in records" :key="log.id" class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-4">
                <p class="font-medium text-gray-900">{{ log.created_at_human || log.created_at || 'Sin fecha' }}</p>
                <p class="text-xs text-gray-400">ID {{ log.id }}</p>
              </td>
              <td class="px-6 py-4">
                <p class="font-medium text-gray-900">{{ log.user?.name || 'Sin usuario' }}</p>
                <p class="text-xs text-gray-400">{{ log.user?.username || '-' }}</p>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">{{ log.action }}</span>
              </td>
              <td class="px-6 py-4">
                <p class="font-medium text-gray-900 break-all">{{ log.auditable_type || 'N/A' }}</p>
                <p class="text-xs text-gray-400">{{ log.auditable_id || '-' }}</p>
              </td>
              <td class="px-6 py-4 text-gray-700">{{ log.ip_address || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-3 text-sm">
        <p class="text-gray-500">Mostrando <span class="font-semibold text-gray-900">{{ pagination.from || 0 }}</span> a <span class="font-semibold text-gray-900">{{ pagination.to || 0 }}</span> de <span class="font-semibold text-gray-900">{{ pagination.total }}</span> registros</p>
        <div class="flex items-center gap-2">
          <button type="button" class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 disabled:opacity-50" :disabled="isLoading || pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">Anterior</button>
          <span class="px-3 py-1.5 rounded-lg bg-gray-50 text-gray-600 text-xs font-semibold border border-gray-200">Página {{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button type="button" class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 disabled:opacity-50" :disabled="isLoading || pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">Siguiente</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import axios from '@/services/axios';

const isLoading = ref(false);
const records = ref([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
  from: 0,
  to: 0,
});

const filters = reactive({
  action: '',
  auditable_type: '',
  auditable_id: '',
  from_date: '',
  to_date: '',
  page: 1,
  per_page: 25,
});

let timer = null;

const buildParams = () => {
  const params = { page: filters.page, per_page: filters.per_page };

  if (filters.action) params.action = filters.action;
  if (filters.auditable_type) params.auditable_type = filters.auditable_type;
  if (filters.auditable_id) params.auditable_id = filters.auditable_id;
  if (filters.from_date) params.from_date = filters.from_date;
  if (filters.to_date) params.to_date = filters.to_date;

  return params;
};

const fetchLogs = async () => {
  isLoading.value = true;

  try {
    const { data } = await axios.get('/admin/audit-logs', {
      params: buildParams(),
      skipGlobalLoading: true,
    });
    const payload = data?.data || {};
    const page = payload.records || {};

    records.value = Array.isArray(page.data) ? page.data : [];
    pagination.value = {
      current_page: Number(page.current_page || 1),
      last_page: Number(page.last_page || 1),
      per_page: Number(page.per_page || filters.per_page),
      total: Number(page.total || 0),
      from: Number(page.from || 0),
      to: Number(page.to || 0),
    };
  } catch (error) {
    records.value = [];
  } finally {
    isLoading.value = false;
  }
};

const queueFetch = () => {
  if (timer) {
    clearTimeout(timer);
  }

  timer = setTimeout(() => {
    filters.page = 1;
    fetchLogs();
  }, 250);
};

const clearFilters = () => {
  filters.action = '';
  filters.auditable_type = '';
  filters.auditable_id = '';
  filters.from_date = '';
  filters.to_date = '';
  filters.page = 1;
  fetchLogs();
};

const changePage = (page) => {
  filters.page = page;
  fetchLogs();
};

watch(
  () => [filters.action, filters.auditable_type, filters.auditable_id, filters.from_date, filters.to_date],
  () => queueFetch()
);

onMounted(() => {
  fetchLogs();
});
</script>