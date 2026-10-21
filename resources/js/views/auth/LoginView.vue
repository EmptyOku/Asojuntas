<template>
  <div class="min-h-screen w-full flex flex-col lg:flex-row bg-white font-sans text-gray-900">
    <!-- Presentación -->
    <section class="login-hero relative isolate overflow-hidden text-white lg:w-[58%] lg:min-h-screen">
      <!-- Textura: anillos grandes, haces de luz diagonales y grano -->
      <div class="login-rings pointer-events-none absolute -z-10" aria-hidden="true">
        <span></span><span></span><span></span>
      </div>
      <div class="login-beams pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
      <div class="login-grain pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>

      <!-- Halos de luz que se desplazan despacio -->
      <div class="login-blob login-blob--a pointer-events-none absolute -z-10" aria-hidden="true"></div>
      <div class="login-blob login-blob--b pointer-events-none absolute -z-10" aria-hidden="true"></div>

      <div class="relative flex h-full flex-col justify-center px-6 sm:px-12 xl:px-16 py-10 lg:py-16">
        <span class="login-rise inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-emerald-100 ring-1 ring-white/15 backdrop-blur-sm">
          <ShieldCheck class="w-3.5 h-3.5 text-emerald-300" />
          Desarrollo y pruebas
        </span>

        <h1 class="login-rise mt-5 max-w-xl font-display text-4xl sm:text-5xl font-bold leading-[1.05] tracking-tight drop-shadow-sm" style="animation-delay: 80ms">
          Cada voto de tu barrio,<br />
          <span class="text-emerald-300">contado con<br class="hidden sm:block" />Veracidad</span>
        </h1>

        <p class="login-rise mt-5 max-w-md text-sm sm:text-base font-medium text-white/80" style="animation-delay: 160ms">
          Plataforma oficial para la digitalización de actas de escrutinio, verificación y consolidación del escrutinio comunal de Girardot.
        </p>
      </div>
    </section>

    <!-- Ingreso -->
    <section class="flex flex-1 flex-col">
      <div class="flex flex-1 items-center justify-center px-6 sm:px-10 py-10">
        <div class="w-full max-w-sm">
          <div class="flex flex-col items-center text-center">
            <!-- Los colores del escudo -->
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-100">
              <span class="h-1 w-5 rounded-full bg-aso-red"></span>
              <span class="h-1 w-5 rounded-full bg-aso-green"></span>
              <span class="h-1 w-5 rounded-full bg-aso-yellow"></span>
              <span class="h-1 w-5 rounded-full bg-aso-blue"></span>
            </span>
            <img src="@/assets/img/logo-asojuntas.png" alt="Logo de Asojuntas Girardot" class="mt-3 h-28 sm:h-32 w-auto object-contain" />

            <h2 class="mt-6 font-display text-3xl font-bold text-gray-950">¡Bienvenido de nuevo!</h2>
            <p class="mt-1.5 text-sm text-gray-500">Ingresa para gestionar el escrutinio de las Juntas de Acción Comunal</p>
          </div>

          <form class="mt-7 space-y-4" novalidate @submit.prevent="handleLogin">
            <div>
              <label for="identity" class="text-sm font-medium text-gray-700">Correo o usuario</label>
              <div class="relative mt-1.5">
                <Mail class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  id="identity"
                  v-model.trim="credentials.identity"
                  type="text"
                  autocomplete="username"
                  required
                  class="login-input pl-10 pr-3.5"
                  placeholder="usuario@asojuntasgirardot.org"
                />
              </div>
            </div>

            <div>
              <label for="password" class="text-sm font-medium text-gray-700">Contraseña</label>
              <div class="relative mt-1.5">
                <LockKeyhole class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  id="password"
                  v-model="credentials.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  class="login-input pl-10 pr-11"
                  placeholder="••••••••••••"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 flex items-center px-3.5 text-gray-400 hover:text-aso-primary transition-colors"
                  :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between gap-3">
              <label class="flex items-center gap-2 text-sm text-gray-700 select-none cursor-pointer">
                <input v-model="credentials.remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-aso-primary focus:ring-aso-primary" />
                Recordarme
              </label>
              <button type="button" class="text-sm font-semibold text-sky-700 hover:text-sky-800" @click="showRecovery = !showRecovery">
                ¿Olvidaste tu contraseña?
              </button>
            </div>

            <p v-if="credentials.remember" class="-mt-1 text-xs text-gray-400">
              Mantiene la sesión abierta en este equipo. No lo marques en un computador compartido.
            </p>
            <p v-if="showRecovery" class="rounded-xl bg-sky-50 px-3.5 py-3 text-sm text-sky-900 ring-1 ring-sky-100">
              Pide a un administrador de la Asociación que te asigne una contraseña nueva desde "Usuarios y accesos".
            </p>

            <p v-if="errorMessage" class="flex items-start gap-2 rounded-xl bg-red-50 px-3.5 py-3 text-sm text-red-700 ring-1 ring-red-100" role="alert">
              <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
              {{ errorMessage }}
            </p>

            <button
              type="submit"
              :disabled="authStore.loading"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-aso-primary py-3 text-sm font-semibold text-white shadow-md shadow-aso-primary/25 transition hover:bg-aso-primary-dark hover:-translate-y-px disabled:opacity-70 disabled:hover:translate-y-0"
            >
              <Loader2 v-if="authStore.loading" class="w-4 h-4 animate-spin" />
              {{ authStore.loading ? 'Verificando…' : 'Iniciar sesión' }}
            </button>
          </form>
        </div>
      </div>

      <footer class="flex items-center justify-center gap-3 px-6 pb-6 text-xs text-gray-400">
        <img src="@/assets/img/logo-piloto.png" alt="Logo de la Universidad Piloto de Colombia" class="h-7 w-auto object-contain opacity-80" />
        <span>© {{ year }} Asojuntas Girardot · Universidad Piloto de Colombia</span>
      </footer>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  AlertCircle, Eye, EyeOff, Loader2, LockKeyhole, Mail, ShieldCheck
} from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { firstAllowedRoute } from '@/router';

