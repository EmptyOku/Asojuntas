<template>
  <!-- Integrantes de una plancha agrupados por bloque. Lo usan la bandeja de
       revisión (con estado de cada borrador) y las planchas oficiales. -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5">
    <section
      v-for="block in blocks"
      :key="block.name"
      class="rounded-2xl bg-white ring-1 ring-gray-200/80 shadow-sm overflow-hidden"
    >
      <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100 bg-gray-50/70">
        <h4 class="flex items-center gap-2 font-display text-sm font-bold text-gray-900 min-w-0">
          <span class="h-2 w-2 shrink-0 rounded-full bg-aso-primary"></span>
          <span class="truncate">{{ block.name }}</span>
        </h4>
        <span class="badge-gray shrink-0">{{ block.items.length }} {{ block.items.length === 1 ? 'cargo' : 'cargos' }}</span>
      </header>

      <ul class="divide-y divide-gray-100">
        <li
          v-for="item in block.items"
          :key="item.id"
          class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50/70"
          :class="item.warnings?.length ? 'bg-red-50/60' : { 'bg-gray-50/40': item.isSubstitute }"
        >
          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-xs font-bold"
            :class="unknown(item) ? 'bg-amber-50 text-amber-600' : item.isSubstitute ? 'bg-gray-100 text-gray-500' : 'bg-emerald-50 text-aso-primary'"
            aria-hidden="true"
          >
            <HelpCircle v-if="unknown(item)" class="w-4 h-4" />
            <template v-else>{{ initials(item.name) }}</template>
          </span>

          <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-aso-primary">
              <span class="truncate">{{ item.cargo }}</span>
              <span v-if="item.isSubstitute" class="rounded-md bg-gray-100 px-1.5 py-px text-[10px] font-semibold normal-case tracking-normal text-gray-500">Suplente</span>
            </p>
            <p class="font-semibold text-gray-900 truncate" :class="{ 'italic text-gray-500': unknown(item) }">
              {{ formatPersonName(item.name) }}
            </p>
            <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500">
              <span class="tabular-nums">CC {{ item.document || 'S/D' }}</span>
              <span
                v-if="isPlaceholderDocument(item.document)"
                class="font-semibold text-amber-700"
                data-tooltip="La extracción no leyó el documento: se asignó un número provisional. Corrígelo cuando se conozca."
              >· Provisional</span>
              <span v-if="unknown(item)" class="font-semibold text-amber-700">· Ilegible en la extracción</span>
            </p>
            <!-- Candidato que ya está registrado en otra plancha o repetido en esta. -->
            <p v-for="warning in item.warnings ?? []" :key="warning" class="mt-1 flex items-start gap-1.5 text-xs font-semibold text-red-700">
              <AlertTriangle class="w-3.5 h-3.5 mt-px shrink-0" />
              {{ warning }}
            </p>
          </div>

          <span v-if="item.status" :class="STATUS[item.status]?.class ?? 'badge-gray'" class="shrink-0">
            <span class="badge-dot"></span>
            {{ STATUS[item.status]?.label ?? item.status }}
          </span>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { AlertTriangle, HelpCircle } from 'lucide-vue-next';
import { formatPersonName, isPlaceholderDocument, isUnknownName } from '@/utils/unknownCandidate';

defineProps({
  // [{ name: 'Directiva', items: [{ id, cargo, name, document, isSubstitute, status?, warnings? }] }]
  blocks: { type: Array, required: true },
});

const STATUS = {
  approved: { label: 'Aprobado', class: 'badge-green' },
  pending: { label: 'Pendiente', class: 'badge-amber' },
  rejected: { label: 'Rechazado', class: 'badge-red' },
  official: { label: 'Oficial', class: 'badge-green' },
};

const unknown = (item) => isUnknownName(item.name);

const initials = (name) => String(name || '?')
  .replace(/_/g, ' ')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part.charAt(0).toUpperCase())
  .join('');
</script>
