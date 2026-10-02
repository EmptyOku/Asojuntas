<template>
  <div class="space-y-6">
    <!-- Encabezado con el paso actual -->
    <section class="card p-5 sm:p-7 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
      <div class="min-w-0">
        <h2 class="page-title">{{ step === 1 ? 'Datos de la persona' : 'Cuenta de acceso' }}</h2>
        <p class="page-subtitle max-w-xl">
          <template v-if="step === 1">Quién usará la cuenta. Nada se guarda hasta el último paso: la persona y su cuenta se registran juntas.</template>
          <template v-else>
            Cuenta de acceso para <span class="font-semibold text-gray-700">{{ personFullName || 'la persona registrada' }}</span>.
          </template>
        </p>
      </div>

      <ol class="flex items-center gap-3 sm:gap-4 shrink-0" aria-label="Pasos">
        <li class="flex items-center gap-3">
          <span
            class="flex h-11 w-11 items-center justify-center rounded-full font-display font-bold transition-all duration-300"
            :class="step > 1 ? 'bg-aso-primary text-white' : 'bg-aso-primary text-white ring-4 ring-aso-primary/15'"
          >
            <Check v-if="step > 1" class="w-5 h-5" />
            <template v-else>1</template>
          </span>
          <span class="text-sm font-semibold text-gray-900">Persona</span>
        </li>
        <li aria-hidden="true" class="h-0.5 w-10 sm:w-16 rounded-full bg-gray-200 overflow-hidden">
          <span class="block h-full bg-aso-primary transition-all duration-500" :class="step > 1 ? 'w-full' : 'w-0'"></span>
        </li>
        <li class="flex items-center gap-3" :aria-current="step === 2 ? 'step' : undefined">
          <span
            class="flex h-11 w-11 items-center justify-center rounded-full font-display font-bold transition-all duration-300"
            :class="step === 2 ? 'bg-aso-primary text-white ring-4 ring-aso-primary/15' : 'bg-gray-100 text-gray-500'"
          >
            2
          </span>
          <span class="text-sm font-semibold" :class="step === 2 ? 'text-gray-900' : 'text-gray-500'">Cuenta</span>
        </li>
      </ol>
    </section>

    <div v-if="wizardError" class="rounded-2xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-start gap-2 animate-rise">
      <AlertCircle class="w-4 h-4 mt-0.5 shrink-0" />
      {{ wizardError }}
    </div>

    <form class="card p-5 sm:p-8" @submit.prevent="handleSubmit">
      <!-- Paso 1: Persona -->
      <div v-if="step === 1" :key="'step-1'" class="space-y-9 animate-rise">
        <section>
          <div class="form-section-title">
            <h3>Identificación</h3>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
            <div>
              <label class="field-label" for="wz-doc-type">Tipo de documento</label>
              <select id="wz-doc-type" v-model="form.document_type_id" required class="field" :class="{ 'field-error': errors.document_type_id }">
                <option value="" disabled>Seleccione...</option>
                <option value="1">Cédula de Ciudadanía (CC)</option>
                <option value="2">Tarjeta de Identidad (TI)</option>
                <option value="3">Cédula de Extranjería (CE)</option>
              </select>
              <p v-if="errors.document_type_id" class="text-xs text-red-600 mt-1.5">{{ errors.document_type_id }}</p>
            </div>
            <div>
              <label class="field-label" for="wz-doc-number">Número de documento</label>
              <input
                id="wz-doc-number"
                v-model="form.document_number"
                required
                type="text"
                inputmode="numeric"
                maxlength="30"
                pattern="[A-Za-z0-9]{5,30}"
                title="Debe contener solo letras y números, entre 5 y 30 caracteres."
                placeholder="Ej: 1070123456"
                class="field"
                :class="{ 'field-error': errors.document_number }"
              />
              <p v-if="errors.document_number" class="text-xs text-red-600 mt-1.5">{{ errors.document_number }}</p>
            </div>
          </div>
        </section>

        <section>
          <div class="form-section-title">
            <h3>Nombre completo</h3>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-5">
            <div>
              <label class="field-label" for="wz-first-name">Primer nombre</label>
              <input
                id="wz-first-name"
                v-model="form.first_name"
                required
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]+"
                title="Solo letras y espacios."
                class="field"
                :class="{ 'field-error': errors.first_name }"
              />
              <p v-if="errors.first_name" class="text-xs text-red-600 mt-1.5">{{ errors.first_name }}</p>
            </div>
            <div>
              <label class="field-label" for="wz-middle-name">
                Segundo nombre <span class="normal-case tracking-normal font-medium text-gray-400">(opcional)</span>
              </label>
              <input
                id="wz-middle-name"
                v-model="form.middle_name"
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]*"
                title="Solo letras y espacios."
                placeholder="Opcional"
                class="field field-optional"
                :class="{ 'field-error': errors.middle_name }"
              />
              <p v-if="errors.middle_name" class="text-xs text-red-600 mt-1.5">{{ errors.middle_name }}</p>
            </div>
            <div>
              <label class="field-label" for="wz-last-name">Primer apellido</label>
              <input
                id="wz-last-name"
                v-model="form.last_name"
                required
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]+"
                title="Solo letras y espacios."
                class="field"
                :class="{ 'field-error': errors.last_name }"
              />
              <p v-if="errors.last_name" class="text-xs text-red-600 mt-1.5">{{ errors.last_name }}</p>
            </div>
            <div>
              <label class="field-label" for="wz-second-last-name">
                Segundo apellido <span class="normal-case tracking-normal font-medium text-gray-400">(opcional)</span>
              </label>
              <input
                id="wz-second-last-name"
                v-model="form.second_last_name"
                type="text"
                maxlength="100"
                pattern="[A-Za-zÁÉÍÓÚÑÜáéíóúñü\s]*"
                title="Solo letras y espacios."
                placeholder="Opcional"
                class="field field-optional"
                :class="{ 'field-error': errors.second_last_name }"
              />
              <p v-if="errors.second_last_name" class="text-xs text-red-600 mt-1.5">{{ errors.second_last_name }}</p>
            </div>
          </div>
        </section>

        <section>
          <div class="form-section-title form-section-title--amber">
            <h3>Ubicación</h3>
            <span class="badge-amber">Requerido para jurados</span>
          </div>
          <p class="-mt-2 mb-4 text-sm text-gray-500">
            Solo es obligatoria si la cuenta va a tener un rol que opera dentro de un barrio (p. ej. Jurado).
            El personal de Asojuntas puede quedar sin barrio.
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
            <div>
              <label class="field-label" for="wz-commune">Comuna</label>
              <select id="wz-commune" v-model="selectedCommune" class="field cursor-pointer">
                <option value="">Seleccione una comuna...</option>
                <option v-for="item in communes" :key="item.id" :value="String(item.id)">
                  {{ item.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="field-label" for="wz-neighborhood">Barrio de residencia</label>
              <div class="relative">
                <select
                  id="wz-neighborhood"
                  v-model="form.neighborhood_id"
                  :disabled="!selectedCommune || loadingNeighborhoods"
                  class="field cursor-pointer"
                  :class="{ 'field-error': errors.neighborhood_id }"
                >
                  <option value="">{{ selectedCommune ? 'Seleccione un barrio...' : 'Primero seleccione una comuna...' }}</option>
                  <option v-for="item in neighborhoodsList" :key="item.id" :value="item.id">
                    {{ item.name }}
                  </option>
                </select>
                <Loader2 v-if="loadingNeighborhoods" class="w-4 h-4 text-aso-primary animate-spin absolute right-10 top-1/2 -translate-y-1/2" />
              </div>
              <p v-if="errors.neighborhood_id" class="text-xs text-red-600 mt-1.5">{{ errors.neighborhood_id }}</p>
            </div>
          </div>
        </section>
      </div>

      <!-- Paso 2: Cuenta -->
      <div v-else :key="'step-2'" class="space-y-9 animate-rise">
        <section>
          <div class="form-section-title">
            <h3>Datos de acceso</h3>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
            <div>
              <label class="field-label" for="wz-email">Correo electrónico</label>
              <div class="relative">
                <Mail class="field-icon" />
                <input
                  id="wz-email"
                  v-model="form.email"
                  required
                  type="email"
                  maxlength="150"
                  autocomplete="off"
                  placeholder="ejemplo@asojuntas.org"
                  class="field pl-10"
                  :class="{ 'field-error': errors.email }"
                />
              </div>
              <p v-if="errors.email" class="text-xs text-red-600 mt-1.5">{{ errors.email }}</p>
            </div>
            <div>
              <label class="field-label" for="wz-username">Nombre de usuario</label>
              <div class="relative">
                <AtSign class="field-icon" />
                <input
                  id="wz-username"
                  v-model="form.username"
                  required
                  type="text"
                  minlength="4"
                  maxlength="50"
                  autocomplete="off"
                  pattern="[A-Za-z0-9_.\-]{4,50}"
                  title="Entre 4 y 50 caracteres: letras, números, punto, guion o guion bajo."
                  placeholder="jperez"
                  class="field pl-10"
                  :class="{ 'field-error': errors.username }"
                />
              </div>
              <p v-if="errors.username" class="text-xs text-red-600 mt-1.5">{{ errors.username }}</p>
            </div>
          </div>
        </section>

        <section>
          <div class="form-section-title">
            <h3>Seguridad</h3>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
            <div>
              <label class="field-label" for="wz-password">Contraseña</label>
              <div class="relative">
                <Lock class="field-icon" />
                <input
                  id="wz-password"
                  v-model="form.password"
                  required
                  :type="showPassword ? 'text' : 'password'"
                  minlength="8"
                  autocomplete="new-password"
                  placeholder="Mínimo 8 caracteres"
                  class="field pl-10 pr-11"
                  :class="{ 'field-error': passwordTooShort || errors.password }"
                />
                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-200/60" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" tabindex="-1" @click="showPassword = !showPassword">
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <div class="mt-2 flex gap-1.5" aria-hidden="true">
                <span v-for="n in 4" :key="n" class="h-1 flex-1 rounded-full transition-colors duration-300" :class="n <= passwordStrength ? strengthColor : 'bg-gray-200'"></span>
              </div>
              <p v-if="passwordTooShort" class="text-xs text-red-600 mt-1.5">La contraseña debe tener al menos 8 caracteres.</p>
              <p v-else-if="errors.password" class="text-xs text-red-600 mt-1.5">{{ errors.password }}</p>
              <p v-else-if="form.password" class="text-xs text-gray-500 mt-1.5">Seguridad: {{ strengthLabel }}</p>
            </div>
            <div>
              <label class="field-label" for="wz-password-confirm">Confirmar contraseña</label>
              <div class="relative">
                <Lock class="field-icon" />
                <input
                  id="wz-password-confirm"
                  v-model="form.password_confirmation"
                  required
                  :type="showPasswordConfirm ? 'text' : 'password'"
                  minlength="8"
                  autocomplete="new-password"
                  placeholder="Repita la contraseña"
                  class="field pl-10 pr-11"
                  :class="{ 'field-error': passwordMismatch }"
                />
                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-200/60" :aria-label="showPasswordConfirm ? 'Ocultar contraseña' : 'Mostrar contraseña'" tabindex="-1" @click="showPasswordConfirm = !showPasswordConfirm">
                  <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <p v-if="passwordMismatch" class="text-xs text-red-600 mt-1.5">Las contraseñas no coinciden.</p>
              <p v-else-if="form.password_confirmation && !passwordTooShort" class="text-xs text-emerald-600 mt-1.5 flex items-center gap-1">
                <Check class="w-3.5 h-3.5" /> Las contraseñas coinciden.
              </p>
            </div>
          </div>
        </section>

        <section>
          <div class="form-section-title form-section-title--amber">
            <h3>Rol asignado</h3>
          </div>

          <div v-if="missingNeighborhoodForRole" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2">
            <span>Este rol opera dentro de un barrio: asigna un barrio a la persona en el paso 1.</span>
            <button type="button" class="font-semibold underline underline-offset-2" @click="step = 1">Volver al paso 1</button>
          </div>

          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3" role="radiogroup" aria-label="Rol asignado">
            <button
              v-for="role in roles"
              :key="role.id"
              type="button"
              role="radio"
              :aria-checked="selectedRoleId === role.id"
              class="relative rounded-xl border-2 px-4 py-4 text-sm font-semibold text-center transition-all duration-200 focus:outline-none focus-visible:ring-4 focus-visible:ring-aso-primary/20"
              :class="selectedRoleId === role.id
                ? 'border-aso-primary bg-emerald-50 text-aso-primary-dark shadow-sm shadow-aso-primary/10'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:-translate-y-0.5'"
              @click="selectedRoleId = role.id"
            >
              <Check v-if="selectedRoleId === role.id" class="w-3.5 h-3.5 absolute top-2 right-2" stroke-width="3" />
              {{ role.display_name }}
              <span v-if="role.requires_neighborhood" class="block mt-1 text-[11px] font-medium text-gray-400">Requiere barrio</span>
            </button>
          </div>
          <p v-if="!roles.length" class="text-sm text-gray-400 italic">No hay roles disponibles.</p>
          <p v-if="errors.roles" class="text-xs text-red-600 mt-2">{{ errors.roles }}</p>

          <!-- Qué podrá hacer con el rol elegido -->
          <div v-if="selectedRole" class="mt-4 rounded-xl bg-gray-50 px-4 py-3.5 animate-fade">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">
              Con «{{ selectedRole.display_name }}» podrá:
            </p>
            <ul v-if="(selectedRole.permissions || []).length" class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5">
              <li v-for="perm in selectedRole.permissions" :key="perm.id" class="flex items-start gap-2 text-sm text-gray-700">
                <Check class="w-3.5 h-3.5 mt-0.5 text-emerald-500 shrink-0" stroke-width="3" />
                {{ perm.description || perm.display_name || perm.name }}
              </li>
            </ul>
            <p v-else class="text-sm text-gray-400 italic">Este rol no tiene permisos asignados.</p>
          </div>
        </section>
      </div>

      <!-- Acciones -->
      <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-6 mt-9 border-t border-gray-100">
        <div class="flex gap-2">
          <button v-if="step === 2" type="button" class="btn-secondary" @click="step = 1">
            <ArrowLeft class="w-4 h-4" />
            Atrás
          </button>
          <button type="button" class="btn px-4 text-gray-500 hover:text-red-600 hover:bg-red-50" @click="cancelWizard">
            Cancelar
          </button>
        </div>

        <button
          v-if="step === 1"
          type="submit"
          class="btn-primary px-6"
        >
          Continuar
          <ArrowRight class="w-4 h-4" />
        </button>
        <button
          v-else
          type="submit"
          class="btn-primary px-6"
          :disabled="loading || !selectedRoleId || passwordTooShort || passwordMismatch || missingNeighborhoodForRole"
        >
          <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
          <UserPlus v-else class="w-4 h-4" />
          {{ loading ? 'Creando...' : 'Registrar en el sistema' }}
        </button>
      </div>
    </form>

    <ConfirmModal
      :open="confirmCancel"
      title="¿Cancelar la creación del usuario?"
      message="Se perderán los datos del formulario que aún no se han guardado."
      confirm-text="Sí, cancelar"
      cancel-text="Seguir editando"
      danger
      @confirm="confirmCancel = false; resetWizard()"
      @cancel="confirmCancel = false"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from '@/services/axios';
import {
  AlertCircle, ArrowLeft, ArrowRight, AtSign, Check, Eye, EyeOff, Loader2, Lock, Mail, UserPlus
} from 'lucide-vue-next';
import { extractFieldErrors, buildErrorMessage } from '@/utils/formErrors';
import ConfirmModal from '@/components/ConfirmModal.vue';

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

const emptyForm = () => ({
  document_type_id: '', document_number: '', first_name: '', middle_name: '',
  last_name: '', second_last_name: '', neighborhood_id: '',
  username: '', email: '', password: '', password_confirmation: '',
});
const form = ref(emptyForm());

const passwordTooShort = computed(() => form.value.password.length > 0 && form.value.password.length < 8);
const passwordMismatch = computed(() => form.value.password_confirmation.length > 0 && form.value.password !== form.value.password_confirmation);

// Indicador orientativo (0-4): largo, mayúsculas y minúsculas, números, símbolos.
// Solo informa; la regla que se exige sigue siendo la del backend (mínimo 8).
const passwordStrength = computed(() => {
  const value = form.value.password;
  if (!value) return 0;
  return [
    value.length >= 8,
    /[a-z]/.test(value) && /[A-Z]/.test(value),
    /\d/.test(value),
    /[^A-Za-z0-9]/.test(value) || value.length >= 12,
  ].filter(Boolean).length;
});
const strengthLabel = computed(() => ['muy débil', 'débil', 'aceptable', 'buena', 'fuerte'][passwordStrength.value]);
const strengthColor = computed(() => ['bg-red-400', 'bg-red-400', 'bg-amber-400', 'bg-lime-500', 'bg-emerald-500'][passwordStrength.value]);

const personFullName = computed(() => [form.value.first_name, form.value.middle_name, form.value.last_name, form.value.second_last_name]
  .filter(Boolean)
  .join(' '));

// El barrio/comuna es opcional en general (hay cuentas de personal de Asojuntas sin barrio),
// pero el backend exige barrio cuando el rol solo opera dentro de un barrio (role.requires_neighborhood).
const selectedRole = computed(() => props.roles.find((role) => role.id === selectedRoleId.value));
const missingNeighborhoodForRole = computed(() => Boolean(selectedRole.value?.requires_neighborhood) && !form.value.neighborhood_id);

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
  showPassword.value = false;
  showPasswordConfirm.value = false;
};

// Con datos en el formulario se pide confirmación (modal) antes de descartarlos.
const confirmCancel = ref(false);
const cancelWizard = () => {
  const hasProgress = step.value === 2 || form.value.document_number || form.value.first_name || form.value.last_name;
  if (hasProgress) {
    confirmCancel.value = true;
    return;
  }
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
});

