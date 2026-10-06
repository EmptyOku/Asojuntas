<template>
  <div class="space-y-6">
    <!-- Encabezado -->
    <section class="card p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4">
      <button type="button" class="btn-secondary self-start shrink-0" @click="goBack">
        <ArrowLeft class="w-4 h-4" />
        Volver
      </button>
      <div class="min-w-0">
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-aso-primary">
          <ScanLine class="w-3.5 h-3.5" />
          Registro de planchas
        </p>
        <h2 class="page-title mt-1">Escanear una plancha</h2>
        <p class="page-subtitle">Elige el barrio, sube las fotos y revisa los datos extraídos antes de registrarla.</p>
      </div>
    </section>

    <!-- Paso 1: barrio -->
    <section class="card overflow-visible">
      <div class="card-header">
        <div class="flex items-center gap-3">
          <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-display text-sm font-bold"
            :class="canCapture ? 'bg-aso-primary text-white' : 'bg-aso-primary/10 text-aso-primary'"
          >
            <Check v-if="canCapture" class="w-4 h-4" />
            <template v-else>1</template>
          </span>
          <div>
            <h3 class="card-title">Barrio</h3>
            <p class="card-subtitle">La plancha se registra en la elección activa de ese barrio.</p>
          </div>
        </div>
      </div>

      <div class="p-5 sm:p-6 space-y-4">
        <!-- Buscador (mientras no haya barrio elegido) -->
        <div v-if="!selectedNeighborhood" class="relative">
          <label for="capture-neighborhood" class="field-label">Buscar barrio de Girardot</label>
          <div class="relative">
            <Search class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              id="capture-neighborhood"
              v-model.trim="searchQuery"
              type="search"
              class="field field-search"
              placeholder="Escribe al menos 3 letras del nombre o el código"
              autocomplete="off"
              @input="searchNeighborhoods"
            >
            <Loader2 v-if="isSearching" class="w-4 h-4 text-aso-primary animate-spin absolute right-3.5 top-1/2 -translate-y-1/2" />
          </div>

          <ul v-if="searchResults.length" class="absolute z-30 mt-2 w-full max-h-64 overflow-y-auto rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 divide-y divide-gray-100">
            <li v-for="item in searchResults" :key="item.id">
              <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50" @click="selectNeighborhood(item)">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-aso-primary"><MapPin class="w-4 h-4" /></span>
                <span class="min-w-0 flex-1">
                  <span class="block font-semibold text-gray-900 truncate">{{ item.name }}</span>
                  <span class="block text-xs text-gray-500 truncate">{{ item.commune?.name || 'Comuna no asignada' }}</span>
                </span>
                <span v-if="item.active_election?.has_approved_acta" class="badge-red shrink-0">Acta aprobada</span>
                <span v-else-if="!item.active_election" class="badge-amber shrink-0">Sin elección</span>
              </button>
            </li>
          </ul>
          <p v-else-if="searchedWithoutResults" class="mt-2 text-sm text-gray-500">Ningún barrio coincide con “{{ searchQuery }}”.</p>
        </div>

        <!-- Barrio elegido -->
        <div v-else class="flex flex-col sm:flex-row sm:items-center gap-4 rounded-2xl bg-emerald-50/60 p-4 ring-1 ring-emerald-100">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-aso-primary shadow-sm"><MapPin class="w-5 h-5" /></span>
            <div class="min-w-0">
              <p class="font-display text-lg font-bold text-gray-900 truncate">{{ selectedNeighborhood.name }}</p>
              <p class="text-xs text-gray-500 truncate">{{ selectedNeighborhood.commune?.name || 'Comuna no asignada' }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <span class="badge-blue"><Files class="w-3 h-3" /> {{ selectedNeighborhood.active_election?.slates_count || 0 }} planchas registradas</span>
            <button type="button" class="btn-secondary" :disabled="isExtracting" @click="selectedNeighborhood = null">Cambiar barrio</button>
          </div>
        </div>

        <!-- Número de la plancha: los votos del acta se asignan por este número. -->
        <div v-if="canCapture" class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-2xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
          <div class="flex-1">
            <label for="capture-slate-number" class="text-sm font-semibold text-gray-900">Número de la plancha</label>
            <p class="text-xs text-gray-500">
              Debe ser el mismo número con que la plancha aparece en el tarjetón y en el acta.
              <template v-if="occupiedNumbers.length">Ya registradas: {{ occupiedNumbers.map((n) => `Plancha ${n}`).join(', ') }}.</template>
            </p>
          </div>
          <select id="capture-slate-number" v-model.number="slateNumber" class="field sm:w-44" :disabled="isExtracting">
            <option v-for="n in freeNumbers" :key="n" :value="n">Plancha {{ n }}</option>
          </select>
        </div>

        <!-- El barrio no puede recibir planchas -->
        <div v-if="selectedNeighborhood && !selectedNeighborhood.active_election" class="rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm flex items-start gap-2" role="alert">
          <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0 text-amber-600" />
          <div>
            <p class="font-bold">Este barrio no tiene una elección activa</p>
            <p>Sin elección no se pueden registrar planchas. Pídele al administrador que la cree en Geografía Electoral.</p>
          </div>
        </div>
        <div v-else-if="captureLocked" class="rounded-2xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-start gap-2" role="alert">
          <Lock class="w-4 h-4 mt-0.5 shrink-0" />
          <div>
            <p class="font-bold">Registro de planchas cerrado para este barrio</p>
            <p>Ya tiene un acta de escrutinio aprobada, así que no se pueden registrar planchas nuevas. Las ya registradas se pueden seguir consultando y corrigiendo desde la bandeja de revisión.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Paso 2: fotos -->
    <section class="card overflow-hidden">
      <div class="card-header">
        <div class="flex items-center gap-3">
          <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-display text-sm font-bold"
            :class="canCapture ? 'bg-aso-primary/10 text-aso-primary' : 'bg-gray-100 text-gray-400'"
          >2</span>
          <div>
            <h3 class="card-title" :class="{ 'text-gray-400': !canCapture }">Fotos de la plancha</h3>
            <p class="card-subtitle">Hasta {{ MAX_PLANCHA_PAGES }} páginas, con la hoja derecha. Si una quedó de lado, gírala con el botón de la foto.</p>
          </div>
        </div>
        <span v-if="capturedImages.length" class="badge-green shrink-0">{{ capturedImages.length }} {{ capturedImages.length === 1 ? 'página' : 'páginas' }}</span>
      </div>

      <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="handleImageUpload">

      <!-- Aún no se puede capturar: se explica por qué, sin tapar ni atenuar la pantalla -->
      <div v-if="!canCapture" class="p-5 sm:p-6">
        <div class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/70 px-6 py-12 text-center">
          <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-gray-400 shadow-sm ring-1 ring-gray-200">
            <Lock v-if="selectedNeighborhood" class="w-6 h-6" />
            <MapPin v-else class="w-6 h-6" />
          </span>
          <p class="font-display text-lg font-semibold text-gray-900">
            {{ selectedNeighborhood ? 'Este barrio no admite planchas nuevas' : 'Primero elige un barrio' }}
          </p>
          <p class="text-sm text-gray-500 max-w-sm">
            {{ selectedNeighborhood
              ? 'Revisa el aviso del paso 1 o elige otro barrio para continuar.'
              : 'Búscalo en el paso 1. Cuando lo elijas, aquí podrás subir las fotos de la plancha.' }}
          </p>
        </div>
      </div>

      <!-- Sin fotos todavía -->
      <div v-else-if="!capturedImages.length" class="p-5 sm:p-6">
        <button
          type="button"
          class="group flex w-full flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50/70 px-6 py-12 text-center transition-colors hover:border-aso-primary hover:bg-emerald-50/40"
          @click="openPicker"
        >
          <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-aso-primary shadow-sm ring-1 ring-gray-200 transition-transform group-hover:-translate-y-0.5">
            <Camera class="w-7 h-7" />
          </span>
          <span class="font-display text-lg font-semibold text-gray-900">Sube las fotos de la plancha</span>
          <span class="text-sm text-gray-500 max-w-sm">Toma las fotos con la cámara o elígelas de la galería. Puedes subir varias a la vez y ordenarlas después.</span>
          <span class="btn-primary mt-1 pointer-events-none"><Camera class="w-4 h-4" /> Abrir cámara o galería</span>
        </button>
      </div>

      <!-- Fotos cargadas -->
      <div v-else class="p-5 sm:p-6 space-y-5">
        <CapturedPagesGrid
          :images="capturedImages"
          :max="MAX_PLANCHA_PAGES"
          :rotating-id="rotatingId"
          @remove="removeImage"
          @move="moveImage"
          @rotate="rotateImage"
          @add="openPicker"
        />

        <p v-if="extractError" class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-start gap-2" role="alert">
          <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0" /> {{ extractError }}
        </p>

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 border-t border-gray-100 pt-5">
          <p class="text-sm text-gray-500 flex-1">
            <template v-if="extractStep"><Loader2 class="w-4 h-4 inline -mt-0.5 mr-1 animate-spin text-aso-primary" />{{ extractStep }}</template>
            <template v-else>Al continuar se extraen los datos y pasas a revisarlos. Nada se registra todavía.</template>
          </p>
          <button type="button" class="btn-primary px-6" :disabled="isExtracting" @click="askExtract">
            <Loader2 v-if="isExtracting" class="w-4 h-4 animate-spin" />
            <Send v-else class="w-4 h-4" />
            {{ isExtracting ? 'Extrayendo datos…' : 'Extraer y revisar' }}
          </button>
        </div>
      </div>
    </section>

    <ConfirmModal
      :open="confirmExtract"
      :title="`¿Extraer los datos de la Plancha ${slateNumber}?`"
      :message="`Se leerán ${capturedImages.length} página(s) de la Plancha ${slateNumber} de ${selectedNeighborhood?.name ?? 'este barrio'}. Después podrás revisar y corregir todo antes de registrarla.`"
      confirm-text="Extraer datos"
      @confirm="confirmExtract = false; extractPlanchas()"
      @cancel="confirmExtract = false"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios, { extractorInstance } from '@/services/axios';
import { useDocumentStore } from '@/stores/document';
import ConfirmModal from '@/components/ConfirmModal.vue';
import CapturedPagesGrid from '@/components/ui/CapturedPagesGrid.vue';
import { rotateCapturedImage } from '@/utils/imageRotation';
import { AlertTriangle, ArrowLeft, Camera, Check, Files, Loader2, Lock, MapPin, ScanLine, Search, Send } from 'lucide-vue-next';

const router = useRouter();
const docStore = useDocumentStore();

docStore.setCaptureBatchUuid(null);

// Estados de Búsqueda
const searchQuery = ref('');
const searchResults = ref([]);
const selectedNeighborhood = ref(null);
const captureLocked = computed(() => Boolean(selectedNeighborhood.value?.active_election?.has_approved_acta));
// Se puede capturar solo con barrio elegido, elección activa y sin acta aprobada.
const canCapture = computed(() => Boolean(selectedNeighborhood.value?.active_election) && !captureLocked.value);
// Número de plancha: por defecto el primero libre; se puede elegir otro libre.
const slateNumber = ref(1);
const occupiedNumbers = computed(() => selectedNeighborhood.value?.active_election?.occupied_slate_numbers ?? []);
const freeNumbers = computed(() => {
  const top = Math.max(3, ...occupiedNumbers.value) + 2;
  return Array.from({ length: top }, (_, index) => index + 1).filter((n) => !occupiedNumbers.value.includes(n));
});
const isSearching = ref(false);
const searchedWithoutResults = ref(false);

// El selector de archivos está oculto: lo abren el recuadro de carga y "Añadir página".
const fileInput = ref(null);
const openPicker = () => fileInput.value?.click();
let searchDebounce = null;

// Estados de Captura
const capturedImages = ref([]);
const isExtracting = ref(false);
const extractError = ref('');
const extractStep = ref('');
const MAX_PLANCHA_PAGES = 6;

// --- LÓGICA DE BÚSQUEDA ---
const searchNeighborhoods = async () => {
  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }

    searchedWithoutResults.value = false;
  if (searchQuery.value.length < 3) {
    searchResults.value = [];
    return;
  }

  searchDebounce = setTimeout(async () => {
    isSearching.value = true;
    try {
      const { data } = await axios.get('/secretary/neighborhoods/search', {
        params: { q: searchQuery.value },
        skipGlobalLoading: true,
      });

      searchResults.value = data?.data || [];
      searchedWithoutResults.value = searchResults.value.length === 0;
    } catch (error) {
      console.error("Error buscando barrios", error);
      searchResults.value = [];
    } finally {
      isSearching.value = false;
    }
  }, 300);
};  

