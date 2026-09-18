<template>
  <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
    <div class="flex items-start justify-between gap-3 mb-6">
      <div>
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Módulo para crear personas antes que usuarios.</h1>
        <p class="text-sm text-gray-500 mt-1">Agrega las personas al programa antes de agregar sus usuarios</p>
      </div>
      <button
        type="button"
        class="px-3 py-2 text-sm font-medium rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark transition-colors shrink-0"
        @click="$emit('reload')"
        :disabled="loading"
      >
        Recargar
      </button>
    </div>

    <div v-if="errorMessage" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
      {{ errorMessage }}
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50 p-5 sm:p-6">
      <h2 class="text-base font-semibold text-gray-900 mb-5">Crear Persona</h2>
      <form class="space-y-6" @submit.prevent="submitPerson">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Identificación</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Tipo de Doc.</label>
              <select v-model="formPerson.document_type_id" required class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="personErrors.document_type_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'">
                <option value="" disabled>Seleccione...</option>
                <option value="1">Cédula de Ciudadanía (CC)</option>
                <option value="2">Tarjeta de Identidad (TI)</option>
                <option value="3">Cédula de Extranjería (CE)</option>
              </select>
              <p v-if="personErrors.document_type_id" class="text-xs text-red-600 mt-1">{{ personErrors.document_type_id }}</p>
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Número de Doc.</label>
              <input
                v-model="formPerson.document_number"
                required
                type="text"
                maxlength="30"
                pattern="[A-Za-z0-9]{5,30}"
                title="Debe contener solo letras y números, entre 5 y 30 caracteres."
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                :class="personErrors.document_number ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
              />
              <p v-if="personErrors.document_number" class="text-xs text-red-600 mt-1">{{ personErrors.document_number }}</p>
            </div>
          </div>
        </div>

        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Nombre completo</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Primer Nombre</label>
              <input
                v-model="formPerson.first_name"
                required
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]+"
                title="Solo letras y espacios."
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                :class="personErrors.first_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
              />
              <p v-if="personErrors.first_name" class="text-xs text-red-600 mt-1">{{ personErrors.first_name }}</p>
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Segundo Nombre</label>
              <input
                v-model="formPerson.middle_name"
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]*"
                title="Solo letras y espacios."
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                :class="personErrors.middle_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                placeholder="Opcional"
              />
              <p v-if="personErrors.middle_name" class="text-xs text-red-600 mt-1">{{ personErrors.middle_name }}</p>
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Primer Apellido</label>
              <input
                v-model="formPerson.last_name"
                required
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]+"
                title="Solo letras y espacios."
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                :class="personErrors.last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
              />
              <p v-if="personErrors.last_name" class="text-xs text-red-600 mt-1">{{ personErrors.last_name }}</p>
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Segundo Apellido</label>
              <input
                v-model="formPerson.second_last_name"
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]*"
                title="Solo letras y espacios."
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                :class="personErrors.second_last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                placeholder="Opcional"
              />
              <p v-if="personErrors.second_last_name" class="text-xs text-red-600 mt-1">{{ personErrors.second_last_name }}</p>
            </div>
          </div>
        </div>

        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Ubicación</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Comuna</label>
              <select
                v-model="selectedCommune"
                @change="handleCommuneChange"
                class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm cursor-pointer focus:ring-2 focus:ring-aso-primary"
              >
                <option value="">Seleccione una comuna...</option>
                <option v-for="item in communes" :key="item.id" :value="String(item.id)">
                  {{ item.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Barrio de Residencia</label>
              <select
                v-model="formPerson.neighborhood_id"
                required
                :disabled="!selectedCommune || loadingNeighborhoods"
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm cursor-pointer focus:ring-2 focus:ring-aso-primary"
                :class="personErrors.neighborhood_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
              >
                <option value="">{{ selectedCommune ? 'Seleccione un barrio...' : 'Primero seleccione una comuna...' }}</option>
                <option v-for="item in neighborhoodsList" :key="item.id" :value="item.id">
                  {{ item.name }}
                </option>
              </select>
              <p v-if="personErrors.neighborhood_id" class="text-xs text-red-600 mt-1">{{ personErrors.neighborhood_id }}</p>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-200">
          <button
            type="submit"
            class="w-full sm:w-auto px-8 py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-60"
            :disabled="loading"
          >
            Registrar Persona
          </button>
        </div>
      </form>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import axios from '@/services/axios';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';

const props = defineProps({
  communes: { type: Array, default: () => [] },
});

const emit = defineEmits(['reload', 'show-result', 'assignment-context-change']);

const loading = ref(false);
const errorMessage = ref('');
const loadingNeighborhoods = ref(false);
const selectedCommune = ref('');
const neighborhoodsList = ref([]);

const formPerson = ref({
  document_type_id: '',
  document_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  second_last_name: '',
  neighborhood_id: '',
  is_active: true,
});
const personErrors = ref({});

const loadAssignmentContext = async () => {
  if (!selectedCommune.value) {
    emit('assignment-context-change', []);
    return;
  }

  const { data } = await axios.get('/admin/users/assignment-context', {
    params: { commune_id: selectedCommune.value },
    skipGlobalLoading: true,
  });
  emit('assignment-context-change', Array.isArray(data.data) ? data.data : []);
};

const loadNeighborhoodsForForms = async () => {
  if (!selectedCommune.value) {
    neighborhoodsList.value = [];
    return;
  }

  loadingNeighborhoods.value = true;
  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', {
      params: { commune_id: selectedCommune.value },
      skipGlobalLoading: true,
    });
    neighborhoodsList.value = data.data ?? [];
  } catch (error) {
    console.error('Error loading neighborhoods:', error);
    throw error;
  } finally {
    loadingNeighborhoods.value = false;
  }
};

const handleCommuneChange = async () => {
  formPerson.value.neighborhood_id = '';

  try {
    await Promise.all([
      loadNeighborhoodsForForms(),
      loadAssignmentContext(),
    ]);
  } catch (error) {
    errorMessage.value = 'No fue posible cargar los barrios de la comuna seleccionada.';
  }
};

const submitPerson = async () => {
  personErrors.value = {};
  if (!formPerson.value.neighborhood_id) {
    personErrors.value = { neighborhood_id: 'Debes seleccionar un barrio.' };
    emit('show-result', false, 'No se pudo crear la persona', 'Debes seleccionar un barrio.');
    return;
  }
  loading.value = true;
  try {
    await axios.post('/admin/persons', formPerson.value);
    formPerson.value = {
      document_type_id: '', document_number: '', first_name: '', middle_name: '',
      last_name: '', second_last_name: '', neighborhood_id: '', is_active: true,
    };
    emit('reload');
    emit('show-result', true, 'Persona creada', 'La persona se registró exitosamente.');
  } catch (error) {
    personErrors.value = extractFieldErrors(error);
    emit('show-result', false, 'No se pudo crear la persona', buildErrorMessage(error, 'Error al crear persona.'));
  } finally {
    loading.value = false;
  }
};
</script>