// Campos del paso 1: si el servidor rechaza alguno al final, se vuelve a ese paso.
const PERSON_FIELDS = ['document_type_id', 'document_number', 'first_name', 'middle_name', 'last_name', 'second_last_name', 'neighborhood_id', 'commune_id'];

// Paso 1: solo se revisa en el navegador y se avanza al instante. Antes se creaba
// la persona aquí (una petición más) y, si se abandonaba el paso 2, quedaba una
// persona registrada sin cuenta.
const submitPersonStep = () => {
  errors.value = {};
  wizardError.value = '';

  const missing = {};
  if (!form.value.document_type_id) missing.document_type_id = ['Selecciona el tipo de documento.'];
  if (!String(form.value.document_number).trim()) missing.document_number = ['Escribe el número de documento.'];
  if (!form.value.first_name.trim()) missing.first_name = ['Escribe el primer nombre.'];
  if (!form.value.last_name.trim()) missing.last_name = ['Escribe el primer apellido.'];

  if (Object.keys(missing).length) {
    errors.value = missing;
    wizardError.value = 'Completa los campos obligatorios.';
    return;
  }

  step.value = 2;
};

// Paso 2: persona y cuenta en una sola petición (y una sola transacción en el servidor).
const submitAccountStep = async () => {
  errors.value = {};
  wizardError.value = '';
  loading.value = true;
  try {
    await axios.post('/admin/users-complete', {
      ...personPayload(),
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
    // Documento repetido, barrio que falta para el rol, etc. viven en el paso 1:
    // se vuelve ahí para que el campo se vea resaltado.
    if (PERSON_FIELDS.some((field) => fieldErrors[field])) {
      step.value = 1;
    }
    emit('show-result', false, 'No se pudo crear la cuenta', message);
  } finally {
    loading.value = false;
  }
};

const handleSubmit = () => (step.value === 1 ? submitPersonStep() : submitAccountStep());
</script>
