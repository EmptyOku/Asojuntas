<template>
  <Teleport to="body">
    <div v-if="user" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
      <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden animate-rise">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Cambiar rol</p>
            <h2 class="mt-1 font-display text-xl font-bold text-gray-900">{{ user.username }}</h2>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="$emit('close')">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

        <p class="px-6 pt-4 text-xs text-gray-500">Cada usuario tiene un solo rol. Elige el que debe tener.</p>

        <!-- Cuentas antiguas con varios roles: se avisa que quedará solo el elegido. -->
        <p v-if="previousRoles.length > 1" class="mx-6 mt-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 text-xs">
          Este usuario tenía {{ previousRoles.length }} roles ({{ previousRoles.join(', ') }}). Al guardar quedará solo con el que elijas.
        </p>

        <div class="px-6 py-4 space-y-2 max-h-72 overflow-y-auto" role="radiogroup" aria-label="Rol del usuario">
          <label
            v-for="role in roles"
            :key="role.id"
            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm cursor-pointer transition-colors"
            :class="selectedRoleId === role.id ? 'border-aso-primary bg-emerald-50/60' : 'border-gray-100 hover:bg-gray-50'"
          >
            <input type="radio" name="user-role" :value="role.id" v-model="selectedRoleId" class="border-gray-300 text-aso-primary focus:ring-aso-primary" />
            <span>{{ role.display_name }} <span class="text-xs text-gray-400">({{ role.name }})</span></span>
          </label>
        </div>

        <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
          <button type="button" class="btn-secondary" @click="$emit('close')">
            Cancelar
          </button>
          <button
            type="button"
            class="btn-primary"
            :disabled="loading || !selectedRoleId"
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

const emit = defineEmits(['close', 'reload', 'show-result']);

const loading = ref(false);
const modalError = ref('');
// Un solo rol por usuario (el servidor también lo exige).
const selectedRoleId = ref(null);
const previousRoles = ref([]);

watch(() => props.user, (user) => {
  modalError.value = '';
  const current = user?.roles || [];
  selectedRoleId.value = current[0]?.id ?? null;
  previousRoles.value = current.map((role) => role.display_name || role.name);
});

const saveRoles = async () => {
  if (!props.user || !selectedRoleId.value) return;
  loading.value = true;
  modalError.value = '';
  try {
    await axios.put(`/admin/users/${props.user.id}/roles`, { roles: [selectedRoleId.value] });
    emit('reload');
    emit('close');
    emit('show-result', true, 'Rol actualizado', 'El rol del usuario se actualizó con éxito.');
  } catch (error) {
    const message = error?.response?.data?.message || 'Error al actualizar el rol.';
    modalError.value = message;
    emit('show-result', false, 'No se pudo actualizar el rol', message);
  } finally {
    loading.value = false;
  }
};
</script>
