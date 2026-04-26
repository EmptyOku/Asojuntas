<template>
  <div class="max-w-screen-2xl mx-auto space-y-6 pb-10">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <button 
            @click="router.push({ name: 'secretary-planchas' })" 
            class="flex items-center justify-center w-7 h-7 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-500 hover:text-aso-primary transition-colors shadow-sm"
            title="Volver a la bandeja"
          >
            <ArrowLeft class="w-4 h-4" />
          </button>
          
          <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold uppercase tracking-wider rounded-lg border border-green-200">
            {{ planchaData.numero }}
          </span>
          <span class="text-sm font-medium text-gray-500">Junta de Acción Comunal</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-900">{{ planchaData.nombrePlancha }}</h2>
        <p class="text-sm text-gray-500 mt-1">Barrio: <strong class="text-gray-700">{{ planchaData.nombreBarrio }}</strong></p>
      </div>
      
      <div class="flex flex-wrap items-center gap-3">
        <button 
          v-if="!isEditing" 
          @click="isEditing = true"
          class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-aso-primary hover:text-aso-primary text-gray-700 text-sm font-bold rounded-xl shadow-sm transition-colors"
        >
          <Edit2 class="w-4 h-4" /> Habilitar Edición
        </button>
        
        <template v-else>
          <button @click="cancelEdit" class="px-4 py-2.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 text-sm font-bold rounded-xl transition-colors">
            Cancelar
          </button>
          <button @click="saveChanges" :disabled="isSaving" class="flex items-center gap-2 px-4 py-2.5 bg-aso-primary hover:bg-green-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
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

    <div v-if="isPollingOcr" class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-xl shadow-sm animate-in fade-in slide-in-from-top-4 mb-4">
      <div class="flex items-start">
        <div class="flex-shrink-0 mt-0.5">
          <svg class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 102 0V6zm-1 8a1.25 1.25 0 100-2.5 1.25 1.25 0 000 2.5z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 w-full">
          <h3 class="text-sm font-bold text-blue-800">Procesando OCR del lote</h3>
          <p class="text-sm text-blue-700 mt-1">{{ ocrPollMessage || 'Esperando respuesta del OCR...' }}</p>
          <p class="text-xs text-blue-600 mt-2 font-medium">Puedes seguir navegando mientras termina el procesamiento.</p>
        </div>
      </div>
    </div>

    <div v-if="dataLoadError" class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-xl shadow-sm animate-in fade-in slide-in-from-top-4 mb-4">
      <div class="flex items-start">
        <div class="flex-shrink-0 mt-0.5">
          <svg class="h-5 w-5 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 w-full">
          <h3 class="text-sm font-bold text-orange-800">Modo Plan B Activado</h3>
          <p class="text-sm text-orange-700 mt-1">{{ dataLoadError }}</p>
          <p class="text-xs text-orange-600 mt-2 font-medium">Completa el formulario manualmente y/o sube las imagenes para continuar.</p>
          <div class="mt-3">
            <button
              v-if="currentBatchUuid"
              @click="retryOcrHydration"
              :disabled="isPollingOcr"
              class="px-3 py-1.5 text-xs font-bold rounded-lg border border-orange-300 text-orange-700 hover:bg-orange-100 disabled:opacity-50"
            >
              Reintentar OCR
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="validationErrors.length > 0" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm animate-in fade-in slide-in-from-top-4">
      <div class="flex items-start">
        <div class="flex-shrink-0 mt-0.5">
          <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 w-full">
          <h3 class="text-sm font-bold text-red-800">Validación Fallida</h3>
          <p class="text-sm text-red-700 mt-1 mb-3">Por favor, completa los siguientes campos obligatorios antes de guardar:</p>
          <ul class="list-disc pl-5 text-sm text-red-600 space-y-1 marker:text-red-400">
            <li v-for="(detalle, idx) in validationDetails" :key="idx">
              <strong>Pág {{ detalle.pagina }} - {{ detalle.nombre }}:</strong> Falta {{ detalle.faltantes }}
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 items-start">
      
      <div class="w-full lg:w-5/12 bg-[#1a1c23] rounded-2xl overflow-hidden relative lg:sticky lg:top-6 shadow-lg flex flex-col h-[50vh] lg:h-[calc(100vh-6rem)] z-10 border border-gray-800">
        
        <div class="p-4 flex justify-between items-center bg-black/80 z-20 absolute top-0 w-full border-b border-white/10 backdrop-blur-sm">
          <h3 class="text-white font-bold text-xs flex items-center gap-2 uppercase tracking-widest">
            <ImageIcon class="w-4 h-4" /> Evidencia Física
          </h3>
          <div class="flex items-center gap-3 bg-white/10 rounded-full px-3 py-1">
            <button @click="prevPage" :disabled="currentPage === 0" class="text-white hover:text-aso-primary disabled:opacity-30 transition-colors"><ChevronLeft class="w-4 h-4" /></button>
            <span class="text-white text-xs font-bold w-16 text-center">Pág {{ currentPage + 1 }} / {{ totalPages }}</span>
            <button @click="nextPage" :disabled="currentPage === totalPages - 1" class="text-white hover:text-aso-primary disabled:opacity-30 transition-colors"><ChevronRight class="w-4 h-4" /></button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 pt-20 custom-scrollbar">
          <img v-if="currentImage" :src="currentImage.url" class="w-full h-auto object-contain rounded shadow-2xl">
          <div v-else class="text-gray-500 text-sm flex flex-col items-center justify-center h-full gap-2">
            <ImageIcon class="w-10 h-10 opacity-30" />
            <p>No hay imágenes asociadas a este lote</p>
          </div>
        </div>
      </div>

      <div class="w-full lg:w-7/12 space-y-6">
        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex justify-between items-end">
          <div>
            <h3 class="text-lg font-black text-gray-900 uppercase">Validación de Datos · Página {{ currentPage + 1 }}</h3>
            <p class="text-xs text-gray-500 mt-1">Verifica la información contra la imagen de la izquierda.</p>
          </div>
          <div v-if="isEditing" class="flex items-center gap-2 text-xs text-orange-600 bg-orange-50 px-3 py-1.5 rounded-lg border border-orange-100 font-bold uppercase tracking-wide">
            <Edit2 class="w-3 h-3" /> Modo Edición
          </div>
        </div>

        <div v-if="currentPage === 0" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 animate-in fade-in duration-300">
          <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Users class="w-4 h-4"/> Bloque Directivo (Pág 1 de 2)</h4>
          <div class="space-y-5">
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque1.presidente')}">
              <CandidateCard cargo="Presidente (a)" :is-editing="isEditing" v-model:nombre="planchaData.bloque1.presidente.nombre" v-model:identificacion="planchaData.bloque1.presidente.identificacion" v-model:celular="planchaData.bloque1.presidente.celular" v-model:correo="planchaData.bloque1.presidente.correo" />
            </div>
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque1.vicepresidente')}">
              <CandidateCard cargo="Vicepresidente (a)" :is-editing="isEditing" v-model:nombre="planchaData.bloque1.vicepresidente.nombre" v-model:identificacion="planchaData.bloque1.vicepresidente.identificacion" v-model:celular="planchaData.bloque1.vicepresidente.celular" v-model:correo="planchaData.bloque1.vicepresidente.correo" />
            </div>
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque1.tesorero')}">
              <CandidateCard cargo="Tesorero (a)" :is-editing="isEditing" v-model:nombre="planchaData.bloque1.tesorero.nombre" v-model:identificacion="planchaData.bloque1.tesorero.identificacion" v-model:celular="planchaData.bloque1.tesorero.celular" v-model:correo="planchaData.bloque1.tesorero.correo" />
            </div>
          </div>
        </div>

        <div v-if="currentPage === 1" class="space-y-6 animate-in fade-in duration-300">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Users class="w-4 h-4"/> Bloque Directivo (Fin)</h4>
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque1.secretario')}">
              <CandidateCard cargo="Secretario (a)" :is-editing="isEditing" v-model:nombre="planchaData.bloque1.secretario.nombre" v-model:identificacion="planchaData.bloque1.secretario.identificacion" v-model:celular="planchaData.bloque1.secretario.celular" v-model:correo="planchaData.bloque1.secretario.correo" />
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><UserPlus class="w-4 h-4"/> Delegados Asojuntas (Pág 1 de 2)</h4>
            <div class="space-y-5">
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque2.suplentePresidente')}">
                <CandidateCard class="border-l-4 border-amber-400" cargo="Suplente de Presidente" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplentePresidente.nombre" v-model:identificacion="planchaData.bloque2.suplentePresidente.identificacion" v-model:celular="planchaData.bloque2.suplentePresidente.celular" v-model:correo="planchaData.bloque2.suplentePresidente.correo" />
              </div>
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque2.delegado1')}">
                <CandidateCard cargo="Delegado (a) 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado1.nombre" v-model:identificacion="planchaData.bloque2.delegado1.identificacion" v-model:celular="planchaData.bloque2.delegado1.celular" v-model:correo="planchaData.bloque2.delegado1.correo" />
              </div>
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque2.suplente1')}">
                <CandidateCard cargo="Suplente Delegado 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente1.nombre" v-model:identificacion="planchaData.bloque2.suplente1.identificacion" v-model:celular="planchaData.bloque2.suplente1.celular" v-model:correo="planchaData.bloque2.suplente1.correo" />
              </div>
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque2.delegado2')}">
                <CandidateCard cargo="Delegado (a) 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado2.nombre" v-model:identificacion="planchaData.bloque2.delegado2.identificacion" v-model:celular="planchaData.bloque2.delegado2.celular" v-model:correo="planchaData.bloque2.delegado2.correo" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="currentPage === 2" class="space-y-6 animate-in fade-in duration-300">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><UserPlus class="w-4 h-4"/> Delegados Asojuntas (Fin)</h4>
            <div class="space-y-5">
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque2.suplente2')}">
                <CandidateCard cargo="Suplente Delegado 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente2.nombre" v-model:identificacion="planchaData.bloque2.suplente2.identificacion" v-model:celular="planchaData.bloque2.suplente2.celular" v-model:correo="planchaData.bloque2.suplente2.correo" />
              </div>
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque2.delegado3')}">
                <CandidateCard cargo="Delegado (a) 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado3.nombre" v-model:identificacion="planchaData.bloque2.delegado3.identificacion" v-model:celular="planchaData.bloque2.delegado3.celular" v-model:correo="planchaData.bloque2.delegado3.correo" />
              </div>
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque2.suplente3')}">
                <CandidateCard cargo="Suplente Delegado 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente3.nombre" v-model:identificacion="planchaData.bloque2.suplente3.identificacion" v-model:celular="planchaData.bloque2.suplente3.celular" v-model:correo="planchaData.bloque2.suplente3.correo" />
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Scale class="w-4 h-4"/> Bloque Fiscal</h4>
            <div class="space-y-5">
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque3.fiscal')}">
                <CandidateCard cargo="Fiscal" :is-editing="isEditing" v-model:nombre="planchaData.bloque3.fiscal.nombre" v-model:identificacion="planchaData.bloque3.fiscal.identificacion" v-model:celular="planchaData.bloque3.fiscal.celular" v-model:correo="planchaData.bloque3.fiscal.correo" />
              </div>
              <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque3.suplente')}">
                <CandidateCard cargo="Suplente Fiscal" :is-editing="isEditing" v-model:nombre="planchaData.bloque3.suplente.nombre" v-model:identificacion="planchaData.bloque3.suplente.identificacion" v-model:celular="planchaData.bloque3.suplente.celular" v-model:correo="planchaData.bloque3.suplente.correo" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="currentPage === 3" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 animate-in fade-in duration-300">
          <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Handshake class="w-4 h-4"/> Convivencia y Empresarial</h4>
          <div class="space-y-5">
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque4.conciliador1')}">
              <CandidateCard cargo="Conciliador (a) 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador1.nombre" v-model:identificacion="planchaData.bloque4.conciliador1.identificacion" v-model:celular="planchaData.bloque4.conciliador1.celular" v-model:correo="planchaData.bloque4.conciliador1.correo" />
            </div>
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque4.conciliador2')}">
              <CandidateCard cargo="Conciliador (a) 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador2.nombre" v-model:identificacion="planchaData.bloque4.conciliador2.identificacion" v-model:celular="planchaData.bloque4.conciliador2.celular" v-model:correo="planchaData.bloque4.conciliador2.correo" />
            </div>
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque4.conciliador3')}">
              <CandidateCard cargo="Conciliador (a) 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador3.nombre" v-model:identificacion="planchaData.bloque4.conciliador3.identificacion" v-model:celular="planchaData.bloque4.conciliador3.celular" v-model:correo="planchaData.bloque4.conciliador3.correo" />
            </div>
            <div :class="{'ring-2 ring-red-500 rounded-2xl shadow-sm': hasError('bloque4.empresarial')}">
              <CandidateCard cargo="Coord. Comisión Empresarial" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.empresarial.nombre" v-model:identificacion="planchaData.bloque4.empresarial.identificacion" v-model:celular="planchaData.bloque4.empresarial.celular" v-model:correo="planchaData.bloque4.empresarial.correo" />
            </div>
          </div>
        </div>

        <div v-if="currentPage > 3" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
          <p class="text-gray-500 font-bold">No hay cargos parametrizados para hojas adicionales.</p>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Edit2, Save, Users, UserPlus, Scale, Handshake, ChevronLeft, ChevronRight, Image as ImageIcon, ArrowLeft } from 'lucide-vue-next';
