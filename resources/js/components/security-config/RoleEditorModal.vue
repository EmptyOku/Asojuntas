<template>
  <Teleport to="body">
    <div v-if="user" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Editar Roles</p>
            <h2 class="mt-1 text-lg font-bold text-gray-900">{{ user.username }}</h2>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="$emit('close')">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

        <div class="px-6 py-5 space-y-2 max-h-72 overflow-y-auto">
          <label
            v-for="role in roles"
            :key="role.id"
            class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
          >
            <input type="checkbox" :value="role.id" v-model="editingRoles" class="border-gray-300 text-aso-primary focus:ring-aso-primary" />
            <span>{{ role.display_name }} <span class="text-xs text-gray-400">({{ role.name }})</span></span>
          </label>
        </div>

        <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="$emit('close')">
            Cancelar
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark transition-colors disabled:opacity-60"
            :disabled="loading || editingRoles.length === 0"
            @click="saveRoles"
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
import { X } from 'lucide-vue-next';

const props = defineProps({
  user: { type: Object, default: null },
  roles: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'reload']);

const loading = ref(false);
const modalError = ref('');
const editingRoles = ref([]);

watch(() => props.user, (user) => {
  modalError.value = '';
  editingRoles.value = (user?.roles || []).map((role) => role.id);
});

const saveRoles = async () => {
  if (!props.user || editingRoles.value.length === 0) return;
  loading.value = true;
  modalError.value = '';
  try {
    await axios.put(`/admin/users/${props.user.id}/roles`, { roles: editingRoles.value });
    emit('reload');
    emit('close');
  } catch (error) {
    modalError.value = error?.response?.data?.message || 'Error al actualizar roles.';
  } finally {
    loading.value = false;
  }
};
</script>
