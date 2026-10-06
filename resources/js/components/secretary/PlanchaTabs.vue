<template>
  <div class="space-y-4">
    <!-- Planchas del barrio + nueva captura -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
      <nav class="flex items-center gap-1 p-1 rounded-2xl bg-white border border-gray-200/70 overflow-x-auto max-w-full shadow-sm" aria-label="Planchas capturadas">
        <button
          v-for="(batch, index) in batches"
          :key="batch.capture_batch_uuid"
          type="button"
          class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition-all"
          :class="activeBatchIndex === index ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/25' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100'"
          :aria-current="activeBatchIndex === index ? 'true' : undefined"
          @click="activeBatchIndex = index"
        >
          <FileText class="w-4 h-4" />
          Plancha {{ batch.number ?? index + 1 }}
          <span v-if="batch.pending > 0" class="h-2 w-2 rounded-full bg-amber-400 animate-pulse" aria-label="Con pendientes"></span>
          <Check v-else-if="batch.official === batch.total" class="w-3.5 h-3.5" aria-label="Oficial" />
        </button>
      </nav>

      <!-- Con un acta aprobada ya no se registran planchas nuevas en el barrio. -->
      <span
        v-if="locked"
        class="badge-red sm:ml-auto"
        data-tooltip="Este barrio ya tiene un acta aprobada: no admite planchas nuevas."
      ><Lock class="w-3 h-3" /> Registro cerrado</span>
      <button v-else v-can="'candidate_drafts.create'" type="button" class="btn-secondary sm:ml-auto" @click="router.push({ name: 'secretary-capture' })">
        <Plus class="w-4 h-4" /> Escanear otra plancha
      </button>
    </div>

    <!-- Estado y acciones de la plancha activa -->
    <div class="card p-4 flex flex-col md:flex-row md:items-center gap-3">
      <div class="flex flex-wrap items-center gap-2 flex-1">
        <span class="badge-green"><span class="badge-dot"></span> {{ currentBatch.approved }} aprobados</span>
        <span class="badge-amber"><span class="badge-dot"></span> {{ currentBatch.pending }} pendientes</span>
        <span v-if="currentBatch.rejected" class="badge-red"><span class="badge-dot"></span> {{ currentBatch.rejected }} rechazados</span>
        <span v-if="currentBatch.official" class="badge-blue"><BadgeCheck class="w-3 h-3" /> {{ currentBatch.official }} oficiales</span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn-secondary" @click="viewPlanchaDetail">
          <Eye class="w-4 h-4" /> Ver / Editar
        </button>
        <button
          v-can="'candidate_drafts.approve'"
          type="button"
          class="btn-secondary !text-red-600 hover:!bg-red-50 hover:!border-red-200"
          :disabled="currentBatch.pending === 0 || deciding"
          @click="pendingDecision = 'rejected'"
        >
          <X class="w-4 h-4" /> Rechazar lote
        </button>
        <button
          v-can="'candidate_drafts.approve'"
          type="button"
          class="btn-primary"
          :disabled="currentBatch.pending === 0 || deciding"
          @click="pendingDecision = 'approved'"
        >
          <Check class="w-4 h-4" /> Aprobar lote
        </button>
      </div>
    </div>

    <div v-if="duplicated.length" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 flex items-start gap-2" role="alert">
      <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0" />
      <div>
        <p class="font-bold">{{ duplicated.length }} candidato(s) de esta plancha ya están registrados</p>
        <p>Revísalos antes de aprobar el lote: una persona solo puede ocupar un cargo. Están marcados en rojo abajo.</p>
      </div>
    </div>

    <SlateRoster :blocks="roster" />

    <ConfirmModal
      :open="Boolean(pendingDecision)"
      :title="pendingDecision === 'rejected' ? '¿Rechazar todo el lote?' : '¿Aprobar todo el lote?'"
      :message="pendingDecision === 'rejected'
        ? `Se rechazarán los ${currentBatch.pending} candidato(s) pendientes de la Plancha ${currentNumber}. No se podrán oficializar.`
        : `Se aprobarán los ${currentBatch.pending} candidato(s) pendientes de la Plancha ${currentNumber}. Luego podrás oficializarlos.`"
      :confirm-text="pendingDecision === 'rejected' ? 'Rechazar lote' : (duplicated.length ? 'Aprobar de todas formas' : 'Aprobar lote')"
      :danger="pendingDecision === 'rejected'"
      :loading="deciding"
      @confirm="handleBatchAction"
      @cancel="pendingDecision = null"
    >
      <div v-if="pendingDecision === 'approved' && duplicated.length" class="mb-2 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-100">
        <p class="font-bold">Atención: {{ duplicated.length }} candidato(s) ya están registrados</p>
        <ul class="mt-1 space-y-1 max-h-40 overflow-y-auto">
          <li v-for="item in duplicated" :key="item.id">
            <strong>{{ formatPersonName(item.name) }}</strong> ({{ item.cargo }}): {{ item.warnings.join(' ') }}
          </li>
        </ul>
        <p class="mt-1">Si apruebas, quedarán aprobados igual. Puedes cancelar y corregirlos en "Ver / Editar".</p>
      </div>
      <p v-if="pendingDecision === 'approved' && unknownCount" class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-100">
        {{ unknownCount }} candidato(s) tienen nombre o documento ilegible (&lt;Desconocido&gt; / documento provisional). Puedes corregirlos después en "Ver / Editar".
      </p>
    </ConfirmModal>

    <ResultModal :open="failed" :success="false" title="No se pudo guardar la decisión" :message="failMessage" @close="failed = false" />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { AlertTriangle, BadgeCheck, Check, Eye, FileText, Lock, Plus, X } from 'lucide-vue-next';
