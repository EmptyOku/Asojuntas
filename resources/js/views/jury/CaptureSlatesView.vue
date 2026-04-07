<template>
  <div class="space-y-6 flex-1 flex flex-col">
    
    <div class="flex items-center gap-4">
      <button @click="goBack" class="p-2 bg-white text-gray-500 hover:text-gray-900 rounded-full shadow-sm border border-gray-100 transition-colors">
        <ArrowLeft class="w-5 h-5" />
      </button>
      <h2 class="text-xl font-bold text-gray-900">Capturar {{ isPlancha ? 'Planchas' : 'Escrutinio' }}</h2>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex-1 flex flex-col">
      
      <input type="file" accept="image/*" multiple id="cameraInput" class="hidden" @change="handleImageUpload">

      <div v-if="capturedImages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center space-y-6">
        <div :class="isPlancha ? 'bg-orange-50 text-orange-500' : 'bg-green-50 text-aso-primary'" class="w-24 h-24 rounded-full flex items-center justify-center">
          <ScanLine class="w-12 h-12" />
        </div>
        
        <div>
          <h3 class="text-lg font-bold text-gray-900">
            Fotografía {{ isPlancha ? 'las Planchas de Candidatos' : 'el Formato de Escrutinio' }}
          </h3>
          <p class="text-sm text-gray-500 mt-2 max-w-xs mx-auto">
            {{ isPlancha ? 'Son alrededor de 6 páginas. Asegúrate de que los nombres y números sean legibles.' : 'Debes subir el paquete completo de 3 páginas juntas. Enfoca claramente las tablas con los totales numéricos.' }}
          </p>
        </div>
        
        <label for="cameraInput" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-gray-900 hover:bg-black text-white px-8 py-4 rounded-2xl font-bold text-lg shadow-md cursor-pointer transition-transform hover:-translate-y-1">
          <Camera class="w-6 h-6" /> Abrir Cámara / Galería
        </label>
      </div>

      <div v-else class="flex-1 flex flex-col h-full space-y-4">
        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">
          Páginas capturadas ({{ capturedImages.length }})
        </h3>

        <div v-if="showScrutinyWarning" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800 text-sm font-semibold">
          {{ scrutinyWarningText }}
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto flex-1 p-1">
          
          <div v-for="(img, index) in capturedImages" :key="img.id" class="relative group aspect-[3/4] bg-gray-100 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
            <img :src="img.url" class="w-full h-full object-cover">
            
            <div class="absolute top-2 left-2 bg-black/60 text-white text-xs font-bold px-2 py-1 rounded-md">
              Pág {{ index + 1 }}
            </div>
            
            <button @click="removeImage(img.id)" class="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-lg opacity-90 hover:opacity-100 shadow-md transition-opacity">
              <X class="w-4 h-4" />
            </button>
          </div>
          
          <label for="cameraInput" class="aspect-[3/4] flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 hover:bg-gray-100 cursor-pointer transition-colors">
             <Plus class="w-8 h-8 text-gray-400" />
             <span class="text-xs font-bold text-gray-500 text-center px-2">Añadir página</span>
          </label>
        </div>

        <button @click="enviarActa" :disabled="isUploading || !canSendPackage" class="w-full py-4 bg-aso-primary text-white font-bold rounded-xl shadow-md hover:bg-aso-primary-dark transition-all flex items-center justify-center gap-2 disabled:opacity-70 mt-4 shrink-0">
          <template v-if="isUploading">
            <Loader2 class="w-5 h-5 animate-spin" /> Extrayendo paquete...
          </template>
          <template v-else>
            <Send class="w-5 h-5" /> Enviar {{ isPlancha ? 'Planchas' : 'Escrutinio' }}
          </template>
        </button>

        <p v-if="uploadStep" class="text-xs text-gray-500 font-semibold">{{ uploadStep }}</p>
        <p v-if="uploadError" class="text-xs text-red-600 font-semibold">{{ uploadError }}</p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ArrowLeft, ScanLine, Camera, Send, Loader2, Plus, X } from 'lucide-vue-next';
import { useDocumentStore } from '@/stores/document';
import axios from '@/services/axios';

const router = useRouter();
const route = useRoute();

// Array reactivo para almacenar el paquete de fotos
const capturedImages = ref([]); 
const isUploading = ref(false);
const uploadError = ref('');
const uploadStep = ref('');
const REQUIRED_SCRUTINY_PAGES = 3;
const PREVIEW_MAX_ATTEMPTS = 3;
const PREVIEW_BASE_BACKOFF_MS = 1200;
const PREVIEW_TIMEOUT_MS = 240000;
const docStore = useDocumentStore();

