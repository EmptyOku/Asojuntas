import { computed, ref } from 'vue';

const pendingRequests = ref(0);
const routeLoading = ref(false);

export const isGlobalLoading = computed(() => pendingRequests.value > 0 || routeLoading.value);

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
