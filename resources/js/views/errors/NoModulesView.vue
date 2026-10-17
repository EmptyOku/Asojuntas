<template>
  <ErrorScene
    code="0"
    kicker="Rol sin módulos"
    title="Tu rol todavía no tiene pantallas asignadas"
    message="Iniciaste sesión correctamente, pero tu rol aún no tiene permisos para ver ninguna pantalla. Pide a un administrador que te asigne los que necesitas."
    :icon="ShieldOff"
  >
    <template #actions>
      <button type="button" class="error-btn-primary" :disabled="checking" @click="retry">
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': checking }" /> Volver a comprobar
      </button>
      <button type="button" class="error-btn-ghost" @click="authStore.logout()">
        <LogOut class="w-4 h-4" /> Cerrar sesión
      </button>
    </template>
  </ErrorScene>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { LogOut, RefreshCw, ShieldOff } from 'lucide-vue-next';
import ErrorScene from '@/components/fun/ErrorScene.vue';
import { useAuthStore } from '@/stores/auth';
import { firstAllowedRoute } from '@/router';

const router = useRouter();
const authStore = useAuthStore();
const checking = ref(false);

// Por si un admin asignó permisos mientras la pantalla estaba abierta.
const retry = async () => {
  checking.value = true;
  try {
    await authStore.fetchUser();
  } catch {
    return;
  } finally {
    checking.value = false;
  }
  const target = firstAllowedRoute(authStore);
  if (target?.name !== 'no-modules') router.replace(target);
};
</script>
