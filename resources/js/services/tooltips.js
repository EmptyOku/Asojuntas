/**
 * Tooltips instantáneos para toda la app.
 *
 *   <button class="icon-btn-blue" data-tooltip="Editar usuario" aria-label="Editar usuario">…</button>
 *
 * El `title` nativo tarda ~1 s en aparecer, no se ve en pantallas táctiles ni
 * con teclado y no se puede estilizar. Aquí basta con `data-tooltip` (también
 * con :data-tooltip dinámico, se lee al mostrarlo): un solo globo compartido,
 * delegado en document, así no hay que registrar nada por componente.
 * Se muestra al pasar el mouse y al enfocar con Tab.
 */
const SELECTOR = '[data-tooltip]';
const GAP = 8;

let bubble = null;
let current = null;

const ensureBubble = () => {
  if (bubble) return bubble;
  bubble = document.createElement('div');
  bubble.className = 'app-tooltip';
  bubble.setAttribute('role', 'tooltip');
  bubble.id = 'app-tooltip';
  document.body.appendChild(bubble);
  return bubble;
};

const place = (target) => {
  const rect = target.getBoundingClientRect();
  const box = bubble.getBoundingClientRect();

  // Arriba por defecto; abajo si no cabe. Nunca se sale por los lados.
  let top = rect.top - box.height - GAP;
  let below = false;
  if (top < 4) {
    top = rect.bottom + GAP;
    below = true;
  }
  const left = Math.min(
    Math.max(4, rect.left + rect.width / 2 - box.width / 2),
    window.innerWidth - box.width - 4,
  );

  bubble.style.top = `${top}px`;
  bubble.style.left = `${left}px`;
  bubble.style.setProperty('--arrow-x', `${rect.left + rect.width / 2 - left}px`);
  bubble.classList.toggle('app-tooltip--below', below);
};

const show = (target) => {
  const text = target.getAttribute('data-tooltip');
  if (!text) return;

  current = target;
  ensureBubble();
  bubble.textContent = text;
  bubble.classList.add('app-tooltip--visible');
  place(target);
};

const hide = () => {
  current = null;
  bubble?.classList.remove('app-tooltip--visible');
};

export function installTooltips() {
  if (typeof document === 'undefined') return;

  document.addEventListener('pointerover', (event) => {
    if (event.pointerType === 'touch') return; // en táctil manda la leyenda de la tabla
    const target = event.target.closest?.(SELECTOR);
    if (target && target !== current) show(target);
    else if (!target && current) hide();
  });

  document.addEventListener('pointerout', (event) => {
    if (current && !current.contains(event.relatedTarget)) hide();
  });

  document.addEventListener('focusin', (event) => {
    const target = event.target.closest?.(SELECTOR);
    if (target && target.matches(':focus-visible')) show(target);
  });

  document.addEventListener('focusout', hide);
  document.addEventListener('click', hide, true);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(); });
  window.addEventListener('scroll', hide, true);
}
