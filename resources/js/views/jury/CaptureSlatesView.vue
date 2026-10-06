<template>
  <div class="space-y-5">
    <!-- Encabezado -->
    <section class="card p-5 flex items-start gap-3">
      <button type="button" class="btn-secondary px-3 shrink-0" aria-label="Volver" @click="goBack">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="min-w-0">
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-aso-primary">
          <ScanLine class="w-3.5 h-3.5" />
          {{ isPlancha ? 'Planchas de candidatos' : 'Acta de escrutinio' }}
        </p>
        <h2 class="page-title mt-1">{{ isPlancha ? 'Capturar planchas' : 'Cargar el acta' }}</h2>
        <p class="page-subtitle">
          {{ isPlancha
            ? `Hasta ${MAX_PLANCHA_PAGES} páginas. Que los nombres y números se lean bien.`
            : 'Una sola foto del acta. Enfoca bien la tabla con los totales.' }}
        </p>
      </div>
    </section>

    <section class="card overflow-hidden">
      <div class="card-header">
        <div>
          <h3 class="card-title">{{ isPlancha ? 'Fotos de la plancha' : 'Foto del acta' }}</h3>
          <p class="card-subtitle">Toma la foto con buena luz, sin sombras y con la hoja completa.</p>
        </div>
        <span v-if="capturedImages.length" class="badge-green shrink-0">{{ capturedImages.length }} de {{ maxPages }}</span>
      </div>

      <input ref="fileInputRef" type="file" accept="image/*" :capture="dynamicCapture" class="hidden" @change="handleImageUpload">

      <!-- Sin fotos todavía -->
      <div v-if="!capturedImages.length" class="p-5">
        <button
          type="button"
          class="group flex w-full flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50/70 px-6 py-12 text-center transition-colors hover:border-aso-primary hover:bg-emerald-50/40"
          @click="showOptions = true"
        >
          <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-aso-primary shadow-sm ring-1 ring-gray-200 transition-transform group-hover:-translate-y-0.5">
            <Camera class="w-7 h-7" />
          </span>
          <span class="font-display text-lg font-semibold text-gray-900">
            {{ isPlancha ? 'Fotografía las planchas' : 'Fotografía el acta de escrutinio' }}
          </span>
          <span class="text-sm text-gray-500 max-w-xs">Puedes tomarla con la cámara o elegirla de la galería.</span>
          <span class="btn-primary mt-1 pointer-events-none"><Camera class="w-4 h-4" /> Abrir cámara o galería</span>
        </button>
      </div>

      <!-- Fotos cargadas -->
      <div v-else class="p-5 space-y-5">
        <p v-if="showScrutinyWarning" class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm flex items-start gap-2" role="alert">
          <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0 text-amber-600" /> {{ scrutinyWarningText }}
        </p>

        <CapturedPagesGrid
          :images="capturedImages"
          :max="maxPages"
          :rotating-id="rotatingId"
          @remove="removeImage"
          @move="moveImage"
          @rotate="rotateImage"
          @add="showOptions = true"
        />

        <p v-if="uploadError" class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-start gap-2" role="alert">
          <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0" /> {{ uploadError }}
        </p>

        <div class="border-t border-gray-100 pt-5 space-y-2">
          <button type="button" class="btn-primary w-full py-3" :disabled="isUploading || !canSendPackage" @click="enviarActa">
            <Loader2 v-if="isUploading" class="w-4 h-4 animate-spin" />
            <Send v-else class="w-4 h-4" />
            {{ isUploading ? 'Enviando…' : (isPlancha ? 'Enviar planchas' : 'Enviar acta') }}
          </button>
          <p v-if="uploadStep" class="text-xs text-gray-500 text-center">
            <Loader2 class="w-3.5 h-3.5 inline -mt-0.5 mr-1 animate-spin text-aso-primary" />{{ uploadStep }}
          </p>
        </div>
      </div>
    </section>
  </div>

  <!-- De dónde sale la foto -->
  <Teleport to="body">
    <div v-if="showOptions" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-slate-950/60 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Elegir de dónde subir la foto" @click.self="showOptions = false">
      <div class="w-full max-w-sm rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden animate-rise">
        <div class="px-6 pt-6 pb-3 text-center">
          <h3 class="font-display text-lg font-bold text-gray-900">¿De dónde subes la foto?</h3>
          <p class="mt-1 text-sm text-gray-500">La cámara suele dar mejor resultado.</p>
        </div>

        <div class="px-3 pb-3 space-y-1">
          <button type="button" class="flex w-full items-center gap-3 rounded-2xl p-3 text-left transition-colors hover:bg-gray-50" @click="triggerInput('camera')">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-aso-primary"><Camera class="w-5 h-5" /></span>
            <span>
              <span class="block font-semibold text-gray-900">Cámara <span class="badge-green ml-1">Recomendado</span></span>
              <span class="block text-xs text-gray-500">Tomar la foto ahora</span>
            </span>
          </button>
          <button type="button" class="flex w-full items-center gap-3 rounded-2xl p-3 text-left transition-colors hover:bg-gray-50" @click="triggerInput('gallery')">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600"><ImageIcon class="w-5 h-5" /></span>
            <span>
              <span class="block font-semibold text-gray-900">Galería o archivos</span>
              <span class="block text-xs text-gray-500">Elegir una foto ya guardada</span>
            </span>
          </button>
        </div>

        <div class="px-6 py-4 bg-gray-50">
          <button type="button" class="btn-secondary w-full" @click="showOptions = false">Cancelar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { AlertTriangle, ArrowLeft, Camera, Image as ImageIcon, Loader2, ScanLine, Send } from 'lucide-vue-next';
