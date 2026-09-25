<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col transform transition-transform duration-300 ease-in-out lg:relative lg:translate-x-0',
      open ? 'translate-x-0 shadow-2xl lg:shadow-none' : '-translate-x-full'
    ]"
  >
    <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 shrink-0">
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 bg-aso-primary rounded-lg flex items-center justify-center text-white font-bold text-xs">AJ</div>
        <span class="font-bold text-gray-900 text-lg tracking-tight">Asojuntas</span>
      </div>
      <button type="button" class="lg:hidden text-gray-500 hover:bg-gray-100 p-1.5 rounded-lg" aria-label="Cerrar menú" @click="$emit('close')">
        <X class="w-5 h-5" />
      </button>
    </div>

    <nav class="flex-1 px-4 py-6 overflow-y-auto" aria-label="Menú principal">
      <div v-for="(group, index) in mainGroups" :key="group.section" :class="index > 0 ? 'mt-6' : ''">
        <p v-if="showSectionTitles" class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
          {{ group.section }}
        </p>
        <div class="space-y-1">
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="item.to"
            :class="linkClass(item)"
            :aria-current="isActive(item) ? 'page' : undefined"
            @click="$emit('close')"
          >
            <component :is="iconFor(item.icon)" class="w-5 h-5 shrink-0" :class="isActive(item) ? 'text-white' : 'text-gray-400'" />
            <span class="truncate">{{ item.label }}</span>
          </router-link>
        </div>
      </div>
    </nav>

    <div v-if="bottomItems.length" class="p-4 border-t border-gray-100 shrink-0 space-y-1">
      <router-link
        v-for="item in bottomItems"
        :key="item.name"
        :to="item.to"
        :class="linkClass(item)"
        :aria-current="isActive(item) ? 'page' : undefined"
        @click="$emit('close')"
      >
        <component :is="iconFor(item.icon)" class="w-5 h-5 shrink-0" :class="isActive(item) ? 'text-white' : 'text-gray-400'" />
        <span class="truncate">{{ item.label }}</span>
      </router-link>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import {
  Camera, Circle, ClipboardList, FileCheck, Files, LayoutDashboard, Map as MapIcon, MapPin, MapPinned, ShieldAlert, Users, X
} from 'lucide-vue-next';

const props = defineProps({
  // Entradas de navigationFor(): { name, to, label, icon, section, order, position? }
  items: { type: Array, required: true },
  open: { type: Boolean, default: false }
});

defineEmits(['close']);

const route = useRoute();

// Íconos permitidos en meta.nav.icon. Se importan uno a uno para no cargar
// toda la librería; si agregas uno en el router, agrégalo también aquí.
const ICONS = { Camera, ClipboardList, FileCheck, Files, LayoutDashboard, Map: MapIcon, MapPin, MapPinned, ShieldAlert, Users };
const iconFor = (name) => ICONS[name] ?? Circle;

const mainItems = computed(() => props.items.filter((item) => item.position !== 'bottom'));
const bottomItems = computed(() => props.items.filter((item) => item.position === 'bottom'));

// Agrupa respetando el orden del menú (las secciones aparecen según su primer ítem).
const mainGroups = computed(() => mainItems.value.reduce((groups, item) => {
  const last = groups[groups.length - 1];
  if (last && last.section === item.section) {
    last.items.push(item);
  } else {
    groups.push({ section: item.section, items: [item] });
  }
  return groups;
}, []));

// Con una sola sección el título no aporta nada (p. ej. un rol solo de Secretaría).
const showSectionTitles = computed(() => mainGroups.value.length > 1);

// Activo si es la misma ruta o una subruta: /admin/audit/5 marca "Auditoría de
// Actas", pero /admin/audit-logs no (el prefijo exige la barra).
const pathOf = (to) => String(to).split('?')[0];
const isActive = (item) => {
  const itemPath = pathOf(item.to);
  return route.path === itemPath || route.path.startsWith(`${itemPath}/`);
};

const linkClass = (item) => [
  'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
  isActive(item) ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/20' : 'text-gray-700 hover:bg-gray-100'
];
</script>
