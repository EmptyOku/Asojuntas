<template>
  <!-- Qué hace cada botón de la columna Acciones, con el mismo color e ícono. -->
  <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500" role="note" aria-label="Significado de los botones de acciones">
    <span class="font-bold uppercase tracking-[0.1em] text-[10.5px] text-gray-400">Acciones</span>
    <span v-for="item in items" :key="item.label" class="inline-flex items-center gap-1.5">
      <span :class="`icon-btn-${item.tone ?? 'gray'}`" class="!h-6 !w-6 !rounded-md pointer-events-none" aria-hidden="true">
        <component :is="item.icon" class="w-3.5 h-3.5" />
      </span>
      <span class="font-medium text-gray-600">{{ item.label }}</span>
    </span>
  </div>
</template>

<script setup>
defineProps({
  // [{ icon: Componente, label: 'Editar', tone: 'blue' | 'amber' | 'red' | 'green' | 'gray' }]
  items: { type: Array, required: true },
});
</script>
