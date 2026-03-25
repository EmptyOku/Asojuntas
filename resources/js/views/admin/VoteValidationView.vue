<template>
  <div class="space-y-4 lg:space-y-6 h-full flex flex-col">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-start sm:items-center gap-4">
        <router-link to="/admin/audit" class="p-2 text-gray-400 hover:text-gray-900 hover:bg-white rounded-xl transition-colors shadow-sm border border-transparent hover:border-gray-200 shrink-0">
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900 tracking-tight">Auditoría Acta #{{ $route.params.id || '14' }}</h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] lg:text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-100">
              <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span> Revisión Numérica
            </span>
          </div>
          <p class="text-xs lg:text-sm text-gray-500 mt-1">Formato #4 Elección de Dignatarios • JAC Centro</p>
        </div>
      </div>
      
      <div class="flex items-center bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
        <span class="text-xs lg:text-sm font-medium text-gray-500">Confianza IA: <span class="text-orange-600 font-bold">88%</span></span>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 flex-1 lg:min-h-[600px] lg:max-h-[850px]">
      
      <div class="w-full lg:w-1/2 h-[40vh] lg:h-auto bg-gray-900 rounded-2xl overflow-hidden flex flex-col relative shadow-[0_4px_20px_rgba(0,0,0,0.05)] shrink-0">
        
        <div class="h-12 bg-gray-800/90 backdrop-blur border-b border-gray-700 flex items-center justify-center gap-4 px-4 absolute top-0 w-full z-10">
          <button class="p-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"><ZoomOut class="w-4 h-4" /></button>
          <span class="text-xs text-gray-400 font-medium">Pág 2/8</span>
          <button class="p-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"><ZoomIn class="w-4 h-4" /></button>
        </div>

        <div class="flex-1 bg-gray-900 p-4 lg:p-8 flex items-center justify-center overflow-auto mt-12 relative">
          <div class="w-full max-w-md aspect-[3/4] bg-white rounded shadow-2xl p-4 lg:p-6 relative flex flex-col items-center justify-center min-h-[400px]">
            <FileText class="w-10 h-10 text-gray-300 mb-2" />
            <p class="text-gray-400 font-medium text-xs text-center">Aquí renderiza la foto de las Tablas de Escrutinio enviada por el jurado.</p>
            
            <div class="absolute top-[40%] right-[30%] w-16 h-8 border-2 border-orange-400 bg-orange-400/20 rounded animate-pulse"></div>
          </div>
        </div>
      </div>

      <div class="w-full lg:w-1/2 bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex flex-col h-[50vh] lg:h-full overflow-hidden">
        
        <div class="p-4 lg:p-5 border-b border-gray-100 bg-gray-50/50 shrink-0">
          <h2 class="text-base lg:text-lg font-bold text-gray-900">Extracción de Escrutinio</h2>
          <p class="text-[10px] lg:text-xs text-gray-500 mt-1">Verifica los valores contra la imagen. El cuociente se calculará en el servidor.</p>
        </div>

        <div class="p-4 lg:p-5 flex-1 overflow-y-auto space-y-6 lg:space-y-8 custom-scrollbar">
          
          <section>
            <h3 class="text-[10px] lg:text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Datos de Participación</h3>
            <div class="grid grid-cols-2 gap-3 lg:gap-4">
              <div>
                <label class="block text-[10px] lg:text-xs font-semibold text-gray-600 mb-1">N° Afiliados</label>
                <div class="relative">
                  <input type="number" v-model="form.afiliados" class="w-full pl-3 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-bold text-gray-900 focus:ring-2 focus:ring-aso-primary outline-none">
                  <CheckCircle2 class="w-4 h-4 absolute right-2.5 top-1/2 -translate-y-1/2 text-green-500" />
                </div>
              </div>
              <div>
                <label class="block text-[10px] lg:text-xs font-semibold text-gray-600 mb-1">N° Votantes</label>
                <div class="relative">
                  <input type="number" v-model="form.votantes" class="w-full pl-3 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-bold text-gray-900 focus:ring-2 focus:ring-aso-primary outline-none">
                  <CheckCircle2 class="w-4 h-4 absolute right-2.5 top-1/2 -translate-y-1/2 text-green-500" />
                </div>
              </div>
            </div>
          </section>

          <div class="p-4 lg:p-5 flex-1 overflow-y-auto space-y-6 lg:space-y-8 custom-scrollbar">

          <section>
            <h3 class="text-[10px] lg:text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Users class="w-4 h-4 text-gray-400" /> Bloque N.º 1 - Directiva (Cociente)
            </h3>
            
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div class="col-span-2 sm:col-span-4 border-b border-gray-200 pb-2 mb-1">
                <label class="block text-[9px] text-gray-500 uppercase">Total Votos</label>
                <input type="number" v-model="form.bloque1.total" class="w-full bg-transparent text-lg font-bold text-gray-900 outline-none">
              </div>

              <div><label class="block text-[9px] text-gray-500">Plancha 1</label><input type="number" v-model="form.bloque1.plancha1" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">Plancha 2</label><input type="number" v-model="form.bloque1.plancha2" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">Plancha 3</label><input type="number" v-model="form.bloque1.plancha3" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              
              <div>
                <label class="block text-[9px] text-gray-500 flex justify-between">V. Blancos <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span></label>
                <input type="number" v-model="form.bloque1.blancos" class="w-full px-2 py-1.5 bg-orange-50 border-2 border-orange-400 rounded text-sm font-bold text-orange-900 outline-none">
              </div>
              <div><label class="block text-[9px] text-gray-500">V. Nulos</label><input type="number" v-model="form.bloque1.nulos" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">No Marcados</label><input type="number" v-model="form.bloque1.noMarcados" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              
              <div class="col-span-2">
                <label class="block text-[9px] text-gray-500">Votos Válidos (Calculado)</label>
                <input type="number" :value="calcularValidosBloque1" class="w-full px-2 py-1.5 bg-gray-200 border border-gray-300 rounded text-sm font-bold text-gray-700" readonly>
              </div>
            </div>
          </section>

          <section>
             <h3 class="text-[10px] lg:text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Users class="w-4 h-4 text-gray-400" /> Bloque N.º 2 - Delegados (Cociente)
            </h3>
            <div class="bg-green-50/50 border border-green-100 rounded-xl p-4 flex justify-center text-xs text-green-600 border-dashed">
              <CheckCircle2 class="w-4 h-4 mr-2" /> 98% de confianza. No requiere revisión.
            </div>
          </section>

          <section>
             <h3 class="text-[10px] lg:text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Users class="w-4 h-4 text-gray-400" /> Bloque N.º 3 - Fiscal (Mayoría)
            </h3>
            <div class="bg-green-50/50 border border-green-100 rounded-xl p-4 flex justify-center text-xs text-green-600 border-dashed">
              <CheckCircle2 class="w-4 h-4 mr-2" /> 99% de confianza. No requiere revisión.
            </div>
          </section>

          <section>
            <h3 class="text-[10px] lg:text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Users class="w-4 h-4 text-gray-400" /> Bloque N.º 4 - Conciliación (Cociente)
            </h3>
            
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div class="col-span-2 sm:col-span-4 border-b border-gray-200 pb-2 mb-1">
                <label class="block text-[9px] text-gray-500 uppercase">Total Votos</label>
                <input type="number" v-model="form.bloque4.total" class="w-full bg-transparent text-lg font-bold text-gray-900 outline-none">
              </div>

              <div><label class="block text-[9px] text-gray-500">Plancha 1</label><input type="number" v-model="form.bloque4.plancha1" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div>
                <label class="block text-[9px] text-gray-500 flex justify-between">Plancha 2 <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span></label>
                <input type="number" v-model="form.bloque4.plancha2" class="w-full px-2 py-1.5 bg-orange-50 border-2 border-orange-400 rounded text-sm font-bold text-orange-900 outline-none">
              </div>
              <div><label class="block text-[9px] text-gray-500">Plancha 3</label><input type="number" v-model="form.bloque4.plancha3" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">V. Blancos</label><input type="number" v-model="form.bloque4.blancos" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">V. Nulos</label><input type="number" v-model="form.bloque4.nulos" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
              <div><label class="block text-[9px] text-gray-500">No Marcados</label><input type="number" v-model="form.bloque4.noMarcados" class="w-full px-2 py-1.5 bg-white border border-gray-200 rounded text-sm font-bold outline-none"></div>
            </div>
          </section>

        </div>

    

        </div>

        <div class="p-4 lg:p-5 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between gap-3 shrink-0">
          <button @click="rechazarActa" class="px-3 lg:px-4 py-2 text-xs lg:text-sm font-semibold text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded-xl transition-colors">
            Rechazar
          </button>
          <button @click="confirmarEscrutinio" class="flex-1 lg:flex-none px-4 lg:px-6 py-2 text-xs lg:text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark shadow-md rounded-xl transition-all flex items-center justify-center gap-2">
            <Save class="w-4 h-4" /> Confirmar Datos
          </button>
        </div>

      </div>
      

        

    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import {
  ArrowLeft, ZoomIn, ZoomOut, FileText,
  CheckCircle2, Users, Save
} from 'lucide-vue-next';

