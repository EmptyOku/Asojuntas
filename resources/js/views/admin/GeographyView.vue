<template>
  <div class="space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Geografía Electoral</h1>
        <p class="text-sm text-gray-500 mt-1">Gestiona los puestos de votación, comunas y mesas de Girardot.</p>
      </div>
      
      <button class="inline-flex items-center justify-center gap-2 bg-aso-primary hover:bg-aso-primary-dark text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm hover:shadow-md">
        <Plus class="w-4 h-4" />
        Nuevo Puesto
      </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden">
      
      <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gray-50/30">
        
        <div class="relative w-full sm:max-w-md">
          <Search class="w-4 h-4 absolute left-3.5 top-1/2 transform -translate-y-1/2 text-gray-400" />
          <input 
            type="text" 
            placeholder="Buscar por puesto, barrio o comuna..." 
            class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-aso-primary/20 focus:border-aso-primary transition-colors"
          >
        </div>

        <div class="flex items-center gap-2">
          <button class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
            <Filter class="w-4 h-4 text-gray-400" />
            Filtros
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50/80 text-gray-500 font-semibold border-b border-gray-100">
            <tr>
              <th class="px-6 py-4 whitespace-nowrap">Puesto de Votación</th>
              <th class="px-6 py-4 whitespace-nowrap">Ubicación</th>
              <th class="px-6 py-4 whitespace-nowrap text-center">Mesas Habilitadas</th>
              <th class="px-6 py-4 whitespace-nowrap text-center">Jurados</th>
              <th class="px-6 py-4 whitespace-nowrap">Estado</th>
              <th class="px-6 py-4 whitespace-nowrap text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            
            <tr class="hover:bg-gray-50/50 transition-colors group">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <MapPin class="w-4 h-4" />
                  </div>
                  <div>
                    <p class="font-semibold text-gray-900">Colegio Departamental</p>
                    <p class="text-xs text-gray-500">Puesto #01</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <p class="font-medium text-gray-700">Comuna 1</p>
                <p class="text-xs text-gray-500">Barrio Centro</p>
              </td>
              <td class="px-6 py-4 text-center">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-700 font-semibold text-xs">
                  12
                </span>
              </td>
              <td class="px-6 py-4 text-center text-gray-600 font-medium">
                48 / 48
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Activo
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Editar">
                    <Edit2 class="w-4 h-4" />
                  </button>
                  <button class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Eliminar">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
                <button class="lg:hidden p-1.5 text-gray-400 hover:text-gray-900 rounded-lg">
                  <MoreVertical class="w-4 h-4" />
                </button>
              </td>
            </tr>

            <tr class="hover:bg-gray-50/50 transition-colors group">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <MapPin class="w-4 h-4" />
                  </div>
                  <div>
                    <p class="font-semibold text-gray-900">Polideportivo Kennedy</p>
                    <p class="text-xs text-gray-500">Puesto #02</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <p class="font-medium text-gray-700">Comuna 3</p>
                <p class="text-xs text-gray-500">Barrio Kennedy</p>
              </td>
              <td class="px-6 py-4 text-center">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-700 font-semibold text-xs">
                  8
                </span>
              </td>
              <td class="px-6 py-4 text-center text-red-600 font-medium">
                30 / 32
                <p class="text-[10px] text-red-400 font-normal">Faltan 2</p>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-50 text-yellow-700 border border-yellow-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> Incompleto
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Editar">
                    <Edit2 class="w-4 h-4" />
                  </button>
                  <button class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Eliminar">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
                <button class="lg:hidden p-1.5 text-gray-400 hover:text-gray-900 rounded-lg">
                  <MoreVertical class="w-4 h-4" />
                </button>
              </td>
            </tr>

          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-sm">
        <p class="text-gray-500">Mostrando <span class="font-semibold text-gray-900">1</span> a <span class="font-semibold text-gray-900">2</span> de <span class="font-semibold text-gray-900">15</span> puestos</p>
        <div class="flex items-center gap-2">
          <button class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 disabled:opacity-50 transition-colors" disabled>Anterior</button>
          <button class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Siguiente</button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { Plus, Search, Filter, MapPin, Edit2, Trash2, MoreVertical } from 'lucide-vue-next';
</script>