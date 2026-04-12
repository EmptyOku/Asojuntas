<template>
  <div class="max-w-7xl mx-auto space-y-6 pb-10">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold uppercase tracking-wider rounded-lg border border-green-200">
            {{ planchaData.numero }}
          </span>
          <span class="text-sm font-medium text-gray-500">Junta de Acción Comunal</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-900">{{ planchaData.nombrePlancha }}</h2>
        <p class="text-sm text-gray-500 mt-1">Barrio: <strong class="text-gray-700">{{ planchaData.nombreBarrio }}</strong></p>
      </div>
      
      <div class="flex items-center gap-3">
        <button 
          v-if="!isEditing" 
          @click="isEditing = true"
          class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-aso-primary hover:text-aso-primary text-gray-700 text-sm font-bold rounded-xl shadow-sm transition-colors"
        >
          <Edit2 class="w-4 h-4" /> Habilitar Edición
        </button>
        
        <template v-else>
          <button 
            @click="cancelEdit"
            class="px-4 py-2.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 text-sm font-bold rounded-xl transition-colors"
          >
            Cancelar
          </button>
          <button 
            @click="saveChanges"
            :disabled="isSaving"
            class="flex items-center gap-2 px-4 py-2.5 bg-aso-primary hover:bg-green-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors"
          >
            <Save class="w-4 h-4" /> {{ isSaving ? 'Guardando...' : 'Guardar Plancha' }}
          </button>
        </template>

        <button
          v-if="currentBatchUuid"
          @click="promoteApprovedBatch"
          :disabled="isPromoting || promotableDraftCount === 0"
          class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors"
        >
          {{ isPromoting ? 'Promoviendo...' : `Promover Aprobados (${promotableDraftCount})` }}
        </button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-1.5 flex overflow-x-auto">
      <button 
        v-for="bloque in bloques" 
        :key="bloque.id"
        @click="activeBlock = bloque.id"
        class="flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold rounded-lg transition-all whitespace-nowrap min-w-[200px]"
        :class="activeBlock === bloque.id ? 'bg-green-50 text-aso-primary shadow-sm ring-1 ring-green-100' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
      >
        <component :is="bloque.icon" class="w-4 h-4 shrink-0" />
        <span>{{ bloque.nombre }}</span>
      </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8">
      
      <div class="border-b border-gray-100 pb-4 mb-6 flex justify-between items-end">
        <div>
          <h3 class="text-xl font-bold text-gray-900">{{ bloques.find(b => b.id === activeBlock)?.nombre }}</h3>
          <p class="text-sm text-gray-500 mt-1">Verifica la información extraída con el documento físico original.</p>
        </div>
        <div v-if="isEditing" class="hidden md:flex items-center gap-2 text-sm text-orange-600 bg-orange-50 px-3 py-1.5 rounded-lg border border-orange-100">
          <Edit2 class="w-4 h-4" /> Modo Edición Activo
        </div>
      </div>

      <div v-if="activeBlock === 'directiva'" class="space-y-5">
        <CandidateCard cargo="Presidente (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.presidente.nombre"
          v-model:identificacion="planchaData.bloque1.presidente.identificacion"
          v-model:celular="planchaData.bloque1.presidente.celular"
          v-model:correo="planchaData.bloque1.presidente.correo" />
          
        <CandidateCard cargo="Vicepresidente (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.vicepresidente.nombre"
          v-model:identificacion="planchaData.bloque1.vicepresidente.identificacion"
          v-model:celular="planchaData.bloque1.vicepresidente.celular"
          v-model:correo="planchaData.bloque1.vicepresidente.correo" />
          
        <CandidateCard cargo="Tesorero (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.tesorero.nombre"
          v-model:identificacion="planchaData.bloque1.tesorero.identificacion"
          v-model:celular="planchaData.bloque1.tesorero.celular"
          v-model:correo="planchaData.bloque1.tesorero.correo" />
          
        <CandidateCard cargo="Secretario (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.secretario.nombre"
          v-model:identificacion="planchaData.bloque1.secretario.identificacion"
          v-model:celular="planchaData.bloque1.secretario.celular"
          v-model:correo="planchaData.bloque1.secretario.correo" />
      </div>

      <div v-if="activeBlock === 'delegados'" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <CandidateCard cargo="Delegado (a) 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado1.nombre" v-model:identificacion="planchaData.bloque2.delegado1.identificacion" />
        <CandidateCard cargo="Suplente Delegado 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente1.nombre" v-model:identificacion="planchaData.bloque2.suplente1.identificacion" />
        
        <CandidateCard cargo="Delegado (a) 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado2.nombre" v-model:identificacion="planchaData.bloque2.delegado2.identificacion" />
        <CandidateCard cargo="Suplente Delegado 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente2.nombre" v-model:identificacion="planchaData.bloque2.suplente2.identificacion" />
        
        <CandidateCard cargo="Delegado (a) 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado3.nombre" v-model:identificacion="planchaData.bloque2.delegado3.identificacion" />
        <CandidateCard cargo="Suplente Delegado 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente3.nombre" v-model:identificacion="planchaData.bloque2.suplente3.identificacion" />
      </div>

      <div v-if="activeBlock === 'fiscal'" class="space-y-5">
        <CandidateCard cargo="Fiscal" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque3.fiscal.nombre"
          v-model:identificacion="planchaData.bloque3.fiscal.identificacion"
          v-model:celular="planchaData.bloque3.fiscal.celular"
          v-model:correo="planchaData.bloque3.fiscal.correo" />
          
        <CandidateCard cargo="Suplente Fiscal" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque3.suplente.nombre"
          v-model:identificacion="planchaData.bloque3.suplente.identificacion"
          v-model:celular="planchaData.bloque3.suplente.celular"
          v-model:correo="planchaData.bloque3.suplente.correo" />
      </div>

      <div v-if="activeBlock === 'convivencia'" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <CandidateCard cargo="Conciliador (a) 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador1.nombre" v-model:identificacion="planchaData.bloque4.conciliador1.identificacion" />
        <CandidateCard cargo="Conciliador (a) 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador2.nombre" v-model:identificacion="planchaData.bloque4.conciliador2.identificacion" />
        <CandidateCard cargo="Conciliador (a) 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador3.nombre" v-model:identificacion="planchaData.bloque4.conciliador3.identificacion" />
        <CandidateCard cargo="Coord. Comisión Empresarial" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.empresarial.nombre" v-model:identificacion="planchaData.bloque4.empresarial.identificacion" />
      </div>

    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
      <h3 class="text-lg font-bold text-gray-900">Evidencia de Imágenes</h3>
      <p class="text-sm text-gray-500 mt-1">Usa estas imágenes para verificar y corregir datos antes de aprobar.</p>

      <div v-if="localEvidenceImages.length > 0" class="mt-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Captura actual (local)</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          <a
            v-for="img in localEvidenceImages"
            :key="img.id"
            :href="img.url"
            target="_blank"
            rel="noopener noreferrer"
            class="block rounded-xl overflow-hidden border border-gray-200 hover:border-aso-primary transition-colors"
          >
            <img :src="img.url" class="w-full h-36 object-cover" />
          </a>
        </div>
      </div>

      <div v-if="evidenceFiles.length > 0" class="mt-6">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Evidencia guardada (servidor)</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <a
            v-for="file in evidenceFiles"
            :key="file.id"
            :href="file.download_url"
            target="_blank"
            rel="noopener noreferrer"
            class="rounded-xl border border-gray-200 px-3 py-2 hover:border-aso-primary transition-colors"
          >
            <p class="text-sm font-semibold text-gray-800">Página {{ file.page_number }}</p>
            <p class="text-xs text-gray-500 truncate">{{ file.original_name }}</p>
          </a>
        </div>
      </div>

      <p v-if="localEvidenceImages.length === 0 && evidenceFiles.length === 0" class="mt-4 text-sm text-gray-500">
        Aún no hay imágenes de evidencia vinculadas a esta plancha.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Edit2, Save, Users, UserPlus, Scale, Handshake } from 'lucide-vue-next';
