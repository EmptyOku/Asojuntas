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

      <button
        @click="fetchBarrios(1)"
        :disabled="loading"
        class="px-6 py-2 bg-aso-primary text-white font-semibold rounded-lg hover:bg-aso-primary-dark transition-colors disabled:opacity-60"
      >
        Buscar
      </button>

      <button
        @click="resetFilters"
        :disabled="loading"
        class="px-6 py-2 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-60"
      >
        Limpiar
      </button>

      <button
        @click="generateReport"
        :disabled="loading || reportLoading"
        class="px-6 py-2 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-60"
      >
        {{ reportLoading ? 'Generando...' : 'Generar reporte' }}
      </button>
    </div>

    <div v-if="reportError" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl flex items-center gap-3">
      <AlertCircle class="w-5 h-5" />
      <p>{{ reportError }}</p>
    </div>

    <div v-if="reportData" class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <div>
          <h2 class="text-lg font-bold text-gray-900">Reporte de planchas y cuocientes por barrio</h2>
          <p class="text-sm text-gray-500">
            Generado: {{ reportData.generated_at || 'Sin fecha' }}
          </p>
        </div>
        <div class="text-sm text-gray-600">
          <span class="font-semibold text-gray-900">{{ reportData.summary?.total_neighborhoods || 0 }}</span> barrios
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="bg-gray-50 rounded-lg border border-gray-100 p-3">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Con planchas registradas</p>
          <p class="text-xl font-bold text-gray-900">{{ reportData.summary?.with_registered_slates || 0 }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg border border-gray-100 p-3">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Sin planchas registradas</p>
          <p class="text-xl font-bold text-gray-900">{{ reportData.summary?.without_registered_slates || 0 }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg border border-gray-100 p-3">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Con escrutinio</p>
          <p class="text-xl font-bold text-gray-900">{{ reportData.summary?.with_scrutiny || 0 }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg border border-gray-100 p-3">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Sin escrutinio</p>
          <p class="text-xl font-bold text-gray-900">{{ reportData.summary?.without_scrutiny || 0 }}</p>
        </div>
      </div>

      <div class="space-y-4">
        <div
          v-for="row in reportData.rows || []"
          :key="row.neighborhood_id"
          class="border border-gray-200 rounded-lg overflow-hidden"
        >
          <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <p class="font-semibold text-gray-900">{{ row.neighborhood_name }}</p>
            <p class="text-xs text-gray-500">{{ row.commune_name || 'Sin comuna' }}</p>
          </div>

          <div class="p-4 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
              <div class="p-3 rounded-lg border" :class="row.has_active_election ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                <p class="font-semibold">Elección activa</p>
                <p>{{ row.has_active_election ? 'Sí' : 'No' }}</p>
              </div>
              <div class="p-3 rounded-lg border" :class="row.has_registered_slate ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                <p class="font-semibold">Planchas registradas</p>
                <p>{{ row.has_registered_slate ? 'Sí' : 'No' }}</p>
              </div>
              <div class="p-3 rounded-lg border" :class="row.has_scrutiny ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                <p class="font-semibold">Escrutinio hecho</p>
                <p>{{ row.has_scrutiny ? 'Sí' : 'No' }}</p>
              </div>
            </div>

            <div v-if="(row.warnings || []).length > 0" class="space-y-2">
              <p
                v-for="(warning, index) in row.warnings"
                :key="`${row.neighborhood_id}-warning-${index}`"
                class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2"
              >
                {{ warning }}
              </p>
            </div>

            <div>
              <p class="text-sm font-semibold text-gray-900 mb-2">Planchas</p>
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                  <thead class="bg-gray-50 text-gray-600">
                    <tr>
                      <th class="text-left px-3 py-2 border-b border-gray-200">Plancha</th>
                      <th class="text-left px-3 py-2 border-b border-gray-200">Registrada</th>
                      <th class="text-left px-3 py-2 border-b border-gray-200">Candidatos</th>
                      <th class="text-left px-3 py-2 border-b border-gray-200">Mensaje</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="slate in row.slates || []" :key="slate.id" class="border-b border-gray-100 last:border-b-0">
                      <td class="px-3 py-2">{{ slate.name }}</td>
                      <td class="px-3 py-2">{{ slate.registered ? 'Sí' : 'No' }}</td>
                      <td class="px-3 py-2">{{ slate.total_candidates }}</td>
                      <td class="px-3 py-2">{{ slate.message }}</td>
                    </tr>
                    <tr v-if="(row.slates || []).length === 0">
                      <td colspan="4" class="px-3 py-2 text-gray-500">Sin planchas disponibles.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div>
              <p class="text-sm font-semibold text-gray-900 mb-2">Cuocientes por bloque</p>
              <div v-if="(row.cuocientes || []).length === 0" class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                No se realizó cálculo de cuocientes para este barrio.
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="(bloque, blockIndex) in row.cuocientes"
                  :key="`${row.neighborhood_id}-block-${blockIndex}`"
                  class="border border-gray-200 rounded-md"
                >
                  <div class="px-3 py-2 bg-gray-50 border-b border-gray-200">
                    <p class="font-semibold text-gray-900">{{ bloque.block_name }}</p>
                    <p class="text-xs text-gray-500">
                      Válidos: {{ bloque.votos_validos }} | Cuociente: {{ formatQuota(bloque.cuociente_electoral) }}
                    </p>
                  </div>
                  <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                      <thead class="bg-white text-gray-600">
                        <tr>
                          <th class="text-left px-3 py-2 border-b border-gray-200">Plancha</th>
                          <th class="text-left px-3 py-2 border-b border-gray-200">Votos</th>
                          <th class="text-left px-3 py-2 border-b border-gray-200">Curules</th>
                          <th class="text-left px-3 py-2 border-b border-gray-200">Residuo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(plancha, slateIndex) in bloque.planchas || []"
                          :key="`${row.neighborhood_id}-block-${blockIndex}-slate-${slateIndex}`"
                          class="border-b border-gray-100 last:border-b-0"
                        >
                          <td class="px-3 py-2">{{ plancha.plancha }}</td>
                          <td class="px-3 py-2">{{ plancha.votos }}</td>
                          <td class="px-3 py-2">{{ plancha.curules }}</td>
                          <td class="px-3 py-2">{{ formatQuota(plancha.residuo) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
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
const reportLoading = ref(false);
const reportError = ref(null);
const reportData = ref(null);

// Paginación y Filtros de Servidor
const searchQuery = ref('');
const selectedCommune = ref('');
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
});

let communesCached = false; // Flag para cachear comunas una sola vez

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
      },
      skipGlobalLoading: true,
    });

    if (response.data.success) {
      // Sincronizamos los datos con la respuesta del Controlador Paginated
      barrios.value = response.data.data.neighborhoods;
      pagination.value = response.data.data.pagination;

      // ✅ Cargamos las comunas solo la primera vez para llenar el select
      if (!communesCached && response.data.data.communes) {
        availableCommunes.value = response.data.data.communes;
        communesCached = true;
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

const generateReport = async () => {
  reportLoading.value = true;
  reportError.value = null;

  try {
    const params = {};

    if (searchQuery.value?.trim()) {
      params.search = searchQuery.value.trim();
    }

    if (selectedCommune.value) {
      params.commune_id = selectedCommune.value;
    }

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

// --- WATCHERS (OBSERVADORES) ---

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