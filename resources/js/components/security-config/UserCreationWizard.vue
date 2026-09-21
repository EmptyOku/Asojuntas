<template>
  <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
      <div>
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Creación de usuarios</h1>
        <p class="text-sm text-gray-500 mt-1">Primero se registra la persona y luego su cuenta, que queda vinculada automáticamente.</p>
      </div>
      <button
        type="button"
        class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors"
        @click="cancelWizard"
      >
        Cancelar
      </button>
    </div>

    <!-- Stepper -->
    <div class="flex items-center justify-center gap-2 sm:gap-3 mb-8">
      <div class="flex items-center gap-2">
        <div
          class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
          :class="step > 1 ? 'bg-emerald-500 text-white' : 'bg-aso-primary text-white'"
        >
          <Check v-if="step > 1" class="w-4 h-4" />
          <span v-else class="text-sm font-semibold">1</span>
        </div>
        <span class="text-sm font-medium" :class="step === 1 ? 'text-gray-900' : 'text-gray-500'">Persona</span>
      </div>
      <div class="w-8 sm:w-24 h-0.5" :class="step > 1 ? 'bg-emerald-500' : 'bg-gray-200'"></div>
      <div class="flex items-center gap-2">
        <div
          class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
          :class="step === 2 ? 'bg-aso-primary text-white' : 'bg-gray-200 text-gray-500'"
        >
          <span class="text-sm font-semibold">2</span>
        </div>
        <span class="text-sm font-medium" :class="step === 2 ? 'text-gray-900' : 'text-gray-500'">Cuenta</span>
      </div>
    </div>

    <div v-if="wizardError" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
      {{ wizardError }}
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50 p-5 sm:p-6">
      <form @submit.prevent="handleSubmit">
        <!-- Paso 1: Persona -->
        <div v-if="step === 1" class="space-y-6">
          <h2 class="text-base font-semibold text-gray-900">Crear Persona</h2>

          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Identificación</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                <input
                  v-model="form.document_number"
                  required
                  type="text"
                  maxlength="30"
                  pattern="[A-Za-z0-9]{5,30}"
                  title="Debe contener solo letras y números, entre 5 y 30 caracteres."
                  class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                  :class="errors.document_number ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                />
                <p v-if="errors.document_number" class="text-xs text-red-600 mt-1">{{ errors.document_number }}</p>
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Nombre completo</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Nombre</label>
                <input
                  v-model="form.first_name"
                  required
                  type="text"
                  maxlength="100"
                  pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]+"
                  title="Solo letras y espacios."
                  class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                  :class="errors.first_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                />
                <p v-if="errors.first_name" class="text-xs text-red-600 mt-1">{{ errors.first_name }}</p>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Nombre</label>
                <input
                  v-model="form.middle_name"
                  type="text"
                  maxlength="100"
                  pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]*"
                  title="Solo letras y espacios."
                  class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                  :class="errors.middle_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                  placeholder="Opcional"
                />
                <p v-if="errors.middle_name" class="text-xs text-red-600 mt-1">{{ errors.middle_name }}</p>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Apellido</label>
                <input
                  v-model="form.last_name"
                  required
                  type="text"
                  maxlength="100"
                  pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]+"
                  title="Solo letras y espacios."
                  class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                  :class="errors.last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                />
                <p v-if="errors.last_name" class="text-xs text-red-600 mt-1">{{ errors.last_name }}</p>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Apellido</label>
                <input
                  v-model="form.second_last_name"
                  type="text"
                  maxlength="100"
                  pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]*"
                  title="Solo letras y espacios."
                  class="w-full px-3 py-2 rounded-lg border bg-white text-sm"
                  :class="errors.second_last_name ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                  placeholder="Opcional"
                />
                <p v-if="errors.second_last_name" class="text-xs text-red-600 mt-1">{{ errors.second_last_name }}</p>
              </div>
            </div>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Ubicación</p>
            <p class="text-xs text-gray-500 mb-2">Opcional, salvo que la cuenta vaya a tener el rol de Jurado (p. ej. personal de Asojuntas sin barrio asignado).</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Comuna</label>
                <select
                  v-model="selectedCommune"
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
                  v-model="form.neighborhood_id"
                  :disabled="!selectedCommune || loadingNeighborhoods"
                  class="w-full px-3 py-2 rounded-lg border bg-white text-sm cursor-pointer focus:ring-2 focus:ring-aso-primary"
                  :class="errors.neighborhood_id ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-200'"
                >
                  <option value="">{{ selectedCommune ? 'Seleccione un barrio...' : 'Primero seleccione una comuna...' }}</option>
                  <option v-for="item in neighborhoodsList" :key="item.id" :value="item.id">
                    {{ item.name }}
                  </option>
                </select>
                <p v-if="errors.neighborhood_id" class="text-xs text-red-600 mt-1">{{ errors.neighborhood_id }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Paso 2: Cuenta -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">
          <div class="space-y-4">
            <div>
              <h2 class="text-base font-semibold text-gray-900">Crear Usuario</h2>
              <p class="text-sm text-gray-500">
                Cuenta para <span class="font-medium text-gray-700">{{ personFullName || 'la persona registrada' }}</span>.
              </p>
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
          </div>

          <div class="lg:sticky lg:top-4">
            <label class="block text-sm text-gray-700 mb-1">Rol asignado</label>
            <p class="text-xs text-gray-500 mb-2">Revisa los permisos de cada rol antes de asignarlo.</p>
            <div v-if="missingNeighborhoodForRole" class="mb-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 text-xs flex items-start justify-between gap-2">
              <span>El rol Jurado requiere un barrio asignado a la persona.</span>
              <button type="button" class="underline shrink-0" @click="step = 1">Volver al paso 1</button>
            </div>
            <RoleDirectoryPicker v-model="selectedRoleId" :roles="roles" radio-name="wizard-account-role" />
            <p v-if="errors.roles" class="text-xs text-red-600 mt-1">{{ errors.roles }}</p>
          </div>
        </div>

        <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200">
          <button
            type="button"
            class="p-2.5 rounded-full border border-gray-200 text-gray-500 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="step === 1"
            @click="step = 1"
            aria-label="Atrás"
          >
            <ArrowLeft class="w-4 h-4" />
          </button>

          <button
            v-if="step === 1"
            type="submit"
            class="px-8 py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-60"
            :disabled="loading"
          >
            {{ loading ? 'Guardando...' : 'Continuar' }}
          </button>
          <button
            v-else
            type="submit"
            class="px-8 py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-60"
            :disabled="loading || !selectedRoleId || passwordTooShort || passwordMismatch || missingNeighborhoodForRole"
          >
            Crear cuenta
          </button>

          <button
            type="button"
            class="p-2.5 rounded-full border border-gray-200 text-gray-500 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
            disabled
            aria-label="Adelante"
          >
            <ArrowRight class="w-4 h-4" />
          </button>
        </div>
      </form>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from '@/services/axios';
import { Check, Eye, EyeOff, ArrowLeft, ArrowRight } from 'lucide-vue-next';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';
import RoleDirectoryPicker from '@/components/security-config/RoleDirectoryPicker.vue';

const props = defineProps({
  communes: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
});

const emit = defineEmits(['reload', 'show-result', 'created']);

const step = ref(1);
const loading = ref(false);
const wizardError = ref('');
const errors = ref({});

const selectedCommune = ref('');
const neighborhoodsList = ref([]);
const loadingNeighborhoods = ref(false);
const selectedRoleId = ref(null);

const showPassword = ref(false);
const showPasswordConfirm = ref(false);

// Id de la persona ya creada (o actualizada) en el paso 1. Mientras sea null no se puede
// avanzar al paso 2: la persona debe existir en la base de datos antes de crear la cuenta.
const createdPersonId = ref(null);

const emptyForm = () => ({
  document_type_id: '', document_number: '', first_name: '', middle_name: '',
  last_name: '', second_last_name: '', neighborhood_id: '',
  username: '', email: '', password: '', password_confirmation: '',
});
const form = ref(emptyForm());

const passwordTooShort = computed(() => form.value.password.length > 0 && form.value.password.length < 8);
const passwordMismatch = computed(() => form.value.password_confirmation.length > 0 && form.value.password !== form.value.password_confirmation);

const personFullName = computed(() => [form.value.first_name, form.value.middle_name, form.value.last_name, form.value.second_last_name]
  .filter(Boolean)
  .join(' '));

// El barrio/comuna es opcional en general (hay cuentas de personal de Asojuntas sin barrio),
// pero el backend exige barrio cuando el rol asignado es Jurado (digitizer).
const selectedRole = computed(() => props.roles.find((role) => role.id === selectedRoleId.value));
const missingNeighborhoodForRole = computed(() => selectedRole.value?.name === 'digitizer' && !form.value.neighborhood_id);

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
    wizardError.value = 'No fue posible cargar los barrios.';
  } finally {
    loadingNeighborhoods.value = false;
  }
});

