<template>
  <div class="space-y-4 max-w-7xl mx-auto pb-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-xl shadow-sm border border-gray-200">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Bandeja de Revision de Planchas</h2>
        <p class="text-xs text-gray-500 mt-0.5">Aprueba o rechaza por lote antes de la promocion oficial.</p>
      </div>

      <div class="flex w-full md:w-auto items-center gap-2">
        <div class="relative w-full md:w-72">
          <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
            <Search class="w-4 h-4 text-gray-400" />
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar nombre o documento..."
            class="block w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-aso-primary focus:border-aso-primary transition-colors"
          >
        </div>

        <button
          @click="loadDrafts"
          class="px-3 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50"
        >
          Recargar
        </button>
      </div>
    </div>

    <div v-if="promotionSummary" class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
      <h3 class="text-sm font-bold text-indigo-900">Resumen de Promocion</h3>
      <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs text-indigo-900">
        <p>Procesados: {{ promotionSummary.processed ?? 0 }}</p>
        <p>Personas creadas: {{ promotionSummary.persons_created ?? 0 }}</p>
        <p>Candidatos creados: {{ promotionSummary.candidates_created ?? 0 }}</p>
        <p>Candidatos existentes: {{ promotionSummary.candidates_existing ?? 0 }}</p>
      </div>
      <p v-if="(promotionSummary.skipped ?? 0) > 0" class="mt-2 text-xs text-red-700 font-semibold">
        Omitidos: {{ promotionSummary.skipped }}. Revisa conflictos antes de cerrar el lote.
      </p>
    </div>

    <div v-if="loading" class="bg-white rounded-xl border border-gray-200 p-6 text-sm text-gray-500">
      Cargando borradores...
    </div>

    <div v-else-if="batchRows.length === 0" class="bg-white rounded-xl border border-gray-200 p-6 text-sm text-gray-500">
      No hay borradores de plancha para revisar.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="batch in filteredBatches"
        :key="batch.capture_batch_uuid"
        class="bg-white rounded-xl shadow-sm border border-gray-200"
      >
        <div class="p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
          <div>
            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-bold">Lote</p>
            <p class="text-sm font-semibold text-gray-900">{{ batch.capture_batch_uuid }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-600">
              <span>Total: {{ batch.total }}</span>
              <span class="text-amber-600">Pendientes: {{ batch.pending }}</span>
              <span class="text-emerald-600">Aprobados: {{ batch.approved }}</span>
              <span class="text-red-600">Rechazados: {{ batch.rejected }}</span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button
              @click="openBatchEvidence(batch.capture_batch_uuid)"
              class="px-3 py-2 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
              Ver evidencia
            </button>

            <button
              @click="decideBatch(batch.capture_batch_uuid, 'approved')"
              :disabled="batch.pending === 0 || batchActionLoading === batch.capture_batch_uuid"
              class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 disabled:opacity-60"
            >
              Aprobar lote
            </button>

            <button
              @click="decideBatch(batch.capture_batch_uuid, 'rejected')"
              :disabled="batch.pending === 0 || batchActionLoading === batch.capture_batch_uuid"
              class="px-3 py-2 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 disabled:opacity-60"
            >
              Rechazar lote
            </button>

            <button
              @click="promoteBatch(batch.capture_batch_uuid)"
              :disabled="batch.promotable === 0 || batchActionLoading === batch.capture_batch_uuid"
              class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 disabled:opacity-60"
            >
              Promover aprobados
            </button>
          </div>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                  <th class="py-2 pr-3">Persona</th>
                  <th class="py-2 pr-3">Documento</th>
                  <th class="py-2 pr-3">Estado</th>
                  <th class="py-2 pr-3">Cargo OCR</th>
                  <th class="py-2 pr-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="draft in batch.drafts" :key="draft.id" class="border-t border-gray-100">
                  <td class="py-2 pr-3 text-gray-800 font-medium">{{ formatPersonName(draft) }}</td>
                  <td class="py-2 pr-3 text-gray-700">{{ draft.document_number || '—' }}</td>
                  <td class="py-2 pr-3">
                    <span :class="statusClass(draft)" class="px-2 py-1 rounded-full text-[11px] font-semibold">
                      {{ draft.review_status }}
                    </span>
                  </td>
                  <td class="py-2 pr-3 text-gray-600">{{ draft.notes || '—' }}</td>
                  <td class="py-2 pr-3">
                    <div class="flex items-center gap-1">
                      <button
                        @click="decideDraft(draft.id, 'approved')"
                        :disabled="draft.is_processed || draft.review_status !== 'pending' || draftActionLoading === draft.id"
                        class="px-2 py-1 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold disabled:opacity-50"
                      >
                        Aprobar
                      </button>

                      <button
                        @click="decideDraft(draft.id, 'rejected')"
                        :disabled="draft.is_processed || draft.review_status !== 'pending' || draftActionLoading === draft.id"
                        class="px-2 py-1 rounded bg-red-50 text-red-700 text-xs font-semibold disabled:opacity-50"
                      >
                        Rechazar
                      </button>

                      <button
                        @click="openDraftInDetail(draft)"
                        class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-semibold"
                      >
                        <Eye class="w-3.5 h-3.5 inline" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div v-if="evidenceDialog.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl w-full max-w-3xl max-h-[80vh] overflow-y-auto">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-sm font-bold text-gray-900">Evidencia del lote</h3>
          <button @click="closeEvidenceDialog" class="text-gray-500 hover:text-gray-800">Cerrar</button>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <a
            v-for="file in evidenceDialog.files"
            :key="file.id"
            :href="file.download_url"
            target="_blank"
            rel="noopener noreferrer"
            class="rounded-lg border border-gray-200 p-3 hover:border-aso-primary"
          >
            <p class="text-xs font-semibold text-gray-800">Pagina {{ file.page_number }}</p>
            <p class="text-xs text-gray-500 truncate">{{ file.original_name }}</p>
          </a>
          <p v-if="evidenceDialog.files.length === 0" class="text-sm text-gray-500">Sin evidencia disponible.</p>
        </div>
      </div>
    </div>

    <p v-if="message" class="text-sm font-semibold text-emerald-700">{{ message }}</p>
    <p v-if="errorMessage" class="text-sm font-semibold text-red-600">{{ errorMessage }}</p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Eye, Search } from 'lucide-vue-next';
