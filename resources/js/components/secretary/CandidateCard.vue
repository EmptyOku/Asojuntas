<template>
  <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 relative transition-all shadow-sm" :class="{'border-aso-primary ring-1 ring-aso-primary bg-white': isEditing && !locked}">
    
    <h4 class="font-bold text-gray-800 text-sm mb-5 uppercase tracking-wide flex items-center gap-2">
      <span class="w-2.5 h-2.5 rounded-full transition-colors" :class="isEditing ? 'bg-aso-primary shadow-[0_0_8px_rgba(30,143,77,0.5)]' : 'bg-gray-300'"></span>
      {{ cargo }}
      <span v-if="STATUS[estado]" :class="STATUS[estado].class" class="ml-auto normal-case tracking-normal">
        <Lock v-if="locked" class="w-3 h-3" />
        <span v-else class="badge-dot"></span>
        {{ STATUS[estado].label }}
      </span>
    </h4>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      
      <div class="flex flex-col justify-end">
        <label class="field-label truncate">Nombre Completo</label>
        <input 
          type="text" 
          :value="nombre"
          @input="$emit('update:nombre', $event.target.value)"
          :disabled="!isEditing || locked"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

      <div class="flex flex-col justify-end">
        <label class="field-label truncate">No. Identificación</label>
        <input 
          type="text" 
          :value="identificacion"
          inputmode="numeric"
          @input="$emit('update:identificacion', $event.target.value.replace(/[.,\s]/g, ''))"
          :disabled="!isEditing || locked"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

      <div class="flex flex-col justify-end">
        <label class="field-label truncate">Celular</label>
        <input 
          type="tel" 
          :value="celular"
          @input="$emit('update:celular', $event.target.value)"
          :disabled="!isEditing || locked"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

      <div class="flex flex-col justify-end">
        <label class="field-label truncate">Correo Electrónico</label>
        <input 
          type="email" 
          :value="correo"
          @input="$emit('update:correo', $event.target.value)"
          :disabled="!isEditing || locked"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

    </div>

    <!-- Candidato ilegible o documento provisional: se avisa para corregirlo. -->
    <p v-if="unknown || provisional" class="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-100">
      <AlertTriangle class="w-4 h-4 shrink-0" />
      <span v-if="unknown">
        La extracción no pudo leer este candidato. Si puedes, escribe el nombre real; si el documento queda vacío se asignará uno provisional.
      </span>
      <span v-else>Documento provisional asignado automáticamente: corrígelo cuando se conozca el real.</span>
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { AlertTriangle, Lock } from 'lucide-vue-next';
import { isPlaceholderDocument, isUnknownName } from '@/utils/unknownCandidate';

const props = defineProps({
  cargo: { type: String, required: true },
  nombre: { type: String, default: '' },
  identificacion: { type: String, default: '' },
  celular: { type: String, default: '' },
  correo: { type: String, default: '' },
  isEditing: { type: Boolean, default: false },
  // '' | 'pending' | 'approved' | 'rejected' | 'official'
  estado: { type: String, default: '' },
});

const STATUS = {
  pending: { label: 'Pendiente', class: 'badge-amber' },
  approved: { label: 'Aprobado', class: 'badge-green' },
  rejected: { label: 'Rechazado', class: 'badge-red' },
  official: { label: 'Oficial · no editable', class: 'badge-green' },
};

// Un candidato ya oficial no se edita desde la plancha (el servidor tampoco lo cambia).
const locked = computed(() => props.estado === 'official');

defineEmits(['update:nombre', 'update:identificacion', 'update:celular', 'update:correo']);

const unknown = computed(() => isUnknownName(props.nombre));
const provisional = computed(() => isPlaceholderDocument(props.identificacion));
</script>