<template>
  <!-- Miniaturas de las páginas fotografiadas (planchas y actas): quitar,
       cambiar el orden y añadir más hasta el máximo. -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
    <figure
      v-for="(img, index) in images"
      :key="img.id"
      class="relative aspect-[3/4] overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-gray-200 shadow-sm"
    >
      <!-- object-contain: se ve la hoja completa, para notar si quedó de lado. -->
      <img :src="img.url" :alt="`Página ${index + 1}`" class="h-full w-full object-contain">

      <figcaption class="absolute top-2 left-2 rounded-full bg-black/70 px-2.5 py-1 text-[11px] font-bold text-white">
        Pág. {{ index + 1 }}
      </figcaption>

      <div class="absolute top-2 right-2 flex items-center gap-1.5">
        <!-- Foto de lado o al revés: se gira aquí, sin volver a tomarla. -->
        <button
          type="button"
          class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/95 text-gray-700 shadow-md ring-1 ring-black/5 transition-colors hover:bg-white hover:text-aso-primary disabled:opacity-60"
          :disabled="rotatingId === img.id"
          :aria-label="`Girar la página ${index + 1} a la derecha`"
          data-tooltip="Girar a la derecha"
          @click="$emit('rotate', img.id)"
        >
          <Loader2 v-if="rotatingId === img.id" class="w-4 h-4 animate-spin" />
          <RotateCw v-else class="w-4 h-4" />
        </button>
        <button
          type="button"
          class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-600 text-white shadow-md transition-colors hover:bg-red-700"
          :aria-label="`Quitar la página ${index + 1}`"
          data-tooltip="Quitar página"
          @click="$emit('remove', img.id)"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Cambiar el orden sin volver a tomar la foto. -->
      <div v-if="images.length > 1" class="absolute bottom-0 inset-x-0 flex items-center justify-between bg-black/65 px-1.5 py-1">
        <button type="button" class="rounded-md p-1.5 text-white hover:bg-white/20 disabled:opacity-30" :disabled="index === 0" :aria-label="`Mover la página ${index + 1} antes`" @click="$emit('move', index, -1)">
          <ChevronLeft class="w-4 h-4" />
        </button>
        <span class="text-[10px] font-semibold uppercase tracking-wide text-white/80">Mover</span>
        <button type="button" class="rounded-md p-1.5 text-white hover:bg-white/20 disabled:opacity-30" :disabled="index === images.length - 1" :aria-label="`Mover la página ${index + 1} después`" @click="$emit('move', index, 1)">
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </figure>

    <button
      v-if="images.length < max"
      type="button"
      class="flex aspect-[3/4] flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 text-gray-500 transition-colors hover:border-aso-primary hover:bg-emerald-50/50 hover:text-aso-primary"
      @click="$emit('add')"
    >
      <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200"><Plus class="w-5 h-5" /></span>
      <span class="text-sm font-semibold">Añadir página</span>
      <span class="text-xs text-gray-400">{{ images.length }} de {{ max }}</span>
    </button>
  </div>
</template>

<script setup>
import { ChevronLeft, ChevronRight, Loader2, Plus, RotateCw, X } from 'lucide-vue-next';

defineProps({
  images: { type: Array, required: true },
  max: { type: Number, required: true },
  // Id de la foto que se está girando (muestra el indicador de espera).
  rotatingId: { type: [Number, String], default: null },
});

defineEmits(['remove', 'move', 'add', 'rotate']);
</script>
