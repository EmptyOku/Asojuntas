<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <form class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl overflow-hidden" @submit.prevent="submitCompleteUser">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between"><div><p class="text-xs uppercase tracking-widest text-gray-400">Nuevo Registro</p><h2 class="text-lg font-bold text-gray-900">{{ completeStep === 1 ? 'Datos de la persona' : 'Datos de la cuenta' }}</h2></div><button type="button" class="text-gray-400" @click="$emit('close')"><X class="w-5 h-5" /></button></div>
        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm">{{ modalError }}</div>
        <div v-if="completeStep === 1" class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1 p-6">
          <div>
            <input v-model="completeForm.document_type_id" required type="number" min="1" placeholder="Tipo de documento" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.document_type_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.document_type_id" class="text-xs text-red-600 mt-1">{{ completeErrors.document_type_id }}</p>
          </div>
          <div>
            <input v-model="completeForm.document_number" required placeholder="Número de documento" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.document_number ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.document_number" class="text-xs text-red-600 mt-1">{{ completeErrors.document_number }}</p>
          </div>
          <div>
            <input v-model="completeForm.first_name" required placeholder="Primer nombre" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.first_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.first_name" class="text-xs text-red-600 mt-1">{{ completeErrors.first_name }}</p>
          </div>
          <div>
            <input v-model="completeForm.middle_name" placeholder="Segundo nombre" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.middle_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.middle_name" class="text-xs text-red-600 mt-1">{{ completeErrors.middle_name }}</p>
          </div>
          <div>
            <input v-model="completeForm.last_name" required placeholder="Primer apellido" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.last_name" class="text-xs text-red-600 mt-1">{{ completeErrors.last_name }}</p>
          </div>
          <div>
            <input v-model="completeForm.second_last_name" placeholder="Segundo apellido" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.second_last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.second_last_name" class="text-xs text-red-600 mt-1">{{ completeErrors.second_last_name }}</p>
          </div>
          <select v-model="completeCommune" class="px-3 py-2 rounded-lg border border-gray-200 text-sm h-fit"><option value="">Comuna</option><option v-for="item in communes" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select>
          <div>
            <select v-model="completeForm.neighborhood_id" :disabled="!completeCommune" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.neighborhood_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"><option value="">Barrio</option><option v-for="item in completeNeighborhoods" :key="item.id" :value="item.id">{{ item.name }}</option></select>
            <p v-if="completeErrors.neighborhood_id" class="text-xs text-red-600 mt-1">{{ completeErrors.neighborhood_id }}</p>
          </div>
        </div>
        <div v-else class="space-y-1 p-6">
          <div>
            <input v-model="completeForm.username" required minlength="4" maxlength="50" pattern="[A-Za-z0-9_.\-]{4,50}" title="Entre 4 y 50 caracteres: letras, números, punto, guion o guion bajo." placeholder="Usuario" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.username ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.username" class="text-xs text-red-600 mt-1 mb-2">{{ completeErrors.username }}</p>
          </div>
          <div>
            <input v-model="completeForm.email" required type="email" maxlength="150" placeholder="Correo" class="w-full px-3 py-2 rounded-lg border text-sm" :class="completeErrors.email ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'" />
            <p v-if="completeErrors.email" class="text-xs text-red-600 mt-1 mb-2">{{ completeErrors.email }}</p>
          </div>
          <div>
            <div class="relative">
              <input
                v-model="completeForm.password"
                required
                :type="showCompletePassword ? 'text' : 'password'"
                minlength="8"
                placeholder="Contraseña"
                class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 text-sm"
                :class="completePasswordTooShort || completeErrors.password ? 'border-red-300' : ''"
              />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100" @click="showCompletePassword = !showCompletePassword" tabindex="-1">
                <EyeOff v-if="showCompletePassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
            <p v-if="completePasswordTooShort" class="text-xs text-red-600 mt-1">La contraseña debe tener al menos 8 caracteres.</p>
            <p v-else-if="completeErrors.password" class="text-xs text-red-600 mt-1">{{ completeErrors.password }}</p>
          </div>
          <div>
            <div class="relative">
              <input
                v-model="completeForm.password_confirmation"
                required
                :type="showCompletePasswordConfirm ? 'text' : 'password'"
                minlength="8"
                placeholder="Confirmar contraseña"
                class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 text-sm"
                :class="completePasswordMismatch ? 'border-red-300' : ''"
              />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100" @click="showCompletePasswordConfirm = !showCompletePasswordConfirm" tabindex="-1">
                <EyeOff v-if="showCompletePasswordConfirm" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
            <p v-if="completePasswordMismatch" class="text-xs text-red-600 mt-1">Las contraseñas no coinciden.</p>
          </div>
          <div>
            <div class="flex flex-wrap gap-2"><label v-for="role in roles" :key="role.id" class="flex items-center gap-2 px-3 py-2 rounded-lg border text-sm" :class="completeErrors.roles ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"><input v-model="completeForm.roles" type="checkbox" :value="role.id" />{{ role.display_name }}</label></div>
            <p v-if="completeErrors.roles" class="text-xs text-red-600 mt-1">{{ completeErrors.roles }}</p>
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3"><button type="button" class="px-4 py-2 rounded-lg border border-gray-200 text-sm" @click="completeStep === 1 ? $emit('close') : completeStep = 1">{{ completeStep === 1 ? 'Cancelar' : 'Atrás' }}</button><button type="submit" class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm" :disabled="loading || (completeStep === 2 && (completeForm.roles.length === 0 || completePasswordTooShort || completePasswordMismatch))">{{ completeStep === 1 ? 'Continuar' : 'Crear cuenta' }}</button></div>
      </form>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from '@/services/axios';