import axios from '@/services/axios';

const router = useRouter();
const searchQuery = ref('');
const loading = ref(false);
const drafts = ref([]);
const batchActionLoading = ref(null);
const draftActionLoading = ref(null);
const message = ref('');
const errorMessage = ref('');
const promotionSummary = ref(null);

const evidenceDialog = ref({
  open: false,
  files: [],
});

const loadDrafts = async () => {
  loading.value = true;
  errorMessage.value = '';
  message.value = '';

  try {
    const { data } = await axios.get('/secretary/planchas/drafts', {
      params: {
        per_page: 100,
      },
    });

    drafts.value = data?.data?.data ?? [];
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.message;
    errorMessage.value = `No se pudo cargar la bandeja: ${backendMessage}`;
  } finally {
    loading.value = false;
  }
};

const batchRows = computed(() => {
  const groups = {};

  drafts.value.forEach((draft) => {
    const key = draft.capture_batch_uuid || `sin-lote-${draft.id}`;

    if (!groups[key]) {
      groups[key] = {
        capture_batch_uuid: key,
        drafts: [],
        total: 0,
        pending: 0,
        approved: 0,
        rejected: 0,
        promotable: 0,
      };
    }

    groups[key].drafts.push(draft);
    groups[key].total += 1;

    if (draft.review_status === 'pending' && !draft.is_processed) groups[key].pending += 1;
    if (draft.review_status === 'approved') groups[key].approved += 1;
    if (draft.review_status === 'rejected') groups[key].rejected += 1;
    if (draft.review_status === 'approved' && !draft.is_processed) groups[key].promotable += 1;
  });

  return Object.values(groups).sort((a, b) => b.total - a.total);
});

