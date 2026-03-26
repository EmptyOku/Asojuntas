<template>
  <div class="flex flex-col min-h-screen bg-aso-bg font-sans pb-24 lg:pb-0">
    
    <!-- 1. CARRUSEL DE IMAGEN (Sticky) -->
    <div class="w-full h-[40vh] bg-[#1a1c23] relative flex flex-col sticky top-0 z-20 shadow-lg lg:h-[45vh]">
      <div class="absolute top-0 inset-x-0 p-4 flex justify-between items-center bg-gradient-to-b from-black/80 to-transparent z-30">
        <h3 class="text-white font-bold text-sm flex items-center gap-2">
          <ImageIcon class="w-4 h-4" /> Evidencia
        </h3>
        <div class="flex items-center gap-3 bg-black/50 rounded-full px-3 py-1 border border-white/10">
          <button @click="prevPage" :disabled="currentPage === 0" class="text-white disabled:opacity-30"><ChevronLeft class="w-4 h-4" /></button>
          <span class="text-white text-xs font-bold">Pág {{ currentPage + 1 }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages - 1" class="text-white disabled:opacity-30"><ChevronRight class="w-4 h-4" /></button>
        </div>
      </div>
      <div class="flex-1 overflow-hidden flex items-center justify-center p-4 pt-12">
        <img v-if="currentImage" :src="currentImage.url" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl transition-all duration-300">
      </div>
    </div>

    <!-- 2. SECCIÓN DE DATOS DINÁMICOS POR PÁGINA -->
    <div class="w-full flex-1 overflow-y-auto p-4 sm:p-6 lg:max-w-3xl lg:mx-auto">
      
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="bg-gray-50 px-5 py-4 border-b border-gray-100">
          <h2 class="text-lg font-bold text-gray-900">{{ isPlancha ? 'Validación de Planchas' : 'Validación de Acta' }}</h2>
          <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mt-1">Página Actual: {{ currentPage + 1 }}</p>
        </div>
        
        <div class="p-5 space-y-8">
          
          <!-- FLUJO A: PLANILLAS DE CANDIDATOS -->
          <template v-if="isPlancha">
            <div v-for="(bloque, bIdx) in currentDataPage?.bloques" :key="bIdx" class="space-y-6">
              <h3 class="bg-aso-primary/5 text-aso-primary text-xs font-black p-2 rounded-md border-l-4 border-aso-primary uppercase">{{ bloque.titulo }}</h3>
              
              <div v-for="(cargo, cIdx) in bloque.cargos" :key="cIdx" class="space-y-3 bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800">{{ cargo.puesto }}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="col-span-1 sm:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Nombre</label>
                    <input type="text" :value="cargo.nombre" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700">
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">No. Identificación</label>
                    <input type="text" :value="cargo.identificacion" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-900">
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Celular</label>
                    <input type="text" :value="cargo.celular" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700">
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Correo Electrónico</label>
                    <input type="email" :value="cargo.correo" readonly class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 truncate">
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- FLUJO B: ACTAS DE ESCRUTINIO -->
          <template v-else>
            <div v-for="(bloque, bIdx) in currentDataPage?.bloques" :key="bIdx" class="space-y-4">
              <h3 class="bg-aso-primary/5 text-aso-primary text-xs font-black p-2 rounded-md border-l-4 border-aso-primary uppercase">{{ bloque.titulo }}</h3>
              
              <div class="grid grid-cols-2 gap-3">
                <div v-for="(valor, label) in bloque.votos" :key="label" class="p-3 bg-white border border-gray-100 rounded-xl shadow-sm">
                  <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">{{ label }}</label>
                  <input type="text" :value="valor" readonly class="w-full text-lg font-black text-aso-primary-dark focus:outline-none">
                </div>
              </div>
            </div>
          </template>

        </div>
      </div>

      <!-- OBSERVACIONES -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-24 lg:mb-6">
        <label class="block text-sm font-bold text-gray-900 mb-2">Observaciones de la Página {{ currentPage + 1 }}</label>
        <textarea v-model="observacionesPorPagina[currentPage]" rows="3" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-aso-primary transition-all" placeholder="Reporta cualquier inconsistencia visual..."></textarea>
      </div>

      <!-- BOTONES -->
      <div class="fixed bottom-0 inset-x-0 p-4 bg-white/80 backdrop-blur-md border-t border-gray-100 grid grid-cols-2 gap-3 z-30 lg:relative lg:bg-transparent lg:border-0 lg:p-0">
        <button @click="rejectAndReturn" class="py-4 bg-white border border-gray-200 text-red-600 font-bold rounded-2xl text-sm">Rechazar</button>
        <button @click="confirmAndSend" class="py-4 bg-aso-primary text-white font-bold rounded-2xl shadow-md flex items-center justify-center gap-2 text-sm">
          <CheckCircle class="w-4 h-4" /> Confirmar Datos
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight, Image as ImageIcon, CheckCircle, AlertCircle, MessageSquareWarning } from 'lucide-vue-next';
import { useDocumentStore } from '@/stores/document';

const router = useRouter();
const docStore = useDocumentStore();

const currentPage = ref(0);
const totalPages = computed(() => docStore.capturedImages.length || 1);
const observacionesPorPagina = ref({});
const isPlancha = computed(() => docStore.documentType === 'plancha');
const currentImage = computed(() => docStore.capturedImages[currentPage.value]);

