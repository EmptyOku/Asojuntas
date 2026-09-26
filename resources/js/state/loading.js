import { computed, ref, watch } from 'vue';

// El overlay solo aparece si la carga dura mas que esto: las peticiones
// rapidas terminan antes y no hacen parpadear la pantalla.
const SHOW_DELAY_MS = 400;

const pendingRequests = ref(0);
const routeLoading = ref(false);
const overlayVisible = ref(false);

const isBusy = computed(() => pendingRequests.value > 0 || routeLoading.value);

let showTimer = null;

watch(isBusy, (busy) => {
  if (busy) {
    showTimer ??= setTimeout(() => {
      showTimer = null;
      overlayVisible.value = true;
    }, SHOW_DELAY_MS);
    return;
  }

  clearTimeout(showTimer);
  showTimer = null;
  overlayVisible.value = false;
});

export const isGlobalLoading = computed(() => overlayVisible.value);

export const startRequestLoading = () => {
  pendingRequests.value += 1;
};

export const stopRequestLoading = () => {
  pendingRequests.value = Math.max(0, pendingRequests.value - 1);
};

export const startRouteLoading = () => {
  routeLoading.value = true;
};

export const stopRouteLoading = () => {
  routeLoading.value = false;
};