import CandidateCard from '@/components/secretary/CandidateCard.vue';
import { useDocumentStore } from '@/stores/document';
import axios from '@/services/axios';

const route = useRoute();
const router = useRouter();
const docStore = useDocumentStore();
const isEditing = ref(false);
const activeBlock = ref('directiva');
const isSaving = ref(false);
const evidenceFiles = ref([]);
const isPromoting = ref(false);
const currentBatchUuid = ref((route.query.batch ?? docStore.captureBatchUuid ?? null));
const promotableDraftCount = ref(0);

const bloques = [
  { id: 'directiva', nombre: '1. Directiva', icon: Users },
  { id: 'delegados', nombre: '2. Delegados', icon: UserPlus },
  { id: 'fiscal', nombre: '3. Fiscal', icon: Scale },
  { id: 'convivencia', nombre: '4. Convivencia', icon: Handshake }
];

// Estructura Reactiva completa de la Plancha
const planchaData = reactive({
  numero: 'Plancha No. 1',
  nombreBarrio: 'Bello Horizonte',
  nombrePlancha: 'Transparencia Comunal',
  bloque1: {
    presidente: { nombre: 'María González', identificacion: '11223344', celular: '3101234567', correo: 'maria@ejemplo.com' },
    vicepresidente: { nombre: '', identificacion: '', celular: '', correo: '' },
    tesorero: { nombre: '', identificacion: '', celular: '', correo: '' },
    secretario: { nombre: '', identificacion: '', celular: '', correo: '' },
  },
  bloque2: {
    delegado1: { nombre: '', identificacion: '' }, suplente1: { nombre: '', identificacion: '' },
    delegado2: { nombre: '', identificacion: '' }, suplente2: { nombre: '', identificacion: '' },
    delegado3: { nombre: '', identificacion: '' }, suplente3: { nombre: '', identificacion: '' },
  },
  bloque3: {
    fiscal: { nombre: '', identificacion: '', celular: '', correo: '' },
    suplente: { nombre: '', identificacion: '', celular: '', correo: '' },
  },
  bloque4: {
    conciliador1: { nombre: '', identificacion: '' },
    conciliador2: { nombre: '', identificacion: '' },
    conciliador3: { nombre: '', identificacion: '' },
    empresarial: { nombre: '', identificacion: '' }
  }
});

