<template>
  <div class="space-y-6 flex-1 flex flex-col">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
      <h2 class="text-xl font-bold text-gray-900">Hola, {{ authStore.user?.name || 'Jurado' }}</h2>
      <p class="text-sm text-gray-500 mt-1">Bienvenido al módulo de transmisión documental.</p>
      
      <div class="mt-6 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-start gap-3">
        <MapPin class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
        <div>
          <p class="text-xs font-bold text-blue-600 uppercase tracking-wide">Tu Asignación Actual</p>
          <p class="text-lg font-bold text-gray-900 mt-1">Mesa 14</p>
          <p class="text-sm text-gray-600">Colegio Departamental - Comuna 1</p>
        </div>
      </div>
    </div>

    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider px-2">Documentos a Transmitir</h3>

    <div class="relative group">
      <router-link 
        to="/jury/capture?doc=escrutinio" 
        class="block bg-white p-5 rounded-2xl shadow-sm border flex items-center gap-4 transition-all duration-200"
        :class="actaSubida ? 'border-green-500 bg-green-50/30' : 'border-gray-100 hover:border-aso-primary hover:shadow-md'"
      >
        <div 
          class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0 transition-colors"
          :class="actaSubida ? 'bg-green-100' : 'bg-aso-primary/10'"
        >
          <FileCheck class="w-7 h-7" :class="actaSubida ? 'text-green-600' : 'text-aso-primary'" />
        </div>
        
        <div class="flex-1">
          <h4 class="font-bold text-gray-900">Acta de Escrutinio</h4>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ actaSubida ? 'Resultados capturados y enviados.' : 'Captura los resultados al cierre.' }}
          </p>
        </div>
        
        <div 
          class="p-3 rounded-xl transition-colors"
          :class="actaSubida ? 'bg-green-100 text-green-600' : 'bg-gray-50 text-gray-600 group-hover:bg-aso-primary/10 group-hover:text-aso-primary'"
        >
          <Check v-if="actaSubida" class="w-5 h-5" />
          <Camera v-else class="w-5 h-5" />
        </div>
      </router-link>

      <div v-if="actaSubida" class="mt-3 flex items-start gap-2 text-sm text-green-600 px-2 animate-in fade-in slide-in-from-top-2">
        <CheckCircle2 class="w-4 h-4 shrink-0 mt-0.5" />
        <p class="font-medium">El acta de esta mesa ya fue transmitida al sistema exitosamente.</p>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { MapPin, Users, FileCheck, Camera, Check, CheckCircle2 } from 'lucide-vue-next';

const authStore = useAuthStore();

// VARIABLE DE ESTADO: 
// Cambia esto a `true` para ver cómo se transforma la tarjeta indicando que ya se subió.
// TODO: En el futuro, debes conectar esta variable con una consulta a tu API 
// (ej. await axios.get('/api/jury/status')) para saber si el acta ya existe en BD.
const actaSubida = ref(false); 
</script>