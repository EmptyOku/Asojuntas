<template>
  <div class="space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Auditoría de Actas</h1>
        <p class="text-sm text-gray-500 mt-1">Bandeja de entrada de resultados transmitidos por los Tribunales de Garantías.</p>
      </div>
      
      <div class="flex items-center gap-3">
        <div class="px-4 py-2 bg-green-50 border border-green-100 rounded-xl">
          <p class="text-[10px] font-bold text-green-600 uppercase">Procesadas (IA OK)</p>
          <p class="text-lg font-bold text-green-700">85</p>
        </div>
        <div class="px-4 py-2 bg-orange-50 border border-orange-100 rounded-xl">
          <p class="text-[10px] font-bold text-orange-600 uppercase">Requieren Revisión</p>
          <p class="text-lg font-bold text-orange-700">12</p>
        </div>
      </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden">
      
      <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gray-50/30">
        <div class="relative w-full sm:max-w-md">
          <Search class="w-4 h-4 absolute left-3.5 top-1/2 transform -translate-y-1/2 text-gray-400" />
          <input type="text" placeholder="Buscar por mesa, comuna o jurado..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary">
        </div>
        <div class="flex gap-2">
          <select class="border border-gray-200 rounded-lg text-sm text-gray-600 py-2 px-3 focus:outline-none">
            <option>Todas las actas</option>
            <option>Requieren revisión</option>
            <option>Consolidadas</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50/80 text-gray-500 font-semibold border-b border-gray-100">
            <tr>
              <th class="px-6 py-4">Mesa / Ubicación</th>
              <th class="px-6 py-4">Jurado Transmisor</th>
              <th class="px-6 py-4 text-center">Votos Válidos</th>
              <th class="px-6 py-4">Estado Textract (IA)</th>
              <th class="px-6 py-4 text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            
            <tr class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-4">
                <p class="font-bold text-gray-900">Mesa 14</p>
                <p class="text-xs text-gray-500">Colegio Departamental - Comuna 1</p>
              </td>
              <td class="px-6 py-4">Ana Martínez<br><span class="text-xs text-gray-400">Hace 10 min</span></td>
              <td class="px-6 py-4 text-center font-bold text-gray-700">102</td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span> Dudas en Bloque 1
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <router-link to="/admin/audit/14" class="inline-block bg-white border border-gray-200 hover:border-aso-primary hover:text-aso-primary text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors shadow-sm">
                  Corroborar
                </router-link>
              </td>
            </tr>

            <tr class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-4">
                <p class="font-bold text-gray-900">Mesa 03</p>
                <p class="text-xs text-gray-500">Polideportivo Kennedy - Comuna 3</p>
              </td>
              <td class="px-6 py-4">Carlos Pérez<br><span class="text-xs text-gray-400">Hace 25 min</span></td>
              <td class="px-6 py-4 text-center font-bold text-gray-700">111</td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> 98% Confianza
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button class="bg-gray-50 border border-gray-200 hover:bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">
                  Ver Cuociente
                </button>
              </td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { Search } from 'lucide-vue-next';
</script>