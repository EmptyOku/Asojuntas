<template>
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 sm:px-6 py-4 border-t border-gray-100 bg-gray-50/50">
    <p class="text-sm text-gray-500">
      <template v-if="total > 0">
        Mostrando <span class="font-semibold text-gray-700">{{ from }}–{{ to }}</span> de {{ total.toLocaleString('es-CO') }} {{ label }}
      </template>
      <template v-else>Sin resultados</template>
    </p>

    <nav v-if="last > 1" class="flex items-center gap-1.5" aria-label="Paginación">
      <button type="button" class="pager-btn" aria-label="Página anterior" :disabled="loading || current <= 1" @click="go(current - 1)">
        <ChevronLeft class="w-4 h-4" />
      </button>

      <template v-for="(page, index) in pages" :key="index">
        <span v-if="page === null" class="px-1 text-gray-400 select-none">…</span>
        <button
          v-else
          type="button"
          class="pager-btn tabular-nums"
          :class="page === current ? '!bg-aso-primary !text-white !border-aso-primary shadow-sm shadow-aso-primary/30' : ''"
          :aria-current="page === current ? 'page' : undefined"
          :disabled="loading"
          @click="go(page)"
        >
          {{ page }}
        </button>
      </template>

      <button type="button" class="pager-btn" aria-label="Página siguiente" :disabled="loading || current >= last" @click="go(current + 1)">
        <ChevronRight class="w-4 h-4" />
      </button>
    </nav>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
  current: { type: Number, default: 1 },
  last: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
  from: { type: Number, default: 0 },
  to: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  // Qué se cuenta: "barrios", "actas"...
  label: { type: String, default: 'registros' },
});

const emit = defineEmits(['change']);

// Primera, última y dos a cada lado de la actual; el resto se resume con "…".
const pages = computed(() => {
  const { current, last } = props;
  const wanted = new Set([1, last, current - 2, current - 1, current, current + 1, current + 2]);
  const sorted = [...wanted].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b);

  return sorted.flatMap((page, index) => (index > 0 && page - sorted[index - 1] > 1 ? [null, page] : [page]));
});

const go = (page) => {
  if (page >= 1 && page <= props.last && page !== props.current) emit('change', page);
};
</script>
