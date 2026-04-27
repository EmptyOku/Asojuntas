<template>
  <div class="flex flex-col min-h-screen bg-aso-bg font-sans pb-24 lg:pb-0">
    <div v-if="isExtracting" class="fixed inset-0 z-[80] bg-black/40 backdrop-blur-sm flex items-center justify-center px-4">
      <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 p-6 w-full max-w-sm text-center">
        <div class="w-12 h-12 mx-auto rounded-full border-4 border-gray-200 border-t-aso-primary animate-spin"></div>
        <h3 class="mt-4 text-base font-bold text-gray-900">Extrayendo texto...</h3>
        <p class="mt-1 text-sm text-gray-500">Estamos leyendo la imagen y reemplazando los valores de validación.</p>
      </div>
    </div>

    <!-- 1. CARRUSEL DE IMAGEN (Sticky) -->
    <div class="w-full h-[40vh] bg-[#1a1c23] relative flex flex-col sticky top-0 z-20 shadow-lg lg:h-[45vh]">
      <div class="absolute top-0 inset-x-0 p-4 flex justify-between items-center bg-gradient-to-b from-black/80 to-transparent z-30">
        <h3 class="text-white font-bold text-sm flex items-center gap-2">
          <ImageIcon class="w-4 h-4" /> Evidencia
        </h3>
        <div class="flex items-center gap-3 bg-black/50 rounded-full px-3 py-1 border border-white/10">
          <button @click="prevPage" :disabled="currentPage === 0" class="text-white disabled:opacity-30"><ChevronLeft class="w-4 h-4" /></button>
          <span class="text-white text-xs font-bold">Pág {{ currentPage + 1 }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages - 1" class="text-white disabled:opacity-30"><ChevronRight class="w-4 h-4" /></button>
        </div>
      </div>
      <div class="flex-1 overflow-hidden flex items-center justify-center p-4 pt-12">
        <img v-if="currentImage" :src="currentImage.url" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl transition-all duration-300">
      </div>
    </div>

    <!-- 2. SECCIÓN DE DATOS DINÁMICOS POR PÁGINA -->
    <div class="w-full flex-1 overflow-y-auto p-4 sm:p-6 lg:max-w-3xl lg:mx-auto">

      <div v-if="docStore.extractionWarning" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
        <p class="text-sm font-semibold">{{ docStore.extractionWarning }}</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="bg-gray-50 px-5 py-4 border-b border-gray-100">
          <h2 class="text-lg font-bold text-gray-900">{{ isPlancha ? 'Validación de Planchas' : 'Validación de Acta' }}</h2>
          <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mt-1">Página Actual: {{ currentPage + 1 }}</p>
        </div>

        <div class="p-5 space-y-8">

          <!-- FLUJO A: PLANILLAS DE CANDIDATOS -->
          <template v-if="isPlancha">
            <div v-for="(bloque, bIdx) in currentDataPage?.bloques" :key="bIdx" class="space-y-6">
              <h3 class="bg-aso-primary/5 text-aso-primary text-xs font-black p-2 rounded-md border-l-4 border-aso-primary uppercase">{{ bloque.titulo }}</h3>

              <div v-for="(cargo, cIdx) in bloque.cargos" :key="cIdx" class="space-y-3 bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800">{{ cargo.puesto }}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="col-span-1 sm:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Nombre</label>
                    <input type="text" :value="cargo.nombre" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700">
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">No. Identificación</label>
                    <input type="text" :value="cargo.identificacion" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-900">
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Celular</label>
                    <input type="text" :value="cargo.celular" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700">
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Correo Electrónico</label>
                    <input type="email" :value="cargo.correo" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 truncate">
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- FLUJO B: ACTAS DE ESCRUTINIO -->
          <template v-else>
            <div v-for="(bloque, bIdx) in scrutinyBlocksForReview" :key="`${bloque.pageIndex}-${bloque.blockIndex}`" class="space-y-4">
              <h3 class="bg-aso-primary/5 text-aso-primary text-xs font-black p-2 rounded-md border-l-4 border-aso-primary uppercase">{{ bloque.titulo }} · Pág {{ bloque.pageIndex + 1 }}</h3>

              <div class="grid grid-cols-2 gap-3">
                <div v-for="(valor, label) in bloque.votos" :key="label" class="p-3 bg-white border border-gray-100 rounded-xl shadow-sm">
                  <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">{{ label }}</label>
                  <input
                    type="text"
                    inputmode="numeric"
                    :value="valor"
                    readonly
                    class="w-full text-lg font-black text-aso-primary-dark focus:outline-none bg-transparent border-b border-dashed border-gray-300"
                  >
                </div>
              </div>
            </div>
          </template>

        </div>
      </div>

      <!-- OBSERVACIONES -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-24 lg:mb-6">
        <label class="block text-sm font-bold text-gray-900 mb-2">Observaciones de la Página {{ currentPage + 1 }}</label>
        <textarea v-model="observacionesPorPagina[currentPage]" rows="3" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-aso-primary transition-all" placeholder="Reporta cualquier inconsistencia visual..."></textarea>
      </div>

      <!-- BOTONES -->
      <div class="fixed bottom-0 inset-x-0 p-4 bg-white/80 backdrop-blur-md border-t border-gray-100 grid grid-cols-2 gap-3 z-30 lg:relative lg:bg-transparent lg:border-0 lg:p-0">
        <button @click="rejectAndReturn" class="py-4 bg-white border border-gray-200 text-red-600 font-bold rounded-2xl text-sm">Rechazar</button>
        <button @click="confirmAndSend" :disabled="isSubmitting" class="py-4 bg-aso-primary text-white font-bold rounded-2xl shadow-md flex items-center justify-center gap-2 text-sm disabled:opacity-70">
          <CheckCircle class="w-4 h-4" /> Confirmar Datos
        </button>
      </div>

      <!-- PRUEBA DE INTEGRACION (DESARROLLO) -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mt-6 mb-24 lg:mb-6">
        <h3 class="text-sm font-bold text-gray-900">Prueba de Integracion API</h3>
        <p class="text-xs text-gray-500 mt-1">Esta prueba usa tu sesion autenticada del jurado, sin token manual.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
          <div>
            <label class="text-[10px] font-bold text-gray-400 uppercase">Scrutiny Record ID (Auto)</label>
            <input :value="integration.recordId || ''" type="number" readonly class="w-full px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm text-gray-700" placeholder="Se asigna automaticamente">
          </div>
          <div>
            <label class="text-[10px] font-bold text-gray-400 uppercase">Mesa de Votacion</label>
            <input
              v-model.number="integration.pollingTableId"
              type="number"
              min="1"
              :readonly="Boolean(integration.pollingTableId)"
              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 disabled:opacity-80"
              :class="integration.pollingTableId ? 'bg-gray-100' : 'bg-gray-50'"
              :placeholder="integration.pollingTableId ? 'Asignada automaticamente' : 'Solo requerida la primera vez'"
            >
          </div>
        </div>

        <p class="mt-2 text-[11px] text-gray-500">
          Si el sistema puede identificar tu barrio, la mesa se autocompleta. Si no, puedes ingresarla una sola vez y se guardará para las siguientes cargas.
        </p>

        <button @click="extractCurrentPage" :disabled="isExtracting" class="mt-4 w-full sm:w-auto px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-bold disabled:opacity-60">
          {{ isExtracting ? 'Procesando...' : 'Extraer texto de esta página' }}
        </button>

        <p v-if="submitError" class="mt-3 text-xs text-red-600 font-semibold">{{ submitError }}</p>
        <p v-if="submitSuccess" class="mt-3 text-xs text-green-700 font-semibold">{{ submitSuccess }}</p>
        <p v-if="integration.lastStoragePath" class="mt-2 text-[11px] text-gray-600">Ruta VPS: {{ integration.lastStoragePath }}</p>
        <p v-if="integration.lastDownloadUrl" class="mt-1 text-[11px] text-gray-600 break-all">URL descarga: {{ integration.lastDownloadUrl }}</p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, Image as ImageIcon, CheckCircle } from 'lucide-vue-next';
