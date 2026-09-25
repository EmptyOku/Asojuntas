<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @keydown.esc="!loading && $emit('cancel')"
    >
      <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
        <div class="p-6 text-center">
          <div class="mx-auto w-12 h-12 rounded-full flex items-center justify-center" :class="danger ? 'bg-red-50' : 'bg-emerald-50'">
            <AlertTriangle v-if="danger" class="w-7 h-7 text-red-600" />
            <HelpCircle v-else class="w-7 h-7 text-emerald-600" />
          </div>
          <h3 class="mt-4 text-lg font-bold text-gray-900">{{ title }}</h3>
          <p v-if="message" class="mt-2 text-sm text-gray-600">{{ message }}</p>
          <!-- Detalle opcional (p. ej. resumen de lo que se va a guardar). -->
          <div v-if="$slots.default" class="mt-4 text-left text-sm text-gray-700">
            <slot />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 flex flex-col-reverse sm:flex-row gap-2">
          <button
            ref="cancelButton"
            type="button"
            class="flex-1 py-2.5 rounded-lg text-sm font-semibold border border-gray-200 text-gray-700 bg-white hover:bg-gray-100 disabled:opacity-50"
            :disabled="loading"
            @click="$emit('cancel')"
          >
            {{ cancelText }}
          </button>
          <button
            type="button"
            class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors disabled:opacity-50"
            :class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-aso-primary hover:bg-aso-primary-dark'"
            :disabled="loading"
            @click="$emit('confirm')"
          >
            {{ loading ? 'Procesando…' : confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import { AlertTriangle, HelpCircle } from 'lucide-vue-next';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '¿Confirmas esta acción?' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Confirmar' },
  cancelText: { type: String, default: 'Cancelar' },
  // Acción destructiva (desactivar, eliminar): botón rojo.
  danger: { type: Boolean, default: false },
  // Mientras se procesa, los botones se bloquean para evitar doble envío.
  loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);

// Foco en "Cancelar" al abrir: Enter por accidente no confirma y Esc funciona.
const cancelButton = ref(null);
watch(() => props.open, async (open) => {
  if (open) {
    await nextTick();
    cancelButton.value?.focus();
  }
});
</script>
