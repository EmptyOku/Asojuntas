<template>
  <div class="space-y-2">
    <div
      v-for="role in roles"
      :key="role.id"
      class="rounded-xl border-2 overflow-hidden transition-all duration-200 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-aso-primary/20"
      :class="modelValue === role.id
        ? 'border-aso-primary bg-emerald-50/70 shadow-sm shadow-aso-primary/10'
        : 'border-gray-100 bg-white hover:border-gray-200'"
    >
      <div class="w-full flex items-center gap-3 px-3.5 py-3">
        <input
          :id="`${radioName}-${role.id}`"
          type="radio"
          :name="radioName"
          :checked="modelValue === role.id"
          class="sr-only"
          @change="$emit('update:modelValue', role.id)"
        />
        <label :for="`${radioName}-${role.id}`" class="flex flex-1 min-w-0 items-center gap-3 cursor-pointer">
          <span
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
            :class="modelValue === role.id ? 'border-aso-primary bg-aso-primary' : 'border-gray-300 bg-white'"
          >
            <Check v-if="modelValue === role.id" class="w-3 h-3 text-white" stroke-width="3" />
          </span>
          <span class="min-w-0">
            <span class="block font-semibold break-words" :class="modelValue === role.id ? 'text-aso-primary-dark' : 'text-gray-900'">{{ role.display_name }}</span>
            <span class="block text-xs text-gray-500">{{ role.name }} · {{ (role.permissions || []).length }} permiso(s)</span>
          </span>
        </label>
        <button
          type="button"
          class="shrink-0 p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-white/80"
          @click="toggleExpanded(role.id)"
          :aria-label="isExpanded(role.id) ? 'Ocultar permisos' : 'Ver permisos'"
        >
          <ChevronDown class="w-4 h-4 transition-transform" :class="isExpanded(role.id) ? 'rotate-180' : ''" />
        </button>
      </div>
      <ul v-if="isExpanded(role.id)" class="px-3.5 pb-3 pt-2 border-t border-black/5 space-y-1.5 sm:ml-8 animate-fade">
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
import { Check, ChevronDown } from 'lucide-vue-next';

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
