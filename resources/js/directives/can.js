import { watchEffect } from 'vue';
import { useAuthStore } from '@/stores/auth';

/**
 * v-can: oculta un elemento si el usuario no tiene el permiso.
 *
 *   <button v-can="'records.approve'">Aprobar</button>
 *   <button v-can="['slates.review', 'slates.promote']">…</button>   // basta con uno
 *
 * Solo oculta (display: none): es para acciones dentro de una pantalla. Para
 * no montar un componente, usa v-if="auth.can('…')". La seguridad real la
 * aplica el backend (api.permission), esto es solo experiencia de usuario.
 */
export const can = {
  mounted(el, binding) {
    const auth = useAuthStore();

    // Reacciona a cambios de permisos (p. ej. refresco tras un 403).
    el.__vCanStop = watchEffect(() => {
      const allowed = auth.canAny(el.__vCanValue ?? binding.value);
      el.style.display = allowed ? el.__vCanDisplay : 'none';
    });
  },

  beforeMount(el, binding) {
    el.__vCanDisplay = el.style.display;
    el.__vCanValue = binding.value;
  },

  updated(el, binding) {
    if (el.__vCanValue !== binding.value) {
      el.__vCanValue = binding.value;
      const allowed = useAuthStore().canAny(binding.value);
      el.style.display = allowed ? el.__vCanDisplay : 'none';
    }
  },

  unmounted(el) {
    el.__vCanStop?.();
  }
};
