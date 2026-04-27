<template>
  <div class="space-y-6 flex-1 flex flex-col">

    <div class="flex items-center gap-4">
      <button @click="goBack" class="p-2 bg-white text-gray-500 hover:text-gray-900 rounded-full shadow-sm border border-gray-100 transition-colors">
        <ArrowLeft class="w-5 h-5" />
      </button>
      <h2 class="text-xl font-bold text-gray-900">Capturar {{ isPlancha ? 'Planchas' : 'Escrutinio' }}</h2>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex-1 flex flex-col">

      <input type="file" accept="image/*" ref="fileInputRef" :capture="dynamicCapture" class="hidden" @change="handleImageUpload"
>
      <div v-if="capturedImages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center space-y-6">
        <div :class="isPlancha ? 'bg-orange-50 text-orange-500' : 'bg-green-50 text-aso-primary'" class="w-24 h-24 rounded-full flex items-center justify-center">
          <ScanLine class="w-12 h-12" />
        </div>

        <div>
          <h3 class="text-lg font-bold text-gray-900">
            Fotografía {{ isPlancha ? 'las Planchas de Candidatos' : 'el Formato de Escrutinio' }}
          </h3>
          <p class="text-sm text-gray-500 mt-2 max-w-xs mx-auto">
            {{ isPlancha ? 'Son alrededor de 6 páginas. Asegúrate de que los nombres y números sean legibles.' : 'Debes subir una sola foto del acta de escrutinio. Enfoca claramente la tabla con los totales numéricos.' }}
          </p>
        </div>

        <button 
          @click="showOptions = true" 
          class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-gray-900 hover:bg-black text-white px-8 py-4 rounded-2xl font-bold text-lg shadow-md transition-transform hover:-translate-y-1">
          <Camera class="w-6 h-6" /> Abrir Cámara / Galería
        </button>
      </div>

      <div v-else class="flex-1 flex flex-col h-full space-y-4">
        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">
          Páginas capturadas ({{ capturedImages.length }})
        </h3>

        <div v-if="showScrutinyWarning" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800 text-sm font-semibold">
          {{ scrutinyWarningText }}
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto flex-1 p-1 custom-scrollbar">

          <div v-for="(img, index) in capturedImages" :key="img.id" 
               class="relative group aspect-[3/4] bg-gray-100 rounded-xl overflow-hidden border border-gray-200 shadow-sm transition-all hover:border-gray-300">
            
            <img :src="img.url" class="w-full h-full object-cover">

            <div class="absolute top-2 left-2 bg-black/70 text-white text-[11px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
              Pág {{ index + 1 }}
            </div>

            <button @click="removeImage(img.id)" 
                    class="absolute top-2 right-2 p-2 bg-red-600/90 text-white rounded-xl shadow-md transition-all hover:bg-red-700 active:scale-95 sm:opacity-0 sm:group-hover:opacity-100">
              <X class="w-4 h-4" />
            </button>
          </div>

          <button @click="showOptions = true" 
                  class="flex flex-col items-center justify-center gap-3 aspect-[3/4] border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 hover:bg-gray-100 hover:border-gray-400 cursor-pointer transition-all active:scale-[0.98] p-3 group">
             
             <div class="bg-gray-200 text-gray-500 p-3.5 rounded-full group-hover:bg-gray-300 group-hover:text-gray-700 transition-colors">
                <Plus class="w-6 h-6" />
             </div>
             
             <div class="flex flex-col items-center">
                <span class="text-sm font-bold text-gray-700 leading-tight">Añadir</span>
                <span class="text-sm font-bold text-gray-700 leading-tight">página</span>
             </div>
          </button>
        </div>
       
        <button @click="enviarActa" :disabled="isUploading || !canSendPackage" class="w-full py-4 bg-aso-primary text-white font-bold rounded-xl shadow-md hover:bg-aso-primary-dark transition-all flex items-center justify-center gap-2 disabled:opacity-70 mt-4 shrink-0">
          <template v-if="isUploading">
            <Loader2 class="w-5 h-5 animate-spin" /> Encolando acta...
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
  <div v-if="showOptions" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 animate-in fade-in duration-200">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden flex flex-col">
        
        <div class="p-5 border-b border-gray-100">
          <h3 class="text-lg font-bold text-gray-900 text-center">Seleccionar fuente</h3>
          <p class="text-xs text-gray-500 text-center mt-1">¿Desde dónde deseas subir la imagen?</p>
        </div>

        <div class="p-2 flex flex-col gap-1">
          <button @click="triggerInput('camera')" class="flex items-center gap-3 p-4 hover:bg-gray-50 rounded-xl transition-colors text-left w-full">
            <div class="bg-emerald-100 p-2 rounded-full text-emerald-600">
              <Camera class="w-5 h-5" />
            </div>
            <div>
              <span class="block font-bold text-gray-900">Cámara (Recomendado)</span>
              <span class="block text-xs text-gray-500">Tomar foto en tiempo real</span>
            </div>
          </button>

          <button @click="triggerInput('gallery')" class="flex items-center gap-3 p-4 hover:bg-gray-50 rounded-xl transition-colors text-left w-full">
            <div class="bg-gray-100 p-2 rounded-full text-gray-600">
              <ScanLine class="w-5 h-5" />
            </div>
            <div>
              <span class="block font-bold text-gray-900">Galería / Archivos</span>
              <span class="block text-xs text-gray-500">Seleccionar foto guardada</span>
            </div>
          </button>
        </div>

        <div class="p-3 bg-gray-50">
          <button @click="showOptions = false" class="w-full py-3 font-bold text-gray-600 hover:text-gray-900 hover:bg-gray-200 rounded-xl transition-colors text-sm">
            Cancelar
          </button>
        </div>

      </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ArrowLeft, ScanLine, Camera, Send, Loader2, Plus, X } from 'lucide-vue-next';