const resetWizard = () => {
  form.value = emptyForm();
  errors.value = {};
  wizardError.value = '';
  step.value = 1;
  selectedCommune.value = '';
  neighborhoodsList.value = [];
  selectedRoleId.value = null;
  createdPersonId.value = null;
  showPassword.value = false;
  showPasswordConfirm.value = false;
};

const cancelWizard = () => {
  const hasProgress = step.value === 2 || form.value.document_number || form.value.first_name || form.value.last_name;
  if (hasProgress && !confirm('¿Deseas cancelar? Se perderán los datos no guardados del formulario.')) return;
  resetWizard();
};

const personPayload = () => ({
  document_type_id: form.value.document_type_id,
  document_number: form.value.document_number,
  first_name: form.value.first_name,
  middle_name: form.value.middle_name || null,
  last_name: form.value.last_name,
  second_last_name: form.value.second_last_name || null,
  neighborhood_id: form.value.neighborhood_id || null,
  is_active: true,
});

// Paso 1: crea (o actualiza, si ya se había creado y el usuario volvió atrás a corregir
// algo) la persona en el backend antes de dejar avanzar al paso 2.
const submitPersonStep = async () => {
  errors.value = {};
  wizardError.value = '';

  loading.value = true;
  try {
    const { data } = createdPersonId.value
      ? await axios.put(`/admin/persons/${createdPersonId.value}`, personPayload())
      : await axios.post('/admin/persons', personPayload());
    createdPersonId.value = data?.data?.id ?? createdPersonId.value;
    step.value = 2;
  } catch (error) {
    const fieldErrors = extractFieldErrors(error);
    errors.value = fieldErrors;
    const message = buildErrorMessage(error, 'No fue posible registrar la persona.');
    wizardError.value = message;
    emit('show-result', false, 'No se pudo registrar la persona', message);
  } finally {
    loading.value = false;
  }
};

const submitAccountStep = async () => {
  errors.value = {};
  wizardError.value = '';
  loading.value = true;
  try {
    await axios.post('/admin/users', {
      person_id: createdPersonId.value,
      username: form.value.username,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
      roles: [selectedRoleId.value],
    });
    resetWizard();
    emit('reload');
    emit('created');
    emit('show-result', true, 'Registro creado', 'La persona y su cuenta de usuario se crearon exitosamente.');
  } catch (error) {
    const fieldErrors = extractFieldErrors(error);
    errors.value = fieldErrors;
    const message = buildErrorMessage(error, 'No fue posible crear la cuenta.');
    wizardError.value = message;
    // El barrio vive en el paso 1: si el backend lo rechaza (p. ej. falta para rol Jurado),
    // hay que volver ahí para que el usuario vea el campo resaltado.
    if (fieldErrors.neighborhood_id) {
      step.value = 1;
    }
    emit('show-result', false, 'No se pudo crear la cuenta', message);
  } finally {
    loading.value = false;
  }
};

const handleSubmit = () => (step.value === 1 ? submitPersonStep() : submitAccountStep());
</script>
