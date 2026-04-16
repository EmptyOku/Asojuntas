<template>
  <div class="space-y-4 lg:space-y-6 h-full flex flex-col">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-start sm:items-center gap-4">
        <router-link to="/admin/audit" class="p-2 text-gray-400 hover:text-gray-900 hover:bg-white rounded-xl transition-colors shadow-sm border border-transparent hover:border-gray-200 shrink-0">
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900 tracking-tight">Auditoría Acta #{{ route.params.id }}</h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] lg:text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
              <span class="w-1.5 h-1.5 rounded-full bg-orange-500" :class="{ 'animate-pulse': detail.status !== 'approved' }"></span>
              {{ statusLabel }}
            </span>
          </div>
          <p class="text-xs lg:text-sm text-gray-500 mt-1">{{ subtitle }}</p>
        </div>
      </div>

      <div class="flex items-center bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
        <span class="text-xs lg:text-sm font-medium text-gray-500">
          Confianza IA:
          <span class="text-orange-600 font-bold">{{ confidenceText }}</span>
        </span>
      </div>
    </div>

    <div v-if="isLoading" class="bg-white border border-gray-100 rounded-2xl p-8 text-center text-gray-500">
      Cargando detalle del acta...
    </div>

    <div v-else-if="loadError" class="bg-white border border-red-200 rounded-2xl p-8 text-center text-red-600">
      {{ loadError }}
    </div>

    <div v-else class="flex flex-col lg:flex-row gap-4 lg:gap-6 flex-1 lg:min-h-[600px] lg:max-h-[850px]">
      <div class="w-full lg:w-1/2 h-[40vh] lg:h-auto bg-gray-900 rounded-2xl overflow-hidden flex flex-col relative shadow-[0_4px_20px_rgba(0,0,0,0.05)] shrink-0">
        <div class="h-12 bg-gray-800/90 backdrop-blur border-b border-gray-700 flex items-center justify-center gap-4 px-4 absolute top-0 w-full z-10">
          <button @click="prevImage" :disabled="currentImageIndex <= 0" class="p-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-40">
            <ChevronLeft class="w-4 h-4" />
          </button>
          <span class="text-xs text-gray-400 font-medium">Pág {{ currentImageIndex + 1 }}/{{ files.length || 1 }}</span>
          <button @click="nextImage" :disabled="currentImageIndex >= files.length - 1" class="p-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-40">
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>

        <div class="flex-1 bg-gray-900 p-4 lg:p-8 flex items-center justify-center overflow-auto mt-12 relative">
          <img
            v-if="currentFileKind === 'image' && currentImageUrl"
            :src="currentImageUrl"
            class="max-h-full max-w-full object-contain rounded-lg shadow-2xl bg-white"
            alt="Acta de escrutinio"
          >
          <iframe
            v-else-if="currentFileKind === 'pdf' && currentImageUrl"
            :src="currentImageUrl"
            class="w-full h-full min-h-[420px] rounded-lg shadow-2xl bg-white border-0"
            title="Acta de escrutinio"
          />
          <div v-else class="w-full max-w-md aspect-[3/4] bg-white rounded shadow-2xl p-4 lg:p-6 relative flex flex-col items-center justify-center min-h-[400px]">
            <FileText class="w-10 h-10 text-gray-300 mb-2" />
            <p class="text-gray-400 font-medium text-xs text-center">No hay archivo visible para esta acta.</p>
          </div>
        </div>
      </div>

      <div class="w-full lg:w-1/2 bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col h-[50vh] lg:h-full overflow-hidden">
        <div class="p-4 lg:p-5 border-b border-gray-100 bg-gray-50/50 shrink-0">
          <h2 class="text-base lg:text-lg font-bold text-gray-900">Extracción de Escrutinio</h2>
          <p class="text-[10px] lg:text-xs text-gray-500 mt-1">Verifica los valores contra la imagen y confirma la auditoría.</p>
        </div>

        <div class="p-4 lg:p-5 flex-1 overflow-y-auto space-y-6 custom-scrollbar">
          <section v-for="(block, index) in editableBlocks" :key="index" class="space-y-3">
            <h3 class="text-[10px] lg:text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Users class="w-4 h-4 text-gray-400" /> {{ block.name }}
            </h3>

            <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div class="col-span-2 sm:col-span-4 border-b border-gray-200 pb-2 mb-1">
                <label class="block text-[9px] text-gray-500 uppercase">Total Votos</label>
                <input type="number" v-model.number="block.votes.total_votes" class="w-full bg-transparent text-lg font-bold text-gray-900 outline-none">
              </div>

              <div><label class="block text-[9px] text-gray-500">Plancha 1</label><input type="number" v-model.number="block.votes.plancha_1" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">Plancha 2</label><input type="number" v-model.number="block.votes.plancha_2" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">Plancha 3</label><input type="number" v-model.number="block.votes.plancha_3" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">V. Blancos</label><input type="number" v-model.number="block.votes.blancos" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">V. Nulos</label><input type="number" v-model.number="block.votes.nulos" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">No Marcados</label><input type="number" v-model.number="block.votes.no_marcados" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>

              <div class="col-span-2">
                <label class="block text-[9px] text-gray-500">Votos Válidos (Calculado)</label>
                <input type="number" :value="calculateValidVotes(block)" class="w-full px-2 py-1.5 bg-gray-200 border border-gray-300 rounded text-sm font-bold text-gray-700" readonly>
              </div>
            </div>
          </section>
        </div>

        <div class="p-4 lg:p-5 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between gap-3 shrink-0">
          <button @click="decide('rejected')" :disabled="isSubmitting" class="px-3 lg:px-4 py-2 text-xs lg:text-sm font-semibold text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded-xl transition-colors disabled:opacity-60">
            Rechazar
          </button>
          <button @click="decide('approved')" :disabled="isSubmitting" class="flex-1 lg:flex-none px-4 lg:px-6 py-2 text-xs lg:text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark shadow-md rounded-xl transition-all flex items-center justify-center gap-2 disabled:opacity-60">
            <Save class="w-4 h-4" /> Confirmar Datos
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, ChevronLeft, ChevronRight, FileText, Users, Save } from 'lucide-vue-next';
import axios from '@/services/axios';