import CandidateCard from '@/components/secretary/CandidateCard.vue';
import { useDocumentStore } from '@/stores/document';
import axios from '@/services/axios';

const route = useRoute();
const router = useRouter();
const docStore = useDocumentStore();

const isEditing = ref(false);
const isSaving = ref(false);
const evidenceFiles = ref([]);
const isPromoting = ref(false);
const currentBatchUuid = ref((route.query.batch ?? docStore.captureBatchUuid ?? null));
const promotableDraftCount = ref(0);
const localEvidenceImages = ref([]);
const dataLoadError = ref(null);
const isFormManual = ref(false);
const isPollingOcr = ref(false);
const ocrPollMessage = ref('');
const MAX_FILE_SIZE = 5 * 1024 * 1024; 
const OCR_POLL_INTERVAL_MS = 4000;
const OCR_MAX_WAIT_MS = 120000;

// --- VALIDACIONES DE FORMULARIO ---
const validationErrors = ref([]);
const validationDetails = ref([]);
const hasError = (key) => validationErrors.value.includes(key);

const currentPage = ref(0);

const allEvidenceImages = computed(() => {
  if (localEvidenceImages.value.length > 0) return localEvidenceImages.value;
  if (evidenceFiles.value.length > 0) return evidenceFiles.value.map(f => ({ url: f.download_url, id: f.id }));
  return [];
});

