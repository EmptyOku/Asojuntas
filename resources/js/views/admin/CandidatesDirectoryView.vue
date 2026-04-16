<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Directorio de Juntas de Acción Comunal</h1>
        <p class="text-gray-500 mt-1">
          Listado paginado. Total registros: <span class="font-bold text-aso-primary">{{ pagination.total }}</span>
        </p>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-4">
      <div class="relative flex-1">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nombre o código del barrio..."
          class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-aso-primary/50 focus:border-aso-primary transition-shadow"
        >
        <div v-if="loading && barrios.length > 0" class="absolute right-3 top-1/2 -translate-y-1/2">
          <Loader2 class="w-4 h-4 text-aso-primary animate-spin" />
        </div>
      </div>

      <div class="relative w-full sm:w-64">
        <Filter class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <select
          v-model="selectedCommune"
          class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-aso-primary/50 focus:border-aso-primary appearance-none bg-white transition-shadow cursor-pointer"
        >
          <option value="">Todas las comunas</option>
          <option v-for="comuna in availableCommunes" :key="comuna.id" :value="comuna.id">
            {{ comuna.name }}
          </option>
        </select>
        <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
      </div>
    </div>

    <div v-if="loading && barrios.length === 0" class="flex flex-col justify-center items-center py-20 space-y-4">
      <Loader2 class="w-8 h-8 text-aso-primary animate-spin" />
      <p class="text-gray-500 font-medium">Conectando con la base de datos...</p>
    </div>

    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl flex items-center gap-3">
      <AlertCircle class="w-5 h-5" />
      <p>{{ error }}</p>
      <button @click="fetchBarrios(1)" class="ml-auto text-sm font-bold underline">Reintentar</button>
    </div>

    <div v-else class="space-y-6">
      
      <div v-if="barrios.length === 0" class="text-center py-10 bg-white border border-gray-200 rounded-xl">
        <Building2 class="w-10 h-10 text-gray-300 mx-auto mb-3" />
        <p class="text-gray-500 font-medium">No se encontraron barrios.</p>
        <button v-if="searchQuery || selectedCommune" @click="resetFilters" class="mt-3 text-aso-primary hover:underline font-medium text-sm">
          Limpiar filtros
        </button>
      </div>

      <div class="space-y-4 transition-opacity duration-200" :class="{ 'opacity-50 pointer-events-none': loading }">
        <div
          v-for="barrio in barrios"
          :key="barrio.id"
          class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm transition-all duration-200 hover:border-aso-primary/30"
          :class="{'ring-2 ring-aso-primary/20 border-aso-primary/30': openCardId === barrio.id}"
        >
          <button
            @click="toggleCard(barrio.id)"
            class="w-full flex items-center justify-between p-5 bg-white hover:bg-gray-50 transition-colors focus:outline-none"
          >
            <div class="flex items-center gap-3">
              <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                <MapPin class="w-5 h-5" />
              </div>
              <div class="text-left">
                <span class="block font-bold text-lg text-gray-900">{{ barrio.name }}</span>
                <span class="block text-xs font-medium text-gray-500 mt-0.5 uppercase tracking-wide">
                  {{ barrio.commune?.name || 'Ubicación no especificada' }}
                </span>
              </div>
            </div>
            <ChevronDown
              class="w-5 h-5 text-gray-400 transition-transform duration-300"
              :class="{'rotate-180': openCardId === barrio.id}"
            />
          </button>

          <div v-show="openCardId === barrio.id" class="p-6 border-t border-gray-100 bg-gray-50/30">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div class="flex items-start gap-3">
                <div class="p-2 bg-white border border-gray-200 rounded-full shadow-sm mt-1">
                  <User class="w-4 h-4 text-gray-500" />
                </div>
                <div>
                  <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Presidente</p>
                  <p class="font-semibold text-gray-900">{{ barrio.president_name || 'Pendiente de asignar' }}</p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="p-2 bg-white border border-gray-200 rounded-full shadow-sm mt-1">
                  <User class="w-4 h-4 text-gray-500" />
                </div>
                <div>
                  <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Vicepresidente</p>
                  <p class="font-semibold text-gray-900">{{ barrio.vicepresident_name || 'Pendiente de asignar' }}</p>
                </div>
              </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
              <router-link
                :to="{ name: 'admin.neighborhood.results', params: { id: barrio.id } }"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-aso-primary text-white text-sm font-bold rounded-lg hover:bg-aso-primary-dark transition-colors shadow-sm"
              >
                Ver resultados totales
                <ArrowRight class="w-4 h-4" />
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <div v-if="pagination.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between border-t border-gray-100 pt-6 mt-4 gap-4">
        <p class="text-sm text-gray-500">
          Página <span class="font-bold text-gray-900">{{ pagination.current_page }}</span> de 
          <span class="font-bold text-gray-900">{{ pagination.last_page }}</span> ({{ pagination.total }} barrios)
        </p>

        <div class="flex items-center gap-2">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1 || loading"
            class="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors text-gray-600"
          >
            <ChevronLeft class="w-5 h-5" />
          </button>

          <div class="flex gap-1">
            <button
              v-for="page in pagination.last_page"
              :key="page"
              @click="changePage(page)"
              :disabled="loading"
              class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
              :class="pagination.current_page === page 
                ? 'bg-aso-primary text-white shadow-sm' 
                : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
              {{ page }}
            </button>
          </div>

          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page || loading"
            class="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors text-gray-600"
          >
            <ChevronRight class="w-5 h-5" />
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from '@/services/axios';
import {
  ChevronDown, MapPin, User, ArrowRight, Loader2, AlertCircle, 
  Building2, Search, Filter, ChevronLeft, ChevronRight
} from 'lucide-vue-next';