const selectNeighborhood = (neighborhood) => {
    selectedNeighborhood.value = neighborhood;
  slateNumber.value = freeNumbers.value[0] ?? 1;
  searchQuery.value = '';
  searchResults.value = [];
  searchedWithoutResults.value = false;
};

// --- LÓGICA DE CAPTURA ORIGINAL ---
const handleImageUpload = (event) => {
  const files = event.target.files;
  if (!files) return;

  for (let i = 0; i < files.length; i += 1) {
    if (capturedImages.value.length >= MAX_PLANCHA_PAGES) {
      console.warn("Límite máximo de páginas de plancha alcanzado");
      break; 
    }

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
    extractError.value = 'No se pudo girar la foto. Vuelve a tomarla con la hoja derecha.';
  } finally {
    rotatingId.value = null;
  }
};

const createManualPageTemplate = () => ({ bloques: [] });
const PREVIEW_MAX_ATTEMPTS = 3;
const PREVIEW_BASE_BACKOFF_MS = 1200;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const hasRecognizedCandidates = (pageData) => {
  const blocks = Array.isArray(pageData?.bloques) ? pageData.bloques : [];
  return blocks.some((block) =>
    Array.isArray(block?.cargos) && block.cargos.some((cargo) =>
      String(cargo?.puesto || '').trim() !== '' || String(cargo?.nombre || '').trim() !== ''
    )
  );
};

