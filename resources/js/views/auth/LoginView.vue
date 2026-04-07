<template>
  <div class="min-h-screen lg:h-screen w-full flex flex-col lg:flex-row font-sans overflow-hidden bg-white">
    
    <div class="w-full lg:w-1/2 bg-white flex flex-col justify-between relative px-8 sm:px-16 xl:px-24 py-8 shadow-[20px_0_60px_rgba(0,0,0,0.02)] z-10 h-full lg:overflow-y-auto scrollbar-hide">
      
      <div class="hidden lg:block"></div>

      <div class="w-full max-w-md mx-auto my-auto py-10 lg:py-0">
        
        <div class="flex items-center justify-center gap-6 mb-10">
          <img src="@/assets/img/logo-asojuntas.png" alt="Logo Asojuntas" class="h-16 sm:h-20 w-auto object-contain drop-shadow-sm" />
          <div class="h-12 sm:h-14 w-px bg-gray-200"></div>
          <img src="@/assets/img/logo-piloto.png" alt="Logo Universidad Piloto" class="h-12 sm:h-14 w-auto object-contain drop-shadow-sm" />
        </div>

        <div class="mb-8 text-center">
          <h1 class="text-3xl sm:text-4xl font-bold text-gray-950 tracking-tight">Bienvenido de nuevo</h1>
          <p class="text-gray-500 mt-2 text-base sm:text-lg">Ingresa tus credenciales para continuar.</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-6">
          
          <div>
            <label for="identity" class="block text-sm font-medium text-gray-700 mb-2 text-left">Usuario o Correo Electrónico</label>
            <input
              id="identity"
              v-model="credentials.identity"
              type="text"
              required
              class="w-full px-5 py-3.5 rounded-2xl border border-gray-100 bg-gray-50 text-gray-950 focus:bg-white focus:outline-none focus:ring-2 focus:ring-aso-primary focus:border-transparent transition-all duration-200"
              placeholder="Ej: admin_asojuntas o correo@ejemplo.com"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2 text-left">Contraseña</label>
            <input
              id="password"
              v-model="credentials.password"
              type="password"
              required
              class="w-full px-5 py-3.5 rounded-2xl border border-gray-100 bg-gray-50 text-gray-950 focus:bg-white focus:outline-none focus:ring-2 focus:ring-aso-primary focus:border-transparent transition-all duration-200"
              placeholder="••••••••"
            />
          </div>

          <div class="flex items-center justify-end mt-2">
            <router-link to="/recovery" class="text-sm font-semibold text-aso-primary hover:text-aso-primary-dark transition-colors">
              ¿Olvidaste tu contraseña?
            </router-link>
          </div>

          <button
            type="submit"
            :disabled="authStore.loading"
            class="w-full py-4 px-6 mt-2 bg-aso-primary hover:bg-aso-primary-dark text-white font-semibold rounded-2xl shadow-lg shadow-aso-primary/25 transform hover:-translate-y-0.5 transition-all duration-200 flex justify-center items-center"
          >
            <svg v-if="authStore.loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span v-if="authStore.loading">Verificando...</span>
            <span v-else>Iniciar Sesión</span>
          </button>

          <p v-if="errorMessage" class="text-red-500 text-sm text-center mt-4 bg-red-50 py-2.5 rounded-xl">
            {{ errorMessage }}
          </p>
        </form>
      </div>

      <p class="text-center text-sm text-gray-400 mt-8">
        © 2026 Universidad Piloto de Colombia
      </p>
    </div>

    <div class="hidden lg:flex lg:w-1/2 h-full flex-col justify-center items-center p-10 xl:p-16 relative bg-aso-bg">
      <div class="absolute top-[-15%] right-[-15%] w-[500px] h-[500px] bg-aso-primary/5 rounded-full blur-3xl pointer-events-none"></div>
      
      <div class="max-w-xl z-10 text-center w-full">
        <h2 class="text-4xl xl:text-5xl font-extrabold text-gray-950 leading-tight mb-6 tracking-tighter">
          Escrutinio <span class="text-aso-primary">Transparente</span> e Inteligente
        </h2>
        <p class="text-lg xl:text-xl text-gray-600 mb-10 max-w-lg mx-auto">
          Plataforma oficial para la gestión, auditoría y lectura automatizada mediante Visión Artificial de las elecciones de Asojuntas.
        </p>
        
        <div class="relative w-full max-w-[280px] xl:max-w-sm mx-auto aspect-square bg-white rounded-3xl shadow-2xl border border-gray-100 p-8 flex items-center justify-center transform hover:scale-105 transition-transform duration-500">
            <img 
              src="@/assets/img/ilustracion-login.svg" 
              alt="Ilustración Escrutinio Asojuntas" 
              class="w-full h-full object-contain drop-shadow-2xl"
            />
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const credentials = ref({
  identity: '',
  password: ''
});
const errorMessage = ref('');

const handleLogin = async () => {
  errorMessage.value = '';
  try {
    await authStore.login(credentials.value);

    if (authStore.permissions.includes('users.view')) {
      router.push('/admin/dashboard');
      return;
    }

    if (authStore.permissions.includes('records.upload')) {
      router.push('/jury/dashboard');
      return;
    }

    errorMessage.value = 'El usuario no tiene permisos para entrar a un módulo.';
  } catch (error) {
    errorMessage.value = 'Credenciales incorrectas o error de conexión.';
  }
};
</script>