const totalPages = computed(() => allEvidenceImages.value.length || 1);
const currentImage = computed(() => allEvidenceImages.value[currentPage.value] || null);

const prevPage = () => { if (currentPage.value > 0) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value - 1) currentPage.value++; };
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// --- ESTRUCTURA: AHORA SÍ CON CELULAR Y CORREO EN TODOS ---
const createCargo = () => ({ nombre: '', identificacion: '', celular: '', correo: '' });

const planchaData = reactive({
  numero: route.query.plancha_number ? `Plancha No. ${route.query.plancha_number}` : 'Plancha No. 1',
  nombreBarrio: route.query.neighborhood_name || 'Cargando territorio...',
  nombrePlancha: 'Transparencia Comunal',
  bloque1: {
    presidente: createCargo(),
    vicepresidente: createCargo(),
    tesorero: createCargo(),
    secretario: createCargo(),
  },
  bloque2: {
    suplentePresidente: createCargo(),
    delegado1: createCargo(), suplente1: createCargo(),
    delegado2: createCargo(), suplente2: createCargo(),
    delegado3: createCargo(), suplente3: createCargo(),
  },
  bloque3: {
    fiscal: createCargo(),
    suplente: createCargo(),
  },
  bloque4: {
    conciliador1: createCargo(),
    conciliador2: createCargo(),
    conciliador3: createCargo(),
    empresarial: createCargo()
  }
});

