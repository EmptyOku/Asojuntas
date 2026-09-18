<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
      <div class="w-full max-w-sm rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden text-center">
        <div class="p-6">
          <div class="mx-auto w-12 h-12 rounded-full flex items-center justify-center" :class="success ? 'bg-emerald-50' : 'bg-red-50'">
            <CheckCircle2 v-if="success" class="w-7 h-7 text-emerald-600" />
            <XCircle v-else class="w-7 h-7 text-red-600" />
          </div>
          <h3 class="mt-4 text-lg font-bold text-gray-900">{{ title }}</h3>
          <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
        </div>
        <div class="px-6 py-4 bg-gray-50">
          <button
            type="button"
            class="w-full py-2.5 rounded-lg text-sm font-semibold text-white transition-colors"
            :class="success ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-900 hover:bg-black'"
            @click="$emit('close')"
          >
            {{ success ? 'Entendido' : 'Cerrar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { CheckCircle2, XCircle } from 'lucide-vue-next';

defineProps({
  open: { type: Boolean, default: false },
  success: { type: Boolean, default: true },
  title: { type: String, default: '' },
  message: { type: String, default: '' },
});

defineEmits(['close']);
</script>
