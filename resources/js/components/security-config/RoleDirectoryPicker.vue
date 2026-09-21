<template>
  <div class="space-y-2">
    <div
      v-for="role in roles"
      :key="role.id"
      class="rounded-lg border bg-white overflow-hidden transition-colors"
      :class="modelValue === role.id ? 'border-aso-primary ring-1 ring-aso-primary' : 'border-gray-200'"
    >
      <div class="w-full flex items-start gap-3 px-3 py-2.5">
        <input
          :id="`${radioName}-${role.id}`"
          type="radio"
          :name="radioName"
          :checked="modelValue === role.id"
          class="mt-1 shrink-0"
          @change="$emit('update:modelValue', role.id)"
        />
        <label :for="`${radioName}-${role.id}`" class="flex-1 min-w-0 cursor-pointer">
          <span class="block font-medium text-gray-900 break-words">{{ role.display_name }}</span>
          <span class="block text-xs text-gray-500">{{ role.name }} · {{ (role.permissions || []).length }} permiso(s)</span>
        </label>
        <button
          type="button"
          class="shrink-0 p-1 text-gray-400 hover:text-gray-700 rounded-full hover:bg-gray-100"
          @click="toggleExpanded(role.id)"
          :aria-label="isExpanded(role.id) ? 'Ocultar permisos' : 'Ver permisos'"
        >
          <ChevronDown class="w-4 h-4 transition-transform" :class="isExpanded(role.id) ? 'rotate-180' : ''" />
        </button>
      </div>
      <ul v-if="isExpanded(role.id)" class="px-3 pb-3 pt-1 border-t border-gray-100 space-y-1.5 sm:ml-7">
        <li v-for="perm in role.permissions || []" :key="perm.id" class="flex items-start gap-2 text-sm text-gray-700">
          <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
          {{ perm.description || perm.display_name || perm.name }}
        </li>
        <li v-if="!(role.permissions || []).length" class="text-sm text-gray-400 italic">Sin permisos asignados.</li>
      </ul>
    </div>
    <p v-if="!roles.length" class="text-sm text-gray-400 italic">No hay roles disponibles.</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

defineProps({
  roles: { type: Array, default: () => [] },
  modelValue: { type: [Number, String, null], default: null },
  radioName: { type: String, default: 'role-directory' },
});

defineEmits(['update:modelValue']);

const expandedRoleIds = ref([]);
const isExpanded = (id) => expandedRoleIds.value.includes(id);
const toggleExpanded = (id) => {
  const idx = expandedRoleIds.value.indexOf(id);
  if (idx === -1) expandedRoleIds.value.push(id);
  else expandedRoleIds.value.splice(idx, 1);
};
</script>