import { useDocumentStore } from '@/stores/document';
import axios from '@/services/axios';

const router = useRouter();
const route = useRoute();

const showOptions = ref(false);
const fileInputRef = ref(null);
const dynamicCapture = ref(null);

//Control de la camara

const triggerInput = async (mode) => {
  if (mode === 'camera') {
    dynamicCapture.value = 'environment'; // Fuerza cámara trasera
  } else {
    dynamicCapture.value = null; // Abre galería/archivos
  }
  
  showOptions.value = false; // Cierra el modal
  
  await nextTick(); // Espera a que Vue actualice el DOM
  
  if (fileInputRef.value) {
    fileInputRef.value.click(); // Dispara el click programático
  }
};

// Array reactivo para almacenar el paquete de fotos
const capturedImages = ref([]);
const isUploading = ref(false);
const uploadError = ref('');
const uploadStep = ref('');
const REQUIRED_SCRUTINY_PAGES = 1;
const PREVIEW_MAX_ATTEMPTS = 3;
const PREVIEW_BASE_BACKOFF_MS = 1200;
const PREVIEW_TIMEOUT_MS = 240000;
const DEFAULT_SCRUTINY_BLOCKS = 3;
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

const normalizeBlockTitle = (title) => {
  if (!title) return '';
  const cleaned = String(title)
    .replace(/BLOQUE\s*N[.°º]?\s*\d+\s*[-–]?\s*/i, '')
    .replace(/^BLOQUE\s*[-–:]?\s*/i, '')
    .trim();
  return cleaned || String(title).trim();
};

const toVoteNumber = (value) => {
  const digits = String(value ?? '').replace(/[^\d]/g, '');
  return digits ? Number(digits) : 0;
};

const pickVote = (votes, patterns) => {
  for (const [label, value] of Object.entries(votes || {})) {
    const normalized = String(label || '').toLowerCase();
    if (patterns.some((regex) => regex.test(normalized))) {
      return toVoteNumber(value);
    }
  }
  return 0;
};

const buildNormalizedPayload = (page, pageIndex) => {
  const blockResults = [];
  const blockVotes = [];

  for (const bloque of page?.bloques || []) {
    const blockName = normalizeBlockTitle(bloque.titulo);
    const votes = bloque.votos || {};

    blockVotes.push({
      block_name: blockName,
      total_votes: pickVote(votes, [/total\s*votos?/i, /^total$/i]),
      plancha_1: pickVote(votes, [/plancha\s*1/i]),
      plancha_2: pickVote(votes, [/plancha\s*2/i]),
      plancha_3: pickVote(votes, [/plancha\s*3/i]),
      blancos: pickVote(votes, [/blancos?/i]),
      nulos: pickVote(votes, [/nulos?/i]),
      no_marcados: pickVote(votes, [/no\s*marcados?/i]),
      validos: pickVote(votes, [/v[aá]lidos?/i, /validos?/i]),
    });

    for (const [label, value] of Object.entries(votes)) {
      const match = /Plancha\s*(\d+)/i.exec(String(label));
      if (!match) continue;

      blockResults.push({
        block_name: blockName,
        slate_code: `P${match[1]}`,
        votes: toVoteNumber(value),
        status: 'pending',
        notes: `Dato validado por jurado en pagina ${pageIndex + 1}`,
      });
    }
  }

  return {
    block_results: blockResults,
    block_votes: blockVotes,
    elected_people: [],
  };
};

