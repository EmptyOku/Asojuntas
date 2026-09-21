<template>
  <Teleport to="body">
    <div v-if="person" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Editar Persona</p>
            <h2 class="mt-1 text-lg font-bold text-gray-900">{{ person.first_name }} {{ person.last_name }}</h2>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="$emit('close')">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

        <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Identificación</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Tipo de Doc.</label>
                <select v-model="form.document_type_id" required class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="fieldErrors.document_type_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'">
                  <option value="" disabled>Seleccione...</option>
                  <option value="1">Cédula de Ciudadanía (CC)</option>
                  <option value="2">Tarjeta de Identidad (TI)</option>
                  <option value="3">Cédula de Extranjería (CE)</option>
                </select>
                <p v-if="fieldErrors.document_type_id" class="text-xs text-red-600 mt-1">{{ fieldErrors.document_type_id }}</p>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Número de Doc.</label>
                <input v-model="form.document_number" required type="text" class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="fieldErrors.document_number ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
                <p v-if="fieldErrors.document_number" class="text-xs text-red-600 mt-1">{{ fieldErrors.document_number }}</p>
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Nombre completo</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Nombre</label>
                <input v-model="form.first_name" required type="text" class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="fieldErrors.first_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
                <p v-if="fieldErrors.first_name" class="text-xs text-red-600 mt-1">{{ fieldErrors.first_name }}</p>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Nombre</label>
                <input v-model="form.middle_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Apellido</label>
                <input v-model="form.last_name" required type="text" class="w-full px-3 py-2 rounded-lg border bg-white text-sm" :class="fieldErrors.last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
                <p v-if="fieldErrors.last_name" class="text-xs text-red-600 mt-1">{{ fieldErrors.last_name }}</p>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Apellido</label>
                <input v-model="form.second_last_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Ubicación</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Comuna</label>
                <select v-model="editorSelectedCommune" @change="handleCommuneChange" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
                  <option value="">Seleccione una comuna...</option>
                  <option v-for="item in communes" :key="item.id" :value="String(item.id)">
                    {{ item.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Barrio</label>
                <div class="relative">
                  <select
                    v-model="form.neighborhood_id"
                    :disabled="!editorSelectedCommune || loadingNeighborhoods"
                    class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm disabled:opacity-60"
                    :class="fieldErrors.neighborhood_id ? 'border-red-400 ring-1 ring-red-300' : ''"
                  >
                    <option value="">{{ editorSelectedCommune ? 'Sin asignar' : 'Primero seleccione una comuna...' }}</option>
                    <option v-for="item in neighborhoods" :key="item.id" :value="String(item.id)">
                      {{ item.name }}
                    </option>
                  </select>
                  <Loader2 v-if="loadingNeighborhoods" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-aso-primary animate-spin" />
                </div>
                <p v-if="fieldErrors.neighborhood_id" class="text-xs text-red-600 mt-1">{{ fieldErrors.neighborhood_id }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="$emit('close')">
            Cancelar
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark transition-colors disabled:opacity-60"
            :disabled="loading || !form.document_type_id || !form.document_number || !form.first_name || !form.last_name"
            @click="savePerson"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from '@/services/axios';
import { X, Loader2 } from 'lucide-vue-next';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';

const props = defineProps({
  person: { type: Object, default: null },
  communes: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'reload', 'show-result']);

const loading = ref(false);
const modalError = ref('');
const fieldErrors = ref({});
const editorSelectedCommune = ref('');
const neighborhoods = ref([]);
const loadingNeighborhoods = ref(false);

const emptyForm = () => ({
  document_type_id: '',
  document_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  second_last_name: '',
  neighborhood_id: '',
});

const form = ref(emptyForm());

const loadNeighborhoods = async (communeId) => {
  if (!communeId) {
    neighborhoods.value = [];
    return;
  }

  loadingNeighborhoods.value = true;
  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', {
      params: { commune_id: communeId },
      skipGlobalLoading: true,
    });
    neighborhoods.value = data.data ?? [];
  } catch (error) {
    console.error('Error loading neighborhoods for person editor:', error);
  } finally {
    loadingNeighborhoods.value = false;
  }
};

const handleCommuneChange = () => {
  form.value.neighborhood_id = '';
  loadNeighborhoods(editorSelectedCommune.value);
};

watch(() => props.person, async (person) => {
  modalError.value = '';
  fieldErrors.value = {};
  if (!person) {
    form.value = emptyForm();
    editorSelectedCommune.value = '';
    neighborhoods.value = [];
    return;
  }

  form.value = {
    document_type_id: person.document_type_id ? String(person.document_type_id) : '',
    document_number: person.document_number ?? '',
    first_name: person.first_name ?? '',
    middle_name: person.middle_name ?? '',
    last_name: person.last_name ?? '',
    second_last_name: person.second_last_name ?? '',
    neighborhood_id: person.neighborhood?.id ? String(person.neighborhood.id) : '',
  };

  editorSelectedCommune.value = person.neighborhood?.commune_id ? String(person.neighborhood.commune_id) : '';
  await loadNeighborhoods(editorSelectedCommune.value);

  // Garantiza que el barrio actual de la persona aparezca en el select aunque
  // no esté entre los primeros resultados devueltos por el backend.
  if (person.neighborhood && !neighborhoods.value.some((item) => Number(item.id) === Number(person.neighborhood.id))) {
    neighborhoods.value = [{ id: person.neighborhood.id, name: person.neighborhood.name }, ...neighborhoods.value];
  }
});

const savePerson = async () => {
  if (!props.person) return;
  loading.value = true;
  modalError.value = '';
  fieldErrors.value = {};
  try {
    await axios.put(`/admin/persons/${props.person.id}`, {
      document_type_id: Number(form.value.document_type_id),
      document_number: form.value.document_number,
      first_name: form.value.first_name,
      middle_name: form.value.middle_name || null,
      last_name: form.value.last_name,
      second_last_name: form.value.second_last_name || null,
      neighborhood_id: form.value.neighborhood_id ? Number(form.value.neighborhood_id) : null,
    });
    emit('reload');
    emit('close');
    emit('show-result', true, 'Persona actualizada', 'Los datos de la persona se actualizaron con éxito.');
  } catch (error) {
    fieldErrors.value = extractFieldErrors(error);
    const message = buildErrorMessage(error, 'Error al actualizar persona.');
    modalError.value = message;
    emit('show-result', false, 'No se pudo actualizar la persona', message);
  } finally {
    loading.value = false;
  }
};
</script>
