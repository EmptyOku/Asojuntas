<template>
  <div class="min-h-screen bg-gray-50 font-sans flex flex-col">
    <header class="bg-aso-primary text-white shadow-md sticky top-0 z-20">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 bg-white rounded-lg flex items-center justify-center text-aso-primary font-bold text-xs shadow-sm">AJ</div>
          <div>
            <h1 class="font-bold text-sm leading-tight">Asojuntas</h1>
            <p class="text-[10px] text-green-100 opacity-90">Módulo de Jurados</p>
          </div>
        </div>
        
        <button @click="handleLogout" class="p-2 text-green-50 hover:bg-aso-primary-dark rounded-full transition-colors">
          <LogOut class="w-5 h-5" />
        </button>
      </div>
    </header>

    <main class="flex-1 w-full max-w-3xl mx-auto p-4 sm:p-6 lg:p-8 flex flex-col">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth';
import { LogOut } from 'lucide-vue-next';

const authStore = useAuthStore();

const handleLogout = () => {
  authStore.logout();
};
</script>