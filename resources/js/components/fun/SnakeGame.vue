<template>
  <div class="snake select-none" @keydown="onKeydown">
    <!-- Marcador -->
    <div class="mb-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-aso-primary">
          <Vote class="w-4.5 h-4.5" />
        </span>
        <div class="leading-tight">
          <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Votos contados</p>
          <p class="font-display text-xl font-bold tabular-nums text-gray-900" aria-live="polite">{{ score }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <div class="text-right leading-tight">
          <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Récord</p>
          <p class="font-display text-xl font-bold tabular-nums text-amber-600">{{ best }}</p>
        </div>
        <button
          v-if="state === 'playing' || state === 'paused'"
          type="button"
          class="icon-btn-blue"
          :aria-label="state === 'paused' ? 'Continuar' : 'Pausar'"
          @click="togglePause"
        >
          <Play v-if="state === 'paused'" class="w-4 h-4" />
          <Pause v-else class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Tablero -->
    <div
      ref="frame"
      class="relative mx-auto aspect-square w-full max-w-[420px] overflow-hidden rounded-2xl ring-1 ring-gray-200 shadow-inner outline-none focus-visible:ring-2 focus-visible:ring-aso-primary"
      tabindex="0"
      @touchstart.passive="onTouchStart"
      @touchend.passive="onTouchEnd"
    >
      <canvas ref="canvas" class="block h-full w-full" role="img" :aria-label="`Juego de la culebrita. Votos contados: ${score}`"></canvas>

      <!-- Pantallas de inicio, pausa y fin -->
      <transition name="snake-fade">
        <div v-if="state !== 'playing'" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/80 backdrop-blur-sm p-6 text-center">
          <template v-if="state === 'ready'">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-aso-primary text-white shadow-lg shadow-aso-primary/30">
              <Vote class="w-7 h-7" />
            </span>
            <p class="font-display text-xl font-bold text-gray-900">Cuenta los votos</p>
            <p class="max-w-[16rem] text-sm text-gray-500">Guía la plancha con las flechas (o deslizando el dedo) y recoge cada voto sin chocar.</p>
          </template>
          <template v-else-if="state === 'paused'">
            <p class="font-display text-xl font-bold text-gray-900">En pausa</p>
            <p class="text-sm text-gray-500">El conteo te espera.</p>
          </template>
          <template v-else>
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
              <Trophy class="w-7 h-7" />
            </span>
            <p class="font-display text-xl font-bold text-gray-900">Fin del escrutinio</p>
            <p class="text-sm text-gray-500">
              Contaste <strong class="text-gray-900">{{ score }}</strong> {{ score === 1 ? 'voto' : 'votos' }}.
              <template v-if="newRecord"><br /><span class="font-semibold text-amber-600">¡Nuevo récord!</span></template>
            </p>
          </template>

          <button type="button" class="btn-primary mt-1" @click="state === 'paused' ? togglePause() : start()">
            <Play class="w-4 h-4" />
            {{ state === 'paused' ? 'Continuar' : (state === 'over' ? 'Jugar otra vez' : 'Jugar') }}
          </button>
          <p class="hidden sm:block text-[11px] text-gray-400">Espacio para empezar o pausar</p>
        </div>
      </transition>
    </div>

    <!-- Cruceta para celular -->
    <div class="mt-4 grid grid-cols-3 gap-2 w-40 mx-auto sm:hidden" aria-label="Controles">
      <span></span>
      <button type="button" class="snake-pad" aria-label="Arriba" @click="turn(0, -1)"><ChevronUp class="w-5 h-5" /></button>
      <span></span>
      <button type="button" class="snake-pad" aria-label="Izquierda" @click="turn(-1, 0)"><ChevronLeft class="w-5 h-5" /></button>
      <button type="button" class="snake-pad" :aria-label="state === 'playing' ? 'Pausar' : 'Jugar'" @click="state === 'playing' || state === 'paused' ? togglePause() : start()">
        <Pause v-if="state === 'playing'" class="w-4 h-4" />
        <Play v-else class="w-4 h-4" />
      </button>
      <button type="button" class="snake-pad" aria-label="Derecha" @click="turn(1, 0)"><ChevronRight class="w-5 h-5" /></button>
      <span></span>
      <button type="button" class="snake-pad" aria-label="Abajo" @click="turn(0, 1)"><ChevronDown class="w-5 h-5" /></button>
      <span></span>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { ChevronDown, ChevronLeft, ChevronRight, ChevronUp, Pause, Play, Trophy, Vote } from 'lucide-vue-next';

/*
 * La culebrita (Snake) con los colores de Asojuntas.
 *
 * Pensado para gastar poco: un solo <canvas>, el ciclo de dibujo solo corre
 * mientras se juega y se detiene solo si la pestaña se oculta. En pausa o en
 * las pantallas de inicio/fin no se dibuja nada.
 */

const GRID = 20; // casillas por lado
const START_SPEED = 135; // ms por paso
const MIN_SPEED = 70;
const BEST_KEY = 'asojuntas.snake.best';

const canvas = ref(null);
const frame = ref(null);
const score = ref(0);
const best = ref(readBest());
const newRecord = ref(false);
const state = ref('ready'); // ready | playing | paused | over

let ctx = null;
let size = 0; // px CSS del tablero
let cell = 0;
let snake = [];
let dir = { x: 1, y: 0 };
let queue = [];
let food = null;
let speed = START_SPEED;
let rafId = 0;
let lastStep = 0;
let particles = [];
let colors = null;
let resizeObserver = null;
let touch = null;

function readBest() {
  try {
    return Number(window.localStorage.getItem(BEST_KEY)) || 0;
  } catch {
    return 0;
  }
}

function saveBest(value) {
  try {
    window.localStorage.setItem(BEST_KEY, String(value));
  } catch {
    // Sin almacenamiento (modo privado): el récord dura lo que dure la página.
  }
}

// Colores de la marca, leídos de las variables de Tailwind (con respaldo).
function brandColors() {
  const css = getComputedStyle(document.documentElement);
  const read = (name, fallback) => css.getPropertyValue(name).trim() || fallback;
  return {
    primary: read('--color-aso-primary', '#45821f'),
    light: read('--color-aso-primary-light', '#62a82e'),
    deep: read('--color-aso-deep', '#1a350b'),
    votes: [read('--color-aso-red', '#d0141d'), read('--color-aso-yellow', '#f2c81e'), read('--color-aso-blue', '#6fb0e8'), read('--color-aso-green', '#62a82e')],
  };
}

function resize() {
  if (!canvas.value || !frame.value) return;
  const ratio = Math.min(window.devicePixelRatio || 1, 2);
  size = frame.value.clientWidth;
  cell = size / GRID;
  canvas.value.width = Math.round(size * ratio);
  canvas.value.height = Math.round(size * ratio);
  ctx = canvas.value.getContext('2d');
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  draw(performance.now());
}

function reset() {
  const mid = Math.floor(GRID / 2);
  snake = [{ x: mid - 1, y: mid }, { x: mid - 2, y: mid }, { x: mid - 3, y: mid }];
  dir = { x: 1, y: 0 };
  queue = [];
  speed = START_SPEED;
  score.value = 0;
  newRecord.value = false;
  particles = [];
  placeFood();
}

function placeFood() {
  const free = [];
  for (let x = 0; x < GRID; x += 1) {
    for (let y = 0; y < GRID; y += 1) {
      if (!snake.some((p) => p.x === x && p.y === y)) free.push({ x, y });
    }
  }
  const spot = free[Math.floor(Math.random() * free.length)];
  food = spot ? { ...spot, color: colors.votes[Math.floor(Math.random() * colors.votes.length)], born: performance.now() } : null;
}

function start() {
  reset();
  state.value = 'playing';
  frame.value?.focus({ preventScroll: true });
  lastStep = performance.now();
  loop(lastStep);
}

function togglePause() {
  if (state.value === 'playing') {
    state.value = 'paused';
    cancelAnimationFrame(rafId);
  } else if (state.value === 'paused') {
    state.value = 'playing';
    frame.value?.focus({ preventScroll: true });
    lastStep = performance.now();
    loop(lastStep);
  }
}

function turn(x, y) {
  if (state.value !== 'playing') return;
  const last = queue.length ? queue[queue.length - 1] : dir;
  // Ni dar media vuelta sobre sí misma ni repetir la misma dirección.
  if ((last.x === -x && last.y === -y) || (last.x === x && last.y === y)) return;
  if (queue.length < 3) queue.push({ x, y });
}

function step() {
  if (queue.length) dir = queue.shift();
  const head = { x: snake[0].x + dir.x, y: snake[0].y + dir.y };

  const hitWall = head.x < 0 || head.y < 0 || head.x >= GRID || head.y >= GRID;
  // La cola se mueve en este mismo paso, así que puede ocupar su casilla.
  const hitSelf = snake.slice(0, -1).some((p) => p.x === head.x && p.y === head.y);
  if (hitWall || hitSelf) {
    gameOver();
    return;
  }

  snake.unshift(head);
  if (food && head.x === food.x && head.y === food.y) {
    score.value += 1;
    burst(food);
    speed = Math.max(MIN_SPEED, speed - 3);
    placeFood();
  } else {
    snake.pop();
  }
}

function gameOver() {
  state.value = 'over';
  cancelAnimationFrame(rafId);
  if (score.value > best.value) {
    best.value = score.value;
    newRecord.value = score.value > 0;
    saveBest(best.value);
  }
  draw(performance.now());
}

function burst(spot) {
  const cx = (spot.x + 0.5) * cell;
  const cy = (spot.y + 0.5) * cell;
  for (let i = 0; i < 10; i += 1) {
    const angle = (Math.PI * 2 * i) / 10;
    particles.push({ x: cx, y: cy, vx: Math.cos(angle) * 1.6, vy: Math.sin(angle) * 1.6, life: 1, color: spot.color });
  }
}

function loop(now) {
  if (state.value !== 'playing') return;
  if (now - lastStep >= speed) {
    lastStep = now;
    step();
  }
  draw(now);
  if (state.value === 'playing') rafId = requestAnimationFrame(loop);
}

// ------------------------------------------------------------------ dibujo

function roundRect(x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

function mix(a, b, t) {
  const hex = /^#[0-9a-f]{6}$/i;
  if (!hex.test(a) || !hex.test(b)) return a;
  const pa = a.match(/\w\w/g).map((h) => parseInt(h, 16));
  const pb = b.match(/\w\w/g).map((h) => parseInt(h, 16));
  return `rgb(${pa.map((v, i) => Math.round(v + (pb[i] - v) * t)).join(',')})`;
}

function draw(now) {
  if (!ctx || !size) return;

  // Tablero ajedrezado muy suave.
  ctx.fillStyle = '#f7f8f4';
  ctx.fillRect(0, 0, size, size);
  ctx.fillStyle = '#eef1e8';
  for (let x = 0; x < GRID; x += 1) {
    for (let y = (x % 2); y < GRID; y += 2) ctx.fillRect(x * cell, y * cell, cell, cell);
  }

  // Voto: una papeleta con su chulo, que late.
  if (food) {
    const pulse = 1 + Math.sin((now - food.born) / 180) * 0.06;
    const s = cell * 0.78 * pulse;
    const fx = (food.x + 0.5) * cell - s / 2;
    const fy = (food.y + 0.5) * cell - s / 2;
    ctx.shadowColor = food.color;
    ctx.shadowBlur = 10;
    ctx.fillStyle = food.color;
    roundRect(fx, fy, s, s, s * 0.28);
    ctx.fill();
    ctx.shadowBlur = 0;
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = Math.max(1.5, cell * 0.1);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(fx + s * 0.27, fy + s * 0.52);
    ctx.lineTo(fx + s * 0.44, fy + s * 0.68);
    ctx.lineTo(fx + s * 0.74, fy + s * 0.34);
    ctx.stroke();
  }

  // Plancha (culebra): degradado del verde claro al oscuro.
  const total = snake.length;
  for (let i = total - 1; i >= 0; i -= 1) {
    const part = snake[i];
    const inset = i === 0 ? cell * 0.04 : cell * 0.1;
    ctx.fillStyle = mix(colors.light, colors.deep, total > 1 ? i / (total - 1) : 0);
    roundRect(part.x * cell + inset, part.y * cell + inset, cell - inset * 2, cell - inset * 2, cell * 0.32);
    ctx.fill();
  }

  // Ojos, mirando hacia donde va.
  if (snake[0]) {
    const head = snake[0];
    const cx = (head.x + 0.5) * cell;
    const cy = (head.y + 0.5) * cell;
    const side = { x: -dir.y, y: dir.x };
    [-1, 1].forEach((k) => {
      const ex = cx + dir.x * cell * 0.16 + side.x * k * cell * 0.2;
      const ey = cy + dir.y * cell * 0.16 + side.y * k * cell * 0.2;
      ctx.fillStyle = '#ffffff';
      ctx.beginPath();
      ctx.arc(ex, ey, cell * 0.12, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = colors.deep;
      ctx.beginPath();
      ctx.arc(ex + dir.x * cell * 0.04, ey + dir.y * cell * 0.04, cell * 0.06, 0, Math.PI * 2);
      ctx.fill();
    });
  }

  // Chispas al contar un voto.
  particles = particles.filter((p) => p.life > 0);
  particles.forEach((p) => {
    p.x += p.vx;
    p.y += p.vy;
    p.life -= 0.05;
    ctx.globalAlpha = Math.max(0, p.life);
    ctx.fillStyle = p.color;
    ctx.beginPath();
    ctx.arc(p.x, p.y, cell * 0.1, 0, Math.PI * 2);
    ctx.fill();
  });
  ctx.globalAlpha = 1;
}

// ------------------------------------------------------------------ controles

const KEYS = {
  ArrowUp: [0, -1], KeyW: [0, -1],
  ArrowDown: [0, 1], KeyS: [0, 1],
  ArrowLeft: [-1, 0], KeyA: [-1, 0],
  ArrowRight: [1, 0], KeyD: [1, 0],
};

function onKeydown(event) {
  if (event.code === 'Space' || event.code === 'Enter' || event.code === 'KeyP') {
    event.preventDefault();
    if (state.value === 'playing' || state.value === 'paused') togglePause();
    else start();
    return;
  }
  const move = KEYS[event.code];
  if (move) {
    event.preventDefault(); // que las flechas no muevan la página
    if (state.value === 'ready' || state.value === 'over') start();
    turn(...move);
  }
}

// Teclas también cuando el foco no está en el tablero (sin robar las de los campos de texto).
function onWindowKeydown(event) {
  const tag = event.target?.tagName;
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || event.target?.isContentEditable) return;
  if (frame.value?.parentElement?.contains(event.target)) return; // ya lo maneja el componente
  if (KEYS[event.code] && state.value === 'playing') onKeydown(event);
}

function onTouchStart(event) {
  const t = event.changedTouches[0];
  touch = { x: t.clientX, y: t.clientY };
}

function onTouchEnd(event) {
  if (!touch) return;
  const t = event.changedTouches[0];
  const dx = t.clientX - touch.x;
  const dy = t.clientY - touch.y;
  touch = null;
  if (Math.max(Math.abs(dx), Math.abs(dy)) < 24) return;
  if (state.value === 'ready' || state.value === 'over') start();
  if (Math.abs(dx) > Math.abs(dy)) turn(Math.sign(dx), 0);
  else turn(0, Math.sign(dy));
}

// Si la pestaña se oculta, pausa sola (y deja de dibujar).
function onVisibility() {
  if (document.hidden && state.value === 'playing') togglePause();
}

onMounted(() => {
  colors = brandColors();
  reset();
  resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(frame.value);
  resize();
  document.addEventListener('visibilitychange', onVisibility);
  window.addEventListener('keydown', onWindowKeydown);
});

onBeforeUnmount(() => {
  cancelAnimationFrame(rafId);
  resizeObserver?.disconnect();
  document.removeEventListener('visibilitychange', onVisibility);
  window.removeEventListener('keydown', onWindowKeydown);
});
</script>

<style scoped>
.snake-pad {
  display: flex;
  height: 3rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.9rem;
  background: #fff;
  color: var(--color-aso-primary);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.06), inset 0 0 0 1px rgb(229 231 235);
  transition: transform 0.1s, background 0.15s;
}
.snake-pad:active {
  transform: scale(0.94);
  background: #f0f7ea;
}
.snake-fade-enter-active,
.snake-fade-leave-active {
  transition: opacity 0.2s ease;
}
.snake-fade-enter-from,
.snake-fade-leave-to {
  opacity: 0;
}
</style>