// --- ESTADO ---
const router = useRouter();
const barrios = ref([]);
const availableCommunes = ref([]);
const openCardId = ref(null);
const loading = ref(false);
const error = ref(null);

// Paginación y Filtros de Servidor
const searchQuery = ref('');
const selectedCommune = ref('');
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
});

let debounceTimer = null;

// --- MÉTODOS ---

/**
 * Obtiene los barrios desde Laravel enviando filtros y número de página
 */
const fetchBarrios = async (page = 1) => {
  loading.value = true;
  error.value = null;

  try {
    const response = await axios.get('/admin/neighborhoods', {
      params: {
        page: page,
        search: searchQuery.value,
        commune_id: selectedCommune.value
      }
    });

    if (response.data.success) {
      // Sincronizamos los datos con la respuesta del Controlador Paginated
      barrios.value = response.data.data.neighborhoods;
      pagination.value = response.data.data.pagination;

      // Cargamos las comunas solo la primera vez para llenar el select
      if (availableCommunes.value.length === 0 && response.data.data.communes) {
        availableCommunes.value = response.data.data.communes;
      }
    }
  } catch (err) {
    console.error("Error en Directorio:", err);
    if (err?.response?.status === 401) {
      router.push({ name: 'login' });
    } else {
      error.value = "Error al conectar con el servidor.";
    }
  } finally {
    loading.value = false;
  }
};

/**
 * Cambia la página y sube el scroll suavemente
 */
const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    openCardId.value = null;
    fetchBarrios(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

/**
 * Limpia todos los filtros y reinicia la búsqueda
 */
const resetFilters = () => {
  searchQuery.value = '';
  selectedCommune.value = '';
  fetchBarrios(1);
};

const toggleCard = (id) => {
  openCardId.value = openCardId.value === id ? null : id;
};

// --- WATCHERS (OBSERVADORES) ---

// Al escribir en el buscador: Debounce de 400ms para no saturar la base de datos
watch(searchQuery, () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchBarrios(1);
  }, 400);
});

// Al cambiar la comuna: Petición inmediata
watch(selectedCommune, () => {
  fetchBarrios(1);
});

// --- INICIO ---
onMounted(() => {
  fetchBarrios(1);
});
</script>

<style scoped>
/* Para evitar parpadeos visuales al cambiar de página */
.space-y-4 {
  min-height: 400px;
}
</style>