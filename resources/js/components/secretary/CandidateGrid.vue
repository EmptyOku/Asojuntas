<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-in fade-in duration-500">
    <div 
      v-for="block in batch.blocks" 
      :key="block.block_name"
      class="space-y-4"
    >
      <div class="flex items-center justify-between border-b border-gray-200 pb-2">
        <h5 class="text-[11px] font-black text-gray-900 uppercase tracking-widest">{{ block.block_name }}</h5>
        <span class="text-[9px] text-gray-400 font-bold px-2 py-0.5 bg-gray-100 rounded-full uppercase">
          {{ block.candidates.length }} cargos
        </span>
      </div>

      <div class="space-y-3">
        <div 
          v-for="candidate in block.candidates" 
          :key="candidate.id"
          class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all group relative overflow-hidden"
        >
          <div 
            class="absolute left-0 top-0 bottom-0 w-1"
            :class="statusColor(candidate.review_status)"
          ></div>

          <div class="flex items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-[9px] font-black text-aso-primary uppercase tracking-tighter truncate">
                    {{ candidate.cargo }}
                </p>
                <h6 class="text-sm font-bold text-gray-800 leading-tight truncate group-hover:text-aso-primary transition-colors">
                    {{ formatTitleCase(candidate.full_name) }}
                </h6>
                <p class="text-[10px] text-gray-500 font-medium">
                    CC {{ candidate.document_number || 'S/D' }}
                </p>
            </div>

            <div 
              class="w-2.5 h-2.5 rounded-full shadow-inner shrink-0"
              :class="statusColor(candidate.review_status)"
              :title="`Estado: ${candidate.review_status}`"
            ></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  batch: { type: Object, required: true }
});

const statusColor = (status) => {
  switch (status) {
    case 'approved': return 'bg-emerald-500';
    case 'rejected': return 'bg-red-500';
    default: return 'bg-amber-500'; // pending
  }
};

const formatTitleCase = (text) => {
  if (!text) return 'S/D';
  return text.toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
};
</script>