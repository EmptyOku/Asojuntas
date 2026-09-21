<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Nuevo Usuario</p>
            <h2 class="mt-1 text-lg font-bold text-gray-900">Crear Usuario</h2>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="close">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

        <form @submit.prevent="submit">
          <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
            <div>
              <label class="block text-sm text-gray-700 mb-1 font-semibold">Persona Física</label>
              <div class="relative" ref="personSearchContainer">
                <div class="relative">
                  <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="text"
                    v-model="personSearchQuery"
                    @input="handlePersonSearch"
                    @focus="isPersonDropdownOpen = true"
                    placeholder="Buscar por cédula o nombre..."
                    class="w-full pl-9 pr-10 py-2 rounded-lg border bg-white text-sm focus:ring-2 focus:ring-aso-primary focus:border-aso-primary"
                    :class="errors.person_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                  >
                  <button v-if="form.person_id" type="button" @click="clearPersonSelection" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-red-500 rounded-full hover:bg-gray-100">
                    <X class="w-4 h-4" />
                  </button>
                  <Loader2 v-else-if="isSearchingPerson" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-aso-primary animate-spin" />
                </div>

                <div
                  v-if="isPersonDropdownOpen && (personSearchResults.length > 0 || isSearchingPerson || personSearchQuery.length > 0)"
                  class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                  <div v-if="isSearchingPerson" class="p-3 text-sm text-gray-500 text-center">Buscando...</div>
                  <div v-else-if="personSearchResults.length === 0 && personSearchQuery.length >= 2" class="p-3 text-sm text-gray-500 text-center">No se encontraron personas</div>
                  <ul v-else-if="personSearchResults.length > 0" class="py-1">
                    <li
                      v-for="person in personSearchResults"
                      :key="person.id"
                      @click="selectPerson(person)"
                      class="px-4 py-2 hover:bg-aso-primary hover:text-white cursor-pointer text-sm border-b border-gray-50 last:border-0"
                      :class="{'bg-gray-50 text-aso-primary font-medium': form.person_id === person.id}"
                    >
                      {{ person.label }}
                    </li>
                  </ul>
                </div>
              </div>
              <p v-if="errors.person_id" class="text-xs text-red-600 mt-1">{{ errors.person_id }}</p>
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Usuario</label>
              <input
                v-model="form.username"
                required
                type="text"
                minlength="4"
                maxlength="50"
                pattern="[A-Za-z0-9_.\-]{4,50}"
                title="Entre 4 y 50 caracteres: letras, números, punto, guion o guion bajo."
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                :class="errors.username ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
              />
              <p v-if="errors.username" class="text-xs text-red-600 mt-1">{{ errors.username }}</p>
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Correo</label>
              <input
                v-model="form.email"
                required
                type="email"
                maxlength="150"
                class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                :class="errors.email ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
              />
              <p v-if="errors.email" class="text-xs text-red-600 mt-1">{{ errors.email }}</p>
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Contraseña</label>
              <div class="relative">
                <input
                  v-model="form.password"
                  required
                  :type="showPassword ? 'text' : 'password'"
                  minlength="8"
                  class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
                  :class="passwordTooShort || errors.password ? 'border-red-300' : ''"
                />
                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100" @click="showPassword = !showPassword" tabindex="-1">
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <p v-if="passwordTooShort" class="text-xs text-red-600 mt-1">La contraseña debe tener al menos 8 caracteres.</p>
              <p v-else-if="errors.password" class="text-xs text-red-600 mt-1">{{ errors.password }}</p>
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Confirmar contraseña</label>
              <div class="relative">
                <input
                  v-model="form.password_confirmation"
                  required
                  :type="showPasswordConfirm ? 'text' : 'password'"
                  minlength="8"
                  class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
                  :class="passwordMismatch ? 'border-red-300' : ''"
                />
                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100" @click="showPasswordConfirm = !showPasswordConfirm" tabindex="-1">
                  <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <p v-if="passwordMismatch" class="text-xs text-red-600 mt-1">Las contraseñas no coinciden.</p>
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-2">Rol asignado</label>
              <RoleDirectoryPicker v-model="selectedRoleId" :roles="roles" radio-name="user-create-role" />
              <p v-if="errors.roles" class="text-xs text-red-600 mt-1">{{ errors.roles }}</p>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex flex-wrap items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="close">Cancelar</button>
            <button
              type="submit"
              class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark transition-colors disabled:opacity-60"
              :disabled="loading || !form.person_id || !selectedRoleId || passwordTooShort || passwordMismatch"
            >
              Crear cuenta
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import axios from '@/services/axios';
import { Search, Loader2, X, Eye, EyeOff } from 'lucide-vue-next';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';
import RoleDirectoryPicker from '@/components/security-config/RoleDirectoryPicker.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  roles: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'reload', 'show-result', 'created']);

const loading = ref(false);
const modalError = ref('');
const errors = ref({});
const selectedRoleId = ref(null);

const emptyForm = () => ({ person_id: '', username: '', email: '', password: '', password_confirmation: '' });
const form = ref(emptyForm());

const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const passwordTooShort = computed(() => form.value.password.length > 0 && form.value.password.length < 8);
const passwordMismatch = computed(() => form.value.password_confirmation.length > 0 && form.value.password !== form.value.password_confirmation);

const personSearchQuery = ref('');
const personSearchResults = ref([]);
const isPersonDropdownOpen = ref(false);
const isSearchingPerson = ref(false);
const personSearchContainer = ref(null);
let personSearchTimeout = null;

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  form.value = emptyForm();
  errors.value = {};
  modalError.value = '';
  selectedRoleId.value = null;
  personSearchQuery.value = '';
  personSearchResults.value = [];
  showPassword.value = false;
  showPasswordConfirm.value = false;
});

const handlePersonSearch = () => {
  if (personSearchQuery.value.length < 2) {
    personSearchResults.value = [];
    return;
  }
  isSearchingPerson.value = true;
  isPersonDropdownOpen.value = true;
  clearTimeout(personSearchTimeout);
  personSearchTimeout = setTimeout(async () => {
    try {
      const response = await axios.get('/admin/users/search-persons', {
        params: { q: personSearchQuery.value },
        skipGlobalLoading: true,
      });
      if (response.data.success) personSearchResults.value = response.data.data;
    } catch (error) {
      console.error(error);
    } finally {
      isSearchingPerson.value = false;
    }
  }, 300);
};

const selectPerson = (person) => {
  form.value.person_id = person.id;
  personSearchQuery.value = person.label;
  isPersonDropdownOpen.value = false;
};

const clearPersonSelection = () => {
  form.value.person_id = '';
  personSearchQuery.value = '';
  personSearchResults.value = [];
};

const close = () => emit('close');

const submit = async () => {
  errors.value = {};
  modalError.value = '';
  loading.value = true;
  try {
    await axios.post('/admin/users', { ...form.value, roles: [selectedRoleId.value] });
    emit('reload');
    emit('close');
    emit('created');
    emit('show-result', true, 'Usuario creado', 'La cuenta se creó exitosamente.');
  } catch (error) {
    errors.value = extractFieldErrors(error);
    const message = buildErrorMessage(error, 'Error al crear usuario.');
    modalError.value = message;
    emit('show-result', false, 'No se pudo crear el usuario', message);
  } finally {
    loading.value = false;
  }
};

const handleClickOutside = (event) => {
  if (personSearchContainer.value && !personSearchContainer.value.contains(event.target)) {
    isPersonDropdownOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>
