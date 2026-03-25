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

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
      <div class="w-14 h-14 bg-orange-50 rounded-xl flex items-center justify-center shrink-0">
        <Users class="w-7 h-7 text-orange-500" />
      </div>
      <div class="flex-1">
        <h4 class="font-bold text-gray-900">1. Planilla de Planchas</h4>
        <p class="text-xs text-gray-500 mt-0.5">Captura la lista de candidatos al inicio.</p>
      </div>
      <router-link to="/jury/capture?doc=plancha" class="p-3 bg-gray-50 hover:bg-orange-50 text-gray-600 hover:text-orange-600 rounded-xl transition-colors">
        <Camera class="w-5 h-5" />
      </router-link>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
      <div class="w-14 h-14 bg-aso-primary/10 rounded-xl flex items-center justify-center shrink-0">
        <FileCheck class="w-7 h-7 text-aso-primary" />
      </div>
      <div class="flex-1">
        <h4 class="font-bold text-gray-900">2. Acta de Escrutinio</h4>
        <p class="text-xs text-gray-500 mt-0.5">Captura los resultados al cierre.</p>
      </div>
      <router-link to="/jury/capture?doc=escrutinio" class="p-3 bg-gray-50 hover:bg-aso-primary/10 text-gray-600 hover:text-aso-primary rounded-xl transition-colors">
        <Camera class="w-5 h-5" />
      </router-link>
    </div>

  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth';
import { MapPin, Users, FileCheck, Camera } from 'lucide-vue-next';

const authStore = useAuthStore();
</script>