import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useDocumentStore = defineStore('document', () => {
  // Aquí guardaremos el array de fotos que toma el jurado
  const capturedImages = ref([]);
  
  // Para saber si estamos validando una 'plancha' o un 'escrutinio'
  const documentType = ref(null); 
  
  // Simulación de los datos devueltos por AWS Textract
  const extractedData = ref({});

  const setImages = (images, type) => {
    capturedImages.value = images;
    documentType.value = type;
  };

  const setExtractedData = (data) => {
    extractedData.value = data;
  };

  const clearStore = () => {
    capturedImages.value = [];
    documentType.value = null;
    extractedData.value = {};
  };

  return { 
    capturedImages, 
    documentType, 
    extractedData, 
    setImages, 
    setExtractedData, 
    clearStore 
  };
});