import axios from '@/services/axios';
import ConfirmModal from '@/components/ConfirmModal.vue';
import ResultModal from '@/components/ResultModal.vue';
import SlateRoster from './SlateRoster.vue';
import { formatPersonName, isPlaceholderDocument, isUnknownName } from '@/utils/unknownCandidate';

const props = defineProps({
  batches: { type: Array, required: true },
  neighborhoodName: { type: String, required: true },
  electionId: { type: Number, required: true },
  locked: { type: Boolean, default: false },
});

const emit = defineEmits(['draft-updated']);
const router = useRouter();

const activeBatchIndex = ref(0);
const currentBatch = computed(() => props.batches[activeBatchIndex.value] ?? props.batches[0]);
// Número de la plancha en el barrio (no cambia cuando otra sale de la bandeja).
const currentNumber = computed(() => currentBatch.value?.number ?? activeBatchIndex.value + 1);

// Al oficializar una plancha la lista se acorta: la pestaña activa no puede quedar fuera.
watch(() => props.batches.length, (length) => {
  if (activeBatchIndex.value >= length) activeBatchIndex.value = Math.max(0, length - 1);
});

// Estado visible de cada borrador: oficial si ya se publicó.
const roster = computed(() => (currentBatch.value?.blocks ?? []).map((block) => ({
  name: block.block_name,
  items: block.candidates.map((candidate) => ({
    id: candidate.id,
    cargo: candidate.cargo,
    name: candidate.full_name,
    document: candidate.document_number,
    isSubstitute: Boolean(candidate.is_substitute),
    status: candidate.is_processed && candidate.review_status !== 'rejected' ? 'official' : candidate.review_status,
    warnings: candidate.warnings ?? [],
  })),
})));

const unknownCount = computed(() => roster.value
  .flatMap((block) => block.items)
  .filter((item) => item.status === 'pending' && (isUnknownName(item.name) || isPlaceholderDocument(item.document)))
  .length);

// Candidatos pendientes de esta plancha que ya están registrados en otro lado.
const duplicated = computed(() => roster.value
  .flatMap((block) => block.items)
  .filter((item) => item.status === 'pending' && item.warnings.length));

const viewPlanchaDetail = () => {
  router.push({
    name: 'secretary-plancha-detail',
    params: { id: currentBatch.value.capture_batch_uuid },
    query: {
      batch: currentBatch.value.capture_batch_uuid,
      neighborhood_name: props.neighborhoodName,
      plancha_number: currentNumber.value,
      election_id: props.electionId, // necesario para guardar en la elección correcta
      edit: 'true',
    },
  });
};

const pendingDecision = ref(null);
const deciding = ref(false);
const failed = ref(false);
const failMessage = ref('');

const handleBatchAction = async () => {
  const decision = pendingDecision.value;
  if (!decision) return;

  deciding.value = true;
  try {
    await axios.post('/secretary/planchas/drafts/decision/batch', {
      capture_batch_uuid: currentBatch.value.capture_batch_uuid,
      decision,
    }, { timeout: 240000, skipGlobalLoading: true });
    emit('draft-updated');
  } catch (error) {
    failMessage.value = error?.response?.data?.message || error?.message || 'Error de conexión.';
    failed.value = true;
  } finally {
    deciding.value = false;
    pendingDecision.value = null;
  }
};
</script>
