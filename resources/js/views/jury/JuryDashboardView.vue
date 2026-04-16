<template>
  <div class="space-y-6 flex-1 flex flex-col">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
      <h2 class="text-xl font-bold text-gray-900">Hola, {{ authStore.user?.name || 'Jurado' }}</h2>
      <p class="text-sm text-gray-500 mt-1">Bienvenido al módulo de transmisión documental.</p>
      
      <div class="mt-6 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-start gap-3">
        <MapPin class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
        <div>
          <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">Tu Asignación Actual</p>
          <p class="text-lg font-bold text-gray-900 mt-1">{{ assignmentTitle }}</p>
          <p class="text-sm text-gray-600">{{ assignmentLocation }}</p>
          <p class="text-sm text-gray-500 mt-1">{{ assignmentNeighborhood }}</p>
        </div>
      </div>
    </div>

    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider px-2">Documentos a Transmitir</h3>

    <div class="relative group">
      <router-link 
        to="/jury/capture?doc=escrutinio" 
        class="block bg-white p-5 rounded-2xl shadow-sm border flex items-center gap-4 transition-all duration-200"
        :class="actaSubida ? 'border-green-500 bg-green-50/30' : statusMeta.cardClass"
      >
        <div 
          class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0 transition-colors"
          :class="actaSubida ? 'bg-green-100' : statusMeta.iconContainerClass"
        >
          <FileCheck class="w-7 h-7" :class="actaSubida ? 'text-green-600' : statusMeta.iconClass" />
        </div>
        
        <div class="flex-1">
          <h4 class="font-bold text-gray-900">Acta de Escrutinio</h4>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ statusDescription }}
          </p>
          <div class="mt-2 flex items-center gap-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
              :class="statusMeta.badgeClass"
            >
              {{ statusMeta.badgeText }}
            </span>
            <span v-if="statusLoading" class="text-[11px] text-gray-500">Actualizando...</span>
          </div>
        </div>
        
        <div 
          class="p-3 rounded-xl transition-colors"
          :class="actaSubida ? 'bg-green-100 text-green-600' : statusMeta.trailingClass"
        >
          <Check v-if="actaSubida" class="w-5 h-5" />
          <Camera v-else class="w-5 h-5" />
        </div>
      </router-link>

      <div v-if="actaSubida" class="mt-3 flex items-start gap-2 text-sm text-green-600 px-2 animate-in fade-in slide-in-from-top-2">
        <CheckCircle2 class="w-4 h-4 shrink-0 mt-0.5" />
        <p class="font-medium">El acta de esta mesa ya fue transmitida al sistema exitosamente.</p>
      </div>

      <div v-else-if="statusError" class="mt-3 px-2 text-xs font-semibold text-red-600">
        {{ statusError }}
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { MapPin, FileCheck, Camera, Check, CheckCircle2 } from 'lucide-vue-next';
import axios from '@/services/axios';

const authStore = useAuthStore();
const STATUS_POLL_MS = 4000;
const juryContext = ref({});
const actaSubida = ref(false);
const statusLoading = ref(false);
const statusError = ref('');
const lastRecordId = ref(null);
const overallStatus = ref('unknown');
const statusSummary = ref(null);
const statusRequestInFlight = ref(false);
let statusIntervalId = null;

const statusMeta = computed(() => {
  if (actaSubida.value) {
    return {
      badgeText: 'Completada',
      badgeClass: 'bg-green-100 text-green-700',
      cardClass: 'border-green-500 bg-green-50/30',
      iconContainerClass: 'bg-green-100',
      iconClass: 'text-green-600',
      trailingClass: 'bg-green-100 text-green-600',
    };
  }

  if (overallStatus.value === 'failed') {
    return {
      badgeText: 'Con errores',
      badgeClass: 'bg-red-100 text-red-700',
      cardClass: 'border-red-200 bg-red-50/40 hover:border-red-300',
      iconContainerClass: 'bg-red-100',
      iconClass: 'text-red-600',
      trailingClass: 'bg-red-100 text-red-600',
    };
  }

  if (overallStatus.value === 'processing') {
    return {
      badgeText: 'Procesando',
      badgeClass: 'bg-amber-100 text-amber-700',
      cardClass: 'border-amber-200 bg-amber-50/30 hover:border-amber-300',
      iconContainerClass: 'bg-amber-100',
      iconClass: 'text-amber-600',
      trailingClass: 'bg-amber-100 text-amber-600',
    };
  }

  if (overallStatus.value === 'queued' || overallStatus.value === 'pending') {
    return {
      badgeText: 'En cola',
      badgeClass: 'bg-blue-100 text-blue-700',
      cardClass: 'border-blue-200 bg-blue-50/20 hover:border-blue-300',
      iconContainerClass: 'bg-blue-100',
      iconClass: 'text-blue-600',
      trailingClass: 'bg-blue-100 text-blue-600',
    };
  }

  return {
    badgeText: 'Sin envío',
    badgeClass: 'bg-gray-100 text-gray-700',
    cardClass: 'border-gray-100 hover:border-aso-primary hover:shadow-md',
    iconContainerClass: 'bg-aso-primary/10',
    iconClass: 'text-aso-primary',
    trailingClass: 'bg-gray-50 text-gray-600 group-hover:bg-aso-primary/10 group-hover:text-aso-primary',
  };
});

