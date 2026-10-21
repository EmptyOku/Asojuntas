<template>
  <div class="space-y-4 lg:space-y-6 h-full flex flex-col">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-start sm:items-center gap-4">
        <router-link to="/admin/audit" class="p-2 text-gray-400 hover:text-gray-900 hover:bg-white rounded-xl transition-colors shadow-sm border border-transparent hover:border-gray-200 shrink-0">
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <h1 class="page-title">Revisión del acta #{{ route.params.id }}</h1>
            <span :class="statusBadgeClass">
              <span class="badge-dot" :class="{ 'animate-pulse': !isFinalStatus }"></span>
              {{ statusLabel }}
            </span>
          </div>
          <p class="text-xs lg:text-sm text-gray-500 mt-1">{{ subtitle }}</p>
          <p v-if="detail.uploaded_at" class="text-xs text-gray-500 mt-0.5">
            Subida el <time :datetime="detail.uploaded_at.iso" class="font-semibold text-gray-700">{{ detail.uploaded_at.texto }}</time>
            <template v-if="detail.jury_name"> por {{ detail.jury_name }}</template>
            <template v-if="detail.decided_at"> · {{ detail.status === 'rejected' ? 'Rechazada' : 'Aprobada' }} el {{ detail.decided_at.texto }}</template>
          </p>
        </div>
      </div>

      <div class="flex items-center bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
        <span class="text-xs lg:text-sm font-medium text-gray-500">
          Confianza de la lectura automática:
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

        <div class="flex-1 min-h-0 bg-gray-900 flex items-center justify-center overflow-hidden mt-12 relative">
          <div
            v-if="fileLoading && currentFileKind === 'image' && !fileLoadFailed"
            class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-gray-400"
          >
            <div class="h-8 w-8 rounded-full border-2 border-gray-600 border-t-white animate-spin"></div>
            <p class="text-xs">Cargando imagen del acta…</p>
          </div>
          <ImageViewer
            v-if="currentFileKind === 'image' && currentImageUrl && !fileLoadFailed"
            :src="currentImageUrl"
            alt="Acta de escrutinio"
            @load="fileLoading = false"
            @error="fileLoading = false; fileLoadFailed = true"
          />
          <iframe
            v-else-if="currentFileKind === 'pdf' && currentImageUrl && !fileLoadFailed"
            :src="currentImageUrl"
            class="w-full h-full min-h-[420px] rounded-lg shadow-2xl bg-white border-0"
            title="Acta de escrutinio"
          />
          <div v-else class="w-full max-w-md aspect-[3/4] bg-white rounded shadow-2xl p-4 lg:p-6 relative flex flex-col items-center justify-center min-h-[400px]">
            <FileText class="w-10 h-10 text-gray-300 mb-2" />
            <!-- El archivo no llegó: p. ej. en la prueba local las imágenes siguen en el almacenamiento del servidor. -->
            <template v-if="fileLoadFailed">
              <p class="text-gray-600 font-semibold text-sm text-center">No se pudo cargar la imagen del acta</p>
              <p class="text-gray-400 text-xs text-center mt-1">El archivo no está disponible en este almacenamiento. Los votos se pueden revisar igual.</p>
            </template>
            <p v-else class="text-gray-400 font-medium text-xs text-center">No hay archivo visible para esta acta.</p>
          </div>
        </div>
      </div>

      <div class="w-full lg:w-1/2 card flex flex-col h-[50vh] lg:h-full overflow-hidden">
        <div class="p-4 lg:p-5 border-b border-gray-100 bg-gray-50/50 shrink-0">
          <h2 class="text-base lg:text-lg font-bold text-gray-900">Votos leídos del acta</h2>
          <p class="text-[10px] lg:text-xs text-gray-500 mt-1">Verifica los valores contra la imagen y confirma la auditoría.</p>
        </div>

        <div class="p-4 lg:p-5 flex-1 overflow-y-auto space-y-6 custom-scrollbar">
          <section v-for="(block, index) in editableBlocks" :key="index" class="space-y-3">
            <h3 class="text-[10px] lg:text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Users class="w-4 h-4 text-gray-400" /> {{ block.name }}
            </h3>

            <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div class="col-span-2 sm:col-span-4 border-b border-gray-200 pb-2 mb-1">
                <label class="block text-[9px] text-gray-500 uppercase">Total de votos</label>
                <input type="number" :value="calculateTotalVotes(block)" readonly class="w-full bg-transparent text-lg font-bold text-gray-900 outline-none" title="Se calcula: votos válidos + nulos + no marcados">
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

        <div class="p-4 lg:p-5 border-t border-gray-100 bg-gray-50/50 shrink-0 space-y-3">
          <p v-if="decisionError" class="field-error" role="alert">{{ decisionError }}</p>
          <p v-if="detail.status === 'rejected'" class="text-xs text-red-700">
            Esta acta está rechazada: sus votos no cuentan. Puedes corregirla y aprobarla.
          </p>
          <p v-else-if="detail.status === 'approved'" class="text-xs text-emerald-700">
            Esta acta ya está aprobada y sus votos cuentan en los resultados.
          </p>
          <div class="flex items-center justify-between gap-3">
            <button v-can="'scrutiny_records.approve'" @click="openDecision('rejected')" :disabled="isSubmitting" class="px-3 lg:px-4 py-2 text-xs lg:text-sm font-semibold text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded-xl transition-colors disabled:opacity-60">
              Rechazar
            </button>
            <button v-can="'scrutiny_records.approve'" @click="openDecision('approved')" :disabled="isSubmitting" class="flex-1 lg:flex-none px-4 lg:px-6 py-2 text-xs lg:text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark shadow-md rounded-xl transition-all flex items-center justify-center gap-2 disabled:opacity-60">
              <Save class="w-4 h-4" /> Aprobar acta
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- Confirmación de la decisión: rechazar exige motivo. -->
    <div v-if="pendingDecision" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeDecision">
      <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-5 space-y-4" role="dialog" aria-modal="true" :aria-label="pendingDecision === 'rejected' ? 'Rechazar acta' : 'Aprobar acta'">
        <h2 class="font-display text-lg font-bold text-gray-900">
          {{ pendingDecision === 'rejected' ? 'Rechazar acta' : 'Aprobar acta' }} #{{ route.params.id }}
        </h2>
        <p class="text-sm text-gray-600">
          <template v-if="pendingDecision === 'rejected'">
            Los votos de esta acta dejarán de contarse en los resultados del barrio. Las cifras editadas no se guardan.
          </template>
          <template v-else>
            Se guardarán las cifras de la derecha y los votos pasarán a contar en los resultados del barrio.
          </template>
        </p>
        <label class="block">
          <span class="text-xs font-semibold text-gray-600">
            {{ pendingDecision === 'rejected' ? 'Motivo del rechazo' : 'Observación (opcional)' }}
          </span>
          <textarea v-model="decisionComment" rows="3" maxlength="1000" class="field mt-1" :placeholder="pendingDecision === 'rejected' ? 'Ej.: el acta está ilegible o las sumas no cuadran' : ''"></textarea>
        </label>
        <p v-if="decisionError" class="field-error" role="alert">{{ decisionError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" :disabled="isSubmitting" @click="closeDecision">Cancelar</button>
          <button type="button" :class="pendingDecision === 'rejected' ? 'btn-danger' : 'btn-primary'" :disabled="isSubmitting || !canSubmitDecision" @click="decide(pendingDecision)">
            {{ isSubmitting ? 'Guardando…' : (pendingDecision === 'rejected' ? 'Rechazar acta' : 'Aprobar acta') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Horas clave de la elección de esta acta. -->
    <!-- Decisiones sobre el acta, con sus comentarios -->
    <section v-if="!isLoading && !loadError && detail.reviews?.length" class="card overflow-hidden">
      <div class="card-header">
        <div>
          <h3 class="card-title">Decisiones y comentarios</h3>
          <p class="card-subtitle">Quién aprobó o rechazó el acta, cuándo y por qué.</p>
        </div>
      </div>
      <ul class="divide-y divide-gray-100">
        <li v-for="(review, index) in detail.reviews" :key="index" class="flex gap-3 px-5 sm:px-6 py-4">
          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
            :class="review.decision === 'rejected' ? 'bg-red-50 text-red-500' : 'bg-emerald-50 text-aso-primary'"
          >
            <XCircle v-if="review.decision === 'rejected'" class="w-4.5 h-4.5" />
            <CheckCircle2 v-else class="w-4.5 h-4.5" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm text-gray-900">
              <strong class="font-semibold">{{ review.decision_label }}</strong>
              por {{ review.by }}
              <span v-if="review.at" class="text-gray-500"> · {{ review.at.texto }}</span>
            </p>
            <p v-if="review.comments" class="mt-1 rounded-xl bg-gray-50 px-3.5 py-2.5 text-sm text-gray-700 ring-1 ring-gray-100">“{{ review.comments }}”</p>
            <p v-else class="mt-0.5 text-xs text-gray-400">Sin comentario.</p>
          </div>
        </li>
      </ul>
    </section>

    <ElectionTimeline v-if="!isLoading && !loadError && detail.timeline" :timeline="detail.timeline" :title="`Línea de tiempo · ${detail.neighborhood_name || 'elección'}`" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, CheckCircle2, ChevronLeft, ChevronRight, FileText, Users, Save, XCircle } from 'lucide-vue-next';
import axios from '@/services/axios';
import ImageViewer from '@/components/ui/ImageViewer.vue';
import ElectionTimeline from '@/components/ui/ElectionTimeline.vue';

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
  uploaded_at: null,
  decided_at: null,
  timeline: null,
  reviews: [],
});

const editableBlocks = ref([]);
const currentImageIndex = ref(0);
// Estado de la imagen del acta: cargando (puede tardar si viene del SFTP) o
// fallida (se muestra un aviso en vez de un recuadro vacío).
const fileLoading = ref(true);
const fileLoadFailed = ref(false);
// Refresco en segundo plano mientras el OCR termina de procesar el acta. Con
// 5s y una BD lenta las peticiones se encimaban y bloqueaban el servidor.
const AUTO_REFRESH_MS = 20000;
let autoRefreshTimer = null;
let refreshInFlight = false;

// Bloques tal como llegaron del servidor la última vez: si el auditor ya editó
// alguna cifra, el refresco no debe pisar su trabajo.
let lastLoadedBlocksJson = '[]';
const hasUnsavedEdits = () => JSON.stringify(editableBlocks.value || []) !== lastLoadedBlocksJson;

const files = computed(() => detail.value.files || []);
const currentFile = computed(() => files.value[currentImageIndex.value] || null);
const currentImageUrl = computed(() => currentFile.value?.url || '');
// Cada archivo nuevo (otra página o refresco con otra URL) vuelve a "cargando".
watch(currentImageUrl, () => {
  fileLoading.value = true;
  fileLoadFailed.value = false;
});
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
    draft: 'Borrador',
    pending: 'Pendiente de revisión',
    pending_review: 'Pendiente de revisión',
    consolidated: 'Consolidada',
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

const statusBadgeClass = computed(() => ({
  approved: 'badge-green',
  reviewed: 'badge-green',
  consolidated: 'badge-green',
  rejected: 'badge-red',
}[detail.value.status] || 'badge-amber'));

const isFinalStatus = computed(() => ['approved', 'rejected', 'reviewed', 'consolidated'].includes(String(detail.value.status || '')));

const calculateValidVotes = (block) => {
  return Number(block.votes.plancha_1 || 0)
    + Number(block.votes.plancha_2 || 0)
    + Number(block.votes.plancha_3 || 0)
    + Number(block.votes.blancos || 0);
};

// El total no se digita: sale de las demás cifras (la lectura automática a
// veces confunde un 1 con un 7 y el total quedaba inflado).
const calculateTotalVotes = (block) => calculateValidVotes(block)
  + Number(block.votes.nulos || 0)
  + Number(block.votes.no_marcados || 0);

const stopAutoRefresh = () => {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = null;
  }
};