// ESTRUCTURA DE DATOS COMPLETA (6 PÁGINAS)
const mockPlanchasData = [
  { // Pág 1
    bloques: [{ titulo: 'Bloque 1 - Directiva', cargos: [
      { puesto: 'Presidente (a)', nombre: 'JUAN PÉREZ', identificacion: '1070...', celular: '310...', correo: 'juan@mail.com' },
      { puesto: 'Vicepresidente (a)', nombre: 'MARÍA SILVA', identificacion: '100...', celular: '320...', correo: 'maria@mail.com' },
      { puesto: 'Tesorero (a)', nombre: 'CARLOS RUIZ', identificacion: '80...', celular: '315...', correo: 'carlos@mail.com' }
    ]}]
  },
  { // Pág 2
    bloques: [
      { titulo: 'Bloque 1 - Continuación', cargos: [{ puesto: 'Secretario (a)', nombre: 'ANA G.', identificacion: '52...', celular: '300...', correo: 'ana@mail.com' }]},
      { titulo: 'Bloque No. 2 – Delegados Asojuntas', cargos: [
        { puesto: 'Suplente Presidente (a)', nombre: 'PEDRO L.', identificacion: '...', celular: '...', correo: 'pedro@mail.com' },
        { puesto: 'Delegado (a) 1', nombre: 'LUISA M.', identificacion: '...', celular: '...', correo: 'luisa@mail.com' },
        { puesto: 'Suplente Delegado (a) 1', nombre: 'JORGE D.', identificacion: '...', celular: '...', correo: 'jorge@mail.com' }
      ]}
    ]
  },
  { // Pág 3
    bloques: [{ titulo: 'Bloque No. 2 – Continuación Delegados', cargos: [
      { puesto: 'Delegado (a) Asojuntas 2', nombre: 'MARTA R.', identificacion: '...', celular: '...', correo: 'marta@mail.com' },
      { puesto: 'Suplente Delegado (a) 2', nombre: 'OSCAR N.', identificacion: '...', celular: '...', correo: 'oscar@mail.com' },
      { puesto: 'Delegado (a) Asojuntas 3', nombre: 'ELENA T.', identificacion: '...', celular: '...', correo: 'elena@mail.com' },
      { puesto: 'Suplente Delegado (a) 3', nombre: 'RAÚL P.', identificacion: '...', celular: '...', correo: 'raul@mail.com' }
    ]}]
  },
  { // Pág 4
    bloques: [
      { titulo: 'BLOQUE No. 3 – FISCAL', cargos: [
        { puesto: 'FISCAL', nombre: 'DIEGO C.', identificacion: '...', celular: '...', correo: 'diego@mail.com' },
        { puesto: 'SUPLENTE FISCAL', nombre: 'NORA V.', identificacion: '...', celular: '...', correo: 'nora@mail.com' }
      ]},
      { titulo: 'BLOQUE No. 4 – CONVIVENCIA', cargos: [
        { puesto: 'CONCILIADOR (A) 1', nombre: 'FABIÁN R.', identificacion: '...', celular: '...', correo: 'fabian@mail.com' },
        { puesto: 'CONCILIADOR (A) 2', nombre: 'SARA J.', identificacion: '...', celular: '...', correo: 'sara@mail.com' }
      ]}
    ]
  },
  { // Pág 5
    bloques: [
      { titulo: 'BLOQUE No. 4 – Continuación', cargos: [
        { puesto: 'CONCILIADOR (A) 3', nombre: 'BEATRIZ S.', identificacion: '...', celular: '...', correo: 'beatriz@mail.com' }
      ]},
      { titulo: 'COMISIÓN EMPRESARIAL', cargos: [
        { puesto: 'COORDINADOR', nombre: 'RICARDO D.', identificacion: '...', celular: '...', correo: 'ricardo@mail.com' }
      ]}
    ]
  }
];

// Mantenemos las actas igual (3 páginas)
const mockActasData = [
  { // Pág 1
    bloques: [{ titulo: 'Bloque N.º 1 - Directiva', votos: {'Votos totales': 60, 'Plancha 1': 60, 'Plancha 2': 45, 'Plancha 3': 0, 'Blancos': 15, 'Nulos': 2, 'No Marcados': 0, 'Válidos': 120 } }]
  },
  { // Pág 2
    bloques: [
      { titulo: 'Bloque N.º 2 - Delegados', votos: { 'Votos totales': 60, 'Plancha 1': 40, 'Plancha 2': 56, 'Plancha 3': 0, 'Blancos': 6, 'Nulos': 3, 'No Marcados': 3, 'Válidos': 102 } },
      { titulo: 'Bloque N.º 3 - Fiscal', votos: {'Votos totales': 60, 'Plancha 1': 50, 'Plancha 2': 30, 'Plancha 3': 10, 'Blancos': 5, 'Nulos': 1, 'No Marcados': 0, 'Válidos': 95 } }
    ]
  },
  { // Pág 3
    bloques: [{ titulo: 'Bloque N.º 4 - Conciliación', votos: { 'Votos totales': 60,'Plancha 1': 65, 'Plancha 2': 40, 'Plancha 3': 0, 'Blancos': 6, 'Nulos': 3, 'No Marcados': 3, 'Válidos': 111 } }]
  }
];

const currentDataPage = computed(() => {
  const data = isPlancha.value ? mockPlanchasData : mockActasData;
  return data[currentPage.value] || { bloques: [] };
});

const prevPage = () => { if (currentPage.value > 0) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value - 1) currentPage.value++; };

// CORRECCIÓN DE REDIRECCIÓN AL RECHAZAR
const rejectAndReturn = () => {
  const typeBeforeClear = docStore.documentType; // Guardamos el tipo antes de borrar
  docStore.clearStore();
  router.push({ 
    name: 'jury-capture', 
    query: { doc: typeBeforeClear } 
  });
};

const confirmAndSend = () => {
  alert('¡Proceso completado exitosamente!');
  router.push('/jury/dashboard');
};

onMounted(() => { if (docStore.capturedImages.length === 0) router.push('/jury/dashboard'); });
</script>