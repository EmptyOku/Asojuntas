<template>
  <div class="h-screen bg-[#f4f6f9] flex overflow-hidden font-sans relative">
    
    <div 
      v-if="isMobileMenuOpen" 
      @click="isMobileMenuOpen = false"
      class="fixed inset-0 bg-gray-900/50 z-40 md:hidden transition-opacity"
    ></div>

    <aside 
      :class="[
        'fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col transform transition-transform duration-300 ease-in-out md:relative md:translate-x-0',
        isMobileMenuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'
      ]"
    >
      <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-aso-primary text-white rounded-lg flex items-center justify-center font-bold text-sm">
            AJ
          </div>
          <span class="font-bold text-gray-900 text-lg tracking-tight">AsoJuntas</span>
        </div>
        <button @click="isMobileMenuOpen = false" class="md:hidden text-gray-400 hover:text-gray-600">
          <X class="w-5 h-5" />
        </button>
      </div>

      <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
        <router-link to="/secretary/dashboard" @click="isMobileMenuOpen = false" class="nav-item" active-class="active">
          <LayoutDashboard class="w-5 h-5" />
          <span>Dashboard</span>
        </router-link>
        
        <router-link to="/secretary/capture?doc=plancha" @click="isMobileMenuOpen = false" class="nav-item" active-class="active">
          <Camera class="w-5 h-5" />
          <span>Escanear Planchas</span>
        </router-link>

        <router-link to="/secretary/planchas" @click="isMobileMenuOpen = false" class="nav-item" active-class="active">
          <Files class="w-5 h-5" />
          <span>Auditoría de Planchas</span>
        </router-link>

        <router-link to="/secretary/planchas-por-barrio" @click="isMobileMenuOpen = false" class="nav-item" active-class="active">
          <MapPinned class="w-5 h-5" />
          <span>Planchas por Barrio</span>
        </router-link>
      </nav>

      <div class="p-4 border-t border-gray-100 shrink-0">
        <button @click="logout" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors">
          <LogOut class="w-5 h-5" /> Cerrar Sesión
        </button>
      </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      
      <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 md:px-8 shrink-0">
        
        <div class="flex items-center gap-3">
          <button 
            @click="isMobileMenuOpen = true" 
            class="p-2 -ml-2 text-gray-500 hover:bg-gray-100 rounded-lg md:hidden transition-colors"
          >
            <Menu class="w-6 h-6" />
          </button>
          <h1 class="text-base font-semibold text-gray-800 hidden sm:block">Secretaría Técnica</h1>
        </div>
        
        <div class="flex items-center gap-4 md:gap-5">
          
          <div class="w-8 h-8 bg-aso-primary text-white rounded-full flex items-center justify-center font-bold text-sm shadow-sm">
            {{ authStore.user?.name?.charAt(0) || 'AE' }}
          </div>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-8">
        <router-view></router-view>
      </main>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { LayoutDashboard, Camera, Files, MapPinned, LogOut, Bell, Menu, X } from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const isMobileMenuOpen = ref(false); // Estado para controlar el menú en móviles

const logout = async () => {
  await authStore.logout();
  router.push('/login');
};
</script>

<style scoped>
.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #64748b;
  border-radius: 0.5rem;
  transition: all 0.2s;
  white-space: nowrap;
}
.nav-item:hover { 
  background: #f8fafc; 
  color: #0f172a; 
}
.nav-item.active { 
  background: #1e8f4d;
  color: #ffffff; 
  font-weight: 600;
  box-shadow: 0 4px 6px -1px rgba(30, 143, 77, 0.2);
} 
</style>