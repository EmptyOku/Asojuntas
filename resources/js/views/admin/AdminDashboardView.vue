<template>
  <div class="space-y-8">
    
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Dashboard General</h1>
      <p class="text-gray-500 mt-1">Resumen en tiempo real del escrutinio electoral.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      
      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
          <FileText class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Actas Escrutadas</p>
          <h3 class="text-3xl font-bold text-gray-900">45 <span class="text-sm font-medium text-gray-400">/ 120</span></h3>
          <p class="text-xs text-blue-600 font-medium mt-1">37.5% del total</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-aso-primary/10 text-aso-primary rounded-xl">
          <CheckCircle class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Votos Procesados</p>
          <h3 class="text-3xl font-bold text-gray-900">12,450</h3>
          <p class="text-xs text-aso-primary font-medium mt-1">IA validando</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
          <Users class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Jurados Activos</p>
          <h3 class="text-3xl font-bold text-gray-900">118</h3>
          <p class="text-xs text-purple-600 font-medium mt-1">En mesas de votación</p>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex items-start gap-4 transition-transform hover:-translate-y-1 duration-300">
        <div class="p-3 bg-red-50 text-red-600 rounded-xl">
          <AlertTriangle class="w-6 h-6" />
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500 mb-1">Requieren Revisión</p>
          <h3 class="text-3xl font-bold text-gray-900">3</h3>
          <p class="text-xs text-red-600 font-medium mt-1">Auditoría manual</p>
        </div>
      </div>

    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden">
      <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Últimas Actas Recibidas</h3>
        <button class="text-sm font-medium text-aso-primary hover:text-aso-primary-dark">Ver todas</button>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50/50 text-gray-500 font-medium border-b border-gray-100">
            <tr>
              <th class="px-6 py-4 font-medium">Mesa</th>
              <th class="px-6 py-4 font-medium">Jurado Remitente</th>
              <th class="px-6 py-4 font-medium">Hora de Recepción</th>
              <th class="px-6 py-4 font-medium">Estado IA</th>
              <th class="px-6 py-4 font-medium text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-4 font-medium text-gray-900">Mesa 01 - Comuna 1</td>
              <td class="px-6 py-4">Carlos Pérez</td>
              <td class="px-6 py-4 text-gray-500">Hace 2 min</td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Procesada
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button class="text-gray-400 hover:text-aso-primary transition-colors">Ver detalle</button>
              </td>
            </tr>
            <tr class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-4 font-medium text-gray-900">Mesa 14 - Comuna 3</td>
              <td class="px-6 py-4">Ana Martínez</td>
              <td class="px-6 py-4 text-gray-500">Hace 5 min</td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Duda en lectura
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button class="text-aso-primary font-medium hover:text-aso-primary-dark transition-colors">Revisar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
// Importamos los íconos de Lucide para las tarjetas
import { FileText, CheckCircle, Users, AlertTriangle } from 'lucide-vue-next';
</script>