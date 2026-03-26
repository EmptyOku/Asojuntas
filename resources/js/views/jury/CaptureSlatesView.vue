<template>
  <div class="space-y-6 flex-1 flex flex-col">
    
    <!-- Encabezado -->
    <div class="flex items-center gap-4">
      <router-link to="/jury/dashboard" class="p-2 bg-white text-gray-500 hover:text-gray-900 rounded-full shadow-sm border border-gray-100 transition-colors">
        <ArrowLeft class="w-5 h-5" />
      </router-link>
      <h2 class="text-xl font-bold text-gray-900">Capturar {{ isPlancha ? 'Planchas' : 'Escrutinio' }}</h2>
    </div>

    <!-- Contenedor Principal -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex-1 flex flex-col">
      
      <!-- INPUT OCULTO: Al no tener capture="environment" y tener "multiple", el celular preguntará si usar Cámara o Galería y permitirá seleccionar varias -->
      <input type="file" accept="image/*" multiple id="cameraInput" class="hidden" @change="handleImageUpload">

      <!-- ESTADO 1: PANTALLA INICIAL (CERO FOTOS) -->
      <div v-if="capturedImages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center space-y-6">
        <div :class="isPlancha ? 'bg-orange-50 text-orange-500' : 'bg-green-50 text-aso-primary'" class="w-24 h-24 rounded-full flex items-center justify-center">
          <ScanLine class="w-12 h-12" />
        </div>
        
        <div>
          <h3 class="text-lg font-bold text-gray-900">
            Fotografía {{ isPlancha ? 'las Planchas de Candidatos' : 'el Formato de Escrutinio' }}
          </h3>
          <p class="text-sm text-gray-500 mt-2 max-w-xs mx-auto">
            {{ isPlancha ? 'Son alrededor de 6 páginas. Asegúrate de que los nombres y números sean legibles.' : 'Son alrededor de 3 páginas. Enfoca claramente las tablas con los totales numéricos.' }}
          </p>
        </div>
        
        <label for="cameraInput" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-gray-900 hover:bg-black text-white px-8 py-4 rounded-2xl font-bold text-lg shadow-md cursor-pointer transition-transform hover:-translate-y-1">
          <Camera class="w-6 h-6" /> Abrir Cámara / Galería
        </label>
      </div>

      <!-- ESTADO 2: CUADRÍCULA DE FOTOS TOMADAS -->
      <div v-else class="flex-1 flex flex-col h-full space-y-4">
        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">
          Páginas capturadas ({{ capturedImages.length }})
        </h3>
        
        <!-- Grid responsivo para las miniaturas -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto flex-1 p-1">
          
          <div v-for="(img, index) in capturedImages" :key="img.id" class="relative group aspect-[3/4] bg-gray-100 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
            <img :src="img.url" class="w-full h-full object-cover">
            
            <!-- Etiqueta de Número de Página -->
            <div class="absolute top-2 left-2 bg-black/60 text-white text-xs font-bold px-2 py-1 rounded-md">
              Pág {{ index + 1 }}
            </div>
            
            <!-- Botón Eliminar -->
            <button @click="removeImage(img.id)" class="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-lg opacity-90 hover:opacity-100 shadow-md transition-opacity">
              <X class="w-4 h-4" />
            </button>
          </div>
          
          <!-- Botón "Añadir otra" como una tarjeta más en el Grid -->
          <label for="cameraInput" class="aspect-[3/4] flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 hover:bg-gray-100 cursor-pointer transition-colors">
             <Plus class="w-8 h-8 text-gray-400" />
             <span class="text-xs font-bold text-gray-500 text-center px-2">Añadir página</span>
          </label>
        </div>

        <!-- Botón de Envío Fijo al fondo -->
        <button @click="enviarActa" :disabled="isUploading" class="w-full py-4 bg-aso-primary text-white font-bold rounded-xl shadow-md hover:bg-aso-primary-dark transition-all flex items-center justify-center gap-2 disabled:opacity-70 mt-4 shrink-0">
          <template v-if="isUploading">
            <Loader2 class="w-5 h-5 animate-spin" /> Transmitiendo paquete...
          </template>
          <template v-else>
            <Send class="w-5 h-5" /> Enviar {{ isPlancha ? 'Planchas' : 'Escrutinio' }}
          </template>
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ArrowLeft, ScanLine, Camera, Send, Loader2, Plus, X } from 'lucide-vue-next';
import { useDocumentStore } from '@/stores/document';

const router = useRouter();
const route = useRoute();

// Array reactivo para almacenar el paquete de fotos
const capturedImages = ref([]); 
const isUploading = ref(false);

const isPlancha = computed(() => route.query.doc === 'plancha');

const handleImageUpload = (event) => {
  const files = event.target.files;
  if (!files) return;

  for (let i = 0; i < files.length; i++) {
    capturedImages.value.push({
      id: Date.now() + i, // ID único para manejo seguro
      file: files[i],
      url: URL.createObjectURL(files[i])
    });
  }
  
  // Limpiamos el valor del input para que el evento @change vuelva a dispararse 
  // incluso si el usuario selecciona la misma foto dos veces seguidas
  event.target.value = ''; 
};

const removeImage = (idToRemove) => {
  // Liberamos la memoria del navegador revocando la URL temporal
  const imageToOmit = capturedImages.value.find(img => img.id === idToRemove);
  if (imageToOmit) URL.revokeObjectURL(imageToOmit.url);
  
  capturedImages.value = capturedImages.value.filter(img => img.id !== idToRemove);
};

const enviarActa = () => {
  // 1. Validamos que haya fotos
  if (capturedImages.value.length === 0) return alert("Debes subir al menos una foto.");
  
  // 2. Activamos el estado de carga para que el botón muestre el "Enviando..."
  isUploading.value = true;
  
  // 3. Imprimimos en consola para nuestras pruebas
  console.log("📦 Armando paquete de envío...");
  const formData = new FormData();
  capturedImages.value.forEach((img, index) => {
    formData.append(`pages[${index}]`, img.file);
    console.log(`✅ Página ${index + 1} lista:`, img.file.name, `(${(img.file.size / 1024 / 1024).toFixed(2)} MB)`);
  });
  
  // 4. Simulamos el tiempo que tarda la IA (Textract) en leer las fotos
  setTimeout(() => {
    isUploading.value = false; // Apagamos el loader
    
    // 5. Guardamos en Pinia y pasamos a la siguiente vista
    const docStore = useDocumentStore();
    docStore.setImages(capturedImages.value, isPlancha.value ? 'plancha' : 'escrutinio');
    docStore.setExtractedData({ plancha1: 45, plancha2: 17, blancos: 3 }); 

    // Empujamos a la vista de revisión
    router.push('/jury/review');
  }, 2000);
};

</script>