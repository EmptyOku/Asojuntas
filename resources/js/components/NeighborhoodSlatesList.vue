<template>
  <div class="space-y-6">
    <!-- Encabezado -->
    <section class="card p-5 sm:p-6 flex flex-col lg:flex-row lg:items-center gap-5">
      <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-aso-primary">
          <ShieldCheck class="w-3.5 h-3.5" />
          Publicación oficial
        </p>
        <h2 class="page-title mt-1">Planchas oficiales por barrio</h2>
        <p class="page-subtitle">Solo aparecen las planchas aprobadas y oficializadas desde la bandeja de revisión.</p>
      </div>

      <div class="flex w-full lg:w-auto flex-col sm:flex-row gap-2">
        <div class="relative w-full sm:w-72">
          <Search class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model.trim="search"
            type="search"
            class="field field-search"
            placeholder="Buscar barrio o código"
            aria-label="Buscar barrio o código"
            @input="handleSearch"
          />
        </div>
        <button type="button" class="btn-secondary" :disabled="loading" @click="loadNeighborhoods(pagination.current_page)">
          <RefreshCw :class="{ 'animate-spin': loading }" class="w-4 h-4" />
          Recargar
        </button>
      </div>
    </section>

    <!-- Resumen de la página -->
    <div v-if="cards.length" class="stagger grid grid-cols-1 sm:grid-cols-3 gap-4">
      <article class="stat-card stat-card--green">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Barrios con plancha oficial</p>
          <span class="stat-icon"><MapPinned class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ pagination.total }}</p>
      </article>
      <article class="stat-card stat-card--blue">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Planchas en esta página</p>
          <span class="stat-icon"><Files class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ pageSlates }}</p>
      </article>
      <article class="stat-card stat-card--amber">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Cargos oficiales en esta página</p>
          <span class="stat-icon"><Users class="w-5 h-5" /></span>
        </div>
        <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ pageRepresentatives }}</p>
      </article>
    </div>

    <!-- Cargando -->
    <div v-if="loading && !cards.length" class="space-y-3">
      <div v-for="n in 4" :key="n" class="card h-20 animate-pulse"></div>
    </div>

    <!-- Error -->
    <div v-else-if="errorMessage" class="card p-8 flex flex-col items-center text-center gap-3">
      <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-500"><AlertCircle class="w-6 h-6" /></span>
      <div>
        <p class="font-display text-lg font-semibold text-gray-900">No se pudieron cargar las planchas</p>
        <p class="text-sm text-gray-500">{{ errorMessage }}</p>
      </div>
      <button type="button" class="btn-primary" @click="loadNeighborhoods(1)"><RefreshCw class="w-4 h-4" /> Reintentar</button>
    </div>

    <!-- Vacío -->
    <div v-else-if="!cards.length" class="card p-10 flex flex-col items-center text-center gap-3">
      <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><FileX class="w-7 h-7" /></span>
      <p class="font-display text-lg font-semibold text-gray-900">Sin planchas oficiales</p>
      <p class="text-sm text-gray-500 max-w-sm">
        {{ search ? 'Ningún barrio coincide con la búsqueda.' : 'Todavía no hay planchas aprobadas y oficializadas.' }}
      </p>
    </div>

    <!-- Barrios -->
    <div v-else class="space-y-3" :class="{ 'opacity-60 transition-opacity': loading }">
      <article
        v-for="card in cards"
        :key="card.id"
        class="card overflow-hidden transition-shadow"
        :class="{ 'ring-2 ring-aso-primary/30 shadow-md': openId === card.id }"
      >
        <button
          type="button"
          class="w-full flex flex-col sm:flex-row sm:items-center gap-4 p-4 sm:p-5 text-left hover:bg-gray-50/70 transition-colors"
          :aria-expanded="openId === card.id"
          @click="toggle(card.id)"
        >
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-aso-primary">
              <MapPin class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <h3 class="font-display text-lg font-bold text-gray-900 truncate">{{ card.name }}</h3>
              <p class="text-xs text-gray-500 truncate">
                {{ card.commune?.name || 'Sin comuna' }}<template v-if="card.code"> · {{ card.code }}</template>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <span class="badge-blue"><Files class="w-3 h-3" /> {{ card.slates.length }} {{ card.slates.length === 1 ? 'plancha' : 'planchas' }}</span>
            <span class="badge-gray"><Users class="w-3 h-3" /> {{ countRepresentatives(card) }} cargos</span>
            <span class="badge-green"><span class="badge-dot"></span> Oficial</span>
            <ChevronDown class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="{ 'rotate-180 text-aso-primary': openId === card.id }" />
          </div>
        </button>

        <div v-if="openId === card.id" class="border-t border-gray-100 bg-gray-50/60 p-4 sm:p-5 space-y-4 animate-rise">
          <!-- Planchas del barrio -->
          <nav v-if="card.slates.length > 1" class="flex items-center gap-1 p-1 rounded-2xl bg-white border border-gray-200/70 overflow-x-auto w-fit max-w-full shadow-sm" aria-label="Planchas">
            <button
              v-for="(slate, index) in card.slates"
              :key="slate.id ?? slate.label"
              type="button"
              class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition-all"
              :class="activeIndex(card.id) === index ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/25' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100'"
              @click="activeSlates[card.id] = index"
            >
              {{ slate.label }}
              <span class="rounded-full px-1.5 text-[11px] tabular-nums" :class="activeIndex(card.id) === index ? 'bg-white/20' : 'bg-gray-100 text-gray-500'">
                {{ slate.representatives.length }}
              </span>
            </button>
          </nav>
          <p v-else class="text-sm font-semibold text-gray-700">{{ card.slates[0]?.label }} · {{ card.slates[0]?.representatives.length }} cargos oficiales</p>

          <SlateRoster :blocks="rosterFor(card)" />
        </div>
      </article>

      <div class="card overflow-hidden">
        <PaginationBar
          :current="pagination.current_page"
          :last="pagination.last_page"
          :total="pagination.total"
          :from="pagination.from"
          :to="pagination.to"
          :loading="loading"
          label="barrios"
          @change="loadNeighborhoods"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
  AlertCircle, ChevronDown, Files, FileX, MapPin, MapPinned, RefreshCw, Search, ShieldCheck, Users
} from 'lucide-vue-next';
import axios from '@/services/axios';
import PaginationBar from '@/components/ui/PaginationBar.vue';
import SlateRoster from '@/components/secretary/SlateRoster.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
});