const normalizeCargoLabel = (value) =>
  String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/\s+/g, ' ')
    .trim();

const buildCandidateLookup = () => {
  const lookup = {};
  const pages = Object.values(docStore.extractedData || {});

  for (const page of pages) {
    for (const block of page?.bloques || []) {
      for (const cargo of block?.cargos || []) {
        const key = normalizeCargoLabel(cargo?.puesto);
        if (!key || lookup[key]) {
          continue;
        }
        lookup[key] = cargo;
      }
    }
  }

  return lookup;
};

const fillFromLookup = (target, source = {}) => {
  target.nombre = source.nombre || target.nombre || '';
  target.identificacion = source.identificacion || target.identificacion || '';
  target.celular = source.celular || target.celular || '';
  target.correo = source.correo || target.correo || '';
};

const composeDraftFullName = (draft) =>
  [draft?.first_name, draft?.middle_name, draft?.last_name, draft?.second_last_name]
    .filter(Boolean)
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim();

const extractCargoFromNotes = (notes) => {
  const noteText = String(notes || '');
  const match = noteText.match(/Cargo:\s*(.+)$/i);
  return match ? match[1].trim() : '';
};

const resolveDraftCargoLabel = (draft) =>
  draft?.position?.name
  || draft?.position?.code
  || extractCargoFromNotes(draft?.notes)
  || '';

