<template>
  <article class="card overflow-hidden transition-shadow" :class="{ 'ring-2 ring-aso-primary/30 shadow-md': isOpen }">
    <div class="flex flex-col lg:flex-row lg:items-center gap-4 p-4 sm:p-5">
      <button
        type="button"
        class="flex items-center gap-3 min-w-0 flex-1 text-left"
        :aria-expanded="isOpen"
        @click="isOpen = !isOpen"
      >
        <span
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
          :class="neighborhood.total_pending > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-aso-primary'"
        >
          <MapPin class="w-5 h-5" />
        </span>
        <span class="min-w-0">
          <span class="block font-display text-lg font-bold text-gray-900 truncate">{{ neighborhood.neighborhood_name }}</span>
          <span class="block text-xs text-gray-500 truncate">
            {{ neighborhood.commune_name }} · {{ neighborhood.batches.length }} {{ neighborhood.batches.length === 1 ? 'plancha' : 'planchas' }}
          </span>
        </span>
      </button>

      <!-- Avance: aprobados sobre el total -->
      <div class="flex flex-wrap items-center gap-2 lg:gap-3">
        <div class="hidden md:block w-32" :aria-label="`${progress}% aprobado`">
          <div class="flex justify-between text-[11px] font-semibold text-gray-500 mb-1">
            <span>Aprobado</span><span class="tabular-nums">{{ progress }}%</span>
          </div>
          <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full rounded-full bg-aso-primary transition-[width] duration-500" :style="{ width: `${progress}%` }"></div>
          </div>
        </div>
        <span v-if="neighborhood.total_pending" class="badge-amber"><span class="badge-dot animate-pulse"></span> {{ neighborhood.total_pending }} pendientes</span>
        <span class="badge-green"><span class="badge-dot"></span> {{ neighborhood.total_approved }} aprobados</span>

        <button
          v-can="'candidate_drafts.promote'"
          type="button"
          class="btn-primary"
          :disabled="isPromoting || promotableTotal === 0"
          :data-tooltip="promotableTotal ? `Publicar ${promotableTotal} candidato(s) aprobados` : 'No hay candidatos aprobados sin oficializar'"
          @click="confirmPromote = true"
        >
          <Loader2 v-if="isPromoting" class="w-4 h-4 animate-spin" />
          <BadgeCheck v-else class="w-4 h-4" />
          Oficializar
        </button>

        <button
          type="button"
          class="h-9 w-9 flex items-center justify-center rounded-xl text-gray-400 hover:bg-gray-100"
          :aria-label="isOpen ? 'Contraer barrio' : 'Ver planchas del barrio'"
          @click="isOpen = !isOpen"
        >
          <ChevronDown class="w-5 h-5 transition-transform duration-300" :class="{ 'rotate-180 text-aso-primary': isOpen }" />
        </button>
      </div>
    </div>

    <div v-if="isOpen" class="border-t border-gray-100 bg-gray-50/60 p-4 sm:p-5 animate-rise">
      <PlanchaTabs
        :batches="neighborhood.batches"
        :neighborhood-name="neighborhood.neighborhood_name"
        :election-id="neighborhood.election_id"
        :locked="Boolean(neighborhood.has_approved_acta)"
        @draft-updated="$emit('reload-requested')"
      />
    </div>

    <ConfirmModal
      :open="confirmPromote"
      title="¿Oficializar las planchas aprobadas?"
      :message="`Se publicarán ${promotableTotal} candidato(s) aprobados de ${neighborhood.neighborhood_name} en las planchas oficiales. Después ya no se pueden editar desde la bandeja.`"
      confirm-text="Oficializar"
      :loading="isPromoting"
      @confirm="promoteOfficial"
      @cancel="confirmPromote = false"
    />

    <ResultModal
      :open="result.open"
      :success="result.success"
      :title="result.title"
      :message="result.message"
      @close="result.open = false"
    />
  </article>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { BadgeCheck, ChevronDown, Loader2, MapPin } from 'lucide-vue-next';
import axios from '@/services/axios';
import ConfirmModal from '@/components/ConfirmModal.vue';
import ResultModal from '@/components/ResultModal.vue';
import PlanchaTabs from '@/components/secretary/PlanchaTabs.vue';

const props = defineProps({
  neighborhood: { type: Object, required: true },
});

const emit = defineEmits(['reload-requested']);

const isOpen = ref(false);
const isPromoting = ref(false);
const confirmPromote = ref(false);
const result = reactive({ open: false, success: true, title: '', message: '' });

const promotableTotal = computed(() => props.neighborhood.batches.reduce((sum, batch) => sum + (batch.promotable || 0), 0));
const progress = computed(() => {
  const total = props.neighborhood.total_drafts || 0;
  return total ? Math.round((props.neighborhood.total_approved / total) * 100) : 0;
});

const showResult = (success, title, message) => Object.assign(result, { open: true, success, title, message });

const promoteOfficial = async () => {
  isPromoting.value = true;
  let processed = 0;
  let skipped = 0;
  const issues = [];
  const errors = [];

  for (const batch of props.neighborhood.batches) {
    const number = batch.number ?? '';
    if (!batch.promotable) continue;
    try {
      const { data } = await axios.post('/secretary/planchas/drafts/promote', {
        capture_batch_uuid: batch.capture_batch_uuid,
      }, { timeout: 240000, skipGlobalLoading: true });

      const summary = data?.data ?? {};
      processed += Number(summary.processed || 0);
      skipped += Number(summary.skipped || 0);
      (summary.issues ?? []).forEach((issue) => issues.push(`Plancha ${number}: ${issue?.reason || 'sin detalle'}`));
    } catch (error) {
      errors.push(`Plancha ${number}: ${error?.response?.data?.message || error.message}`);
    }
  }

  isPromoting.value = false;
  confirmPromote.value = false;

  const detail = issues.length ? `\n\n${issues.slice(0, 8).join('\n')}` : '';
  if (errors.length) {
    showResult(false, 'La oficialización tuvo errores', `${errors.join('\n')}${detail}`);
  } else if (processed && !skipped) {
    showResult(true, 'Planchas oficializadas', `Se publicaron ${processed} candidato(s) de ${props.neighborhood.neighborhood_name}.\n\nYa salieron de esta bandeja: ahora están en "Planchas por Barrio".`);
  } else if (processed) {
    showResult(true, 'Oficialización parcial', `Publicados: ${processed}. Omitidos: ${skipped}.${detail}`);
  } else {
    showResult(false, 'No se oficializó ningún candidato', `Omitidos: ${skipped}.${detail}`);
  }

  emit('reload-requested');
};
</script>
