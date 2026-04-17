<template>
  <div class="space-y-6">
    <div class="flex items-center gap-2 bg-white p-1.5 rounded-xl border border-gray-200 shadow-sm relative">
      <button 
        @click="scrollTabs('left')"
        class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 transition-colors"
      >
        <ChevronLeft class="w-5 h-5" />
      </button>

      <div 
        ref="tabsContainer"
        class="flex-1 flex overflow-x-auto no-scrollbar gap-1 scroll-smooth"
      >
        <button
          v-for="(batch, index) in batches"
          :key="batch.capture_batch_uuid"
          @click="activeBatchIndex = index"
          class="shrink-0 px-6 py-2.5 rounded-lg text-sm font-bold transition-all flex items-center gap-2 whitespace-nowrap"
          :class="activeBatchIndex === index 
            ? 'bg-aso-primary/10 text-aso-primary ring-1 ring-aso-primary/20' 
            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
        >
          <FileText class="w-4 h-4" />
          Plancha {{ index + 1 }}
          <span 
            v-if="batch.pending > 0"
            class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"
          ></span>
        </button>
      </div>

      <button 
        @click="scrollTabs('right')"
        class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 transition-colors"
      >
        <ChevronRight class="w-5 h-5" />
      </button>

      <button 
        @click="addNewPlancha"
        class="p-2 bg-gray-900 text-white rounded-lg hover:bg-black transition-colors shadow-md ml-2"
        title="Escanear nueva plancha para este barrio"
      >
        <Plus class="w-5 h-5" />
      </button>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-2">
      <div class="flex items-center gap-4 text-[10px] font-black uppercase tracking-widest text-gray-400">
        <span class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-emerald-500"></div> {{ currentBatch.approved }} Aprobados</span>
        <span class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-500"></div> {{ currentBatch.pending }} Pendientes</span>
        <span class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-red-500"></div> {{ currentBatch.rejected }} Rechazados</span>
      </div>

      <div class="flex items-center gap-2 w-full sm:w-auto">
        <button 
          @click="viewPlanchaDetail"
          class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-700 hover:border-aso-primary hover:text-aso-primary transition-all shadow-sm"
        >
          <Eye class="w-4 h-4" /> Ver / Editar
        </button>
        
        <button 
          @click="handleBatchAction('approved')"
          :disabled="currentBatch.pending === 0"
          class="flex-1 sm:flex-none px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 disabled:opacity-50 transition-all shadow-sm"
        >
          Aprobar Lote
        </button>
      </div>
    </div>

    <CandidateGrid :batch="currentBatch" />

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, FileText, Plus, Eye } from 'lucide-vue-next';
import axios from '@/services/axios';
import CandidateGrid from './CandidateGrid.vue';

const props = defineProps({
  batches: { type: Array, required: true },
  neighborhoodName: { type: String, required: true },
  electionId: { type: Number, required: true }
});

const emit = defineEmits(['draft-updated']);
const router = useRouter();

const activeBatchIndex = ref(0);
const tabsContainer = ref(null);

const currentBatch = computed(() => props.batches[activeBatchIndex.value]);

const scrollTabs = (direction) => {
  if (!tabsContainer.value) return;
  const amount = direction === 'left' ? -200 : 200;
  tabsContainer.value.scrollBy({ left: amount, behavior: 'smooth' });
};

const addNewPlancha = () => router.push({ name: 'secretary-capture' });

const viewPlanchaDetail = () => {
  router.push({
    name: 'secretary-plancha-detail', 
    params: { id: currentBatch.value.capture_batch_uuid }, 
    query: { 
      batch: currentBatch.value.capture_batch_uuid,
      neighborhood_name: props.neighborhoodName,
      plancha_number: activeBatchIndex.value + 1,
      election_id: props.electionId, // <-- VITAL PARA EL GUARDADO
      edit: 'true'
    }
  });
};

const handleBatchAction = async (decision) => {
  if (!window.confirm(`¿Confirmas aprobar todos los candidatos de esta plancha?`)) return;
  try {
    await axios.post('/secretary/planchas/drafts/decision/batch', {
      capture_batch_uuid: currentBatch.value.capture_batch_uuid,
      decision
    });
    emit('draft-updated');
  } catch (error) {
    console.error(error);
  }
};
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>