<template>
  <aside
    :class="[
      'sidebar fixed inset-y-0 left-0 z-50 flex flex-col text-white transform transition-all duration-300 ease-in-out lg:relative lg:translate-x-0',
      collapsed ? 'lg:w-[5.25rem]' : 'lg:w-72',
      'w-72',
      open ? 'translate-x-0 shadow-2xl lg:shadow-none' : '-translate-x-full'
    ]"
  >
    <!-- Marca -->
    <div class="h-[5.5rem] flex items-center justify-between px-5 border-b border-white/10 shrink-0">
      <div class="flex items-center gap-3 min-w-0">
        <div class="h-11 w-11 rounded-xl bg-white/10 ring-1 ring-white/20 flex items-center justify-center font-display font-bold text-lg shrink-0 shadow-inner">
          AJ
        </div>
        <div :class="['min-w-0 transition-opacity duration-200', collapsed ? 'lg:opacity-0 lg:pointer-events-none' : '']">
          <p class="font-display font-bold text-lg leading-tight tracking-wide truncate">ASOJUNTAS</p>
          <p class="text-[11px] font-semibold text-white/60 tracking-wide truncate">GIRARDOT, CUND.</p>
        </div>
      </div>
      <button type="button" class="lg:hidden text-white/70 hover:bg-white/10 p-1.5 rounded-lg" aria-label="Cerrar menú" @click="$emit('close')">
        <X class="w-5 h-5" />
      </button>
    </div>

    <nav class="sidebar-scroll flex-1 px-3 py-5 overflow-y-auto overflow-x-hidden" aria-label="Menú principal">
      <div v-for="(group, index) in mainGroups" :key="group.section" :class="index > 0 ? 'mt-6' : ''">
        <p
          v-if="showSectionTitles"
          :class="['px-3 mb-2 text-[10.5px] font-bold uppercase tracking-[0.12em] text-white/40 truncate', collapsed ? 'lg:invisible' : '']"
        >
          {{ group.section }}
        </p>
        <div class="space-y-1">
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="item.to"
            :class="linkClass(item)"
            :title="collapsed ? item.label : undefined"
            :aria-current="isActive(item) ? 'page' : undefined"
            @click="$emit('close')"
          >
            <component :is="iconFor(item.icon)" class="w-5 h-5 shrink-0" />
            <span :class="['truncate', collapsed ? 'lg:hidden' : '']">{{ item.label }}</span>
          </router-link>
        </div>
      </div>
    </nav>

    <div :class="['p-3 border-t border-white/10 shrink-0 space-y-1', bottomItems.length ? '' : 'hidden lg:block']">
      <router-link
        v-for="item in bottomItems"
        :key="item.name"
        :to="item.to"
        :class="linkClass(item)"
        :title="collapsed ? item.label : undefined"
        :aria-current="isActive(item) ? 'page' : undefined"
        @click="$emit('close')"
      >
        <component :is="iconFor(item.icon)" class="w-5 h-5 shrink-0" />
        <span :class="['truncate', collapsed ? 'lg:hidden' : '']">{{ item.label }}</span>
      </router-link>

      <!-- Contraer / expandir (solo escritorio): va en el pie del menú, no
           flotando sobre el borde, para no tapar la barra de desplazamiento. -->
      <button
        type="button"
        :class="[
          'hidden lg:flex w-full items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-white/55 hover:text-white hover:bg-white/10 transition-colors',
          collapsed ? 'justify-center px-0' : ''
        ]"
        :title="collapsed ? 'Expandir menú' : undefined"
        :aria-label="collapsed ? 'Expandir menú' : 'Contraer menú'"
        @click="$emit('toggle-collapse')"
      >
        <ChevronsLeft :class="['w-5 h-5 shrink-0 transition-transform duration-300', collapsed ? 'rotate-180' : '']" />
        <span v-if="!collapsed" class="truncate">Contraer menú</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import {
  Camera, ChevronsLeft, Circle, ClipboardList, FileCheck, Files, LayoutDashboard, Map as MapIcon, MapPin, MapPinned, ShieldAlert, Users, X
} from 'lucide-vue-next';

const props = defineProps({
  // Entradas de navigationFor(): { name, to, label, icon, section, order, position? }
  items: { type: Array, required: true },
  open: { type: Boolean, default: false },
  // Solo iconos en escritorio; en móvil el menú siempre se ve completo.
  collapsed: { type: Boolean, default: false }
});

defineEmits(['close', 'toggle-collapse']);

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
  'group relative flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[0.9rem] font-semibold transition-all duration-200',
  props.collapsed ? 'lg:justify-center lg:px-0' : '',
  isActive(item)
    ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/15 before:absolute before:left-0 before:top-2.5 before:bottom-2.5 before:w-1 before:rounded-r-full before:bg-lime-300'
    : 'text-white/75 hover:bg-white/10 hover:text-white'
];
</script>

<style scoped>
.sidebar {
  background:
    radial-gradient(120% 60% at 0% 0%, rgb(63 174 106 / 0.35), transparent 60%),
    linear-gradient(180deg, #14582f 0%, #0f4726 55%, #0c3d22 100%);
}

/* Barra de desplazamiento fina y del color del menú (cuando la pantalla es
   baja y no caben todas las opciones), en vez de la gris del navegador. */
.sidebar-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgb(255 255 255 / 0.22) transparent;
}
.sidebar-scroll::-webkit-scrollbar {
  width: 6px;
}
.sidebar-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
  background: rgb(255 255 255 / 0.22);
  border-radius: 999px;
}
.sidebar-scroll::-webkit-scrollbar-thumb:hover {
  background: rgb(255 255 255 / 0.35);
}
</style>
