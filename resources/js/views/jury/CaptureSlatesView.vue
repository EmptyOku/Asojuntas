<template>
  <div class="space-y-6 flex-1 flex flex-col">
    
    <div class="flex items-center gap-4">
      <router-link to="/jury/dashboard" class="p-2 bg-white text-gray-500 hover:text-gray-900 rounded-full shadow-sm border border-gray-100 transition-colors">
        <ArrowLeft class="w-5 h-5" />
      </router-link>
      <h2 class="text-xl font-bold text-gray-900">Capturar {{ isPlancha ? 'Planchas' : 'Escrutinio' }}</h2>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex-1 flex flex-col">
      
      <div v-if="!imagePreview" class="flex-1 flex flex-col items-center justify-center text-center space-y-6">
        <div :class="isPlancha ? 'bg-orange-50 text-orange-500' : 'bg-green-50 text-aso-primary'" class="w-24 h-24 rounded-full flex items-center justify-center">
          <ScanLine class="w-12 h-12" />
        </div>
        
        <div>
          <h3 class="text-lg font-bold text-gray-900">
            Fotografía {{ isPlancha ? 'la Planilla de Candidatos' : 'el Formato de Escrutinio' }}
          </h3>
          <p class="text-sm text-gray-500 mt-2 max-w-xs mx-auto">
            {{ isPlancha ? 'Asegúrate de que los nombres y números de identificación sean legibles.' : 'Enfoca claramente las tablas con los totales numéricos.' }}
          </p>
        </div>

        <input type="file" accept="image/*" capture="environment" id="cameraInput" class="hidden" @change="handleImageUpload">
        
        <label for="cameraInput" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-gray-900 hover:bg-black text-white px-8 py-4 rounded-2xl font-bold text-lg shadow-md cursor-pointer transition-transform hover:-translate-y-1">
          <Camera class="w-6 h-6" /> Abrir Cámara
        </label>
      </div>

      <div v-else class="flex-1 flex flex-col h-full">
        <div class="bg-gray-900 rounded-xl overflow-hidden flex-1 relative flex items-center justify-center mb-6">
          <img :src="imagePreview" alt="Vista previa" class="max-h-full max-w-full object-contain">
        </div>

        <div class="grid grid-cols-2 gap-3 shrink-0">
          <button @click="imagePreview = null" :disabled="isUploading" class="px-4 py-3 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition-colors disabled:opacity-50">
            Repetir Foto
          </button>
          <button @click="enviarActa" :disabled="isUploading" class="px-4 py-3 bg-aso-primary text-white font-bold rounded-xl shadow-md hover:bg-aso-primary-dark transition-all flex items-center justify-center gap-2 disabled:opacity-70">
            <template v-if="isUploading"><Loader2 class="w-5 h-5 animate-spin" /> Enviando...</template>
            <template v-else><Send class="w-5 h-5" /> Enviar Datos</template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ArrowLeft, ScanLine, Camera, Send, Loader2 } from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();
const imagePreview = ref(null);
const isUploading = ref(false);

// Verifica la URL para saber qué documento estamos escaneando
const isPlancha = computed(() => route.query.doc === 'plancha');

const handleImageUpload = (event) => {
  const file = event.target.files[0];
  if (file) imagePreview.value = URL.createObjectURL(file);
};

const enviarActa = () => {
  isUploading.value = true;
  setTimeout(() => {
    isUploading.value = false;
    alert(`¡${isPlancha.value ? 'Planilla' : 'Acta'} transmitida con éxito a la IA!`);
    router.push('/jury/dashboard');
  }, 2000);
};
</script>