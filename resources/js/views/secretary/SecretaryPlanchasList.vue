<template>
  <div class="space-y-4 max-w-7xl mx-auto pb-10">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-xl shadow-sm border border-gray-200">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Gestión de Planchas</h2>
        <p class="text-xs text-gray-500 mt-0.5">Administra y audita las planchas por barrio.</p>
      </div>
      
      <div class="relative w-full md:w-64">
        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
          <Search class="w-4 h-4 text-gray-400" />
        </div>
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Buscar barrio..." 
          class="block w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-aso-primary focus:border-aso-primary transition-colors"
        >
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      
      <div 
        v-for="barrio in barriosFiltrados" 
        :key="barrio.id"
        class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col transition-all duration-200 hover:border-gray-300"
      >
        <div 
          @click="toggleBarrio(barrio.id)"
          class="p-4 cursor-pointer group select-none"
        >
          <div class="flex justify-between items-start mb-2">
            <h3 class="text-sm font-bold text-gray-800 group-hover:text-aso-primary transition-colors">
              {{ barrio.nombre }}
            </h3>
            <ChevronDown 
              class="w-4 h-4 text-gray-400 transition-transform duration-300" 
              :class="{ 'rotate-180': expandedBarrio === barrio.id }" 
            />
          </div>
          
          <div class="flex items-center gap-3 mt-2">
            <div class="flex items-center gap-1 text-[11px] text-gray-500">
              <Files class="w-3 h-3" />
              <span>{{ barrio.planchas.length }} planchas</span>
            </div>
            <div class="flex items-center gap-1 text-[11px] text-gray-500">
              <span class="w-1.5 h-1.5 rounded-full" :class="barrio.colorPunto"></span>
              <span>{{ barrio.estadoTexto }}</span>
            </div>
          </div>
        </div>

        <transition name="slide-fade">
          <div v-show="expandedBarrio === barrio.id" class="border-t border-gray-100 bg-gray-50/50">
            <div class="p-2 space-y-1">
              
              <div 
                v-for="plancha in barrio.planchas" 
                :key="plancha.id"
                class="flex items-center justify-between p-2 bg-white rounded-lg border border-gray-100 hover:border-gray-200 transition-colors group/item"
              >
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-gray-400 w-5 text-center">{{ plancha.numero }}</span>
                  <div>
                    <p class="text-xs font-semibold text-gray-700">{{ plancha.nombre }}</p>
                    <p class="text-[10px] text-gray-400">{{ plancha.candidatos }} candidatos</p>
                  </div>
                </div>

                <div class="flex items-center gap-0.5 opacity-100 lg:opacity-0 lg:group-hover/item:opacity-100 transition-opacity">
  
                    <button 
                        @click="irADetalle(plancha.id)"
                        title="Ver Detalle" 
                        class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded transition-colors"
                    >
                        <Eye class="w-3.5 h-3.5" />
                    </button>
                    
                    <button 
                        @click="irADetalle(plancha.id, true)"
                        title="Editar Plancha" 
                        class="p-1.5 text-gray-400 hover:text-aso-primary hover:bg-green-50 rounded transition-colors"
                    >
                        <Edit2 class="w-3.5 h-3.5" />
                    </button>
                    
                    <button title="Eliminar" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors">
                        <Trash2 class="w-3.5 h-3.5" />
                    </button>
                    </div>
              </div>
              
              <div v-if="barrio.planchas.length === 0" class="text-center py-3 text-xs text-gray-400 italic">
                Sin registros.
              </div>

            </div>
          </div>
        </transition>

      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router'; // NUEVO: Importar el enrutador
import { Search, Files, ChevronDown, Eye, Edit2, Trash2 } from 'lucide-vue-next';

const router = useRouter(); // NUEVO: Inicializar el enrutador

// NUEVO: Función para navegar a la vista de detalle
const irADetalle = (idPlancha, modoEdicion = false) => {
  router.push({
    name: 'secretary-plancha-detail', // El nombre que le dimos en router/index.js
    params: { id: idPlancha },
    query: { edit: modoEdicion } // Envía ?edit=true si le dio al botón editar
  });
};

const searchQuery = ref('');
const expandedBarrio = ref(null);

const toggleBarrio = (id) => {
  expandedBarrio.value = expandedBarrio.value === id ? null : id;
};

// Datos simulados con estructura neutral
const barriosDb = ref([
  {
    id: 1,
    nombre: 'Barrio Centro',
    colorPunto: 'bg-green-500', 
    estadoTexto: 'Completo',
    planchas: [
      { id: 101, numero: 'P1', nombre: 'Renovación', candidatos: 14 },
      { id: 102, numero: 'P2', nombre: 'Unidos', candidatos: 14 }
    ]
  },
  {
    id: 2,
    nombre: 'Bello Horizonte',
    colorPunto: 'bg-yellow-500', 
    estadoTexto: 'Revisión',
    planchas: [
      { id: 103, numero: 'P1', nombre: 'Transparencia', candidatos: 12 },
      { id: 104, numero: 'P2', nombre: 'Progreso', candidatos: 14 }
    ]
  },
  {
    id: 3,
    nombre: 'Ciudad Jardín',
    colorPunto: 'bg-gray-300', 
    estadoTexto: 'Sin datos',
    planchas: []
  }
]);

const barriosFiltrados = computed(() => {
  if (!searchQuery.value) return barriosDb.value;
  const term = searchQuery.value.toLowerCase();
  return barriosDb.value.filter(b => b.nombre.toLowerCase().includes(term));
});
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.2s ease-in-out;
  max-height: 400px; 
  opacity: 1;
  overflow: hidden;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>