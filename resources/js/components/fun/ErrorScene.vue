<template>
  <div class="min-h-screen bg-aso-bg font-sans text-gray-900">
    <!-- Encabezado verde: qué pasó y qué hacer -->
    <header class="error-hero relative isolate overflow-hidden text-white">
      <div class="error-beams pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
      <div class="error-grain pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
      <div class="brand-stripe"></div>

      <div class="mx-auto max-w-6xl px-4 sm:px-6 pt-5 pb-14 sm:pb-20">
        <div class="flex items-center gap-3">
          <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white p-1 shadow-sm">
            <img src="@/assets/img/logo-asojuntas.png" alt="Logo de Asojuntas Girardot" class="h-full w-auto object-contain" />
          </span>
          <div class="leading-tight">
            <p class="font-display text-sm font-bold">ASOJUNTAS GIRARDOT</p>
            <p class="text-xs text-white/60">Sistema de escrutinio comunal</p>
          </div>
        </div>

        <div class="mt-10 grid items-center gap-6 lg:grid-cols-[auto_1fr]">
          <p class="error-code font-display text-7xl sm:text-8xl font-bold leading-none tracking-tight" aria-hidden="true">{{ code }}</p>
          <div class="max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-emerald-100 ring-1 ring-white/15">
              <component :is="icon" class="w-3.5 h-3.5 text-aso-yellow" />
              {{ kicker }}
            </span>
            <h1 class="mt-3 font-display text-2xl sm:text-4xl font-bold leading-tight">{{ title }}</h1>
            <p class="mt-2 text-sm sm:text-base text-white/75">{{ message }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
              <slot name="actions" />
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Cuerpo: el juego -->
    <main class="relative z-10 mx-auto -mt-8 sm:-mt-12 max-w-6xl px-4 sm:px-6 pb-10">
      <div class="grid gap-5 lg:grid-cols-[1fr_18rem] items-start">
        <section class="card p-4 sm:p-6">
          <div class="mb-4">
            <h2 class="font-display text-lg font-bold text-gray-900">Mientras tanto, cuenta unos votos</h2>
            <p class="text-sm text-gray-500">Un descanso corto antes de volver al escrutinio.</p>
          </div>
          <SnakeGame />
        </section>

        <aside class="card p-5 space-y-4">
          <h3 class="font-display text-base font-bold text-gray-900">Cómo se juega</h3>
          <ul class="space-y-3 text-sm text-gray-600">
            <li class="flex gap-3">
              <span class="error-key">←↑→↓</span>
              <span>Flechas o <strong>W A S D</strong> para mover la plancha.</span>
            </li>
            <li class="flex gap-3">
              <span class="error-key">Espacio</span>
              <span>Empieza o pausa el juego.</span>
            </li>
            <li class="flex gap-3">
              <span class="error-key">Deslizar</span>
              <span>En el celular, desliza el dedo sobre el tablero o usa los botones.</span>
            </li>
          </ul>
          <p class="rounded-xl bg-emerald-50 px-3.5 py-3 text-xs text-emerald-900 ring-1 ring-emerald-100">
            Cada voto que recoges acelera un poco el conteo. Si chocas con el borde o contigo mismo, termina el escrutinio.
          </p>
        </aside>
      </div>
    </main>
  </div>
</template>

<script setup>
import SnakeGame from '@/components/fun/SnakeGame.vue';

defineProps({
  code: { type: String, required: true },
  kicker: { type: String, required: true },
  title: { type: String, required: true },
  message: { type: String, required: true },
  icon: { type: [Object, Function], required: true },
});
</script>

<style scoped>
.error-hero {
  background:
    radial-gradient(90% 120% at 90% 0%, rgba(98, 168, 46, 0.35), transparent 55%),
    linear-gradient(135deg, #0c330b 0%, #16500f 50%, #24691a 100%);
}
.error-beams {
  background: repeating-linear-gradient(115deg, transparent 0 120px, rgba(255, 255, 255, 0.035) 120px 180px, transparent 180px 300px);
  background-size: 200% 200%;
  animation: error-beams 30s linear infinite;
}
.error-grain {
  opacity: 0.18;
  mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}
/* El número grande, con el contorno y relleno degradado. */
.error-code {
  background: linear-gradient(180deg, #ffffff 10%, rgba(167, 243, 208, 0.75) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  filter: drop-shadow(0 6px 18px rgba(0, 0, 0, 0.25));
}
.error-key {
  flex-shrink: 0;
  min-width: 4.5rem;
  border-radius: 0.5rem;
  background: rgb(243 244 246);
  padding: 0.15rem 0.5rem;
  text-align: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: rgb(55 65 81);
  box-shadow: inset 0 -2px 0 rgb(229 231 235);
  height: fit-content;
}
@keyframes error-beams { from { background-position: 0% 0%; } to { background-position: 100% 100%; } }
@media (prefers-reduced-motion: reduce) {
  .error-beams { animation: none; }
}
</style>