const statusDescription = computed(() => {
  if (actaSubida.value) {
    return 'Resultados capturados y procesados.';
  }

  if (overallStatus.value === 'queued' || overallStatus.value === 'pending') {
    return 'Acta recibida, esperando procesamiento en segundo plano.';
  }

  if (overallStatus.value === 'processing') {
    return 'Acta en procesamiento. Puedes continuar con otras tareas.';
  }

  if (overallStatus.value === 'failed') {
    return 'Hubo errores de procesamiento en una o más páginas.';
  }

  return 'Captura los resultados al cierre.';
});

const assignmentTitle = computed(() => {
  const table = juryContext.value?.suggested_polling_table;
  if (!table?.name) {
    return 'Mesa no asignada';
  }

  return table.code ? `${table.name} (${table.code})` : table.name;
});

const assignmentLocation = computed(() => {
  const table = juryContext.value?.suggested_polling_table;
  if (!table?.name) {
    return 'Aún no tienes una mesa asignada.';
  }

  return table.location || 'Mesa asignada sin ubicación registrada.';
});

const assignmentNeighborhood = computed(() => {
  const personNeighborhood = authStore.user?.person?.neighborhood;
  const commune = personNeighborhood?.commune;
  const address = authStore.user?.person?.address;

  if (!personNeighborhood) {
    return 'No hay barrio registrado para tu persona.';
  }

  const parts = [
    `Barrio: ${personNeighborhood.name}`,
    commune?.name ? `Comuna: ${commune.name}` : null,
    address ? `Dirección: ${address}` : null,
  ].filter(Boolean);

  return parts.join(' · ');
});

const syncStatusFromResponse = (payload) => {
  const status = payload?.overall_status || 'unknown';
  const summary = payload?.summary || null;

  overallStatus.value = status;
  statusSummary.value = summary;
  actaSubida.value = status === 'completed';
};

const stopStatusPolling = () => {
  if (statusIntervalId) {
    clearInterval(statusIntervalId);
    statusIntervalId = null;
  }
};

const fetchStatus = async () => {
  if (statusRequestInFlight.value) {
    return;
  }

  if (!lastRecordId.value) {
    overallStatus.value = 'unknown';
    actaSubida.value = false;
    return;
  }

  statusRequestInFlight.value = true;
  statusLoading.value = true;
  statusError.value = '';

  try {
    const { data } = await axios.get(`/jury/status/${lastRecordId.value}`, {
      timeout: 8000,
      skipGlobalLoading: true,
    });
    syncStatusFromResponse(data?.data || {});

    if (actaSubida.value || overallStatus.value === 'failed') {
      stopStatusPolling();
    }
  } catch (error) {
    const statusCode = Number(error?.response?.status || 0);
    if (statusCode === 403 || statusCode === 404) {
      localStorage.removeItem('juryLastScrutinyRecordId');
      lastRecordId.value = null;
      overallStatus.value = 'unknown';
      actaSubida.value = false;
      stopStatusPolling();
      return;
    }

    const backendMessage = error?.response?.data?.message || error?.message;
    statusError.value = `No se pudo actualizar el estado del acta: ${backendMessage}`;
  } finally {
    statusLoading.value = false;
    statusRequestInFlight.value = false;
  }
};

const loadRecordId = async () => {
  const localRecordId = Number(localStorage.getItem('juryLastScrutinyRecordId') || 0);
  if (localRecordId > 0) {
    lastRecordId.value = localRecordId;
  }

  try {
    const { data } = await axios.get('/jury/context', {
      timeout: 8000,
      skipGlobalLoading: true,
    });
    juryContext.value = data?.data || {};
    const suggestedRecordId = Number(data?.data?.suggested_scrutiny_record_id || 0);
    if (suggestedRecordId > 0) {
      lastRecordId.value = suggestedRecordId;
      localStorage.setItem('juryLastScrutinyRecordId', String(suggestedRecordId));
    }
  } catch (error) {
    // If context fails, we keep local fallback.
  }
};

const startStatusPolling = () => {
  stopStatusPolling();
  statusIntervalId = setInterval(() => {
    if (!actaSubida.value && overallStatus.value !== 'failed') {
      fetchStatus();
    }
  }, STATUS_POLL_MS);
};

onMounted(async () => {
  await loadRecordId();
  await fetchStatus();
  startStatusPolling();
});

onBeforeUnmount(() => {
  stopStatusPolling();
});
</script>