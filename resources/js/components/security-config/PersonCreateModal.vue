<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Nueva Persona</p>
            <h2 class="mt-1 text-lg font-bold text-gray-900">Crear Persona</h2>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="close">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

        <form @submit.prevent="submit">
          <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Identificación</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Tipo de Doc.</label>
                  <select v-model="form.document_type_id" required class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="errors.document_type_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'">
                    <option value="" disabled>Seleccione...</option>
                    <option value="1">Cédula de Ciudadanía (CC)</option>
                    <option value="2">Tarjeta de Identidad (TI)</option>
                    <option value="3">Cédula de Extranjería (CE)</option>
                  </select>
                  <p v-if="errors.document_type_id" class="text-xs text-red-600 mt-1">{{ errors.document_type_id }}</p>
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Número de Doc.</label>
                  <input v-model="form.document_number" required type="text" maxlength="30" class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="errors.document_number ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
                  <p v-if="errors.document_number" class="text-xs text-red-600 mt-1">{{ errors.document_number }}</p>
                </div>
              </div>
            </div>

            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Nombre completo</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Primer Nombre</label>
                  <input v-model="form.first_name" required type="text" maxlength="100" class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="errors.first_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
                  <p v-if="errors.first_name" class="text-xs text-red-600 mt-1">{{ errors.first_name }}</p>
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Segundo Nombre</label>
                  <input v-model="form.middle_name" type="text" maxlength="100" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Primer Apellido</label>
                  <input v-model="form.last_name" required type="text" maxlength="100" class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="errors.last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
                  <p v-if="errors.last_name" class="text-xs text-red-600 mt-1">{{ errors.last_name }}</p>
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Segundo Apellido</label>
                  <input v-model="form.second_last_name" type="text" maxlength="100" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
                </div>
              </div>
            </div>

            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Ubicación</p>
              <p class="text-xs text-gray-500 mb-2">Opcional, salvo que la cuenta que se vincule luego tenga rol de Jurado.</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Comuna</label>
                  <select v-model="selectedCommune" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
                    <option value="">Seleccione una comuna...</option>
                    <option v-for="item in communes" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Barrio de Residencia</label>
                  <select
                    v-model="form.neighborhood_id"
                    :disabled="!selectedCommune || loadingNeighborhoods"
                    class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                    :class="errors.neighborhood_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                  >
                    <option value="">{{ selectedCommune ? 'Seleccione un barrio...' : 'Primero seleccione una comuna...' }}</option>
                    <option v-for="item in neighborhoodsList" :key="item.id" :value="item.id">{{ item.name }}</option>
                  </select>
                  <p v-if="errors.neighborhood_id" class="text-xs text-red-600 mt-1">{{ errors.neighborhood_id }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex flex-wrap items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="close">Cancelar</button>
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark transition-colors disabled:opacity-60" :disabled="loading">
              Registrar Persona
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from '@/services/axios';
import { X } from 'lucide-vue-next';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';

const props = defineProps({
  open: { type: Boolean, default: false },
  communes: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'reload', 'show-result']);

const loading = ref(false);
const modalError = ref('');
const errors = ref({});
const selectedCommune = ref('');
const neighborhoodsList = ref([]);
const loadingNeighborhoods = ref(false);

const emptyForm = () => ({
  document_type_id: '', document_number: '', first_name: '', middle_name: '',
  last_name: '', second_last_name: '', neighborhood_id: '', is_active: true,
});
const form = ref(emptyForm());

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  form.value = emptyForm();
  errors.value = {};
  modalError.value = '';
  selectedCommune.value = '';
  neighborhoodsList.value = [];
});

watch(selectedCommune, async (communeId) => {
  form.value.neighborhood_id = '';
  if (!communeId) {
    neighborhoodsList.value = [];
    return;
  }
  loadingNeighborhoods.value = true;
  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', { params: { commune_id: communeId }, skipGlobalLoading: true });
    neighborhoodsList.value = data.data ?? [];
  } catch {
    modalError.value = 'No fue posible cargar los barrios.';
  } finally {
    loadingNeighborhoods.value = false;
  }
});

const close = () => emit('close');

const submit = async () => {
  errors.value = {};
  modalError.value = '';
  loading.value = true;
  try {
    await axios.post('/admin/persons', { ...form.value, neighborhood_id: form.value.neighborhood_id || null });
    emit('reload');
    emit('close');
    emit('show-result', true, 'Persona creada', 'La persona se registró exitosamente.');
  } catch (error) {
    errors.value = extractFieldErrors(error);
    const message = buildErrorMessage(error, 'Error al crear persona.');
    modalError.value = message;
    emit('show-result', false, 'No se pudo crear la persona', message);
  } finally {
    loading.value = false;
  }
};
</script>