import CapturedPagesGrid from '@/components/ui/CapturedPagesGrid.vue';
import { rotateCapturedImage } from '@/utils/imageRotation';
import { useDocumentStore } from '@/stores/document';
import axios, { extractorInstance } from '@/services/axios';

const router = useRouter();
const route = useRoute();

const showOptions = ref(false);
const fileInputRef = ref(null);
const dynamicCapture = ref(null);

//Control de la camara

const triggerInput = async (mode) => {
  // 1. SEGURIDAD: Definir el límite exacto según el tipo de documento
  const limit = isPlancha.value ? MAX_PLANCHA_PAGES : REQUIRED_SCRUTINY_PAGES;
  
  // 2. Bloquear si ya se alcanzó o superó el límite (aplica para ambos casos)
  if (capturedImages.value.length >= limit) {
    showOptions.value = false;
    return;
  }

  // 3. Configurar el modo (cámara o galería)
  if (mode === 'camera') {
    dynamicCapture.value = 'environment';
  } else {
    dynamicCapture.value = null;
  }
  
  // 4. Cerrar el modal para limpiar la interfaz
  showOptions.value = false;
  
  // 5. CRÍTICO: Esperar que Vue actualice el DOM
  await nextTick();
  
  // 6. Disparar el click programático UNA SOLA VEZ
  if (fileInputRef.value) {
    fileInputRef.value.click();
  }
};

// Array reactivo para almacenar el paquete de fotos
const capturedImages = ref([]);
const isUploading = ref(false);
const uploadError = ref('');
const uploadStep = ref('');
const REQUIRED_SCRUTINY_PAGES = 1;
const PREVIEW_MAX_ATTEMPTS = 3;
const MAX_PLANCHA_PAGES = 6;
const PREVIEW_BASE_BACKOFF_MS = 1200;

const DEFAULT_SCRUTINY_BLOCKS = 4; // Ajustado a 4 bloques según el nuevo formato visual
const docStore = useDocumentStore();

const isPlancha = computed(() => route.query.doc === 'plancha');
// Máximo de páginas según el documento: 6 para planchas, 1 para el acta.
const maxPages = computed(() => (isPlancha.value ? MAX_PLANCHA_PAGES : REQUIRED_SCRUTINY_PAGES));
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
  const message = String(error?.response?.data?.message || error?.response?.data?.error || error?.message || '').toLowerCase();

  if (
    error?.code === 'ECONNABORTED'
    || error?.code === 'ERR_NETWORK'
    || message.includes('timeout')
    || message.includes('timed out')
    || message.includes('network error')
  ) {
    return true;
  }

  if (status === 503) {
    return true;
  }

  if ([500, 502, 504].includes(status)) {
    return true;
  }

  return [
    'bedrock_connectivity_error',
    'bedrock_credentials_error',
    'extractor_process_failed',
    'extractor_invalid_json',
  ].includes(code);
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
      const { data } = await extractorInstance.post('/jury/extract-preview', form, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },

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

  const limit = isPlancha.value ? MAX_PLANCHA_PAGES : REQUIRED_SCRUTINY_PAGES;

  // Para escrutinio solo se conserva una imagen (la ultima seleccionada).
  if (!isPlancha.value) {
    const file = files[files.length - 1];
    if (!file) {
      event.target.value = '';
      return;
    }

    capturedImages.value.forEach((img) => URL.revokeObjectURL(img.url));
    capturedImages.value = [{
      id: Date.now(),
      file,
      url: URL.createObjectURL(file),
    }];

    event.target.value = '';
    return;
  }

  for (let i = 0; i < files.length; i++) {
    if (capturedImages.value.length >= limit) {
        console.warn("Límite de páginas alcanzado");
        break; 
    }
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

// Intercambia una página con su vecina (el orden es el número de página que se guarda).
const moveImage = (index, direction) => {
  const target = index + direction;
  if (target < 0 || target >= capturedImages.value.length) return;
  const images = [...capturedImages.value];
  [images[index], images[target]] = [images[target], images[index]];
  capturedImages.value = images;
};

// Gira la foto 90° a la derecha. Se gira el archivo (no solo la vista), para
// que la extracción y la evidencia queden con la hoja derecha.
const rotatingId = ref(null);
const rotateImage = async (id) => {
  if (rotatingId.value !== null) return;
  rotatingId.value = id;
  try {
    capturedImages.value = await rotateCapturedImage(capturedImages.value, id);
  } catch (error) {
    console.error('No se pudo girar la imagen', error);
    uploadError.value = 'No se pudo girar la foto. Vuelve a tomarla con la hoja derecha.';
  } finally {
    rotatingId.value = null;
  }
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