const router = useRouter();

// Modelo de datos extraído adaptado a los 4 Bloques del nuevo Formato de Escrutinio
const form = ref({
  afiliados: 150,
  votantes: 142,
  bloque1: { 
    total: 142, 
    plancha1: 60, 
    plancha2: 45, 
    plancha3: 0, 
    blancos: 15, 
    nulos: 2, 
    noMarcados: 0 
  },
  bloque2: { validado: true },
  bloque3: { validado: true }, // Bloque Fiscal
  bloque4: { 
    total: 142, 
    plancha1: 50, 
    plancha2: 40, 
    plancha3: 20, 
    blancos: 10, 
    nulos: 12, 
    noMarcados: 10 
  } // Comisión Conciliación
});

const calcularValidosBloque1 = computed(() => {
  return form.value.bloque1.plancha1 + 
         form.value.bloque1.plancha2 + 
         form.value.bloque1.plancha3 + 
         form.value.bloque1.blancos;
});

const rechazarActa = () => {
  alert("Abrir modal de justificación de rechazo. Luego redirigir a bandeja.");
  router.push('/admin/audit');
};

const confirmarEscrutinio = () => {
  // Aquí ya puedes enviar form.value a tu API de Laravel
  console.log("Datos a enviar:", form.value);
  alert("Datos confirmados y enviados al backend.");
  router.push('/admin/audit');
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f9fafb; 
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e5e7eb; 
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #d1d5db; 
}
</style>