const startAutoRefresh = () => {
  stopAutoRefresh();

  autoRefreshTimer = setInterval(async () => {
    // Una sola petición a la vez, y nada si la pestaña está oculta.
    if (refreshInFlight || document.hidden || isSubmitting.value || isLoading.value || isFinalStatus.value) {
      return;
    }

    refreshInFlight = true;
    try {
      await fetchDetail({ silent: true });
    } finally {
      refreshInFlight = false;
    }
  }, AUTO_REFRESH_MS);
};

const fetchDetail = async ({ silent = false } = {}) => {
  if (!silent) {
    isLoading.value = true;
  }
  loadError.value = '';

  try {
    const { data } = await axios.get(`/admin/audit-records/${route.params.id}`, {
      skipGlobalLoading: true,
    });
    const editsPending = silent && hasUnsavedEdits();
    detail.value = data?.data || detail.value;
    if (!isSubmitting.value && !editsPending) {
      lastLoadedBlocksJson = JSON.stringify(detail.value.blocks || []);
      editableBlocks.value = JSON.parse(lastLoadedBlocksJson);
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

const pendingDecision = ref(null);
const decisionComment = ref('');
const decisionError = ref('');

const canSubmitDecision = computed(() => pendingDecision.value !== 'rejected' || decisionComment.value.trim().length >= 5);

const openDecision = (decision) => {
  decisionError.value = '';
  decisionComment.value = '';
  pendingDecision.value = decision;
};

const closeDecision = () => {
  if (!isSubmitting.value) {
    pendingDecision.value = null;
  }
};

const decide = async (decision) => {
  isSubmitting.value = true;
  decisionError.value = '';

  try {
    await axios.post(`/admin/audit-records/${route.params.id}/decision`, {
      decision,
      comments: decisionComment.value.trim() || null,
      // Al rechazar no se envían cifras: el acta completa queda fuera del conteo.
      changes_payload: decision === 'rejected'
        ? null
        : {
          blocks: (editableBlocks.value || []).map((block) => ({
            ...JSON.parse(JSON.stringify(block)),
            votes: { ...block.votes, validos: calculateValidVotes(block), total_votes: calculateTotalVotes(block) },
          })),
        },
    });

    pendingDecision.value = null;
    router.push('/admin/audit');
  } catch (error) {
    // Antes el error reemplazaba toda la pantalla y se perdían las cifras editadas.
    const errors = error?.response?.data?.errors;
    decisionError.value = (errors && Object.values(errors).flat()[0])
      || error?.response?.data?.message
      || 'No se pudo actualizar el estado del acta.';
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