import { useDocumentStore } from '@/stores/document';
import axios from '@/services/axios';

const router = useRouter();
const docStore = useDocumentStore();

const currentPage = ref(0);
const totalPages = computed(() => docStore.capturedImages.length || 1);
const observacionesPorPagina = ref({});
const isSubmitting = ref(false);
const isExtracting = ref(false);
const submitError = ref('');
const submitSuccess = ref('');
const integration = ref({
  recordId: null,
  pollingTableId: null,
  lastStoragePath: '',
  lastDownloadUrl: '',
});
const REQUIRED_SCRUTINY_PAGES = 1;
const DEFAULT_SCRUTINY_BLOCKS = 4;
const isPlancha = computed(() => docStore.documentType === 'plancha');
const currentImage = computed(() => docStore.capturedImages[currentPage.value]);

const extractedPages = ref({ ...(docStore.extractedData || {}) });

const hasAllPagesExtracted = computed(() => {
  return docStore.capturedImages.every((_, index) => {
    const page = extractedPages.value[index];
    return page && Array.isArray(page.bloques);
  });
});

const currentDataPage = computed(() => {
  return extractedPages.value[currentPage.value] || { bloques: [] };
});

const createFallbackVotes = () => ({
  'Votos totales': 0,
  'Plancha 1': 0,
  'Plancha 2': 0,
  'Plancha 3': 0,
  'Votos blancos': 0,
  'Votos nulos': 0,
  'Votos no marcados': 0,
  'Votos validos': 0,
});

