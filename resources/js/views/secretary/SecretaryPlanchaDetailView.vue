<template>
  <div class="max-w-7xl mx-auto space-y-6 pb-10">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold uppercase tracking-wider rounded-lg border border-green-200">
            {{ planchaData.numero }}
          </span>
          <span class="text-sm font-medium text-gray-500">Junta de Acción Comunal</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-900">{{ planchaData.nombrePlancha }}</h2>
        <p class="text-sm text-gray-500 mt-1">Barrio: <strong class="text-gray-700">{{ planchaData.nombreBarrio }}</strong></p>
      </div>
      
      <div class="flex items-center gap-3">
        <button 
          v-if="!isEditing" 
          @click="isEditing = true"
          class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-aso-primary hover:text-aso-primary text-gray-700 text-sm font-bold rounded-xl shadow-sm transition-colors"
        >
          <Edit2 class="w-4 h-4" /> Habilitar Edición
        </button>
        
        <template v-else>
          <button 
            @click="cancelEdit"
            class="px-4 py-2.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 text-sm font-bold rounded-xl transition-colors"
          >
            Cancelar
          </button>
          <button 
            @click="saveChanges"
            class="flex items-center gap-2 px-4 py-2.5 bg-aso-primary hover:bg-green-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors"
          >
            <Save class="w-4 h-4" /> Guardar Plancha
          </button>
        </template>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-1.5 flex overflow-x-auto">
      <button 
        v-for="bloque in bloques" 
        :key="bloque.id"
        @click="activeBlock = bloque.id"
        class="flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold rounded-lg transition-all whitespace-nowrap min-w-[200px]"
        :class="activeBlock === bloque.id ? 'bg-green-50 text-aso-primary shadow-sm ring-1 ring-green-100' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
      >
        <component :is="bloque.icon" class="w-4 h-4 shrink-0" />
        <span>{{ bloque.nombre }}</span>
      </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8">
      
      <div class="border-b border-gray-100 pb-4 mb-6 flex justify-between items-end">
        <div>
          <h3 class="text-xl font-bold text-gray-900">{{ bloques.find(b => b.id === activeBlock)?.nombre }}</h3>
          <p class="text-sm text-gray-500 mt-1">Verifica la información extraída con el documento físico original.</p>
        </div>
        <div v-if="isEditing" class="hidden md:flex items-center gap-2 text-sm text-orange-600 bg-orange-50 px-3 py-1.5 rounded-lg border border-orange-100">
          <Edit2 class="w-4 h-4" /> Modo Edición Activo
        </div>
      </div>

      <div v-if="activeBlock === 'directiva'" class="space-y-5">
        <CandidateCard cargo="Presidente (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.presidente.nombre"
          v-model:identificacion="planchaData.bloque1.presidente.identificacion"
          v-model:celular="planchaData.bloque1.presidente.celular"
          v-model:correo="planchaData.bloque1.presidente.correo" />
          
        <CandidateCard cargo="Vicepresidente (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.vicepresidente.nombre"
          v-model:identificacion="planchaData.bloque1.vicepresidente.identificacion"
          v-model:celular="planchaData.bloque1.vicepresidente.celular"
          v-model:correo="planchaData.bloque1.vicepresidente.correo" />
          
        <CandidateCard cargo="Tesorero (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.tesorero.nombre"
          v-model:identificacion="planchaData.bloque1.tesorero.identificacion"
          v-model:celular="planchaData.bloque1.tesorero.celular"
          v-model:correo="planchaData.bloque1.tesorero.correo" />
          
        <CandidateCard cargo="Secretario (a)" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque1.secretario.nombre"
          v-model:identificacion="planchaData.bloque1.secretario.identificacion"
          v-model:celular="planchaData.bloque1.secretario.celular"
          v-model:correo="planchaData.bloque1.secretario.correo" />
      </div>

      <div v-if="activeBlock === 'delegados'" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <CandidateCard cargo="Delegado (a) 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado1.nombre" v-model:identificacion="planchaData.bloque2.delegado1.identificacion" />
        <CandidateCard cargo="Suplente Delegado 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente1.nombre" v-model:identificacion="planchaData.bloque2.suplente1.identificacion" />
        
        <CandidateCard cargo="Delegado (a) 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado2.nombre" v-model:identificacion="planchaData.bloque2.delegado2.identificacion" />
        <CandidateCard cargo="Suplente Delegado 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente2.nombre" v-model:identificacion="planchaData.bloque2.suplente2.identificacion" />
        
        <CandidateCard cargo="Delegado (a) 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.delegado3.nombre" v-model:identificacion="planchaData.bloque2.delegado3.identificacion" />
        <CandidateCard cargo="Suplente Delegado 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque2.suplente3.nombre" v-model:identificacion="planchaData.bloque2.suplente3.identificacion" />
      </div>

      <div v-if="activeBlock === 'fiscal'" class="space-y-5">
        <CandidateCard cargo="Fiscal" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque3.fiscal.nombre"
          v-model:identificacion="planchaData.bloque3.fiscal.identificacion"
          v-model:celular="planchaData.bloque3.fiscal.celular"
          v-model:correo="planchaData.bloque3.fiscal.correo" />
          
        <CandidateCard cargo="Suplente Fiscal" :is-editing="isEditing"
          v-model:nombre="planchaData.bloque3.suplente.nombre"
          v-model:identificacion="planchaData.bloque3.suplente.identificacion"
          v-model:celular="planchaData.bloque3.suplente.celular"
          v-model:correo="planchaData.bloque3.suplente.correo" />
      </div>

      <div v-if="activeBlock === 'convivencia'" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <CandidateCard cargo="Conciliador (a) 1" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador1.nombre" v-model:identificacion="planchaData.bloque4.conciliador1.identificacion" />
        <CandidateCard cargo="Conciliador (a) 2" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador2.nombre" v-model:identificacion="planchaData.bloque4.conciliador2.identificacion" />
        <CandidateCard cargo="Conciliador (a) 3" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.conciliador3.nombre" v-model:identificacion="planchaData.bloque4.conciliador3.identificacion" />
        <CandidateCard cargo="Coord. Comisión Empresarial" :is-editing="isEditing" v-model:nombre="planchaData.bloque4.empresarial.nombre" v-model:identificacion="planchaData.bloque4.empresarial.identificacion" />
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { Edit2, Save, Users, UserPlus, Scale, Handshake } from 'lucide-vue-next';
import CandidateCard from '@/components/secretary/CandidateCard.vue';

