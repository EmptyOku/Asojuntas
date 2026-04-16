<template>
  <div class="space-y-6 flex-1 flex flex-col">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button @click="goBack" class="p-2 bg-white text-gray-500 hover:text-gray-900 rounded-full shadow-sm border border-gray-100 transition-colors">
          <ArrowLeft class="w-5 h-5" />
        </button>
        <h2 class="text-xl font-bold text-gray-900">Capturar Planchas</h2>
      </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
      <div class="relative">
        <label class="text-xs font-black text-gray-400 uppercase tracking-widest mb-2 block">1. Seleccionar Barrio de Girardot</label>
        <div class="relative">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input 
            v-model="searchQuery" 
            @input="searchNeighborhoods"
            type="text" 
            placeholder="Escribe el nombre del barrio..." 
            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-aso-primary outline-none transition-all"
          >
        </div>

        <div v-if="searchResults.length > 0" class="absolute z-50 w-full mt-2 bg-white border border-gray-100 rounded-xl shadow-xl max-h-60 overflow-y-auto">
          <div 
            v-for="item in searchResults" 
            :key="item.id" 
            @click="selectNeighborhood(item)"
            class="p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0 transition-colors"
          >
            <p class="font-bold text-gray-900">{{ item.name }}</p>
            <p class="text-xs text-gray-500">{{ item.commune?.name || 'Comuna no asignada' }}</p>
          </div>
        </div>
      </div>

      <transition name="fade">
        <div v-if="selectedNeighborhood" class="bg-aso-primary/5 border border-aso-primary/10 rounded-xl p-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="bg-white p-3 rounded-lg shadow-sm">
              <MapPin class="w-6 h-6 text-aso-primary" />
            </div>
            <div>
              <p class="text-sm font-black text-aso-primary uppercase tracking-tight">{{ selectedNeighborhood.name }}</p>
              <p class="text-[10px] text-gray-500 font-bold uppercase">{{ selectedNeighborhood.commune?.name }}</p>
            </div>
          </div>
          
          <div class="flex gap-4 text-center">
            <div class="px-3 border-r border-aso-primary/20">
              <p class="text-[10px] text-gray-400 font-bold uppercase">Planchas Hoy</p>
              <p class="text-lg font-black text-gray-900">{{ selectedNeighborhood.active_election?.slates_count || 0 }}</p>
            </div>
            <button @click="selectedNeighborhood = null" class="text-gray-400 hover:text-red-500 transition-colors">
              <X class="w-5 h-5" />
            </button>
          </div>
        </div>
      </transition>
    </div>

    <div 
      class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex-1 flex flex-col transition-opacity duration-300"
      :class="{ 'opacity-40 pointer-events-none': !selectedNeighborhood }"
    >
      <div v-if="!selectedNeighborhood" class="absolute inset-0 z-10 flex items-center justify-center">
        <p class="bg-white px-4 py-2 rounded-full shadow-md text-sm font-bold text-gray-500 border border-gray-100">
          Debes seleccionar un barrio primero
        </p>
      </div>

      <input type="file" accept="image/*" multiple id="secretaryCameraInput" class="hidden" @change="handleImageUpload">

      <div v-if="capturedImages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center space-y-6">
        <div class="bg-orange-50 text-orange-500 w-24 h-24 rounded-full flex items-center justify-center">
          <ScanLine class="w-12 h-12" />
        </div>
        <div>
          <h3 class="text-lg font-bold text-gray-900">Fotografía las Planchas</h3>
          <p class="text-sm text-gray-500 mt-2 max-w-xs mx-auto">Sube todas las páginas de la plancha para el barrio <b>{{ selectedNeighborhood?.name }}</b>.</p>
        </div>
        <label for="secretaryCameraInput" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-gray-900 hover:bg-black text-white px-8 py-4 rounded-2xl font-bold text-lg shadow-md cursor-pointer transition-all hover:-translate-y-1">
          <Camera class="w-6 h-6" /> Abrir Cámara / Galería
        </label>
      </div>

      <div v-else class="flex-1 flex flex-col h-full space-y-4">
        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Páginas capturadas ({{ capturedImages.length }})</h3>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto flex-1 p-1 text-center">
          <div v-for="(img, index) in capturedImages" :key="img.id" class="relative group aspect-[3/4] bg-gray-100 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
            <img :src="img.url" class="w-full h-full object-cover">
            <div class="absolute top-2 left-2 bg-black/60 text-white text-xs font-bold px-2 py-1 rounded-md">Pág {{ index + 1 }}</div>
            <button @click="removeImage(img.id)" class="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-lg opacity-90 hover:opacity-100 shadow-md">
              <X class="w-4 h-4" />
            </button>
          </div>
          <label for="secretaryCameraInput" class="aspect-[3/4] flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 hover:bg-gray-100 cursor-pointer">
            <Plus class="w-8 h-8 text-gray-400" />
            <span class="text-xs font-bold text-gray-500">Añadir página</span>
          </label>
        </div>

        <button @click="extractPlanchas" :disabled="isExtracting" class="w-full py-4 bg-aso-primary text-white font-bold rounded-xl shadow-md hover:bg-aso-primary-dark transition-all flex items-center justify-center gap-2 disabled:opacity-70 mt-4 shrink-0">
          <template v-if="isExtracting">
            <Loader2 class="w-5 h-5 animate-spin" /> Extrayendo...
          </template>
          <template v-else>
            <Send class="w-5 h-5" /> Extraer y Revisar en {{ selectedNeighborhood?.name }}
          </template>
        </button>

        <p v-if="extractStep" class="text-xs text-gray-500 font-semibold">{{ extractStep }}</p>
        <p v-if="extractError" class="text-xs text-red-600 font-semibold">{{ extractError }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from '@/services/axios';
import { useDocumentStore } from '@/stores/document';
import { ArrowLeft, ScanLine, Camera, Send, Loader2, Plus, X, Search, MapPin } from 'lucide-vue-next';

const router = useRouter();
const docStore = useDocumentStore();

docStore.setCaptureBatchUuid(null);

// Estados de Búsqueda
const searchQuery = ref('');
const searchResults = ref([]);
const selectedNeighborhood = ref(null);

// Estados de Captura
const capturedImages = ref([]);
const isExtracting = ref(false);
const extractError = ref('');
const extractStep = ref('');

// --- LÓGICA DE BÚSQUEDA ---
const searchNeighborhoods = async () => {
  if (searchQuery.value.length < 3) {
    searchResults.value = [];
    return;
  }

  try {
    const { data } = await axios.get('/secretary/neighborhoods/search', {
      params: { q: searchQuery.value }
    });
    
    searchResults.value = data?.data || []; 
    
  } catch (error) {
    console.error("Error buscando barrios", error);
    searchResults.value = []; 
  }
};  

const selectNeighborhood = (neighborhood) => {
  selectedNeighborhood.value = neighborhood;
  searchQuery.value = '';
  searchResults.value = [];
};

// --- LÓGICA DE CAPTURA ORIGINAL ---
const handleImageUpload = (event) => {
  const files = event.target.files;
  if (!files) return;

  for (let i = 0; i < files.length; i += 1) {
    capturedImages.value.push({
      id: Date.now() + i,
      file: files[i],
      url: URL.createObjectURL(files[i]),
    });
  }
  event.target.value = '';
};

const removeImage = (idToRemove) => {
  const image = capturedImages.value.find((img) => img.id === idToRemove);
  if (image) URL.revokeObjectURL(image.url);
  capturedImages.value = capturedImages.value.filter((img) => img.id !== idToRemove);
};

// --- LÓGICA DE EXTRACCIÓN (Vinculada al flujo Slates > Election > Neighborhood) ---
const extractPlanchas = async () => {
  extractError.value = '';

  // Start each extraction flow with a fresh batch to avoid mixing records across neighborhoods.
  docStore.setCaptureBatchUuid(null);

  if (!selectedNeighborhood.value?.active_election?.id) {
    extractError.value = 'El barrio seleccionado no tiene una elección activa configurada.';
    return;
  }

  if (capturedImages.value.length === 0) {
    extractError.value = 'Debes cargar al menos una imagen.';
    return;
  }

  isExtracting.value = true;

  try {
    const extractedPages = {};

    for (let index = 0; index < capturedImages.value.length; index += 1) {
      extractStep.value = `Procesando página ${index + 1} de ${capturedImages.value.length}...`;

      const form = new FormData();
      form.append('document_file', capturedImages.value[index].file);
      form.append('page_number', String(index + 1));
      
      // VINCULACIÓN: Enviamos el election_id que pertenece al barrio seleccionado
      form.append('election_id', selectedNeighborhood.value.active_election.id);

      const { data } = await axios.post('/secretary/planchas/extract-preview', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 240000,
      });

      extractedPages[index] = data?.data?.review_page_data || { bloques: [] };
    }

    docStore.setImages(capturedImages.value, 'plancha');
    docStore.setExtractedData(extractedPages);

    router.push({
      name: 'secretary-plancha-detail',
      params: { id: 'preview' },
      query: { preview: '1', edit: 'true', election_id: selectedNeighborhood.value.active_election.id , neighborhood_name: selectedNeighborhood.value.name},
    });
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
    extractError.value = `Error en la extracción: ${backendMessage}`;
  } finally {
    isExtracting.value = false;
    extractStep.value = '';
  }
};

const goBack = () => {
  router.push('/secretary/dashboard');
};
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>