const resolveIntegrationContext = async () => {
  let recordId = null;
  let pollingTableId = null;

  const localPollingTable = Number(localStorage.getItem('juryPollingTableId') || 0);
  if (localPollingTable > 0) {
    pollingTableId = localPollingTable;
  }

  const localRecordId = Number(localStorage.getItem('juryLastScrutinyRecordId') || 0);
  if (localRecordId > 0) {
    recordId = localRecordId;
  }

  try {
    const { data } = await axios.get('/jury/context');
    const ctx = data?.data || {};
    const suggestedRecordId = Number(ctx.suggested_scrutiny_record_id || 0);
    const suggestedPollingTableId = Number(ctx.suggested_polling_table_id || 0);

    if (suggestedRecordId > 0) {
      recordId = suggestedRecordId;
    }

    if (suggestedPollingTableId > 0) {
      pollingTableId = suggestedPollingTableId;
    }
  } catch (error) {
    // If backend context is unavailable, local fallback is still valid.
  }

  return { recordId, pollingTableId };
};

const submitScrutinyPackageInBackground = async () => {
  const context = await resolveIntegrationContext();
  let recordId = context.recordId;
  const pollingTableId = context.pollingTableId;

  if (!recordId && !pollingTableId) {
    throw new Error('No se pudo resolver la mesa de votacion para enviar el acta.');
  }

  const uploadSinglePage = async (forcedRecordId = null) => {
    const image = capturedImages.value[0];
    if (!image?.file) {
      return null;
    }

    const uploadForm = new FormData();

    const effectiveRecordId = forcedRecordId || recordId;
    if (effectiveRecordId) {
      uploadForm.append('scrutiny_record_id', String(effectiveRecordId));
    }

    if (!effectiveRecordId && pollingTableId) {
      uploadForm.append('polling_table_id', String(pollingTableId));
    }

    uploadForm.append('document_file', image.file);
    uploadForm.append('page_number', '1');
    uploadForm.append('is_primary', '1');
    uploadForm.append('notes', 'Enviado directamente desde captura jurado.');
    uploadForm.append('source_type', 'ai');
    uploadForm.append('engine_name', 'Jury-UI-Capture-Queue');
    uploadForm.append('engine_version', 'queue-ocr');

    const { data } = await axios.post('/jury/submit', uploadForm, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    const responseData = data?.data || {};
    if (responseData.scrutiny_record_id) {
      recordId = Number(responseData.scrutiny_record_id);
    }

    return responseData;
  };

  if (!recordId) {
    uploadStep.value = 'Encolando acta para procesamiento...';
    await uploadSinglePage(null);
  } else {
    uploadStep.value = 'Actualizando acta en cola...';
    await uploadSinglePage(recordId);
  }

  if (pollingTableId) {
    localStorage.setItem('juryPollingTableId', String(pollingTableId));
  }

  if (recordId) {
    localStorage.setItem('juryLastScrutinyRecordId', String(recordId));
  }
};

const createManualVotesTemplate = () => ({
  'Votos totales': 0,
  'Plancha 1': 0,
  'Plancha 2': 0,
  'Plancha 3': 0,
  'Votos blancos': 0,
  'Votos nulos': 0,
  'Votos no marcados': 0,
  'Votos validos': 0,
});

const createManualScrutinyPageTemplate = () => ({
  bloques: Array.from({ length: DEFAULT_SCRUTINY_BLOCKS }, (_, index) => ({
    titulo: `Bloque - BLOQUE ${index + 1}`,
    votos: createManualVotesTemplate(),
  })),
});

const shouldFallbackToManualReview = (error) => {
  const status = error?.response?.status;
  const code = error?.response?.data?.error_code;

  if (status === 503) {
    return true;
  }

  return code === 'bedrock_connectivity_error' || code === 'bedrock_credentials_error';
};

const isRetryablePreviewError = (error) => {
  const backendRetryable = error?.response?.data?.retriable;
  if (typeof backendRetryable === 'boolean') {
    return backendRetryable;
  }

  const backendCode = error?.response?.data?.error_code;
  if (backendCode === 'bedrock_connectivity_error') {
    return true;
  }

  const status = error?.response?.status;
  if (!status) {
    return true;
  }

  if ([408, 429, 500, 502, 503, 504].includes(status)) {
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
  docStore.clearExtractionWarning();

  try {
    if (!isPlancha.value) {
      uploadStep.value = 'Enviando acta a la cola de procesamiento...';
      await submitScrutinyPackageInBackground();
      docStore.clearStore();
      router.push('/jury/dashboard');
      return;
    }

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
    if (!isPlancha.value) {
      const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
      uploadError.value = `No se pudo encolar el acta: ${backendMessage}`;
      return;
    }

    const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
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