const shouldFallbackToManualReview = (error) => {
  const status = error?.response?.status;
  const code = error?.response?.data?.error_code;

  if ([422, 502, 503, 504].includes(status)) {
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

  const code = error?.response?.data?.error_code;
  if (['bedrock_connectivity_error', 'extractor_process_failed'].includes(code)) {
    return true;
  }

  const status = error?.response?.status;
  if (!status) {
    return true;
  }

  return [408, 422, 429, 500, 502, 503, 504].includes(status);
};

// --- LÓGICA DE EXTRACCIÓN (Vinculada al flujo Slates > Election > Neighborhood) ---
// Revisa lo básico y pide confirmación antes de gastar la extracción con IA.
const confirmExtract = ref(false);
const APPROVED_ACTA_MESSAGE = 'Este barrio ya tiene un acta de escrutinio aprobada: no se pueden registrar planchas nuevas.';
const askExtract = () => {
  extractError.value = '';
  if (!selectedNeighborhood.value?.active_election?.id) {
    extractError.value = 'El barrio seleccionado no tiene una elección activa configurada.';
    return;
  }
  if (selectedNeighborhood.value.active_election.has_approved_acta) {
    extractError.value = APPROVED_ACTA_MESSAGE;
    return;
  }
  if (capturedImages.value.length === 0) {
    extractError.value = 'Debes cargar al menos una imagen.';
    return;
  }
  confirmExtract.value = true;
};

const extractPlanchas = async () => {
  extractError.value = '';
  docStore.clearExtractionWarning();

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
    const fallbackPages = [];

    for (let index = 0; index < capturedImages.value.length; index += 1) {
      let pageResolved = false;

      for (let attempt = 1; attempt <= PREVIEW_MAX_ATTEMPTS; attempt += 1) {
        extractStep.value = `Procesando página ${index + 1} de ${capturedImages.value.length} (intento ${attempt}/${PREVIEW_MAX_ATTEMPTS})...`;

        const form = new FormData();
        form.append('document_file', capturedImages.value[index].file);
        form.append('page_number', String(index + 1));
        form.append('election_id', selectedNeighborhood.value.active_election.id);

        try {
          // Usamos extractorInstance para respetar el timeout extendido de 4 minutos
          const { data } = await extractorInstance.post('/secretary/planchas/extract-preview', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
          });

          const pageData = data?.data?.review_page_data;
          if (hasRecognizedCandidates(pageData)) {
            extractedPages[index] = pageData;
            pageResolved = true;
            break;
          }

          if (attempt < PREVIEW_MAX_ATTEMPTS) {
            await sleep(PREVIEW_BASE_BACKOFF_MS * attempt);
            continue;
          }

          extractedPages[index] = createManualPageTemplate();
          fallbackPages.push(index + 1);
          pageResolved = true;
          break;
        } catch (error) {
          if (attempt < PREVIEW_MAX_ATTEMPTS && isRetryablePreviewError(error)) {
            await sleep(PREVIEW_BASE_BACKOFF_MS * attempt);
            continue;
          }

          if (!shouldFallbackToManualReview(error)) {
            throw error;
          }

          extractedPages[index] = createManualPageTemplate();
          fallbackPages.push(index + 1);
          pageResolved = true;
          break;
        }
      }

      if (!pageResolved) {
        extractedPages[index] = createManualPageTemplate();
        fallbackPages.push(index + 1);
      }
    }

    const fallbackTriggered = fallbackPages.length > 0;
    if (fallbackTriggered) {
      docStore.setExtractionWarning(
        `La extracción no pudo completar las páginas ${fallbackPages.join(', ')}. Esas páginas quedaron habilitadas para corrección manual.`
      );
    }

    docStore.setImages(capturedImages.value, 'plancha');
    docStore.setExtractedData(extractedPages);

    router.push({
      name: 'secretary-plancha-detail',
      params: { id: 'preview' },
      query: {
        preview: '1',
        edit: 'true',
        election_id: selectedNeighborhood.value.active_election.id,
        neighborhood_name: selectedNeighborhood.value.name,
        plancha_number: slateNumber.value,
      },
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
