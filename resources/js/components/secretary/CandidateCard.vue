<template>
  <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 relative transition-all shadow-sm" :class="{'border-aso-primary ring-1 ring-aso-primary bg-white': isEditing}">
    
    <h4 class="font-bold text-gray-800 text-sm mb-5 uppercase tracking-wide flex items-center gap-2">
      <span class="w-2.5 h-2.5 rounded-full transition-colors" :class="isEditing ? 'bg-aso-primary shadow-[0_0_8px_rgba(30,143,77,0.5)]' : 'bg-gray-300'"></span>
      {{ cargo }}
    </h4>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      
      <div class="flex flex-col justify-end">
        <label class="block text-sm font-medium text-gray-600 mb-1.5 truncate">Nombre Completo</label>
        <input 
          type="text" 
          :value="nombre"
          @input="$emit('update:nombre', $event.target.value)"
          :disabled="!isEditing"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

      <div class="flex flex-col justify-end">
        <label class="block text-sm font-medium text-gray-600 mb-1.5 truncate">No. Identificación</label>
        <input 
          type="text" 
          :value="identificacion"
          @input="$emit('update:identificacion', $event.target.value)"
          :disabled="!isEditing"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

      <div class="flex flex-col justify-end">
        <label class="block text-sm font-medium text-gray-600 mb-1.5 truncate">Celular</label>
        <input 
          type="tel" 
          :value="celular"
          @input="$emit('update:celular', $event.target.value)"
          :disabled="!isEditing"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

      <div class="flex flex-col justify-end">
        <label class="block text-sm font-medium text-gray-600 mb-1.5 truncate">Correo Electrónico</label>
        <input 
          type="email" 
          :value="correo"
          @input="$emit('update:correo', $event.target.value)"
          :disabled="!isEditing"
          class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-aso-primary outline-none disabled:bg-gray-100 disabled:text-gray-500 disabled:border-transparent transition-all"
        />
      </div>

    </div>
  </div>
</template>

<script setup>
defineProps({
  cargo: { type: String, required: true },
  nombre: { type: String, default: '' },
  identificacion: { type: String, default: '' },
  celular: { type: String, default: '' },
  correo: { type: String, default: '' },
  isEditing: { type: Boolean, default: false }
});

defineEmits(['update:nombre', 'update:identificacion', 'update:celular', 'update:correo']);
</script>