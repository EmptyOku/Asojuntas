<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
      <div class="mx-auto h-12 w-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center mb-4">
        <Lock class="w-6 h-6" />
      </div>
      <h1 class="text-lg font-bold text-gray-900">No tienes permiso para ver esta pantalla</h1>
      <p class="mt-2 text-sm text-gray-600">
        Tu rol no incluye el permiso que exige esta sección. Si crees que es un error, pide a un administrador que lo revise.
      </p>
      <div class="mt-6 flex flex-col sm:flex-row gap-2 justify-center">
        <button v-if="authStore.isAuthenticated" type="button" class="px-4 py-2 rounded-lg text-sm font-medium bg-aso-primary text-white hover:bg-aso-primary-dark" @click="goHome">
          Ir a mi inicio
        </button>
        <router-link v-else to="/login" class="px-4 py-2 rounded-lg text-sm font-medium bg-aso-primary text-white hover:bg-aso-primary-dark">
          Iniciar sesión
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { Lock } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { firstAllowedRoute } from '@/router';

const router = useRouter();
const authStore = useAuthStore();

const goHome = () => router.replace(firstAllowedRoute(authStore));
</script>