const isPlancha = computed(() => route.query.doc === 'plancha');
const missingScrutinyPages = computed(() => Math.max(0, REQUIRED_SCRUTINY_PAGES - capturedImages.value.length));
const extraScrutinyPages = computed(() => Math.max(0, capturedImages.value.length - REQUIRED_SCRUTINY_PAGES));
const showScrutinyWarning = computed(() => !isPlancha.value && capturedImages.value.length > 0 && capturedImages.value.length !== REQUIRED_SCRUTINY_PAGES);
const scrutinyWarningText = computed(() => {
  if (missingScrutinyPages.value > 0) {
    return `Para Escrutinio debes cargar exactamente ${REQUIRED_SCRUTINY_PAGES} fotos. Te faltan ${missingScrutinyPages.value}.`;
  }

  if (extraScrutinyPages.value > 0) {
    return `Cargaste ${extraScrutinyPages.value} foto(s) de más. El paquete de Escrutinio debe tener exactamente ${REQUIRED_SCRUTINY_PAGES}.`;
  }

  return '';
});
const canSendPackage = computed(() => isPlancha.value || capturedImages.value.length === REQUIRED_SCRUTINY_PAGES);

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const isRetryablePreviewError = (error) => {
  const status = error?.response?.status;
  if (!status) {
    return true;
  }

  if ([408, 422, 429, 500, 502, 503, 504].includes(status)) {
    return true;
  }

  return false;
};

const extractPreviewForPage = async (image, pageIndex) => {
  const form = new FormData();
  form.append('document_file', image.file);
  form.append('document_type', isPlancha.value ? 'plancha' : 'escrutinio');
  form.append('page_number', String(pageIndex + 1));

  for (let attempt = 1; attempt <= PREVIEW_MAX_ATTEMPTS; attempt += 1) {
    try {
      const { data } = await axios.post('/jury/extract-preview', form, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        timeout: PREVIEW_TIMEOUT_MS,
      });

      const pageData = data?.data?.review_page_data;
      if (!pageData || !Array.isArray(pageData.bloques)) {
        throw new Error(`La extracción de la página ${pageIndex + 1} no devolvió bloques válidos.`);
      }

      return pageData;
    } catch (error) {
      if (attempt >= PREVIEW_MAX_ATTEMPTS || !isRetryablePreviewError(error)) {
        throw error;
      }

      uploadStep.value = `Conexión inestable. Reintentando página ${pageIndex + 1} (${attempt + 1}/${PREVIEW_MAX_ATTEMPTS})...`;
      await sleep(PREVIEW_BASE_BACKOFF_MS * attempt);
    }
  }

  throw new Error(`La extracción de la página ${pageIndex + 1} agotó los reintentos.`);
};

const handleImageUpload = (event) => {
  const files = event.target.files;
  if (!files) return;

  for (let i = 0; i < files.length; i++) {
    capturedImages.value.push({
      id: Date.now() + i, // ID único para manejo seguro
      file: files[i],
      url: URL.createObjectURL(files[i])
    });
  }
  
  // Limpiamos el valor del input para que el evento @change vuelva a dispararse 
  // incluso si el usuario selecciona la misma foto dos veces seguidas
  event.target.value = ''; 
};

const removeImage = (idToRemove) => {
  // Liberamos la memoria del navegador revocando la URL temporal
  const imageToOmit = capturedImages.value.find(img => img.id === idToRemove);
  if (imageToOmit) URL.revokeObjectURL(imageToOmit.url);
  
  capturedImages.value = capturedImages.value.filter(img => img.id !== idToRemove);
};

const enviarActa = async () => {
  // 1. Validamos que haya fotos
  if (capturedImages.value.length === 0) return alert("Debes subir al menos una foto.");
  if (!isPlancha.value && capturedImages.value.length !== REQUIRED_SCRUTINY_PAGES) {
    return alert(`Para escrutinio debes subir exactamente ${REQUIRED_SCRUTINY_PAGES} fotos antes de continuar.`);
  }
  
  // 2. Activamos el estado de carga para que el botón muestre el "Enviando..."
  isUploading.value = true;
  uploadError.value = '';
  uploadStep.value = '';
  
  try {
    const extractedPages = {};
    for (let index = 0; index < capturedImages.value.length; index += 1) {
      uploadStep.value = `Extrayendo página ${index + 1} de ${capturedImages.value.length}...`;
      extractedPages[index] = await extractPreviewForPage(capturedImages.value[index], index);
    }

    uploadStep.value = 'Preparando validación...';

    // 3. Guardamos en Pinia y pasamos a la siguiente vista
    docStore.setImages(capturedImages.value, isPlancha.value ? 'plancha' : 'escrutinio');
    docStore.setExtractedData(extractedPages);

    router.push('/jury/review');
  } catch (error) {
    const backendMessage = error?.response?.data?.error || error?.response?.data?.message || error?.message;
    uploadError.value = `No se pudo completar la extracción del paquete: ${backendMessage}`;
  } finally {
    isUploading.value = false;
    uploadStep.value = '';
  }
};

const goBack = () => {
  if (route.path.includes('/secretary')) {
    router.push('/secretary/dashboard');
  } else {
    router.push('/jury/dashboard');
  }
};

</script>