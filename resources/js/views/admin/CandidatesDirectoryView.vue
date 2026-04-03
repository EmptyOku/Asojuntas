<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Directorio de Juntas de Acción Comunal</h1>
        <p class="text-gray-500 mt-1">Listado alfabético de barrios y sus dignatarios principales.</p>
      </div>
    </div>

    <div v-if="loading" class="flex flex-col justify-center items-center py-20 space-y-4">
      <Loader2 class="w-8 h-8 text-aso-primary animate-spin" />
      <p class="text-gray-500 font-medium">Cargando directorio desde la base de datos...</p>
    </div>

    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl flex items-center gap-3">
      <AlertCircle class="w-5 h-5" />
      <p>{{ error }}</p>
    </div>

    <div v-else class="space-y-6">

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar por nombre del barrio..."
            class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-aso-primary/50 focus:border-aso-primary transition-shadow"
          >
        </div>

        <div class="relative w-full sm:w-64">
          <Filter class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <select
            v-model="selectedCommune"
            class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-aso-primary/50 focus:border-aso-primary appearance-none bg-white transition-shadow"
          >
            <option value="">Todas las comunas</option>
            <option v-for="comuna in availableCommunes" :key="comuna" :value="comuna">
              {{ comuna }}
            </option>
          </select>
          <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
        </div>
      </div>

      <div v-if="paginatedBarrios.length === 0" class="text-center py-10 bg-white border border-gray-200 rounded-xl">
        <Building2 class="w-10 h-10 text-gray-300 mx-auto mb-3" />
        <p class="text-gray-500 font-medium">No se encontraron barrios con esos filtros.</p>
        <button v-if="searchQuery || selectedCommune" @click="resetFilters" class="mt-3 text-aso-primary hover:underline font-medium text-sm">
          Limpiar filtros
        </button>
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="barrio in paginatedBarrios"
          :key="barrio.id"
          class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm transition-all duration-200"
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
                <span v-if="barrio.commune" class="block text-xs font-medium text-gray-500 mt-0.5">
                  {{ barrio.commune.name }}
                </span>
              </div>
            </div>
            <ChevronDown
              class="w-5 h-5 text-gray-400 transition-transform duration-300"
              :class="{'rotate-180': openCardId === barrio.id}"
            />
          </button>

          <div
            v-show="openCardId === barrio.id"
            class="p-6 border-t border-gray-100 bg-gray-50/30"
          >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div class="flex items-start gap-3">
                <div class="p-2 bg-white border border-gray-200 rounded-full shadow-sm mt-1">
                  <User class="w-4 h-4 text-gray-500" />
                </div>
                <div>
                  <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Presidente</p>
                  <p class="font-semibold text-gray-900">
                    {{ barrio.president_name || 'Sin asignar / Pendiente' }}
                  </p>
                </div>
              </div>

              <div class="flex items-start gap-3">
                <div class="p-2 bg-white border border-gray-200 rounded-full shadow-sm mt-1">
                  <User class="w-4 h-4 text-gray-500" />
                </div>
                <div>
                  <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Vicepresidente</p>
                  <p class="font-semibold text-gray-900">
                    {{ barrio.vicepresident_name || 'Sin asignar / Pendiente' }}
                  </p>
                </div>
              </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
              <router-link
                :to="{ name: `admin.neighborhood.results`, params: { id: barrio.id } }"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-aso-primary text-white text-sm font-bold rounded-lg hover:bg-aso-primary-dark transition-colors shadow-sm hover:shadow"
              >
                Ver resultados totales
                <ArrowRight class="w-4 h-4" />
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between border-t border-gray-100 pt-6 mt-4 gap-4">
        <p class="text-sm text-gray-500">
          Mostrando <span class="font-bold text-gray-900">{{ startIndex + 1 }}</span> a <span class="font-bold text-gray-900">{{ Math.min(endIndex, filteredBarrios.length) }}</span> de <span class="font-bold text-gray-900">{{ filteredBarrios.length }}</span> resultados
        </p>

        <div class="flex items-center gap-2">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-gray-600"
            title="Anterior"
          >
            <ChevronLeft class="w-5 h-5" />
          </button>

          <div class="flex gap-1">
            <button
              v-for="page in totalPages"
              :key="page"
              @click="currentPage = page"
              class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
              :class="currentPage === page ? 'bg-aso-primary text-white shadow-sm' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
              {{ page }}
            </button>
          </div>

          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-gray-600"
            title="Siguiente"
          >
            <ChevronRight class="w-5 h-5" />
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from '@/services/axios';
import {
  ChevronDown,
  MapPin,
  User,
  ArrowRight,
  Loader2,
  AlertCircle,
  Building2,
  Search,
  Filter,
  ChevronLeft,
  ChevronRight
} from 'lucide-vue-next';

// Estado Base
const barrios = ref([]);
const openCardId = ref(null);
const loading = ref(true);
const error = ref(null);

// Estado de Filtros y Paginación
const searchQuery = ref('');
const selectedCommune = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const toggleCard = (id) => {
  openCardId.value = openCardId.value === id ? null : id;
};

// Reiniciar página a 1 cada vez que se busca o filtra
watch([searchQuery, selectedCommune], () => {
  currentPage.value = 1;
  openCardId.value = null; // Cierra acordeones al filtrar
});

const resetFilters = () => {
  searchQuery.value = '';
  selectedCommune.value = '';
};

// Computar la lista única de comunas disponible en los datos
const availableCommunes = computed(() => {
  // Se extrae el nombre de la comuna (si el backend lo envía) o un fallback
  const communes = barrios.value
    .map(b => b.commune?.name || `Comuna ID: ${b.commune_id}`)
    .filter(Boolean);
  return [...new Set(communes)].sort();
});

// Filtrado de la data
const filteredBarrios = computed(() => {
  return barrios.value.filter(barrio => {
    // Coincidencia de búsqueda (case insensitive)
    const matchesSearch = barrio.name.toLowerCase().includes(searchQuery.value.toLowerCase());

    // Coincidencia de comuna
    const communeValue = barrio.commune?.name || `Comuna ID: ${barrio.commune_id}`;
    const matchesCommune = selectedCommune.value === '' || communeValue === selectedCommune.value;

    return matchesSearch && matchesCommune;
  });
});

// Cálculos de Paginación
const totalPages = computed(() => Math.ceil(filteredBarrios.value.length / itemsPerPage) || 1);
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage);
const endIndex = computed(() => startIndex.value + itemsPerPage);

const paginatedBarrios = computed(() => {
  return filteredBarrios.value.slice(startIndex.value, endIndex.value);
});

// Fetch de datos
const fetchBarrios = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await axios.get('/admin/neighborhoods');
    if (response.data.success) {
      barrios.value = response.data.data;
    } else {
      error.value = "La API respondió, pero indicó un fallo al obtener los datos.";
    }
  } catch (err) {
    console.error("Error al cargar el directorio:", err);
    error.value = "Error de conexión con el servidor. Verifica que Laravel esté en ejecución.";
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchBarrios();
});
</script>
