<template>
  <div class="space-y-6">
    <!-- Encabezado -->
    <section class="card p-5 sm:p-6 flex flex-col lg:flex-row lg:items-center gap-5">
      <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-aso-primary">
          <ClipboardCheck class="w-3.5 h-3.5" />
          Revisión de planchas
        </p>
        <h2 class="page-title mt-1">Bandeja de revisión</h2>
        <p class="page-subtitle">Revisa cada plancha, apruébala por lote y oficialízala para publicarla.</p>
      </div>

      <div class="flex w-full lg:w-auto flex-col sm:flex-row gap-2">
        <div class="relative w-full sm:w-72">
          <Search class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model.trim="searchQuery"
            type="search"
            class="field field-search"
            placeholder="Buscar barrio, nombre o documento"
            aria-label="Buscar barrio, nombre o documento"
            @input="handleSearch"
          />
        </div>
        <button type="button" class="btn-secondary" :disabled="loading" @click="fetchData(pagination.current_page)">
          <RefreshCw :class="{ 'animate-spin': loading }" class="w-4 h-4" />
          Recargar
        </button>
      </div>
    </section>

    <!-- Pasos del flujo -->
    <ol class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm" aria-label="Pasos de la revisión">
      <li v-for="(stepItem, index) in STEPS" :key="stepItem.title" class="card px-4 py-3 flex items-start gap-3">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-aso-primary/10 font-display text-sm font-bold text-aso-primary">{{ index + 1 }}</span>
        <div>
          <p class="font-semibold text-gray-900">{{ stepItem.title }}</p>
          <p class="text-xs text-gray-500">{{ stepItem.text }}</p>
        </div>
      </li>
    </ol>

    <div v-if="loading && !neighborhoods.length" class="space-y-3">
      <div v-for="n in 4" :key="n" class="card h-20 animate-pulse"></div>
    </div>

    <div v-else-if="errorMessage" class="card p-8 flex flex-col items-center text-center gap-3">
      <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-500"><AlertCircle class="w-6 h-6" /></span>
      <div>
        <p class="font-display text-lg font-semibold text-gray-900">No se pudo cargar la bandeja</p>
        <p class="text-sm text-gray-500">{{ errorMessage }}</p>
      </div>
      <button type="button" class="btn-primary" @click="fetchData(1)"><RefreshCw class="w-4 h-4" /> Reintentar</button>
    </div>

    <div v-else-if="!neighborhoods.length" class="card p-10 flex flex-col items-center text-center gap-3">
      <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><Inbox class="w-7 h-7" /></span>
      <p class="font-display text-lg font-semibold text-gray-900">Bandeja vacía</p>
      <p class="text-sm text-gray-500 max-w-sm">
        {{ searchQuery ? 'Ninguna plancha coincide con la búsqueda.' : 'No hay planchas capturadas pendientes de revisión.' }}
      </p>
    </div>

    <div v-else class="space-y-3" :class="{ 'opacity-60 transition-opacity': loading }">
      <NeighborhoodAccordion
        v-for="neighborhood in neighborhoods"
        :key="neighborhood.election_id"
        :neighborhood="neighborhood"
        @reload-requested="fetchData(pagination.current_page)"
      />

      <div class="card overflow-hidden">
        <PaginationBar
          :current="pagination.current_page"
          :last="pagination.last_page"
          :total="pagination.total_neighborhoods"
          :from="pageFrom"
          :to="pageFrom + neighborhoods.length - 1"
          :loading="loading"
          label="barrios"
          @change="fetchData"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { AlertCircle, ClipboardCheck, Inbox, RefreshCw, Search } from 'lucide-vue-next';
import axios from '@/services/axios';
import PaginationBar from '@/components/ui/PaginationBar.vue';
import NeighborhoodAccordion from '@/components/secretary/NeighborhoodAccordion.vue';

const STEPS = [
  { title: 'Revisar', text: 'Abre la plancha y corrige lo que la extracción leyó mal.' },
  { title: 'Aprobar lote', text: 'Aprueba todos los candidatos de la plancha.' },
  { title: 'Oficializar', text: 'Publica lo aprobado en las planchas oficiales.' },
];

const searchQuery = ref('');
const loading = ref(false);
const errorMessage = ref('');
const neighborhoods = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total_neighborhoods: 0 });
// El servidor pagina de a 10 barrios.
const pageFrom = computed(() => (pagination.value.current_page - 1) * 10 + 1);

const fetchData = async (page = 1) => {
  loading.value = true;
  errorMessage.value = '';
  try {
    const { data } = await axios.get('/secretary/planchas/drafts/grouped', {
      params: { page, q: searchQuery.value || undefined },
      skipGlobalLoading: true,
    });
    neighborhoods.value = data?.data ?? [];
    pagination.value = { ...pagination.value, ...(data?.meta ?? {}) };
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error?.message || 'Error de conexión.';
  } finally {
    loading.value = false;
  }
};

let searchTimeout = null;
const handleSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchData(1), 500);
};

onMounted(() => fetchData(1));
</script>
