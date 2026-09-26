import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Anima un número desde su valor actual hasta el nuevo cada vez que cambia
 * la fuente (p. ej. al llegar los datos del servidor).
 *
 * @param {() => number} source  función reactiva con el valor destino
 * @param {number} duration      duración en ms
 */
export function useCountUp(source, duration = 900) {
  const display = ref(0);
  let frame = null;

  const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  watch(source, (target) => {
    const to = Number(target) || 0;
    cancelAnimationFrame(frame);

    if (reducedMotion()) {
      display.value = to;
      return;
    }

    const from = display.value;
    const start = performance.now();

    const step = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      display.value = Math.round(from + (to - from) * eased);
      if (progress < 1) frame = requestAnimationFrame(step);
    };

    frame = requestAnimationFrame(step);
  }, { immediate: true });

  onBeforeUnmount(() => cancelAnimationFrame(frame));

  return display;
}