const router = useRouter();
const authStore = useAuthStore();

const credentials = ref({ identity: '', password: '', remember: false });
const errorMessage = ref('');
const showPassword = ref(false);
const showRecovery = ref(false);
const year = new Date().getFullYear();

const handleLogin = async () => {
  errorMessage.value = '';
  if (!credentials.value.identity || !credentials.value.password) {
    errorMessage.value = 'Escribe tu usuario o correo y tu contraseña.';
    return;
  }

  try {
    await authStore.login(credentials.value);
    // Inicio según permisos (primera entrada del menú que puede abrir); si el
    // rol no tiene ningún módulo, va a la pantalla "sin módulos".
    router.push(firstAllowedRoute(authStore));
  } catch (error) {
    const status = Number(error?.response?.status || 0);
    errorMessage.value = {
      401: 'Usuario o contraseña incorrectos, o la cuenta está desactivada.',
      419: 'La página estuvo abierta mucho tiempo. Recárgala e inténtalo de nuevo.',
      422: 'Escribe tu usuario o correo y tu contraseña.',
      429: 'Demasiados intentos seguidos. Espera un minuto e inténtalo de nuevo.',
    }[status] ?? 'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.';
  }
};
</script>

<style scoped>
/* ---------------------------------------------------------------- fondo verde */
.login-hero {
  background:
    radial-gradient(120% 90% at 85% 10%, rgba(98, 168, 46, 0.35), transparent 55%),
    linear-gradient(135deg, #0c330b 0%, #16500f 45%, #24691a 100%);
}

/* Anillos concéntricos muy suaves que giran lento. */
.login-rings {
  width: 70rem;
  height: 70rem;
  left: -24rem;
  bottom: -40rem;
  animation: login-spin 120s linear infinite;
}
.login-rings span {
  position: absolute;
  inset: 0;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.07);
}
.login-rings span:nth-child(2) { inset: 9rem; border-color: rgba(255, 255, 255, 0.06); border-style: dashed; }
.login-rings span:nth-child(3) { inset: 18rem; border-color: rgba(167, 243, 208, 0.08); }

/* Haces de luz diagonales que se deslizan. */
.login-beams {
  background: repeating-linear-gradient(
    115deg,
    transparent 0 120px,
    rgba(255, 255, 255, 0.035) 120px 180px,
    transparent 180px 300px
  );
  background-size: 200% 200%;
  animation: login-beams 28s linear infinite;
  mask-image: linear-gradient(to bottom, black 0%, black 60%, transparent 100%);
}

/* Grano fino para darle textura de papel. */
.login-grain {
  opacity: 0.18;
  mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}

.login-blob {
  border-radius: 9999px;
  filter: blur(64px);
}
.login-blob--a {
  width: 28rem; height: 28rem; top: -8rem; left: 20%;
  background: rgba(110, 231, 183, 0.22);
  animation: login-float-a 18s ease-in-out infinite;
}
.login-blob--b {
  width: 22rem; height: 22rem; bottom: -6rem; right: 5%;
  background: rgba(242, 200, 30, 0.12);
  animation: login-float-b 22s ease-in-out infinite;
}

/* ---------------------------------------------------------------- entrada del texto */
.login-rise {
  opacity: 0;
  animation: login-rise 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) forwards;
}

/* ---------------------------------------------------------------- campos */
.login-input {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid rgb(229 231 235);
  background: #fff;
  padding-top: 0.75rem;
  padding-bottom: 0.75rem;
  font-size: 0.875rem;
  color: rgb(17 24 39);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.login-input::placeholder { color: rgb(156 163 175); }
.login-input:focus {
  outline: none;
  border-color: var(--color-aso-primary);
  box-shadow: 0 0 0 4px rgb(69 130 31 / 0.15);
}

@keyframes login-spin { to { transform: rotate(360deg); } }
@keyframes login-beams { from { background-position: 0% 0%; } to { background-position: 100% 100%; } }
@keyframes login-float-a { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(6rem, 3rem); } }
@keyframes login-float-b { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-5rem, -4rem); } }
@keyframes login-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

/* Quien prefiere menos movimiento ve la pantalla quieta. */
@media (prefers-reduced-motion: reduce) {
  .login-rings, .login-beams, .login-blob { animation: none; }
  .login-rise { animation: none; opacity: 1; }
}
</style>