const route = useRoute();
const router = useRouter();

const isLoading = ref(false);
const isSubmitting = ref(false);
const loadError = ref('');
const detail = ref({
  id: null,
  status: 'pending_review',
  election_name: '',
  commune_name: '',
  ai_confidence: null,
  valid_votes: 0,
  files: [],
  blocks: [],
  updated_at_human: '',
});

const editableBlocks = ref([]);
const currentImageIndex = ref(0);
const AUTO_REFRESH_MS = 5000;
let autoRefreshTimer = null;

const files = computed(() => detail.value.files || []);
const currentFile = computed(() => files.value[currentImageIndex.value] || null);
const currentImageUrl = computed(() => currentFile.value?.url || '');
const currentFileKind = computed(() => {
  const mimeType = String(currentFile.value?.mime_type || '').toLowerCase();

  if (mimeType.startsWith('image/')) {
    return 'image';
  }

  if (mimeType === 'application/pdf') {
    return 'pdf';
  }

  return 'other';
});

const confidenceText = computed(() => {
  if (detail.value.ai_confidence === null || detail.value.ai_confidence === undefined) {
    return 'N/A';
  }

  return `${Math.round(Number(detail.value.ai_confidence) * 100)}%`;
});

const statusLabel = computed(() => {
  const map = {
    pending_review: 'Revisión Numérica',
    reviewed: 'Revisada',
    approved: 'Aprobada',
    rejected: 'Rechazada',
  };

  return map[detail.value.status] || detail.value.status;
});

const subtitle = computed(() => {
  const election = detail.value.election_name || 'Elección';
  const commune = detail.value.commune_name || 'Comuna';
  const location = detail.value.polling_table?.location || '';
  return location ? `${election} • ${commune} • ${location}` : `${election} • ${commune}`;
});

const isFinalStatus = computed(() => ['approved', 'rejected', 'reviewed', 'consolidated'].includes(String(detail.value.status || '')));

const calculateValidVotes = (block) => {
  return Number(block.votes.plancha_1 || 0)
    + Number(block.votes.plancha_2 || 0)
    + Number(block.votes.plancha_3 || 0)
    + Number(block.votes.blancos || 0);
};

const stopAutoRefresh = () => {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = null;
  }
};

const startAutoRefresh = () => {
  stopAutoRefresh();

  autoRefreshTimer = setInterval(async () => {
    if (isSubmitting.value || isLoading.value || isFinalStatus.value) {
      return;
    }

    await fetchDetail({ silent: true });
  }, AUTO_REFRESH_MS);
};

const fetchDetail = async ({ silent = false } = {}) => {
  if (!silent) {
    isLoading.value = true;
  }
  loadError.value = '';

  try {
    const { data } = await axios.get(`/admin/audit-records/${route.params.id}`);
    detail.value = data?.data || detail.value;
    if (!isSubmitting.value) {
      editableBlocks.value = JSON.parse(JSON.stringify(detail.value.blocks || []));
    }
    if (!silent) {
      currentImageIndex.value = 0;
    }
  } catch (error) {
    loadError.value = error?.response?.data?.message || 'No se pudo cargar el detalle del acta.';
  } finally {
    if (!silent) {
      isLoading.value = false;
    }
  }
};

const prevImage = () => {
  if (currentImageIndex.value > 0) {
    currentImageIndex.value -= 1;
  }
};

const nextImage = () => {
  if (currentImageIndex.value < files.value.length - 1) {
    currentImageIndex.value += 1;
  }
};

const decide = async (decision) => {
  isSubmitting.value = true;

  try {
    await axios.post(`/admin/audit-records/${route.params.id}/decision`, {
      decision,
      comments: decision === 'rejected' ? 'Rechazo manual en auditoria de demo.' : 'Aprobacion manual en auditoria de demo.',
      changes_payload: {
        blocks: JSON.parse(JSON.stringify(editableBlocks.value || [])),
      },
    });

    router.push('/admin/audit');
  } catch (error) {
    loadError.value = error?.response?.data?.message || 'No se pudo actualizar el estado del acta.';
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(async () => {
  await fetchDetail();
  startAutoRefresh();
});

onBeforeUnmount(() => {
  stopAutoRefresh();
});
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f9fafb;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e5e7eb;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #d1d5db;
}
</style>
