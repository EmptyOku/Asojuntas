<template>
  <Teleport to="body">
    <div v-if="user" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Editar Usuario</p>
            <h2 class="mt-1 text-lg font-bold text-gray-900">{{ user.username }}</h2>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="$emit('close')">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

        <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Datos de la persona</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Tipo de Doc.</label>
                <select v-model="editingUserForm.document_type_id" required class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
                  <option value="" disabled>Seleccione...</option>
                  <option value="1">Cédula de Ciudadanía (CC)</option>
                  <option value="2">Tarjeta de Identidad (TI)</option>
                  <option value="3">Cédula de Extranjería (CE)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Número de Doc.</label>
                <input v-model="editingUserForm.document_number" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Nombre</label>
                <input v-model="editingUserForm.first_name" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Nombre</label>
                <input v-model="editingUserForm.middle_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Apellido</label>
                <input v-model="editingUserForm.last_name" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Apellido</label>
                <input v-model="editingUserForm.second_last_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Ubicación</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Comuna</label>
                <select v-model="editorSelectedCommune" @change="handleEditorCommuneChange" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
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
                    v-model="editingUserForm.neighborhood_id"
                    :disabled="!editorSelectedCommune || loadingEditorNeighborhoods"
                    class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm disabled:opacity-60"
                  >
                    <option value="">{{ editorSelectedCommune ? 'Sin asignar' : 'Primero seleccione una comuna...' }}</option>
                    <option v-for="item in editorNeighborhoods" :key="item.id" :value="String(item.id)">
                      {{ item.name }}
                    </option>
                  </select>
                  <Loader2 v-if="loadingEditorNeighborhoods" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-aso-primary animate-spin" />
                </div>
                <p v-if="loadingEditorNeighborhoods" class="text-xs text-gray-400 mt-1">Cargando barrios...</p>
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Cuenta</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Usuario</label>
                <input v-model="editingUserForm.username" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Correo</label>
                <input v-model="editingUserForm.email" required type="email" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
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
            :disabled="loading || !editingUserForm.document_type_id || !editingUserForm.document_number || !editingUserForm.first_name || !editingUserForm.last_name || !editingUserForm.username || !editingUserForm.email"
            @click="saveUserData"
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

const props = defineProps({
  user: { type: Object, default: null },
  communes: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'reload']);

const loading = ref(false);
const modalError = ref('');
const editorSelectedCommune = ref('');
const editorNeighborhoods = ref([]);
const loadingEditorNeighborhoods = ref(false);

const emptyUserForm = () => ({
  document_type_id: '',
  document_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  second_last_name: '',
  neighborhood_id: '',
  username: '',
  email: '',
});

const editingUserForm = ref(emptyUserForm());

const loadEditorNeighborhoods = async (communeId) => {
  if (!communeId) {
    editorNeighborhoods.value = [];
    return;
  }

  loadingEditorNeighborhoods.value = true;
  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', {
      params: { commune_id: communeId },
      skipGlobalLoading: true,
    });
    editorNeighborhoods.value = data.data ?? [];
  } catch (error) {
    console.error('Error loading neighborhoods for editor:', error);
  } finally {
    loadingEditorNeighborhoods.value = false;
  }
};

const handleEditorCommuneChange = () => {
  editingUserForm.value.neighborhood_id = '';
  loadEditorNeighborhoods(editorSelectedCommune.value);
};

watch(() => props.user, async (user) => {
  modalError.value = '';
  if (!user) {
    editingUserForm.value = emptyUserForm();
    editorSelectedCommune.value = '';
    editorNeighborhoods.value = [];
    return;
  }

  const person = user.person ?? null;
  const currentNeighborhood = person?.neighborhood ?? null;

  editingUserForm.value = {
    document_type_id: person?.document_type_id ? String(person.document_type_id) : '',
    document_number: person?.document_number ?? '',
    first_name: person?.first_name ?? '',
    middle_name: person?.middle_name ?? '',
    last_name: person?.last_name ?? '',
    second_last_name: person?.second_last_name ?? '',
    neighborhood_id: currentNeighborhood?.id ? String(currentNeighborhood.id) : '',
    username: user.username,
    email: user.email,
  };

  editorSelectedCommune.value = currentNeighborhood?.commune_id ? String(currentNeighborhood.commune_id) : '';
  await loadEditorNeighborhoods(editorSelectedCommune.value);

  // Garantiza que el barrio actual del usuario aparezca en el select aunque
  // no esté entre los primeros resultados devueltos por el backend.
  if (currentNeighborhood && !editorNeighborhoods.value.some((item) => Number(item.id) === Number(currentNeighborhood.id))) {
    editorNeighborhoods.value = [{ id: currentNeighborhood.id, name: currentNeighborhood.name }, ...editorNeighborhoods.value];
  }
});

const saveUserData = async () => {
  if (!props.user) return;
  loading.value = true;
  modalError.value = '';
  try {
    const form = editingUserForm.value;
    await axios.put(`/admin/users/${props.user.id}`, {
      document_type_id: Number(form.document_type_id),
      document_number: form.document_number,
      first_name: form.first_name,
      middle_name: form.middle_name || null,
      last_name: form.last_name,
      second_last_name: form.second_last_name || null,
      neighborhood_id: form.neighborhood_id ? Number(form.neighborhood_id) : null,
      username: form.username,
      email: form.email,
    });
    emit('reload');
    emit('close');
  } catch (error) {
    modalError.value = error?.response?.data?.message || 'Error al actualizar usuario.';
  } finally {
    loading.value = false;
  }
};
</script>