const createFallbackBlocks = (pageIndex) => {
  const blocks = [];
  for (let index = 0; index < DEFAULT_SCRUTINY_BLOCKS; index += 1) {
    blocks.push({
      titulo: `Bloque - BLOQUE ${index + 1}`,
      votos: createFallbackVotes(),
      pageIndex,
      blockIndex: index,
    });
  }

  return blocks;
};

const scrutinyBlocksForReview = computed(() => {
  if (isPlancha.value) {
    return [];
  }

  const pages = Object.entries(extractedPages.value)
    .map(([pageIndex, page]) => ({
      pageIndex: Number(pageIndex),
      page,
    }))
    .sort((a, b) => a.pageIndex - b.pageIndex);

  const blocks = [];
  for (const pageEntry of pages) {
    const pageBlocks = Array.isArray(pageEntry.page?.bloques) ? pageEntry.page.bloques : [];

    if (pageBlocks.length === 0) {
      blocks.push(...createFallbackBlocks(pageEntry.pageIndex));
      continue;
    }

    pageBlocks.forEach((bloque, blockIndex) => {
      blocks.push({
        ...bloque,
        votos: { ...createFallbackVotes(), ...(bloque?.votos || {}) },
        pageIndex: pageEntry.pageIndex,
        blockIndex,
      });
    });
  }

  if (blocks.length === 0) {
    return createFallbackBlocks(currentPage.value);
  }

  return blocks;
});

const prevPage = () => { if (currentPage.value > 0) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value - 1) currentPage.value++; };

const extractPageAt = async (pageIndex, image) => {
  const file = await toFileFromImage(image);
  const form = new FormData();
  form.append('document_file', file);
  form.append('document_type', isPlancha.value ? 'plancha' : 'escrutinio');
  form.append('page_number', String(pageIndex + 1));

  const { data } = await axios.post('/jury/extract-preview', form, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });

  const preview = data?.data?.review_page_data;
  if (!preview || !Array.isArray(preview.bloques)) {
    throw new Error('El extractor no devolvió bloques de datos para esta página.');
  }

  extractedPages.value[pageIndex] = preview;
  docStore.setExtractedData({ ...extractedPages.value });
};

const extractCurrentPage = async () => {
  submitError.value = '';

  if (!currentImage.value) {
    submitError.value = 'No hay imagen para extraer en la página actual.';
    return;
  }

  isExtracting.value = true;

  try {
    await extractPageAt(currentPage.value, currentImage.value);
    submitSuccess.value = 'Valores actualizados con datos extraídos de la imagen.';
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
    submitError.value = `No se pudo extraer texto: ${backendMessage}`;
  } finally {
    isExtracting.value = false;
  }
};

