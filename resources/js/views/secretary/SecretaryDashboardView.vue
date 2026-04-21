<template>
  <div class="space-y-6 flex-1 flex flex-col">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
      <h2 class="text-xl font-bold text-gray-900">Hola, {{ authStore.user?.name || 'Administrador Electoral' }}</h2>
      <p class="text-sm text-gray-500 mt-1">Gestión y digitalización de planchas de candidatos.</p>
      
      <div class="mt-6 p-4 bg-orange-50 border border-orange-100 rounded-xl flex items-start gap-3">
        <ShieldCheck class="w-5 h-5 text-orange-600 shrink-0 mt-0.5" />
        <div>
          <p class="text-xs font-bold text-orange-600 uppercase tracking-wide">Rol Activo</p>
          <p class="text-lg font-bold text-gray-900 mt-1">Comisión de Planchas</p>
          <p class="text-sm text-gray-600">Encargada de la recepción y validación previa al escrutinio.</p>
        </div>
      </div>
    </div>

    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider px-2">Acciones Principales</h3>

    <router-link 
      to="/secretary/capture?doc=plancha" 
      class="group block bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:border-orange-500 hover:shadow-md transition-all duration-200"
    >
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-orange-50 rounded-xl flex items-center justify-center shrink-0 group-hover:bg-orange-100 transition-colors">
          <ScanLine class="w-7 h-7 text-orange-600" />
        </div>
        <div class="flex-1">
          <h4 class="font-bold text-gray-900">1. Digitalizar Planchas</h4>
          <p class="text-xs text-gray-500 mt-0.5">Captura las 6 páginas de los candidatos inscritos.</p>
        </div>
        <div class="p-3 bg-gray-50 text-gray-600 group-hover:bg-orange-50 group-hover:text-orange-600 rounded-xl transition-colors">
          <Camera class="w-5 h-5" />
        </div>
      </div>
    </router-link>

    <router-link 
      to="/secretary/planchas" 
      class="group block bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:border-blue-500 hover:shadow-md transition-all duration-200"
    >
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-50 rounded-xl flex items-center justify-center shrink-0 group-hover:bg-blue-100 transition-colors">
          <FileSearch class="w-7 h-7 text-blue-600" />
        </div>
        <div class="flex-1">
          <h4 class="font-bold text-gray-900">2. Revisión de Planchas Transmitidas</h4>
          <p class="text-xs text-gray-500 mt-0.5">Consulta, edita o elimina actas de planchas subidas.</p>
        </div>
        <div class="p-3 bg-gray-50 text-gray-600 group-hover:bg-blue-50 group-hover:text-blue-600 rounded-xl transition-colors">
          <ArrowRight class="w-5 h-5" />
        </div>
      </div>
    </router-link>

  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth';
import { ShieldCheck, ScanLine, Camera, FileSearch, ArrowRight } from 'lucide-vue-next';

const authStore = useAuthStore();
</script>