const normalizeCargoLabel = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().replace(/\s+/g, ' ').trim();

const buildCandidateLookup = () => {
  const lookup = {};
  const pages = Object.values(docStore.extractedData || {});
  for (const page of pages) {
    for (const block of page?.bloques || []) {
      for (const cargo of block?.cargos || []) {
        const key = normalizeCargoLabel(cargo?.puesto);
        if (!key || lookup[key]) continue;
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

const extractDraftArray = (resData) => {
  if (Array.isArray(resData?.data?.data)) return resData.data.data;
  if (Array.isArray(resData?.data)) return resData.data;
  if (Array.isArray(resData)) return resData;
  return [];
};

const isRetryableDraftsError = (error, hasBatchUuid) => {
  if (!hasBatchUuid) return false;
  const status = Number(error?.response?.status ?? 0);
  return status === 422 || status === 404 || status === 409 || status === 429 || status >= 500 || status === 0;
};

const composeDraftFullName = (draft) => {
  const rawName = [draft?.first_name, draft?.middle_name, draft?.last_name, draft?.second_last_name]
    .filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
  return rawName.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
};
const extractCargoFromNotes = (notes) => { const match = String(notes || '').match(/Cargo:\s*(.+)$/i); return match ? match[1].trim() : ''; };
const resolveDraftCargoLabel = (draft) => draft?.position?.name || draft?.position?.code || extractCargoFromNotes(draft?.notes) || '';

const applyDraftToPlancha = (draft) => {
  const label = normalizeCargoLabel(resolveDraftCargoLabel(draft));
  const lookup = {
    PRESIDENTE: planchaData.bloque1.presidente,
    VICEPRESIDENTE: planchaData.bloque1.vicepresidente,
    TESORERO: planchaData.bloque1.tesorero,
    SECRETARIO: planchaData.bloque1.secretario,
    'SUPLENTE DE PRESIDENTE': planchaData.bloque2.suplentePresidente,
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
  if (!target) return;

  fillFromLookup(target, {
    nombre: composeDraftFullName(draft),
    identificacion: draft?.document_number || '',
    celular: draft?.phone || '',
    correo: draft?.email || '',
  });
};

const hydratePlanchaFromDrafts = async () => {
  const params = { per_page: 100 };
  if (currentBatchUuid.value) params.capture_batch_uuid = currentBatchUuid.value;
  const hasBatchUuid = Boolean(params.capture_batch_uuid);

  const startedAt = Date.now();
  let attempt = 0;
  let lastContextMessage = '';

  isPollingOcr.value = hasBatchUuid;
  ocrPollMessage.value = hasBatchUuid ? 'Esperando resultado de OCR...' : '';
  dataLoadError.value = null;

  while (Date.now() - startedAt <= OCR_MAX_WAIT_MS) {
    attempt += 1;
    if (hasBatchUuid) {
      ocrPollMessage.value = `Procesando OCR del lote... intento ${attempt}`;
    }

    try {
      const response = await axios.get('/secretary/planchas/drafts', {
        params,
        timeout: 30000,
        skipGlobalLoading: true,
      });
      const apiDrafts = extractDraftArray(response.data);

      if (apiDrafts.length > 0) {
        const first = apiDrafts[0];

        if (!route.query.neighborhood_name) {
          planchaData.nombreBarrio = first?.neighborhood_name || first?.election?.neighborhood?.name || 'JAC Sin Identificar';
        }

        if (!currentBatchUuid.value && first?.capture_batch_uuid) {
          currentBatchUuid.value = first.capture_batch_uuid;
          docStore.setCaptureBatchUuid(first.capture_batch_uuid);
        }

        apiDrafts.forEach((draft) => applyDraftToPlancha(draft));
        dataLoadError.value = null;
        isFormManual.value = false;
        isPollingOcr.value = false;
        ocrPollMessage.value = '';
        return true;
      }

      lastContextMessage = hasBatchUuid
        ? 'Aun no hay registros OCR disponibles para este lote.'
        : 'No hay borradores disponibles para precargar.';
    } catch (error) {
      console.error('Auditoria - Error al cargar datos:', error);

      if (!isRetryableDraftsError(error, hasBatchUuid)) {
        const detail = error?.response?.data?.message || error?.message || 'Error desconocido';
        dataLoadError.value = `Error al cargar datos: ${detail}`;
        isFormManual.value = true;
        isPollingOcr.value = false;
        ocrPollMessage.value = '';
        return false;
      }

      if (Number(error?.response?.status) === 422) {
        lastContextMessage = 'El OCR sigue procesando el lote en servidor.';
      } else {
        lastContextMessage = 'Esperando respuesta del servidor de OCR.';
      }
    }

    if (!hasBatchUuid) break;
    if ((Date.now() - startedAt) + OCR_POLL_INTERVAL_MS > OCR_MAX_WAIT_MS) break;
    await sleep(OCR_POLL_INTERVAL_MS);
  }

  isPollingOcr.value = false;
  ocrPollMessage.value = '';
  isFormManual.value = true;

  if (hasBatchUuid) {
    const seconds = Math.round(OCR_MAX_WAIT_MS / 1000);
    dataLoadError.value = `El OCR aun no finaliza para este lote tras ${seconds}s de espera. Puedes continuar manualmente o reintentar OCR.${lastContextMessage ? ` ${lastContextMessage}` : ''}`;
  } else {
    dataLoadError.value = 'No se encontro un lote de captura para consultar OCR. Puedes continuar manualmente.';
  }

  return false;
};

const hydratePlanchaFromExtraction = () => {
  const lookup = buildCandidateLookup();
  if (Object.keys(lookup).length === 0) return false;

  fillFromLookup(planchaData.bloque1.presidente, lookup.PRESIDENTE);
  fillFromLookup(planchaData.bloque1.vicepresidente, lookup.VICEPRESIDENTE);
  fillFromLookup(planchaData.bloque1.tesorero, lookup.TESORERO);
  fillFromLookup(planchaData.bloque1.secretario, lookup.SECRETARIO);

  fillFromLookup(planchaData.bloque2.suplentePresidente, lookup['SUPLENTE DE PRESIDENTE']);
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

  return true;
};

onMounted(async () => {
  if (route.query.edit === 'true') isEditing.value = true;
  if (route.query.neighborhood_name) planchaData.nombreBarrio = route.query.neighborhood_name; 
  
  localEvidenceImages.value = docStore.capturedImages || [];

  const batchFromRoute = route.query.batch ?? docStore.captureBatchUuid ?? null;
  if (batchFromRoute) {
    currentBatchUuid.value = batchFromRoute;
    docStore.setCaptureBatchUuid(batchFromRoute);
    await loadEvidence(batchFromRoute);
    await loadPromotableCount(batchFromRoute);
  }

  if (route.query.preview === '1' || route.params.id === 'preview') {
    const hydratedFromStore = hydratePlanchaFromExtraction();
    if (!hydratedFromStore) {
      await hydratePlanchaFromDrafts();
    }
    return;
  }

  await hydratePlanchaFromDrafts();

  if (currentBatchUuid.value) {
    await loadPromotableCount(currentBatchUuid.value);
  }
});

const retryOcrHydration = async () => {
  if (isPollingOcr.value || isSaving.value) return;
  await hydratePlanchaFromDrafts();
  if (currentBatchUuid.value) {
    await loadPromotableCount(currentBatchUuid.value);
  }
};

const cancelEdit = () => { 
  isEditing.value = false; 
  validationErrors.value = []; 
  validationDetails.value = []; 
};

const saveChanges = () => {
  validationErrors.value = [];
  validationDetails.value = [];
  
  // MAPA DE VALIDACIÓN DETALLADO
  const cargosAChequear = [
    { key: 'bloque1.presidente', obj: planchaData.bloque1.presidente, nombre: 'Presidente', pagina: 1 },
    { key: 'bloque1.vicepresidente', obj: planchaData.bloque1.vicepresidente, nombre: 'Vicepresidente', pagina: 1 },
    { key: 'bloque1.tesorero', obj: planchaData.bloque1.tesorero, nombre: 'Tesorero', pagina: 1 },
    { key: 'bloque1.secretario', obj: planchaData.bloque1.secretario, nombre: 'Secretario', pagina: 2 },
    { key: 'bloque2.suplentePresidente', obj: planchaData.bloque2.suplentePresidente, nombre: 'Suplente de Presidente', pagina: 2 },
    { key: 'bloque2.delegado1', obj: planchaData.bloque2.delegado1, nombre: 'Delegado 1', pagina: 2 },
    { key: 'bloque2.suplente1', obj: planchaData.bloque2.suplente1, nombre: 'Suplente Delegado 1', pagina: 2 },
    { key: 'bloque2.delegado2', obj: planchaData.bloque2.delegado2, nombre: 'Delegado 2', pagina: 2 },
    { key: 'bloque2.suplente2', obj: planchaData.bloque2.suplente2, nombre: 'Suplente Delegado 2', pagina: 3 },
    { key: 'bloque2.delegado3', obj: planchaData.bloque2.delegado3, nombre: 'Delegado 3', pagina: 3 },
    { key: 'bloque2.suplente3', obj: planchaData.bloque2.suplente3, nombre: 'Suplente Delegado 3', pagina: 3 },
    { key: 'bloque3.fiscal', obj: planchaData.bloque3.fiscal, nombre: 'Fiscal', pagina: 3 },
    { key: 'bloque3.suplente', obj: planchaData.bloque3.suplente, nombre: 'Suplente Fiscal', pagina: 3 },
    { key: 'bloque4.conciliador1', obj: planchaData.bloque4.conciliador1, nombre: 'Conciliador 1', pagina: 4 },
    { key: 'bloque4.conciliador2', obj: planchaData.bloque4.conciliador2, nombre: 'Conciliador 2', pagina: 4 },
    { key: 'bloque4.conciliador3', obj: planchaData.bloque4.conciliador3, nombre: 'Conciliador 3', pagina: 4 },
    { key: 'bloque4.empresarial', obj: planchaData.bloque4.empresarial, nombre: 'Coord. Comisión Empresarial', pagina: 4 },
  ];

  for (const cargo of cargosAChequear) {
    const faltantes = [];
    if (!cargo.obj.nombre?.trim()) faltantes.push('Nombre');
    if (!cargo.obj.identificacion?.trim()) faltantes.push('No. Identificación');
    if (!cargo.obj.celular?.trim()) faltantes.push('Celular');
    if (!cargo.obj.correo?.trim()) faltantes.push('Correo Electrónico');

    if (faltantes.length > 0) {
      validationErrors.value.push(cargo.key);
      validationDetails.value.push({
        pagina: cargo.pagina,
        nombre: cargo.nombre,
        faltantes: faltantes.join(', ')
      });
    }
  }

  if (validationErrors.value.length > 0) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return; // Se detiene el proceso y muestra los errores en pantalla
  }

  const generatedBatchUuid = currentBatchUuid.value || (globalThis.crypto?.randomUUID?.() ?? null);
  const captureBatchUuid = generatedBatchUuid || `batch-${Date.now()}`;

  currentBatchUuid.value = captureBatchUuid;
  docStore.setCaptureBatchUuid(captureBatchUuid);

  const slateCodeMatch = String(planchaData.numero || '').match(/(\d+)/);
  const slateCode = slateCodeMatch ? `P${slateCodeMatch[1]}` : null;

  const reviewPageData = {
    bloques: [
      { titulo: 'Bloque - Directiva', cargos: [
        { puesto: 'PRESIDENTE', ...planchaData.bloque1.presidente },
        { puesto: 'VICEPRESIDENTE', ...planchaData.bloque1.vicepresidente },
        { puesto: 'TESORERO', ...planchaData.bloque1.tesorero },
        { puesto: 'SECRETARIO', ...planchaData.bloque1.secretario },
      ]},
      { titulo: 'Bloque - Delegados Asojuntas', cargos: [
        { puesto: 'SUPLENTE DE PRESIDENTE', ...planchaData.bloque2.suplentePresidente },
        { puesto: 'DELEGADO ASOJUNTAS 1', ...planchaData.bloque2.delegado1 },
        { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 1', ...planchaData.bloque2.suplente1 },
        { puesto: 'DELEGADO ASOJUNTAS 2', ...planchaData.bloque2.delegado2 },
        { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 2', ...planchaData.bloque2.suplente2 },
        { puesto: 'DELEGADO ASOJUNTAS 3', ...planchaData.bloque2.delegado3 },
        { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 3', ...planchaData.bloque2.suplente3 },
      ]},
      { titulo: 'Bloque - Fiscal', cargos: [
        { puesto: 'FISCAL', ...planchaData.bloque3.fiscal },
        { puesto: 'SUPLENTE FISCAL', ...planchaData.bloque3.suplente },
      ]},
      { titulo: 'Bloque - Comisión de convivencia y conciliación', cargos: [
        { puesto: 'CONCILIADOR 1', ...planchaData.bloque4.conciliador1 },
        { puesto: 'CONCILIADOR 2', ...planchaData.bloque4.conciliador2 },
        { puesto: 'CONCILIADOR 3', ...planchaData.bloque4.conciliador3 },
        { puesto: 'COMISION EMPRESARIAL', ...planchaData.bloque4.empresarial },
      ]},
    ],
  };

  isSaving.value = true;

  // PASO 1: Intentar guardar los datos
  let dataSaveSuccess = false;
  axios.post('/secretary/planchas/drafts', {
    source_type: 'ocr',
    capture_batch_uuid: captureBatchUuid,
    slate_code: slateCode,
    review_page_data: reviewPageData,
    replace_pending: route.query.preview === '1',
    election_id: route.query.election_id 
  }, {
    timeout: 240000,
    skipGlobalLoading: true,
  })
    .then(async () => {
      dataSaveSuccess = true;
      console.log('✅ Datos guardados exitosamente');
    })
    .catch((error) => {
      console.error('❌ Error al guardar datos:', error);
      dataSaveSuccess = false;
    })
    .finally(async () => {
      // PASO 2: SIEMPRE intentar guardar las imágenes, independientemente de si los datos se guardaron
      let evidenceWarning = '';
      let evidenceSaveSuccess = false;

      if (localEvidenceImages.value.length > 0) {
        try {
          const form = new FormData();
          form.append('capture_batch_uuid', captureBatchUuid);

          localEvidenceImages.value.forEach((img, index) => {
            if (img.file instanceof File) {
              // Validar tamaño máximo de 5MB
              if (img.file.size > MAX_FILE_SIZE) {
                throw new Error(`El archivo "${img.file.name}" excede 5MB. Tamaño: ${(img.file.size / 1024 / 1024).toFixed(2)}MB`);
              }
              form.append('document_files[]', img.file);
              form.append('page_numbers[]', String(index + 1));
            }
          });

          await axios.post('/secretary/planchas/evidence', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            timeout: 240000,
            skipGlobalLoading: true,
          });
          evidenceSaveSuccess = true;
          console.log('✅ Evidencia guardada exitosamente');
        } catch (error) {
          evidenceWarning = ` La evidencia no se guardó: ${error?.response?.data?.message || error?.message}`;
          console.error('❌ Error al guardar evidencia:', error);
        }
      }

      // PASO 3: Recargar y redirigir
      if (dataSaveSuccess || evidenceSaveSuccess) {
        await loadEvidence(captureBatchUuid);
        await loadPromotableCount(captureBatchUuid);
        isEditing.value = false;

        router.replace({
          name: 'secretary-plancha-detail',
          params: { id: route.params.id || 'preview' },
          query: { batch: captureBatchUuid },
        });

        let message = '';
        if (dataSaveSuccess && evidenceSaveSuccess) {
          message = 'Plancha e imágenes guardadas en borradores.';
        } else if (dataSaveSuccess) {
          message = `Plancha guardada en borradores.${evidenceWarning}`;
        } else if (evidenceSaveSuccess) {
          message = 'Imágenes guardadas en borradores (datos no se procesaron).';
        }
        window.alert(message);
      } else {
        window.alert(`No se pudo guardar la plancha ni las imágenes.${evidenceWarning}`);
      }
      
      isSaving.value = false;
    });
};

const loadEvidence = async (batchUuid) => {
  if (!batchUuid) return;
  try {
    const { data } = await axios.get(`/secretary/planchas/evidence/${batchUuid}`, {
      skipGlobalLoading: true,
    });
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
      params: { capture_batch_uuid: batchUuid, review_status: 'approved', is_processed: 0 },
      skipGlobalLoading: true,
    });
    promotableDraftCount.value = Number(data?.data?.total ?? 0);
  } catch {
    promotableDraftCount.value = 0;
  }
};

const promoteApprovedBatch = async () => {
  if (!currentBatchUuid.value || isPromoting.value) return;
  if (promotableDraftCount.value === 0) {
    window.alert('No hay borradores aprobados pendientes en este lote.');
    return;
  }
  isPromoting.value = true;
  try {
    await axios.post('/secretary/planchas/drafts/promote', { capture_batch_uuid: currentBatchUuid.value }, {
      timeout: 240000,
      skipGlobalLoading: true,
    });
    await loadPromotableCount(currentBatchUuid.value);
    window.alert('Promoción finalizada con éxito.');
  } catch (error) {
    window.alert(`Fallo al promover: ${error?.response?.data?.message || error?.message}`);
  } finally {
    isPromoting.value = false;
  }
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #1a1c23;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #4b5563;
  border-radius: 10px;
}
</style>