// CORRECCIÓN DE REDIRECCIÓN AL RECHAZAR
const rejectAndReturn = () => {
  const typeBeforeClear = docStore.documentType; // Guardamos el tipo antes de borrar
  docStore.clearStore();
  router.push({
    name: 'jury-capture',
    query: { doc: typeBeforeClear }
  });
};

const toFileFromImage = async (img) => {
  if (img?.file instanceof File) {
    return img.file;
  }

  const response = await fetch(img.url);
  const blob = await response.blob();
  const fileName = img?.name || `page-${currentPage.value + 1}.jpg`;
  return new File([blob], fileName, { type: blob.type || 'image/jpeg' });
};

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

const deepClone = (value) => JSON.parse(JSON.stringify(value));

const ensureEditableCurrentPage = () => {
  if (extractedPages.value[currentPage.value]) {
    return;
  }

  extractedPages.value[currentPage.value] = deepClone(currentDataPage.value || { bloques: [] });
};

const setBlockVote = (blockIndex, label, rawValue) => {
  ensureEditableCurrentPage();

  const page = extractedPages.value[currentPage.value];
  if (!page?.bloques?.[blockIndex]?.votos) {
    return;
  }

  page.bloques[blockIndex].votos[label] = toVoteNumber(rawValue);
  docStore.setExtractedData({ ...extractedPages.value });
};

