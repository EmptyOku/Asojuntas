<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-xl shadow-sm border border-gray-200">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Bandeja de Revisión de Planchas</h2>
        <p class="text-xs text-gray-500 mt-0.5">Aprueba o rechaza por lote antes de la promoción oficial.</p>
      </div>

      <div class="flex w-full md:w-auto items-center gap-2">
        <div class="relative w-full md:w-72">
          <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
            <Search class="w-4 h-4 text-gray-400" />
          </div>
          <input
            v-model="searchQuery"
            @input="handleSearch"
            type="text"
            placeholder="Buscar barrio, nombre o documento..."
            class="block w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-aso-primary focus:border-aso-primary transition-colors"
          >
        </div>

        <button
          @click="fetchData(1)"
          class="px-3 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 flex items-center gap-2"
        >
          <RefreshCw :class="{ 'animate-spin': loading }" class="w-4 h-4" />
          <span class="hidden sm:inline">Recargar</span>
        </button>
      </div>
    </div>

    <div v-if="loading && neighborhoods.length === 0" class="flex justify-center p-10">
      <div class="w-10 h-10 border-4 border-gray-200 border-t-aso-primary rounded-full animate-spin"></div>
    </div>

    <div v-else-if="errorMessage" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl shadow-sm">
      <p class="text-sm font-bold text-red-800">Error al cargar datos</p>
      <p class="text-sm text-red-700">{{ errorMessage }}</p>
    </div>

    <div v-else-if="neighborhoods.length === 0" class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-500">
      <p class="font-bold">No se encontraron resultados.</p>
      <p class="text-sm mt-1">No hay barrios con planchas pendientes que coincidan con tu búsqueda.</p>
    </div>

    <div v-else class="space-y-4">
      <NeighborhoodAccordion 
        v-for="neighborhood in neighborhoods" 
        :key="neighborhood.election_id" 
        :neighborhood="neighborhood"
        @reload-requested="fetchData(pagination.current_page)"
      />

      <div v-if="pagination.last_page > 1" class="flex items-center justify-between bg-white px-4 py-3 border border-gray-200 rounded-xl sm:px-6">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
              Mostrando página <span class="font-medium">{{ pagination.current_page }}</span> de <span class="font-medium">{{ pagination.last_page }}</span>
              (Total: {{ pagination.total_neighborhoods }} barrios)
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
              <button 
                @click="fetchData(pagination.current_page - 1)" 
                :disabled="pagination.current_page === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
              >
                <span class="sr-only">Anterior</span>
                <ChevronLeft class="h-5 w-5" />
              </button>
              
              <button 
                @click="fetchData(pagination.current_page + 1)" 
                :disabled="pagination.current_page === pagination.last_page"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
              >
                <span class="sr-only">Siguiente</span>
                <ChevronRight class="h-5 w-5" />
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Search, RefreshCw, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import axios from '@/services/axios';

// Importamos el componente hijo que crearemos en el paso 2
import NeighborhoodAccordion from '@/components/secretary/NeighborhoodAccordion.vue';

const searchQuery = ref('');
const loading = ref(false);
const errorMessage = ref('');
const neighborhoods = ref([]);

const pagination = ref({
  current_page: 1,
  last_page: 1,
  total_neighborhoods: 0
});

let searchTimeout = null;

// Función principal para traer los datos del nuevo endpoint
const fetchData = async (page = 1) => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/secretary/planchas/drafts/grouped', {
      params: {
        page: page,
        q: searchQuery.value
      },
      skipGlobalLoading: true,
    });

    neighborhoods.value = data.data;
    pagination.value = data.meta;

  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error.message;
  } finally {
    loading.value = false;
  }
};

// Evita hacer spam al backend mientras el usuario escribe
const handleSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchData(1); // Volvemos a la página 1 al buscar
  }, 500);
};

onMounted(() => {
  fetchData();
});
</script>