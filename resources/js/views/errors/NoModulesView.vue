<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
      <div class="mx-auto h-12 w-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
        <ShieldOff class="w-6 h-6" />
      </div>
      <h1 class="text-lg font-bold text-gray-900">Tu rol no tiene módulos asignados</h1>
      <p class="mt-2 text-sm text-gray-600">
        Iniciaste sesión correctamente, pero tu rol todavía no tiene permisos para ver ninguna pantalla.
        Pide a un administrador que te asigne los permisos que necesitas.
      </p>
      <div class="mt-6 flex flex-col sm:flex-row gap-2 justify-center">
        <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-700 hover:bg-gray-50" @click="retry">
          Volver a comprobar
        </button>
        <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium bg-aso-primary text-white hover:bg-aso-primary-dark" @click="authStore.logout()">
          Cerrar sesión
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { ShieldOff } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { firstAllowedRoute } from '@/router';

const router = useRouter();
const authStore = useAuthStore();

// Por si un admin asignó permisos mientras la pantalla estaba abierta.
const retry = async () => {
  try {
    await authStore.fetchUser();
  } catch {
    return;
  }
  const target = firstAllowedRoute(authStore);
  if (target?.name !== 'no-modules') router.replace(target);
};
</script>