const PER_PAGE = 10;

const loading = ref(false);
const cards = ref([]);
const search = ref('');
const errorMessage = ref('');
const pagination = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const openId = ref(null);
const activeSlates = reactive({});

const countRepresentatives = (card) => card.slates.reduce((sum, slate) => sum + slate.representatives.length, 0);
const pageSlates = computed(() => cards.value.reduce((sum, card) => sum + card.slates.length, 0));
const pageRepresentatives = computed(() => cards.value.reduce((sum, card) => sum + countRepresentatives(card), 0));

const toggle = (id) => { openId.value = openId.value === id ? null : id; };
const activeIndex = (id) => Number(activeSlates[id] ?? 0);

// Orden fijo de los bloques, como en el formulario de la plancha.
const BLOCK_ORDER = [/directiva/i, /delegad/i, /fiscal/i, /convivencia|concilia/i];
const blockRank = (name) => {
  const index = BLOCK_ORDER.findIndex((pattern) => pattern.test(name));
  return index === -1 ? BLOCK_ORDER.length : index;
};

// Datos viejos sin bloque: se deduce del nombre del cargo.
const blockFromPosition = (position = '') => {
  const label = String(position).toUpperCase();
  if (/PRESIDENTE|TESORERO|SECRETARIO/.test(label) && !label.includes('DELEGADO')) return 'Directiva';
  if (label.includes('DELEGADO')) return 'Delegados Asojuntas';
  if (label.includes('FISCAL')) return 'Fiscal';
  if (/CONCILIADOR|EMPRESARIAL/.test(label)) return 'Comisión de convivencia y conciliación';
  return 'Otros cargos';
};

const titleCase = (text) => {
  const lower = String(text).toLowerCase();
  return lower.charAt(0).toUpperCase() + lower.slice(1);
};

const rosterFor = (card) => {
  const slate = card.slates[activeIndex(card.id)] ?? card.slates[0];
  const groups = new Map();
  for (const rep of slate?.representatives ?? []) {
    const name = titleCase(rep.block || blockFromPosition(rep.position));
    if (!groups.has(name)) groups.set(name, []);
    groups.get(name).push({
      id: rep.id,
      cargo: rep.position,
      name: rep.name,
      document: rep.document_number,
      isSubstitute: Boolean(rep.is_substitute),
    });
  }
  return [...groups.entries()]
    .sort(([a], [b]) => blockRank(a) - blockRank(b))
    .map(([name, items]) => ({ name, items }));
};

const loadNeighborhoods = async (page = 1) => {
  loading.value = true;
  errorMessage.value = '';
  try {
    const { data } = await axios.get(props.apiUrl, {
      params: { page, per_page: PER_PAGE, search: search.value || undefined },
      skipGlobalLoading: true,
    });
    const payload = data?.data ?? {};
    cards.value = (payload.items ?? []).map((card) => ({ ...card, slates: card.slates ?? [] }));
    if (!cards.value.some((card) => card.id === openId.value)) openId.value = null;

    const meta = payload.pagination ?? {};
    pagination.value = {
      current_page: Number(meta.current_page || 1),
      last_page: Number(meta.last_page || 1),
      total: Number(meta.total || 0),
      from: Number(meta.from || 0),
      to: Number(meta.to || 0),
    };
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error?.message || 'Error de conexión.';
    cards.value = [];
  } finally {
    loading.value = false;
  }
};

let searchDebounce = null;
const handleSearch = () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => loadNeighborhoods(1), 500);
};

onMounted(() => loadNeighborhoods(1));
</script>
