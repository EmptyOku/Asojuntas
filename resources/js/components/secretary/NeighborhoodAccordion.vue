<template>
  <div class="bg-white rounded-xl shadow-sm border overflow-hidden transition-colors" :class="isOpen ? 'border-amber-400' : 'border-gray-200'">
    
    <div 
      @click="toggleOpen"
      class="p-4 cursor-pointer hover:bg-gray-50 flex flex-col lg:flex-row lg:items-center justify-between gap-4 transition-colors"
      :class="isOpen ? 'bg-amber-50/50 hover:bg-amber-50' : ''"
    >
      <div class="flex items-center gap-4">
        <div class="w-1.5 h-12 rounded-full" :class="neighborhood.total_pending > 0 ? 'bg-amber-400' : 'bg-emerald-500'"></div>
        <div>
          <h3 class="text-lg font-black text-gray-900 leading-tight">{{ neighborhood.neighborhood_name }}</h3>
          <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">{{ neighborhood.commune_name }}</p>
        </div>
      </div>

      <div class="flex items-center gap-6 overflow-x-auto pb-1 lg:pb-0">
        
        <div class="flex items-center gap-6 px-4 border-l border-gray-200">
          <div class="text-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total</p>
            <p class="text-lg font-black text-gray-900">{{ neighborhood.total_drafts }}</p>
          </div>
          <div class="text-center">
            <p class="text-[10px] font-bold text-amber-500 uppercase tracking-widest">Pendientes</p>
            <p class="text-lg font-black text-amber-600">{{ neighborhood.total_pending }}</p>
          </div>
          <div class="text-center">
            <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">Aprobados</p>
            <p class="text-lg font-black text-emerald-600">{{ neighborhood.total_approved }}</p>
          </div>
        </div>

        <button 
          @click.stop="promoteOfficial"
          :disabled="isPromoting || !hasPromotableData"
          class="shrink-0 px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          :class="hasPromotableData ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-gray-100 text-gray-400'"
        >
          <span v-if="isPromoting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          Promover Oficial
        </button>

        <div class="shrink-0 p-2 bg-gray-100 rounded-full text-gray-500">
          <ChevronDown class="w-5 h-5 transition-transform duration-300" :class="isOpen ? 'rotate-180' : ''" />
        </div>
      </div>
    </div>

    <div v-if="isOpen" class="border-t border-gray-100 bg-gray-50 p-4">
      
      <PlanchaTabs 
        :batches="neighborhood.batches" 
        :neighborhood-name="neighborhood.neighborhood_name"
        :election-id="neighborhood.election_id"
        @draft-updated="$emit('reload-requested')"
      />

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import axios from '@/services/axios';

// Importamos el componente de las Pestañas que crearemos en el próximo paso
import PlanchaTabs from '@/components/secretary/PlanchaTabs.vue';

const props = defineProps({
  neighborhood: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['reload-requested']);

const isOpen = ref(false);
const isPromoting = ref(false);

const toggleOpen = () => {
  isOpen.value = !isOpen.value;
};

// Verifica si hay al menos una plancha con candidatos aprobados listos para promover
const hasPromotableData = computed(() => {
  return props.neighborhood.batches.some(batch => batch.promotable > 0);
});

const promoteOfficial = async () => {
  if (!window.confirm(`¿Confirmas promover todos los datos aprobados del barrio ${props.neighborhood.neighborhood_name}?`)) {
    return;
  }

  isPromoting.value = true;
  let processedTotal = 0;
  let skippedTotal = 0;
  const promotionIssues = [];
  let errors = [];

  // Iteramos sobre las planchas del barrio que tienen algo para promover
  const batchesToPromote = props.neighborhood.batches.filter(b => b.promotable > 0);

  for (const batch of batchesToPromote) {
    try {
      const { data } = await axios.post('/secretary/planchas/drafts/promote', { 
        capture_batch_uuid: batch.capture_batch_uuid 
      }, {
        timeout: 240000,
        skipGlobalLoading: true,
      });

      const summary = data?.data || {};
      processedTotal += Number(summary.processed || 0);
      skippedTotal += Number(summary.skipped || 0);

      if (Array.isArray(summary.issues) && summary.issues.length > 0) {
        summary.issues.forEach((issue) => {
          const detail = issue?.reason || 'Sin detalle';
          promotionIssues.push(`Lote ${batch.capture_batch_uuid}: ${detail}`);
        });
      }
    } catch (error) {
      errors.push(`Error en lote ${batch.capture_batch_uuid}: ${error?.response?.data?.message || error.message}`);
    }
  }

  isPromoting.value = false;

  if (errors.length > 0) {
    window.alert(`Promoción finalizada con errores:\n\n${errors.join('\n')}`);
  } else if (processedTotal > 0 && skippedTotal === 0) {
    window.alert('¡Promoción oficial completada con éxito para todo el barrio!');
  } else if (processedTotal > 0 && skippedTotal > 0) {
    window.alert(
      `Promoción parcial completada.\n\nPromovidos: ${processedTotal}\nOmitidos: ${skippedTotal}`
      + (promotionIssues.length ? `\n\nDetalle:\n${promotionIssues.slice(0, 12).join('\n')}` : '')
    );
  } else if (skippedTotal > 0) {
    window.alert(
      `No se promovieron candidatos para este barrio.\n\nOmitidos: ${skippedTotal}`
      + (promotionIssues.length ? `\n\nDetalle:\n${promotionIssues.slice(0, 12).join('\n')}` : '')
    );
  } else {
    window.alert('No hubo cambios para promover en este barrio.');
  }

  // Le decimos al padre que recargue los datos para actualizar los contadores
  emit('reload-requested');
};
</script>