<template>
  <ErrorScene
    code="404"
    kicker="Página no encontrada"
    title="Esta página no se encuentra dentro de nuestro sitio web"
    message="La dirección que abriste no existe o cambió de lugar. Revisa el enlace o vuelve al inicio."
    :icon="MapPinOff"
  >
    <template #actions>
      <button v-if="authStore.isAuthenticated" type="button" class="error-btn-primary" @click="goHome">
        <Home class="w-4 h-4" /> Ir a mi inicio
      </button>
      <router-link v-else to="/login" class="error-btn-primary">
        <LogIn class="w-4 h-4" /> Iniciar sesión
      </router-link>
      <button type="button" class="error-btn-ghost" @click="router.back()">
        <ArrowLeft class="w-4 h-4" /> Volver atrás
      </button>
    </template>
  </ErrorScene>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { ArrowLeft, Home, LogIn, MapPinOff } from 'lucide-vue-next';
import ErrorScene from '@/components/fun/ErrorScene.vue';
import { useAuthStore } from '@/stores/auth';
import { firstAllowedRoute } from '@/router';

const router = useRouter();
const authStore = useAuthStore();

const goHome = () => router.replace(firstAllowedRoute(authStore));
</script>
