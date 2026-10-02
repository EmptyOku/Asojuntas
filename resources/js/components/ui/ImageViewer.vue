<template>
  <!-- Visor de fotos de actas y planchas con zoom, para celular y computador.
       - Botones − / + / ajustar.
       - Computador: Ctrl + rueda para acercar, arrastrar para moverse, doble clic.
       - Celular: pellizcar para acercar, arrastrar con un dedo, doble toque.
       El desplazamiento es el nativo del navegador: al acercar, la imagen
       simplemente se hace más grande que el recuadro. -->
  <div class="relative h-full w-full min-h-0">
    <div
      ref="scroller"
      class="viewer-scroll h-full w-full overflow-auto"
      :class="scale > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      @pointerleave="onPointerUp"
      @dblclick="onDoubleClick"
    >
      <img
        :src="src"
        :alt="alt"
        draggable="false"
        class="block select-none object-contain mx-auto"
        :style="{ width: `${scale * 100}%`, height: `${scale * 100}%`, maxWidth: 'none' }"
        @load="$emit('load')"
        @error="$emit('error')"
      >
    </div>

    <!-- Controles -->
    <div class="absolute bottom-3 right-3 z-10 flex items-center gap-1 rounded-xl bg-black/70 p-1 text-white shadow-lg backdrop-blur">
      <button type="button" class="viewer-btn" :disabled="scale <= MIN" aria-label="Alejar" data-tooltip="Alejar" @click="zoomBy(-STEP)">
        <ZoomOut class="w-4 h-4" />
      </button>
      <span class="w-11 text-center text-xs font-semibold tabular-nums" aria-live="polite">{{ Math.round(scale * 100) }}%</span>
      <button type="button" class="viewer-btn" :disabled="scale >= MAX" aria-label="Acercar" data-tooltip="Acercar" @click="zoomBy(STEP)">
        <ZoomIn class="w-4 h-4" />
      </button>
      <button type="button" class="viewer-btn" :disabled="scale === 1" aria-label="Ajustar a la pantalla" data-tooltip="Ajustar a la pantalla" @click="reset">
        <Maximize class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Maximize, ZoomIn, ZoomOut } from 'lucide-vue-next';

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: 'Documento' },
});

defineEmits(['load', 'error']);

const MIN = 1;
const MAX = 5;
const STEP = 0.5;

const scroller = ref(null);
const scale = ref(1);
const dragging = ref(false);

// Cambia el zoom manteniendo fijo el punto (x, y) del recuadro: lo que está
// bajo el cursor o entre los dedos no se mueve de sitio.
const zoomTo = async (next, x, y) => {
  const el = scroller.value;
  if (!el) return;
  const target = Math.min(MAX, Math.max(MIN, Number(next.toFixed(2))));
  if (target === scale.value) return;

  const fx = x ?? el.clientWidth / 2;
  const fy = y ?? el.clientHeight / 2;
  const ratioX = (el.scrollLeft + fx) / el.scrollWidth;
  const ratioY = (el.scrollTop + fy) / el.scrollHeight;

  scale.value = target;
  await nextTick();
  el.scrollLeft = ratioX * el.scrollWidth - fx;
  el.scrollTop = ratioY * el.scrollHeight - fy;
};

const zoomBy = (delta) => zoomTo(scale.value + delta);
const reset = () => zoomTo(1);

const localPoint = (event) => {
  const rect = scroller.value.getBoundingClientRect();
  return [event.clientX - rect.left, event.clientY - rect.top];
};

const onDoubleClick = (event) => {
  const [x, y] = localPoint(event);
  zoomTo(scale.value > 1 ? 1 : 2.5, x, y);
};

// --- Computador: arrastrar con el mouse para moverse por la imagen ampliada ---
let dragStart = null;
const onPointerDown = (event) => {
  if (event.pointerType !== 'mouse' || event.button !== 0 || scale.value <= 1) return;
  dragStart = { x: event.clientX, y: event.clientY, left: scroller.value.scrollLeft, top: scroller.value.scrollTop };
  dragging.value = true;
};
const onPointerMove = (event) => {
  if (!dragStart) return;
  scroller.value.scrollLeft = dragStart.left - (event.clientX - dragStart.x);
  scroller.value.scrollTop = dragStart.top - (event.clientY - dragStart.y);
};
const onPointerUp = () => {
  dragStart = null;
  dragging.value = false;
};

// --- Ctrl + rueda (y el gesto de pellizco del panel táctil, que llega igual) ---
const onWheel = (event) => {
  if (!event.ctrlKey && !event.metaKey) return; // la rueda sola sigue desplazando
  event.preventDefault();
  const [x, y] = localPoint(event);
  zoomTo(scale.value * (event.deltaY < 0 ? 1.15 : 1 / 1.15), x, y);
};

// --- Celular: pellizcar con dos dedos ---
let pinch = null;
const distance = (touches) => Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
const onTouchStart = (event) => {
  if (event.touches.length === 2) {
    pinch = { distance: distance(event.touches), scale: scale.value };
  }
};
const onTouchMove = (event) => {
  if (!pinch || event.touches.length !== 2) return;
  event.preventDefault(); // que el navegador no amplíe toda la página
  const rect = scroller.value.getBoundingClientRect();
  const x = (event.touches[0].clientX + event.touches[1].clientX) / 2 - rect.left;
  const y = (event.touches[0].clientY + event.touches[1].clientY) / 2 - rect.top;
  zoomTo(pinch.scale * (distance(event.touches) / pinch.distance), x, y);
};
const onTouchEnd = (event) => {
  if (event.touches.length < 2) pinch = null;
};

// Estos eventos necesitan passive: false para poder cancelar el gesto del navegador.
onMounted(() => {
  const el = scroller.value;
  el.addEventListener('wheel', onWheel, { passive: false });
  el.addEventListener('touchstart', onTouchStart, { passive: true });
  el.addEventListener('touchmove', onTouchMove, { passive: false });
  el.addEventListener('touchend', onTouchEnd, { passive: true });
});
onBeforeUnmount(() => {
  const el = scroller.value;
  if (!el) return;
  el.removeEventListener('wheel', onWheel);
  el.removeEventListener('touchstart', onTouchStart);
  el.removeEventListener('touchmove', onTouchMove);
  el.removeEventListener('touchend', onTouchEnd);
});

// Otra foto: vuelve a verse completa.
watch(() => props.src, () => {
  scale.value = 1;
  if (scroller.value) {
    scroller.value.scrollLeft = 0;
    scroller.value.scrollTop = 0;
  }
});
</script>

<style scoped>
.viewer-scroll {
  /* Un dedo desplaza (nativo); el pellizco lo maneja el componente. */
  touch-action: pan-x pan-y;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: #4b5563 transparent;
}
.viewer-btn {
  display: inline-flex;
  height: 2.25rem;
  width: 2.25rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  transition: background-color 0.15s;
}
.viewer-btn:hover:not(:disabled) { background-color: rgb(255 255 255 / 0.15); }
.viewer-btn:disabled { opacity: 0.35; cursor: not-allowed; }
</style>
