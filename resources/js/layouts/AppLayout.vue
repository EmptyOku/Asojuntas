<template>
  <div class="h-screen bg-aso-bg font-sans flex overflow-hidden relative">

    <template v-if="hasSidebar">
      <div
        v-if="isMobileMenuOpen"
        class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden transition-opacity"
        @click="isMobileMenuOpen = false"
      ></div>

      <AppSidebar :items="navigation" :open="isMobileMenuOpen" @close="isMobileMenuOpen = false" />
    </template>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-8 shrink-0 z-10">
        <div class="flex items-center gap-3 min-w-0">
          <button
            v-if="hasSidebar"
            type="button"
            class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg focus:outline-none"
            aria-label="Abrir menú"
            @click="isMobileMenuOpen = true"
          >
            <Menu class="w-6 h-6" />
          </button>

          <!-- Sin sidebar (p. ej. jurado con una sola pantalla) el logo va en el header. -->
          <div v-else class="h-8 w-8 bg-aso-primary rounded-lg flex items-center justify-center text-white font-bold text-xs shrink-0">AJ</div>

          <h2 class="text-base sm:text-lg font-semibold text-gray-800 truncate">{{ title }}</h2>
        </div>

        <div class="flex items-center gap-3 sm:gap-4 relative">
          <button type="button" class="p-2 text-gray-400 hover:bg-gray-50 rounded-full transition-colors relative" aria-label="Notificaciones">
            <Bell class="w-5 h-5" />
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
          </button>

          <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>

          <div>
            <button type="button" class="flex items-center focus:outline-none" aria-label="Menú de usuario" @click="isProfileOpen = !isProfileOpen">
              <div class="h-9 w-9 rounded-full bg-aso-primary text-white flex items-center justify-center font-bold shadow-sm">
                {{ initial }}
              </div>
            </button>

            <div v-if="isProfileOpen" class="fixed inset-0 z-30" @click="isProfileOpen = false"></div>

            <div v-if="isProfileOpen" class="absolute right-0 mt-2 w-56 sm:w-64 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-40">
              <div class="px-4 py-3 border-b border-gray-50">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ displayName }}</p>
                <p class="text-xs text-gray-500 truncate">{{ rolesLabel }}</p>
              </div>
              <div class="border-t border-gray-50 py-1">
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
        <div :class="['flex-1 flex flex-col w-full', narrow ? 'max-w-3xl mx-auto' : '']">
          <router-view />
        </div>
      </main>

    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Bell, LogOut, Menu } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { navigationFor } from '@/router';
import AppSidebar from '@/components/layout/AppSidebar.vue';

const route = useRoute();
const authStore = useAuthStore();

const isProfileOpen = ref(false);
const isMobileMenuOpen = ref(false);
const scrollContainer = ref(null);

// Menú según permisos: se recalcula solo si cambian (p. ej. refresco tras un 403).
const navigation = computed(() => navigationFor(authStore));

// Con una sola pantalla (p. ej. un jurado) el sidebar sobra: se usa el header solo.
const hasSidebar = computed(() => navigation.value.length > 1);

// meta del módulo (padre) y de la vista: title / layout: 'narrow'.
const title = computed(() => route.meta.title ?? 'Asojuntas');
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
watch(() => route.path, () => {
  isMobileMenuOpen.value = false;
  isProfileOpen.value = false;
  scrollContainer.value?.scrollTo({ top: 0 });
});
</script>
