<template>
  <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 flex flex-col">
    <h2 class="text-base font-semibold text-gray-900 mb-3">Crear Usuario</h2>
    <form class="space-y-3" @submit.prevent="createUser">

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
              :class="createErrors.person_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
            >
            <button
              v-if="createForm.person_id"
              type="button"
              @click="clearPersonSelection"
              class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-red-500 rounded-full hover:bg-gray-100"
            >
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
                :class="{'bg-gray-50 text-aso-primary font-medium': createForm.person_id === person.id}"
              >
                {{ person.label }}
              </li>
            </ul>
          </div>
        </div>
        <p v-if="createErrors.person_id" class="text-xs text-red-600 mt-1">{{ createErrors.person_id }}</p>
      </div>

      <div>
        <label class="block text-sm text-gray-700 mb-1">Usuario</label>
        <input
          v-model="createForm.username"
          required
          type="text"
          minlength="4"
          maxlength="50"
          pattern="[A-Za-z0-9_.\-]{4,50}"
          title="Entre 4 y 50 caracteres: letras, números, punto, guion o guion bajo."
          class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
          :class="createErrors.username ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
        />
        <p v-if="createErrors.username" class="text-xs text-red-600 mt-1">{{ createErrors.username }}</p>
      </div>

      <div>
        <label class="block text-sm text-gray-700 mb-1">Correo</label>
        <input
          v-model="createForm.email"
          required
          type="email"
          maxlength="150"
          class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
          :class="createErrors.email ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
        />
        <p v-if="createErrors.email" class="text-xs text-red-600 mt-1">{{ createErrors.email }}</p>
      </div>

      <div>
        <label class="block text-sm text-gray-700 mb-1">Contraseña</label>
        <div class="relative">
          <input
            v-model="createForm.password"
            required
            :type="showCreatePassword ? 'text' : 'password'"
            minlength="8"
            class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
            :class="createPasswordTooShort || createErrors.password ? 'border-red-300' : ''"
          />
          <button
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
            @click="showCreatePassword = !showCreatePassword"
            tabindex="-1"
          >
            <EyeOff v-if="showCreatePassword" class="w-4 h-4" />
            <Eye v-else class="w-4 h-4" />
          </button>
        </div>
        <p v-if="createPasswordTooShort" class="text-xs text-red-600 mt-1">La contraseña debe tener al menos 8 caracteres.</p>
        <p v-else-if="createErrors.password" class="text-xs text-red-600 mt-1">{{ createErrors.password }}</p>
      </div>

      <div>
        <label class="block text-sm text-gray-700 mb-1">Confirmar contraseña</label>
        <div class="relative">
          <input
            v-model="createForm.password_confirmation"
            required
            :type="showCreatePasswordConfirm ? 'text' : 'password'"
            minlength="8"
            class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
            :class="createPasswordMismatch ? 'border-red-300' : ''"
          />
          <button
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
            @click="showCreatePasswordConfirm = !showCreatePasswordConfirm"
            tabindex="-1"
          >
            <EyeOff v-if="showCreatePasswordConfirm" class="w-4 h-4" />
            <Eye v-else class="w-4 h-4" />
          </button>
        </div>
        <p v-if="createPasswordMismatch" class="text-xs text-red-600 mt-1">Las contraseñas no coinciden.</p>
      </div>

      <div>
        <label class="block text-sm text-gray-700 mb-1">Roles iniciales</label>
        <select
          v-model="createForm.roles"
          multiple
          required
          class="w-full min-h-28 px-3 py-2 rounded-lg border bg-white text-sm"
          :class="createErrors.roles ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
        >
          <option v-for="role in roles" :key="role.id" :value="role.id">
            {{ role.display_name }} ({{ role.name }})
          </option>
        </select>
        <p v-if="createErrors.roles" class="text-xs text-red-600 mt-1">{{ createErrors.roles }}</p>
      </div>

      <button
        type="submit"
        class="w-full py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-60"
        :disabled="loading || createForm.roles.length === 0 || !createForm.person_id || createPasswordTooShort || createPasswordMismatch"
      >
        Crear cuenta
      </button>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from '@/services/axios';
import { Search, Loader2, X, Eye, EyeOff } from 'lucide-vue-next';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';

defineProps({
  roles: { type: Array, default: () => [] },
});

const emit = defineEmits(['reload', 'show-result']);

const loading = ref(false);

const createForm = ref({
  person_id: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  roles: [],
});
const createErrors = ref({});

const showCreatePassword = ref(false);
const showCreatePasswordConfirm = ref(false);
const createPasswordTooShort = computed(() => createForm.value.password.length > 0 && createForm.value.password.length < 8);
const createPasswordMismatch = computed(() => createForm.value.password_confirmation.length > 0 && createForm.value.password !== createForm.value.password_confirmation);

const personSearchQuery = ref('');
const personSearchResults = ref([]);
const isPersonDropdownOpen = ref(false);
const isSearchingPerson = ref(false);
const personSearchContainer = ref(null);
let personSearchTimeout = null;

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
  createForm.value.person_id = person.id;
  personSearchQuery.value = person.label;
  isPersonDropdownOpen.value = false;
};

const clearPersonSelection = () => {
  createForm.value.person_id = '';
  personSearchQuery.value = '';
  personSearchResults.value = [];
};

const resetCreateForm = () => {
  createForm.value = { person_id: '', username: '', email: '', password: '', password_confirmation: '', roles: [] };
  personSearchQuery.value = '';
  personSearchResults.value = [];
  showCreatePassword.value = false;
  showCreatePasswordConfirm.value = false;
};

const createUser = async () => {
  createErrors.value = {};
  loading.value = true;
  try {
    await axios.post('/admin/users', createForm.value);
    resetCreateForm();
    emit('reload');
    emit('show-result', true, 'Usuario creado', 'La cuenta se creó exitosamente.');
  } catch (error) {
    createErrors.value = extractFieldErrors(error);
    emit('show-result', false, 'No se pudo crear el usuario', buildErrorMessage(error, 'Error al crear usuario.'));
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