const applyDraftToPlancha = (draft) => {
  const label = normalizeCargoLabel(resolveDraftCargoLabel(draft));

  const lookup = {
    PRESIDENTE: planchaData.bloque1.presidente,
    VICEPRESIDENTE: planchaData.bloque1.vicepresidente,
    TESORERO: planchaData.bloque1.tesorero,
    SECRETARIO: planchaData.bloque1.secretario,
    'DELEGADO ASOJUNTAS 1': planchaData.bloque2.delegado1,
    'SUPLENTE DELEGADO ASOJUNTAS 1': planchaData.bloque2.suplente1,
    'DELEGADO ASOJUNTAS 2': planchaData.bloque2.delegado2,
    'SUPLENTE DELEGADO ASOJUNTAS 2': planchaData.bloque2.suplente2,
    'DELEGADO ASOJUNTAS 3': planchaData.bloque2.delegado3,
    'SUPLENTE DELEGADO ASOJUNTAS 3': planchaData.bloque2.suplente3,
    FISCAL: planchaData.bloque3.fiscal,
    'SUPLENTE FISCAL': planchaData.bloque3.suplente,
    'CONCILIADOR 1': planchaData.bloque4.conciliador1,
    'CONCILIADOR 2': planchaData.bloque4.conciliador2,
    'CONCILIADOR 3': planchaData.bloque4.conciliador3,
    'COMISION EMPRESARIAL': planchaData.bloque4.empresarial,
  };

  const target = lookup[label];
  if (!target) {
    return;
  }

  fillFromLookup(target, {
    nombre: composeDraftFullName(draft),
    identificacion: draft?.document_number || '',
    celular: draft?.phone || '',
    correo: draft?.email || '',
  });
};

const hydratePlanchaFromDrafts = async () => {
  const params = {
    per_page: 100,
  };

  if (route.params.id && route.params.id !== 'preview') {
    params.draft_id = route.params.id;
  }

  if (currentBatchUuid.value) {
    params.capture_batch_uuid = currentBatchUuid.value;
  }

  try {
    const { data } = await axios.get('/secretary/planchas/drafts', { params });
    const apiDrafts = data?.data?.data ?? [];
    if (apiDrafts.length === 0) {
      return;
    }

    const first = apiDrafts[0];
    if (!currentBatchUuid.value && first?.capture_batch_uuid) {
      currentBatchUuid.value = first.capture_batch_uuid;
      docStore.setCaptureBatchUuid(first.capture_batch_uuid);
    }

    apiDrafts.forEach((draft) => applyDraftToPlancha(draft));
  } catch {
    // If hydration fails, keep current view values and allow manual editing.
  }
};

const hydratePlanchaFromExtraction = () => {
  const lookup = buildCandidateLookup();
  if (Object.keys(lookup).length === 0) {
    return;
  }

  fillFromLookup(planchaData.bloque1.presidente, lookup.PRESIDENTE);
  fillFromLookup(planchaData.bloque1.vicepresidente, lookup.VICEPRESIDENTE);
  fillFromLookup(planchaData.bloque1.tesorero, lookup.TESORERO);
  fillFromLookup(planchaData.bloque1.secretario, lookup.SECRETARIO);

  fillFromLookup(planchaData.bloque2.delegado1, lookup['DELEGADO ASOJUNTAS 1']);
  fillFromLookup(planchaData.bloque2.suplente1, lookup['SUPLENTE DELEGADO ASOJUNTAS 1']);
  fillFromLookup(planchaData.bloque2.delegado2, lookup['DELEGADO ASOJUNTAS 2']);
  fillFromLookup(planchaData.bloque2.suplente2, lookup['SUPLENTE DELEGADO ASOJUNTAS 2']);
  fillFromLookup(planchaData.bloque2.delegado3, lookup['DELEGADO ASOJUNTAS 3']);
  fillFromLookup(planchaData.bloque2.suplente3, lookup['SUPLENTE DELEGADO ASOJUNTAS 3']);

  fillFromLookup(planchaData.bloque3.fiscal, lookup.FISCAL);
  fillFromLookup(planchaData.bloque3.suplente, lookup['SUPLENTE FISCAL']);

  fillFromLookup(planchaData.bloque4.conciliador1, lookup['CONCILIADOR 1']);
  fillFromLookup(planchaData.bloque4.conciliador2, lookup['CONCILIADOR 2']);
  fillFromLookup(planchaData.bloque4.conciliador3, lookup['CONCILIADOR 3']);
  fillFromLookup(planchaData.bloque4.empresarial, lookup['COMISION EMPRESARIAL']);
};

