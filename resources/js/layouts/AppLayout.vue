<template>
  <div class="h-screen bg-aso-bg font-sans flex overflow-hidden relative">

    <template v-if="hasSidebar">
      <div
        v-if="isMobileMenuOpen"
        class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden transition-opacity"
        @click="isMobileMenuOpen = false"
      ></div>

      <AppSidebar
        :items="navigation"
        :open="isMobileMenuOpen"
        :collapsed="isCollapsed"
        @close="isMobileMenuOpen = false"
        @toggle-collapse="toggleCollapsed"
      />
    </template>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Franja con los colores del escudo de Asojuntas -->
      <div class="brand-stripe"></div>

      <header class="h-[5.5rem] bg-white/85 backdrop-blur-md border-b border-gray-200/70 flex items-center justify-between px-4 sm:px-8 shrink-0 z-10">
        <div class="flex items-center gap-3 min-w-0">
          <button
            v-if="hasSidebar"
            type="button"
            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-xl focus:outline-none"
            aria-label="Abrir menú"
            @click="isMobileMenuOpen = true"
          >
            <Menu class="w-6 h-6" />
          </button>

          <!-- Sin sidebar (p. ej. jurado con una sola pantalla) el logo va en el header. -->
          <div v-else class="h-10 w-10 bg-aso-primary rounded-xl flex items-center justify-center text-white font-display font-bold shrink-0">AJ</div>

          <div class="min-w-0">
            <h1 class="font-display text-lg sm:text-2xl font-bold text-gray-900 truncate">{{ title }}</h1>
            <p v-if="subtitle" class="hidden sm:block text-sm text-gray-500 truncate">{{ subtitle }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 relative">
          <!-- Campana: solo para quien revisa actas o planchas (el punto rojo antes era fijo). -->
          <NotificationBell />

          <div v-if="authStore.canAny(['scrutiny_records.view', 'slates.view'])" class="h-8 w-px bg-gray-200 hidden sm:block"></div>

          <div>
            <button type="button" class="flex items-center gap-3 rounded-xl p-1 sm:pr-3 hover:bg-gray-100 transition-colors focus:outline-none" aria-label="Menú de usuario" @click="isProfileOpen = !isProfileOpen">
              <div class="avatar h-9 w-9 text-sm">{{ initial }}</div>
              <div class="hidden md:block text-left min-w-0 max-w-40">
                <p class="text-sm font-semibold text-gray-900 truncate leading-tight">{{ displayName }}</p>
                <p class="text-xs text-gray-500 truncate">{{ rolesLabel }}</p>
              </div>
              <ChevronDown class="hidden md:block w-4 h-4 text-gray-400" />
            </button>

            <div v-if="isProfileOpen" class="fixed inset-0 z-30" @click="isProfileOpen = false"></div>

            <div v-if="isProfileOpen" class="absolute right-0 mt-2 w-56 sm:w-64 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-40 animate-rise">
              <div class="px-4 py-3 border-b border-gray-50">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ displayName }}</p>
                <p class="text-xs text-gray-500 truncate">{{ rolesLabel }}</p>
              </div>
              <div class="py-1">
                <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 text-left" @click="authStore.logout()">
                  <LogOut class="w-4 h-4 text-red-500" />
                  Cerrar Sesión
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Contenedor con scroll propio: los `sticky` de las vistas se anclan aquí,
           debajo del header. Una vista que necesite hacer scroll usa [data-app-scroll],
           no window. -->
      <main ref="scrollContainer" data-app-scroll class="flex-1 overflow-y-auto overflow-x-hidden flex flex-col p-4 sm:p-6 lg:p-8">
        <div ref="pageContainer" :class="['flex-1 flex flex-col w-full', narrow ? 'max-w-3xl mx-auto' : 'max-w-[1600px] mx-auto']">
          <router-view />
        </div>
      </main>

    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronDown, LogOut, Menu } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { navigationFor } from '@/router';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import NotificationBell from '@/components/layout/NotificationBell.vue';

const route = useRoute();
const authStore = useAuthStore();

const isProfileOpen = ref(false);
const isMobileMenuOpen = ref(false);
const scrollContainer = ref(null);
const pageContainer = ref(null);

// Menú según permisos: se recalcula solo si cambian (p. ej. refresco tras un 403).
const navigation = computed(() => navigationFor(authStore));

// Con una sola pantalla (p. ej. un jurado) el sidebar sobra: se usa el header solo.
const hasSidebar = computed(() => navigation.value.length > 1);

// Menú contraído (solo iconos): preferencia de este navegador. Si el
// almacenamiento no está disponible, simplemente arranca expandido.
const COLLAPSE_KEY = 'aso.sidebar.collapsed';
const readCollapsed = () => {
  try {
    return window.localStorage.getItem(COLLAPSE_KEY) === '1';
  } catch {
    return false;
  }
};
const isCollapsed = ref(readCollapsed());
const toggleCollapsed = () => {
  isCollapsed.value = !isCollapsed.value;
  try {
    window.localStorage.setItem(COLLAPSE_KEY, isCollapsed.value ? '1' : '0');
  } catch {
    // Sin almacenamiento: la preferencia dura solo esta sesión.
  }
};

// meta del módulo (padre) y de la vista: title / subtitle / layout: 'narrow'.
// La vista puede tener su propio título; si no, se usa el del módulo.
const title = computed(() => route.meta.title ?? 'Asojuntas');
const subtitle = computed(() => route.meta.subtitle ?? '');
const narrow = computed(() => route.meta.layout === 'narrow');

const displayName = computed(() => {
  const user = authStore.user;
  const person = user?.person;
  const fullName = [person?.first_name, person?.last_name].filter(Boolean).join(' ');
  return user?.name || fullName || user?.username || 'Usuario';
});
const initial = computed(() => displayName.value.charAt(0).toUpperCase());
const rolesLabel = computed(() => {
  const names = (authStore.user?.roles ?? []).map((role) => role.display_name || role.name);
  const list = names.length ? names : authStore.roles;
  return list.length ? list.join(', ') : 'Sin rol';
});

// Cada pantalla nueva empieza arriba y con los menús cerrados.
// La entrada suave se hace con la Web Animations API sobre el contenedor: no
// vuelve a montar la vista (el mapa de Leaflet y los formularios se conservan).
const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

watch(() => route.path, () => {
  isMobileMenuOpen.value = false;
  isProfileOpen.value = false;
  scrollContainer.value?.scrollTo({ top: 0 });

  if (!prefersReducedMotion()) {
    pageContainer.value?.animate?.(
      [{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }],
      { duration: 320, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
    );
  }
});
</script>