const route = useRoute();
const isEditing = ref(false);
const activeBlock = ref('directiva');

const bloques = [
  { id: 'directiva', nombre: '1. Directiva', icon: Users },
  { id: 'delegados', nombre: '2. Delegados', icon: UserPlus },
  { id: 'fiscal', nombre: '3. Fiscal', icon: Scale },
  { id: 'convivencia', nombre: '4. Convivencia', icon: Handshake }
];

// Estructura Reactiva completa de la Plancha
const planchaData = reactive({
  numero: 'Plancha No. 1',
  nombreBarrio: 'Bello Horizonte',
  nombrePlancha: 'Transparencia Comunal',
  bloque1: {
    presidente: { nombre: 'María González', identificacion: '11223344', celular: '3101234567', correo: 'maria@ejemplo.com' },
    vicepresidente: { nombre: '', identificacion: '', celular: '', correo: '' },
    tesorero: { nombre: '', identificacion: '', celular: '', correo: '' },
    secretario: { nombre: '', identificacion: '', celular: '', correo: '' },
  },
  bloque2: {
    delegado1: { nombre: '', identificacion: '' }, suplente1: { nombre: '', identificacion: '' },
    delegado2: { nombre: '', identificacion: '' }, suplente2: { nombre: '', identificacion: '' },
    delegado3: { nombre: '', identificacion: '' }, suplente3: { nombre: '', identificacion: '' },
  },
  bloque3: {
    fiscal: { nombre: '', identificacion: '', celular: '', correo: '' },
    suplente: { nombre: '', identificacion: '', celular: '', correo: '' },
  },
  bloque4: {
    conciliador1: { nombre: '', identificacion: '' },
    conciliador2: { nombre: '', identificacion: '' },
    conciliador3: { nombre: '', identificacion: '' },
    empresarial: { nombre: '', identificacion: '' }
  }
});

// Capturar el parámetro ?edit=true de la URL cuando venimos desde la vista de lista
onMounted(() => {
  if (route.query.edit === 'true') {
    isEditing.value = true;
  }
});

const cancelEdit = () => {
  isEditing.value = false;
};

const saveChanges = () => {
  isEditing.value = false;
  // Lógica Axios aquí para enviar `planchaData` al backend
};
</script>