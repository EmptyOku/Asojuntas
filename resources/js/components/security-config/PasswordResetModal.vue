<template>
  <Teleport to="body">
    <div v-if="user" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Restablecer Contraseña</p>
            <h2 class="mt-1 text-lg font-bold text-gray-900">{{ user.username }}</h2>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="$emit('close')">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Nueva contraseña</label>
            <div class="relative">
              <input
                v-model="passwordForm.password"
                required
                :type="showPassword ? 'text' : 'password'"
                class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
                :class="passwordTooShort ? 'border-red-300' : ''"
              />
              <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                @click="showPassword = !showPassword"
                tabindex="-1"
              >
                <EyeOff v-if="showPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
            <p v-if="passwordTooShort" class="text-xs text-red-600 mt-1">La contraseña debe tener al menos 8 caracteres.</p>
            <p v-else-if="passwordForm.password.length >= 8" class="text-xs text-emerald-600 mt-1">Longitud válida.</p>
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Confirmar contraseña</label>
            <div class="relative">
              <input
                v-model="passwordForm.password_confirmation"
                required
                :type="showPasswordConfirm ? 'text' : 'password'"
                class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
                :class="passwordMismatch ? 'border-red-300' : ''"
              />
              <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                @click="showPasswordConfirm = !showPasswordConfirm"
                tabindex="-1"
              >
                <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
            <p v-if="passwordMismatch" class="text-xs text-red-600 mt-1">Las contraseñas no coinciden.</p>
            <p v-else-if="passwordForm.password_confirmation.length > 0" class="text-xs text-emerald-600 mt-1">Las contraseñas coinciden.</p>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="$emit('close')">
            Cancelar
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 transition-colors disabled:opacity-60"
            :disabled="loading || passwordForm.password.length < 8 || passwordForm.password !== passwordForm.password_confirmation"
            @click="saveNewPassword"
          >
            Restablecer
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from '@/services/axios';
import { X, Eye, EyeOff } from 'lucide-vue-next';

const props = defineProps({
  user: { type: Object, default: null },
});

const emit = defineEmits(['close', 'show-result']);

const loading = ref(false);
const modalError = ref('');
const passwordForm = ref({ password: '', password_confirmation: '' });
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const passwordTooShort = computed(() => passwordForm.value.password.length > 0 && passwordForm.value.password.length < 8);
const passwordMismatch = computed(() => passwordForm.value.password_confirmation.length > 0 && passwordForm.value.password !== passwordForm.value.password_confirmation);

watch(() => props.user, (user) => {
  if (!user) return;
  modalError.value = '';
  passwordForm.value = { password: '', password_confirmation: '' };
  showPassword.value = false;
  showPasswordConfirm.value = false;
});

const saveNewPassword = async () => {
  if (!props.user) return;
  loading.value = true;
  modalError.value = '';
  try {
    await axios.post(`/admin/users/${props.user.id}/reset-password`, passwordForm.value);
    emit('close');
    emit('show-result', true, 'Contraseña restablecida', 'La contraseña se restableció con éxito.');
  } catch (error) {
    const message = error?.response?.data?.message || 'Error al restablecer la contraseña.';
    modalError.value = message;
    emit('show-result', false, 'No se pudo restablecer la contraseña', message);
  } finally {
    loading.value = false;
  }
};
</script>