const filteredBatches = computed(() => {
  const term = searchQuery.value.trim().toLowerCase();
  if (!term) return batchRows.value;

  return batchRows.value.filter((batch) => {
    if (batch.capture_batch_uuid.toLowerCase().includes(term)) {
      return true;
    }

    return batch.drafts.some((draft) => {
      const fullName = `${draft.first_name || ''} ${draft.last_name || ''}`.toLowerCase();
      return (
        fullName.includes(term)
        || String(draft.document_number || '').toLowerCase().includes(term)
        || String(draft.notes || '').toLowerCase().includes(term)
      );
    });
  });
});

const statusClass = (draft) => {
  if (draft.review_status === 'approved') return 'bg-emerald-100 text-emerald-700';
  if (draft.review_status === 'rejected') return 'bg-red-100 text-red-700';
  return 'bg-amber-100 text-amber-700';
};

const formatPersonName = (draft) => {
  const name = `${draft.first_name || ''} ${draft.middle_name || ''} ${draft.last_name || ''} ${draft.second_last_name || ''}`
    .replace(/\s+/g, ' ')
    .trim();
  return name || 'Sin nombre';
};

const decideDraft = async (draftId, decision) => {
  draftActionLoading.value = draftId;
  errorMessage.value = '';
  message.value = '';

  try {
    await axios.post(`/secretary/planchas/drafts/${draftId}/decision`, { decision });
    message.value = `Borrador ${decision === 'approved' ? 'aprobado' : 'rechazado'} correctamente.`;
    await loadDrafts();
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.message;
    errorMessage.value = `No se pudo decidir el borrador: ${backendMessage}`;
  } finally {
    draftActionLoading.value = null;
  }
};

const decideBatch = async (captureBatchUuid, decision) => {
  const confirmMessage = decision === 'approved'
    ? '¿Confirmas aprobar todos los borradores pendientes de este lote?'
    : '¿Confirmas rechazar todos los borradores pendientes de este lote?';

  if (!window.confirm(confirmMessage)) {
    return;
  }

  batchActionLoading.value = captureBatchUuid;
  errorMessage.value = '';
  message.value = '';

  try {
    const { data } = await axios.post('/secretary/planchas/drafts/decision/batch', {
      capture_batch_uuid: captureBatchUuid,
      decision,
    });

    const updated = data?.data?.updated ?? 0;
    message.value = `Lote ${decision === 'approved' ? 'aprobado' : 'rechazado'}: ${updated} borradores actualizados.`;
    await loadDrafts();
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.message;
    errorMessage.value = `No se pudo decidir el lote: ${backendMessage}`;
  } finally {
    batchActionLoading.value = null;
  }
};

const promoteBatch = async (captureBatchUuid) => {
  if (!window.confirm('¿Confirmas promover los borradores aprobados de este lote a datos oficiales?')) {
    return;
  }

  batchActionLoading.value = captureBatchUuid;
  errorMessage.value = '';
  message.value = '';

  try {
    const { data } = await axios.post('/secretary/planchas/drafts/promote', {
      capture_batch_uuid: captureBatchUuid,
    });

    const result = data?.data;
    promotionSummary.value = result || null;
    message.value = `Promocion realizada. Procesados: ${result?.processed ?? 0}, candidatos creados: ${result?.candidates_created ?? 0}.`;
    await loadDrafts();
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.message;
    errorMessage.value = `No se pudo promover el lote: ${backendMessage}`;
  } finally {
    batchActionLoading.value = null;
  }
};

const openBatchEvidence = async (captureBatchUuid) => {
  errorMessage.value = '';

  try {
    const { data } = await axios.get(`/secretary/planchas/evidence/${captureBatchUuid}`);
    evidenceDialog.value = {
      open: true,
      files: data?.data?.files ?? [],
    };
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.message;
    errorMessage.value = `No se pudo cargar evidencia del lote: ${backendMessage}`;
  }
};

const closeEvidenceDialog = () => {
  evidenceDialog.value = { open: false, files: [] };
};

const openDraftInDetail = (draft) => {
  router.push({
    name: 'secretary-plancha-detail',
    params: { id: draft.id },
    query: {
      batch: draft.capture_batch_uuid,
      edit: true,
      neighborhood_name: draft.neighborhood_name || 'JAC Sin Identificar'
    },
  });
};

onMounted(async () => {
  await loadDrafts();
});
</script>