// Capturar el parámetro ?edit=true de la URL cuando venimos desde la vista de lista
onMounted(async () => {
  if (route.query.edit === 'true') {
    isEditing.value = true;
  }

  localEvidenceImages.value = docStore.capturedImages || [];

  const batchFromRoute = route.query.batch ?? docStore.captureBatchUuid ?? null;
  if (batchFromRoute) {
    currentBatchUuid.value = batchFromRoute;
    docStore.setCaptureBatchUuid(batchFromRoute);
    await loadEvidence(batchFromRoute);
    await loadPromotableCount(batchFromRoute);
  }

  if (route.query.preview === '1' || route.params.id === 'preview') {
    hydratePlanchaFromExtraction();
    return;
  }

  await hydratePlanchaFromDrafts();

  if (currentBatchUuid.value) {
    await loadPromotableCount(currentBatchUuid.value);
  }
});

const cancelEdit = () => {
  isEditing.value = false;
};

const saveChanges = () => {
  const generatedBatchUuid = currentBatchUuid.value || (globalThis.crypto?.randomUUID?.() ?? null);
  const captureBatchUuid = generatedBatchUuid || `batch-${Date.now()}`;

  currentBatchUuid.value = captureBatchUuid;
  docStore.setCaptureBatchUuid(captureBatchUuid);

  const slateCodeMatch = String(planchaData.numero || '').match(/(\d+)/);
  const slateCode = slateCodeMatch ? `P${slateCodeMatch[1]}` : null;

  const reviewPageData = {
    bloques: [
      {
        titulo: 'Bloque - Directiva',
        cargos: [
          { puesto: 'PRESIDENTE', ...planchaData.bloque1.presidente },
          { puesto: 'VICEPRESIDENTE', ...planchaData.bloque1.vicepresidente },
          { puesto: 'TESORERO', ...planchaData.bloque1.tesorero },
          { puesto: 'SECRETARIO', ...planchaData.bloque1.secretario },
        ],
      },
      {
        titulo: 'Bloque - Delegados Asojuntas',
        cargos: [
          { puesto: 'DELEGADO ASOJUNTAS 1', ...planchaData.bloque2.delegado1 },
          { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 1', ...planchaData.bloque2.suplente1 },
          { puesto: 'DELEGADO ASOJUNTAS 2', ...planchaData.bloque2.delegado2 },
          { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 2', ...planchaData.bloque2.suplente2 },
          { puesto: 'DELEGADO ASOJUNTAS 3', ...planchaData.bloque2.delegado3 },
          { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 3', ...planchaData.bloque2.suplente3 },
        ],
      },
      {
        titulo: 'Bloque - Fiscal',
        cargos: [
          { puesto: 'FISCAL', ...planchaData.bloque3.fiscal },
          { puesto: 'SUPLENTE FISCAL', ...planchaData.bloque3.suplente },
        ],
      },
      {
        titulo: 'Bloque - Comisión de convivencia y conciliación',
        cargos: [
          { puesto: 'CONCILIADOR 1', ...planchaData.bloque4.conciliador1 },
          { puesto: 'CONCILIADOR 2', ...planchaData.bloque4.conciliador2 },
          { puesto: 'CONCILIADOR 3', ...planchaData.bloque4.conciliador3 },
          { puesto: 'COMISION EMPRESARIAL', ...planchaData.bloque4.empresarial },
        ],
      },
    ],
  };

  isSaving.value = true;

  axios.post('/secretary/planchas/drafts', {
    source_type: 'ocr',
    capture_batch_uuid: captureBatchUuid,
    slate_code: slateCode,
    review_page_data: reviewPageData,
    replace_pending: route.query.preview === '1',
  })
    .then(async () => {
      let evidenceWarning = '';

      if (localEvidenceImages.value.length > 0) {
        try {
          const form = new FormData();
          form.append('capture_batch_uuid', captureBatchUuid);

          localEvidenceImages.value.forEach((img, index) => {
            if (img.file instanceof File) {
              form.append('document_files[]', img.file);
              form.append('page_numbers[]', String(index + 1));
            }
          });

          await axios.post('/secretary/planchas/evidence', form, {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
            timeout: 240000,
          });
        } catch (error) {
          const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
          evidenceWarning = ` La evidencia no se completo: ${backendMessage}`;
        }
      }

      await loadEvidence(captureBatchUuid);
      await loadPromotableCount(captureBatchUuid);
      isEditing.value = false;

      router.replace({
        name: 'secretary-plancha-detail',
        params: { id: route.params.id || 'preview' },
        query: { batch: captureBatchUuid },
      });

      window.alert(`Plancha guardada en borradores para revision.${evidenceWarning}`);
    })
    .catch((error) => {
      const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
      window.alert(`No se pudo guardar la plancha: ${backendMessage}`);
    })
    .finally(() => {
      isSaving.value = false;
    });
};

const loadEvidence = async (batchUuid) => {
  if (!batchUuid) {
    evidenceFiles.value = [];
    return;
  }

  try {
    const { data } = await axios.get(`/secretary/planchas/evidence/${batchUuid}`);
    evidenceFiles.value = data?.data?.files ?? [];
  } catch {
    evidenceFiles.value = [];
  }
};

const loadPromotableCount = async (batchUuid) => {
  if (!batchUuid) {
    promotableDraftCount.value = 0;
    return;
  }

  try {
    const { data } = await axios.get('/secretary/planchas/drafts', {
      params: {
        capture_batch_uuid: batchUuid,
        review_status: 'approved',
        is_processed: false,
        per_page: 1,
      },
    });

    promotableDraftCount.value = Number(data?.data?.total ?? 0);
  } catch {
    promotableDraftCount.value = 0;
  }
};

const promoteApprovedBatch = async () => {
  if (!currentBatchUuid.value || isPromoting.value) {
    return;
  }

  if (promotableDraftCount.value === 0) {
    window.alert('No hay borradores aprobados pendientes por promover en este lote.');
    return;
  }

  isPromoting.value = true;

  try {
    const { data } = await axios.post('/secretary/planchas/drafts/promote', {
      capture_batch_uuid: currentBatchUuid.value,
    });

    const result = data?.data;
    await loadPromotableCount(currentBatchUuid.value);
    window.alert(
      `Promocion finalizada. Procesados: ${result?.processed ?? 0}, ` +
      `Personas creadas: ${result?.persons_created ?? 0}, ` +
      `Candidatos creados: ${result?.candidates_created ?? 0}, ` +
      `Omitidos: ${result?.skipped ?? 0}.`
    );
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.response?.data?.error || error?.message;
    window.alert(`No se pudo ejecutar la promocion oficial: ${backendMessage}`);
  } finally {
    isPromoting.value = false;
  }
};

const localEvidenceImages = ref([]);
</script>