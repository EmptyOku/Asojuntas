<template>
  <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 flex flex-col">
    <h2 class="text-base font-semibold text-gray-900 mb-3 shrink-0">Catálogo de Roles</h2>
    <div class="flex-1 max-h-[34rem] overflow-y-auto pr-1 space-y-2">
      <div v-for="role in roles" :key="role.id" class="rounded-lg border border-gray-200 bg-white overflow-hidden">
        <button
          type="button"
          class="w-full flex items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-gray-50 transition-colors"
          @click="toggleRoleExpanded(role.id)"
        >
          <span>
            <span class="block font-medium text-gray-900">{{ role.display_name }}</span>
            <span class="block text-xs text-gray-500">{{ role.name }} · {{ (role.permissions || []).length }} permiso(s)</span>
          </span>
          <ChevronDown class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="isRoleExpanded(role.id) ? 'rotate-180' : ''" />
        </button>
        <ul v-if="isRoleExpanded(role.id)" class="px-3 pb-3 pt-1 border-t border-gray-100 space-y-1.5">
          <li v-for="perm in role.permissions || []" :key="perm.id" class="flex items-start gap-2 text-sm text-gray-700">
            <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
            {{ perm.description || perm.display_name || perm.name }}
          </li>
          <li v-if="!(role.permissions || []).length" class="text-sm text-gray-400 italic">Sin permisos asignados.</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

defineProps({
  roles: { type: Array, default: () => [] },
});

const expandedRoleIds = ref([]);
const toggleRoleExpanded = (id) => {
  const idx = expandedRoleIds.value.indexOf(id);
  if (idx === -1) expandedRoleIds.value.push(id);
  else expandedRoleIds.value.splice(idx, 1);
};
const isRoleExpanded = (id) => expandedRoleIds.value.includes(id);
</script>
