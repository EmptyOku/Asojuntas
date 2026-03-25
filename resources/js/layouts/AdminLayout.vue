<template>
  <div class="min-h-screen bg-aso-bg font-sans flex relative">
    
    <div 
      v-if="isMobileMenuOpen" 
      @click="isMobileMenuOpen = false" 
      class="fixed inset-0 bg-gray-900/50 z-20 lg:hidden transition-opacity"
    ></div>

    <aside 
      :class="[
        'w-64 bg-white border-r border-gray-200 flex flex-col fixed inset-y-0 left-0 z-30 transform transition-transform duration-300 ease-in-out lg:translate-x-0',
        isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100">
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 bg-aso-primary rounded-lg flex items-center justify-center text-white font-bold text-xs">AJ</div>
          <span class="font-bold text-gray-900 text-lg tracking-tight">Asojuntas</span>
        </div>
        <button @click="isMobileMenuOpen = false" class="lg:hidden text-gray-500 hover:bg-gray-100 p-1.5 rounded-lg">
          <X class="w-5 h-5" />
        </button>
      </div>

      <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
        <router-link to="/admin/dashboard" @click="isMobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200" active-class="bg-aso-primary text-white shadow-md shadow-aso-primary/20" :class="[$route.path.includes('/dashboard') ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/20' : 'text-gray-700 hover:bg-gray-100']">
          <LayoutDashboard class="w-5 h-5" :class="[$route.path.includes('/dashboard') ? 'text-white' : 'text-gray-400']" />
          Dashboard
        </router-link>

        <router-link to="/admin/geography" @click="isMobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200" active-class="bg-aso-primary text-white shadow-md shadow-aso-primary/20" :class="[$route.path.includes('/geography') ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/20' : 'text-gray-700 hover:bg-gray-100']">
          <Map class="w-5 h-5" :class="[$route.path.includes('/geography') ? 'text-white' : 'text-gray-400']" />
          Geografía Electoral
        </router-link>

        <router-link to="/admin/candidates" @click="isMobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200" active-class="bg-aso-primary text-white shadow-md shadow-aso-primary/20" :class="[$route.path.includes('/candidates') ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/20' : 'text-gray-700 hover:bg-gray-100']">
          <Users class="w-5 h-5" :class="[$route.path.includes('/candidates') ? 'text-white' : 'text-gray-400']" />
          Candidatos y Planchas
        </router-link>

        <router-link to="/admin/audit" @click="isMobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200" active-class="bg-aso-primary text-white shadow-md shadow-aso-primary/20" :class="[$route.path.includes('/audit') ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/20' : 'text-gray-700 hover:bg-gray-100']">
          <FileCheck class="w-5 h-5" :class="[$route.path.includes('/audit') ? 'text-white' : 'text-gray-400']" />
          Auditoría de Actas
        </router-link>
      </nav>

      <div class="p-4 border-t border-gray-100">
        <router-link to="/admin/security" @click="isMobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">
          <ShieldAlert class="w-5 h-5 text-gray-400" />
          Roles y Permisos
        </router-link>
      </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen lg:ml-64 w-full transition-all duration-300">
      
      <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-8 sticky top-0 z-10 w-full">
        
        <div class="flex items-center gap-4">
          <button @click="isMobileMenuOpen = true" class="lg:hidden p-2 -ml-2 text-gray-600 hover:bg-gray-100 rounded-lg focus:outline-none">
            <Menu class="w-6 h-6" />
          </button>
          <h2 class="text-lg font-semibold text-gray-800 hidden sm:block">Panel de Administración</h2>
        </div>

        <div class="flex items-center gap-3 sm:gap-4 relative">
          
          <button class="p-2 text-gray-400 hover:bg-gray-50 rounded-full transition-colors relative">
            <Bell class="w-5 h-5" />
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
          </button>

          <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>

          <div>
            <button @click="isProfileOpen = !isProfileOpen" class="flex items-center focus:outline-none">
              <div class="h-9 w-9 rounded-full bg-aso-primary text-white flex items-center justify-center font-bold shadow-sm">
                {{ authStore.user?.name ? authStore.user.name.charAt(0).toUpperCase() : 'U' }}
              </div>
            </button>

            <div v-if="isProfileOpen" @click="isProfileOpen = false" class="fixed inset-0 z-30"></div>

            <div v-if="isProfileOpen" class="absolute right-0 mt-2 w-56 sm:w-64 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-40 transform transition-all">
              <div class="px-4 py-3 border-b border-gray-50">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ authStore.user?.name || 'Usuario' }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ authStore.user?.role || 'Sin Rol' }}</p>
              </div>
              <div class="py-1">
                <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                  <Settings class="w-4 h-4 text-gray-400" />
                  Configuración
                </a>
              </div>
              <div class="border-t border-gray-50 py-1">
                <button @click="handleLogout" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 text-left">
                  <LogOut class="w-4 h-4 text-red-500" />
                  Cerrar Sesión
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="flex-1 p-4 sm:p-6 lg:p-8 w-full overflow-x-hidden">
        <router-view />
      </main>

    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// Añadimos Menu y X de Lucide para la versión móvil
import { LayoutDashboard, Map, Users, FileCheck, ShieldAlert, Bell, Settings, LogOut, Menu, X } from 'lucide-vue-next';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const isProfileOpen = ref(false);
const isMobileMenuOpen = ref(false); // Estado para controlar la barra lateral en celular

// Cierra el menú móvil automáticamente si la ruta cambia (si el usuario hace clic en un enlace)
watch(route, () => {
  isMobileMenuOpen.value = false;
});

const handleLogout = () => {
  authStore.logout();
};
</script>