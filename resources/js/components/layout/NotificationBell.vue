<template>
  <div v-if="canSee" ref="root" class="relative">
    <button
      type="button"
      class="h-10 w-10 flex items-center justify-center rounded-xl transition-colors relative"
      :class="open ? 'bg-gray-100 text-gray-800' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'"
      :aria-label="unread ? `Notificaciones: ${unreadLabel} sin ver` : 'Notificaciones'"
      :aria-expanded="open"
      aria-haspopup="dialog"
      @click="toggle"
    >
      <Bell class="w-5 h-5" />
      <span
        v-if="unread"
        class="absolute -top-0.5 -right-0.5 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-[1.15rem] text-center ring-2 ring-white tabular-nums"
      >{{ unreadLabel }}</span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 top-12 z-50 w-[calc(100vw-2rem)] max-w-sm rounded-2xl bg-white shadow-xl ring-1 ring-gray-200/80 overflow-hidden animate-rise"
      role="dialog"
      aria-label="Notificaciones"
    >
      <div class="brand-stripe"></div>
      <div class="px-4 py-3 border-b border-gray-100">
        <p class="font-display font-bold text-gray-900">Notificaciones</p>
        <p class="text-xs text-gray-500">Actas recibidas y planchas registradas por otros usuarios</p>
      </div>

      <!-- Cargando -->
      <ul v-if="loading && !items.length" class="divide-y divide-gray-100">
        <li v-for="n in 4" :key="n" class="flex gap-3 px-4 py-3">
          <span class="h-9 w-9 rounded-xl bg-gray-100 animate-pulse"></span>
          <span class="flex-1 space-y-2 py-1">
            <span class="block h-3 w-2/3 rounded bg-gray-100 animate-pulse"></span>
            <span class="block h-3 w-1/2 rounded bg-gray-100 animate-pulse"></span>
          </span>
        </li>
      </ul>

      <p v-else-if="error" class="px-4 py-8 text-center text-sm text-red-600">{{ error }}</p>

      <div v-else-if="!items.length" class="px-4 py-10 flex flex-col items-center text-center gap-2">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><BellOff class="w-5 h-5" /></span>
        <p class="text-sm font-semibold text-gray-700">Sin notificaciones</p>
        <p class="text-xs text-gray-500">Aquí verás cuando llegue un acta o se registre una plancha.</p>
      </div>

      <ul v-else class="max-h-[60vh] overflow-y-auto divide-y divide-gray-100" :class="{ 'opacity-60': loading }">
        <li v-for="item in items" :key="item.id">
          <button
            type="button"
            class="w-full flex items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50"
            :class="{ 'bg-emerald-50/50': item.unread }"
            @click="go(item)"
          >
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
              :class="item.type === 'acta' ? 'bg-amber-50 text-amber-600' : 'bg-sky-50 text-sky-600'"
            >
              <FileText v-if="item.type === 'acta'" class="w-4 h-4" />
              <ClipboardList v-else class="w-4 h-4" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-900 truncate">{{ item.title }}</span>
                <span v-if="item.unread" class="h-2 w-2 shrink-0 rounded-full bg-aso-primary" aria-label="Sin ver"></span>
              </span>
              <span class="block text-xs text-gray-600 truncate">{{ item.detail }}</span>
              <span class="block text-[11px] text-gray-400 mt-0.5">{{ item.actor }} · {{ timeAgo(item.created_at) }}</span>
            </span>
          </button>
        </li>
      </ul>

      <!-- Paginación: sin total, solo "hay más" (más barato para el servidor). -->
      <div v-if="items.length && (page > 1 || hasMore)" class="flex items-center justify-between gap-2 px-4 py-2.5 border-t border-gray-100 bg-gray-50/60">
        <button type="button" class="pager-btn" :disabled="loading || page <= 1" @click="load(page - 1)">
          <ChevronLeft class="w-4 h-4" /> Más recientes
        </button>
        <span class="text-xs text-gray-500 tabular-nums">Página {{ page }}</span>
        <button type="button" class="pager-btn" :disabled="loading || !hasMore" @click="load(page + 1)">
          Anteriores <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Bell, BellOff, ChevronLeft, ChevronRight, ClipboardList, FileText } from 'lucide-vue-next';
