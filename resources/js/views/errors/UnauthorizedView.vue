<template>
  <ErrorScene
    code="403"
    kicker="Acceso restringido"
    title="Esta sección no está disponible para ti o no tienes los permisos necesarios para acceder a ella"
    message="Tu rol no incluye el permiso que exige esta pantalla. Si crees que es un error, pide a un administrador que lo revise."
    :icon="Lock"
  >
    <template #actions>
      <button v-if="authStore.isAuthenticated" type="button" class="error-btn-primary" @click="goHome">
        <Home class="w-4 h-4" /> Ir a mi inicio
      </button>
      <router-link v-else to="/login" class="error-btn-primary">
        <LogIn class="w-4 h-4" /> Iniciar sesión
      </router-link>
    </template>
  </ErrorScene>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { Home, Lock, LogIn } from 'lucide-vue-next';
import ErrorScene from '@/components/fun/ErrorScene.vue';
import { useAuthStore } from '@/stores/auth';
import { firstAllowedRoute } from '@/router';

const router = useRouter();
const authStore = useAuthStore();

const goHome = () => router.replace(firstAllowedRoute(authStore));
</script>