import { X, Eye, EyeOff } from 'lucide-vue-next';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';

const props = defineProps({
  open: { type: Boolean, default: false },
  communes: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'reload', 'show-result']);

const loading = ref(false);
const modalError = ref('');
const completeStep = ref(1);
const completeCommune = ref('');
const completeNeighborhoods = ref([]);
const completeForm = ref({ document_type_id: '', document_number: '', first_name: '', middle_name: '', last_name: '', second_last_name: '', neighborhood_id: '', username: '', email: '', password: '', password_confirmation: '', roles: [] });
const completeErrors = ref({});
// Campos que viven en el paso 1 del wizard: si alguno de estos falla, hay que devolver
// al usuario a ese paso para que vea el resaltado (si no, quedaría viendo el paso 2 sin pistas).
const completeStep1Fields = ['document_type_id', 'document_number', 'first_name', 'middle_name', 'last_name', 'second_last_name', 'neighborhood_id'];

const showCompletePassword = ref(false);
const showCompletePasswordConfirm = ref(false);
const completePasswordTooShort = computed(() => completeForm.value.password.length > 0 && completeForm.value.password.length < 8);
const completePasswordMismatch = computed(() => completeForm.value.password_confirmation.length > 0 && completeForm.value.password !== completeForm.value.password_confirmation);

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  completeStep.value = 1;
  modalError.value = '';
  completeErrors.value = {};
  showCompletePassword.value = false;
  showCompletePasswordConfirm.value = false;
});

watch(completeCommune, async (communeId) => {
  completeForm.value.neighborhood_id = '';
  if (!communeId) {
    completeNeighborhoods.value = [];
    return;
  }
  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', { params: { commune_id: communeId }, skipGlobalLoading: true });
    completeNeighborhoods.value = data.data ?? [];
  } catch {
    modalError.value = 'No fue posible cargar los barrios.';
  }
});

const submitCompleteUser = async () => {
  if (completeStep.value === 1) {
    completeStep.value = 2;
    return;
  }
  loading.value = true;
  modalError.value = '';
  completeErrors.value = {};
  try {
    await axios.post('/admin/users-complete', completeForm.value);
    completeForm.value = { document_type_id: '', document_number: '', first_name: '', middle_name: '', last_name: '', second_last_name: '', neighborhood_id: '', username: '', email: '', password: '', password_confirmation: '', roles: [] };
    emit('reload');
    emit('close');
    emit('show-result', true, 'Registro creado', 'La persona y su cuenta de usuario se crearon exitosamente.');
  } catch (error) {
    const fieldErrors = extractFieldErrors(error);
    completeErrors.value = fieldErrors;
    const message = buildErrorMessage(error, 'No fue posible crear el registro.');
    modalError.value = message;
    // Si el campo con error vive en el paso 1 (p. ej. barrio), regresa ahí para que se vea resaltado.
    if (Object.keys(fieldErrors).some((field) => completeStep1Fields.includes(field))) {
      completeStep.value = 1;
    }
    emit('show-result', false, 'No se pudo crear el registro', message);
  } finally {
    loading.value = false;
  }
};
</script>