import axios from '@/services/axios';
import { useAuthStore } from '@/stores/auth';

// Consumo bajo: solo se pide el número de no vistos, cada minuto y solo con
// la pestaña visible. La lista se pide al abrir la campana, 8 avisos por página.
const POLL_MS = 60000;
const PER_PAGE = 8;

const router = useRouter();
const authStore = useAuthStore();
const canSee = computed(() => authStore.canAny(['scrutiny_records.view', 'slates.view']));

const root = ref(null);
const open = ref(false);
const unread = ref(0);
const items = ref([]);
const page = ref(1);
const hasMore = ref(false);
const loading = ref(false);
const error = ref('');

const unreadLabel = computed(() => (unread.value > 99 ? '99+' : String(unread.value)));

let timer = null;
let lastPoll = 0;
let polling = false;

const pollUnread = async () => {
  if (!canSee.value || polling || document.hidden || open.value) return;
  polling = true;
  lastPoll = Date.now();
  try {
    const { data } = await axios.get('/admin/notifications/unread-count', { skipGlobalLoading: true });
    unread.value = Number(data?.data?.unread ?? 0);
  } catch {
    // Silencioso: la campana no debe mostrar errores por un conteo.
  } finally {
    polling = false;
  }
};

const markSeen = async (upToId) => {
  try {
    await axios.post('/admin/notifications/seen', { up_to_id: upToId }, { skipGlobalLoading: true });
    unread.value = 0;
  } catch {
    // Si falla, el contador se corrige en el siguiente conteo.
  }
};

const load = async (target = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/admin/notifications', {
      params: { page: target, per_page: PER_PAGE },
      skipGlobalLoading: true,
    });
    items.value = data?.data?.items ?? [];
    hasMore.value = Boolean(data?.data?.has_more);
    page.value = target;

    // Al ver la primera página, todo lo que hay hasta el aviso más reciente queda visto.
    if (target === 1 && items.value.length && unread.value) {
      markSeen(items.value[0].id);
    }
  } catch {
    error.value = 'No se pudieron cargar las notificaciones.';
  } finally {
    loading.value = false;
  }
};

const toggle = () => {
  open.value = !open.value;
  if (open.value) load(1);
};

const close = () => { open.value = false; };

const go = (item) => {
  close();
  if (item.type === 'acta' && item.record_id) {
    router.push(`/admin/audit/${item.record_id}`);
    return;
  }
  if (item.type === 'plancha' && item.batch && authStore.can('candidate_drafts.view')) {
    router.push({
      name: 'secretary-plancha-detail',
      params: { id: item.batch },
      query: { batch: item.batch, neighborhood_name: item.neighborhood, election_id: item.election_id },
    });
    return;
  }
  router.push('/admin/registration');
};

const relative = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
const timeAgo = (iso) => {
  if (!iso) return '';
  const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
  const steps = [[60, 'second'], [60, 'minute'], [24, 'hour'], [7, 'day']];
  let value = seconds;
  for (const [size, unit] of steps) {
    if (Math.abs(value) < size) return relative.format(value, unit);
    value = Math.round(value / size);
  }
  return new Date(iso).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' });
};

const onDocumentClick = (event) => {
  if (open.value && root.value && !root.value.contains(event.target)) close();
};
const onKeydown = (event) => { if (event.key === 'Escape') close(); };
// Al volver a la pestaña se actualiza enseguida (si pasó más de un ciclo).
const onVisibility = () => {
  if (!document.hidden && Date.now() - lastPoll > POLL_MS) pollUnread();
};

onMounted(() => {
  pollUnread();
  timer = setInterval(pollUnread, POLL_MS);
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('visibilitychange', onVisibility);
});

onBeforeUnmount(() => {
  clearInterval(timer);
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('visibilitychange', onVisibility);
});
</script>