const setBlockVoteByRef = (pageIndex, blockIndex, label, rawValue) => {
  if (!extractedPages.value[pageIndex]) {
    extractedPages.value[pageIndex] = { bloques: [] };
  }

  const targetBlock = extractedPages.value[pageIndex]?.bloques?.[blockIndex];
  if (!targetBlock?.votos) {
    return;
  }

  targetBlock.votos[label] = toVoteNumber(rawValue);
  docStore.setExtractedData({ ...extractedPages.value });
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

  if (isPlancha.value) {
    const electedPeople = [];
    for (const bloque of page?.bloques || []) {
      for (const cargo of bloque?.cargos || []) {
        const fullName = String(cargo.nombre || '').trim();
        if (!fullName) continue;

        const parts = fullName.split(/\s+/);
        const firstName = parts.shift() || '';
        const lastName = parts.join(' ') || 'SIN_APELLIDO';

        electedPeople.push({
          first_name: firstName,
          last_name: lastName,
          document_number: String(cargo.identificacion || '').trim() || null,
          phone: String(cargo.celular || '').trim() || null,
          email: String(cargo.correo || '').trim() || null,
          notes: `Cargo OCR: ${cargo.puesto || 'SIN_CARGO'}`,
          review_status: 'pending',
        });
      }
    }

    return {
      block_results: [],
      elected_people: electedPeople,
    };
  }

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

const confirmAndSend = async () => {
  submitError.value = '';
  submitSuccess.value = '';
  integration.value.lastStoragePath = '';
  integration.value.lastDownloadUrl = '';

  if (docStore.capturedImages.length === 0) {
    submitError.value = 'No hay imagenes seleccionadas para enviar.';
    return;
  }

  if (!isPlancha.value && docStore.capturedImages.length !== REQUIRED_SCRUTINY_PAGES) {
    submitError.value = `Debes cargar exactamente ${REQUIRED_SCRUTINY_PAGES} fotos para enviar el paquete de escrutinio.`;
    return;
  }

  if (!hasAllPagesExtracted.value) {
    submitError.value = 'Faltan páginas por extraer. Regresa y vuelve a cargar el paquete para extraer las 3 en orden.';
    return;
  }

  isSubmitting.value = true;
  const queueModes = [];

  try {
    const recordId = Number(integration.value.recordId);
    const pollingTableId = Number(integration.value.pollingTableId);
    const hasRecordId = Number.isInteger(recordId) && recordId > 0;
    const hasPollingTableId = Number.isInteger(pollingTableId) && pollingTableId > 0;

    if (!hasRecordId && !hasPollingTableId) {
      submitError.value = 'Debes indicar Mesa de Votacion ID al menos la primera vez para crear/reusar el acta.';
      isSubmitting.value = false;
      return;
    }

    const uploadPage = async (index, forcedRecordId = null) => {
      const image = docStore.capturedImages[index];
      if (!image) {
        return null;
      }

      const file = await toFileFromImage(image);
      const pageData = extractedPages.value[index] || { bloques: [] };
      const uploadForm = new FormData();

      const effectiveRecordId = forcedRecordId || integration.value.recordId;

      if (effectiveRecordId) {
        uploadForm.append('scrutiny_record_id', String(effectiveRecordId));
      }

      if (!effectiveRecordId && hasPollingTableId) {
        uploadForm.append('polling_table_id', String(pollingTableId));
      }

      uploadForm.append('document_file', file);
      uploadForm.append('page_number', String(index + 1));
      uploadForm.append('is_primary', index === 0 ? '1' : '0');
      uploadForm.append('notes', observacionesPorPagina.value[index] || `Enviado desde vista de revision jurado (pagina ${index + 1})`);
      uploadForm.append('source_type', 'ai');
      uploadForm.append('engine_name', 'Jury-UI-Review');
      uploadForm.append('engine_version', 'dev-1');
      uploadForm.append('confidence_score', '0.85');
      uploadForm.append('status', 'pending_review');
      uploadForm.append('raw_payload', JSON.stringify({
        page: index + 1,
        image_name: file.name,
        review_data: pageData,
      }));
      uploadForm.append('normalized_payload', JSON.stringify(buildNormalizedPayload(pageData, index)));

      const submitResponse = await axios.post('/jury/submit', uploadForm, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      const data = submitResponse.data?.data || {};
      integration.value.recordId = data.scrutiny_record_id || integration.value.recordId;
      integration.value.lastStoragePath = data.storage_path || integration.value.lastStoragePath;
      integration.value.lastDownloadUrl = data.download_url || integration.value.lastDownloadUrl;
      if (data.queue_mode) {
        queueModes.push(String(data.queue_mode));
      }

      return data;
    };

    let seedRecordId = integration.value.recordId || null;
    let startIndex = 0;

    if (!seedRecordId) {
      const firstUpload = await uploadPage(0, null);
      seedRecordId = firstUpload?.scrutiny_record_id || integration.value.recordId || null;
      startIndex = 1;
    }

    const remainingUploads = [];
    for (let index = startIndex; index < docStore.capturedImages.length; index += 1) {
      remainingUploads.push(uploadPage(index, seedRecordId));
    }

    if (remainingUploads.length > 0) {
      await Promise.all(remainingUploads);
    }

    if (integration.value.pollingTableId) {
      localStorage.setItem('juryPollingTableId', String(integration.value.pollingTableId));
    }

    if (integration.value.recordId) {
      localStorage.setItem('juryLastScrutinyRecordId', String(integration.value.recordId));
    }

    const queueModeLabel = queueModes.includes('direct')
      ? 'almacenamiento inmediato'
      : 'cola de trabajos';
    submitSuccess.value = `Acta enviada: ${docStore.capturedImages.length} pagina(s) almacenadas (${queueModeLabel}).`;
    router.push('/jury/dashboard');
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
    submitError.value = `Fallo la prueba de integracion: ${backendMessage}`;
  } finally {
    isSubmitting.value = false;
  }
};

const loadJuryContext = async () => {
  try {
    const { data } = await axios.get('/jury/context');
    const ctx = data?.data || {};

    integration.value.recordId = ctx.suggested_scrutiny_record_id || null;
    integration.value.pollingTableId = ctx.suggested_polling_table_id || integration.value.pollingTableId;
  } catch (error) {
    // Si no hay contexto backend, intentamos continuar con mesa persistida localmente.
  }

  const localPollingTable = localStorage.getItem('juryPollingTableId');
  if (!integration.value.pollingTableId && localPollingTable) {
    const parsed = Number(localPollingTable);
    if (!Number.isNaN(parsed) && parsed > 0) {
      integration.value.pollingTableId = parsed;
    }
  }
};

onMounted(async () => {
  if (docStore.capturedImages.length === 0) {
    router.push('/jury/dashboard');
    return;
  }

  extractedPages.value = { ...(docStore.extractedData || {}) };

  await loadJuryContext();
});
</script>
