<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-10">
    <section class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-gray-900">Planchas Oficiales por Barrio</h2>
          <p class="text-xs text-gray-500 mt-0.5">
            Modulo oficial donde se publican las planchas aprobadas y promovidas.
          </p>
        </div>

        <div class="flex w-full md:w-auto items-center gap-2">
          <div class="relative w-full md:w-72">
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
              <Search class="w-4 h-4 text-gray-400" />
            </div>
            <input
              v-model="search"
              @input="handleSearch"
              type="text"
              placeholder="Buscar barrio o codigo..."
              class="block w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-aso-primary focus:border-aso-primary transition-colors"
            />
          </div>

          <button
            @click="loadNeighborhoods(1)"
            class="px-3 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 flex items-center gap-2"
          >
            <RefreshCw :class="{ 'animate-spin': loading }" class="w-4 h-4" />
            <span class="hidden sm:inline">Recargar</span>
          </button>
        </div>
      </div>
    </section>

    <section class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
      <div class="flex items-start gap-3">
        <ShieldCheck class="w-5 h-5 text-emerald-700 shrink-0 mt-0.5" />
        <div>
          <p class="text-sm font-bold text-emerald-900">Publicacion Oficial de Planchas</p>
          <p class="text-xs text-emerald-800 mt-1">
            Esta vista consolida las planchas ya validadas. Solo deben aparecer registros que fueron aprobados y promovidos desde la bandeja de revision.
          </p>
        </div>
      </div>
    </section>

    <section v-if="loading && cards.length === 0" class="flex justify-center p-10">
      <div class="w-10 h-10 border-4 border-gray-200 border-t-aso-primary rounded-full animate-spin"></div>
    </section>

    <section v-else-if="errorMessage" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl shadow-sm">
      <p class="text-sm font-bold text-red-800">Error al cargar datos</p>
      <p class="text-sm text-red-700">{{ errorMessage }}</p>
    </section>

    <section v-else-if="cards.length === 0" class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-500">
      <p class="font-bold">No se encontraron planchas oficiales.</p>
      <p class="text-sm mt-1">No hay barrios con planchas aprobadas/promovidas para este filtro.</p>
    </section>

    <section v-else class="space-y-4">
      <article
        v-for="card in cards"
        :key="card.id"
        class="bg-white rounded-xl shadow-sm border overflow-hidden transition-colors"
        :class="openNeighborhoodId === card.id ? 'border-amber-400' : 'border-gray-200'"
      >
        <div
          class="p-4 cursor-pointer hover:bg-gray-50 flex flex-col lg:flex-row lg:items-center justify-between gap-4 transition-colors"
          :class="openNeighborhoodId === card.id ? 'bg-amber-50/50 hover:bg-amber-50' : ''"
          @click="toggleNeighborhood(card.id)"
        >
          <div class="flex items-center gap-4">
            <div class="w-1.5 h-12 rounded-full bg-emerald-500"></div>
            <div>
              <h3 class="text-lg font-black text-gray-900 leading-tight">{{ card.name }}</h3>
              <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">
                {{ card.commune?.name || 'Sin comuna' }}
                <span v-if="card.code">· {{ card.code }}</span>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-6 overflow-x-auto pb-1 lg:pb-0">
            <div class="flex items-center gap-6 px-4 border-l border-gray-200">
              <div class="text-center">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Planchas</p>
                <p class="text-lg font-black text-gray-900">{{ getSlateCount(card) }}</p>
              </div>
              <div class="text-center">
                <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">Cargos</p>
                <p class="text-lg font-black text-emerald-600">{{ getRepresentativeCount(card) }}</p>
              </div>
            </div>

            <span class="shrink-0 text-[10px] uppercase tracking-wider font-bold px-2 py-1 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">
              Oficial
            </span>

            <div class="shrink-0 p-2 bg-gray-100 rounded-full text-gray-500">
              <ChevronDown class="w-5 h-5 transition-transform duration-300" :class="openNeighborhoodId === card.id ? 'rotate-180' : ''" />
            </div>
          </div>
        </div>

        <div v-if="openNeighborhoodId === card.id" class="border-t border-gray-100 bg-gray-50 p-4 space-y-5">
          <div class="flex items-center gap-2 bg-white p-1.5 rounded-xl border border-gray-200 shadow-sm relative">
            <button
              v-for="(slate, index) in card.slates"
              :key="`${card.id}-${slate.id ?? slate.code ?? slate.label}`"
              @click="setActiveSlate(card.id, index)"
              class="shrink-0 px-4 py-2 rounded-lg text-sm font-bold transition-all whitespace-nowrap"
              :class="getActiveSlateIndex(card.id) === index ? 'bg-aso-primary/10 text-aso-primary ring-1 ring-aso-primary/20' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'"
            >
              {{ slate.label }}
            </button>
          </div>

          <div v-if="getActiveSlate(card)" class="space-y-4">
            <div class="flex items-center gap-4 text-[10px] font-black uppercase tracking-widest text-gray-400 px-2">
              <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                {{ getActiveSlate(card).representatives.length }} Cargos Oficiales
              </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 animate-in fade-in duration-300">
              <div
                v-for="(group, blockName) in groupRepresentativesByBlock(getActiveSlate(card).representatives)"
                :key="`${card.id}-${getActiveSlateIndex(card.id)}-${blockName}`"
                class="space-y-3"
              >
                <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                  <h5 class="text-[11px] font-black text-gray-900 uppercase tracking-widest">{{ blockName }}</h5>
                  <span class="text-[9px] text-gray-400 font-bold px-2 py-0.5 bg-gray-100 rounded-full uppercase">{{ group.length }} cargos</span>
                </div>

                <div class="space-y-2.5">
                  <div
                    v-for="(rep, idx) in group"
                    :key="`${card.id}-${rep.id ?? idx}-${rep.position}`"
                    class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm relative overflow-hidden"
                  >
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500"></div>
                    <p class="text-[9px] font-black text-aso-primary uppercase tracking-tighter truncate">{{ rep.position }}</p>
                    <h6 class="text-sm font-bold text-gray-800 leading-tight">{{ toTitleCase(rep.name) }}</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </article>
    </section>

    <section
      v-if="!loading && pagination.last_page > 1"
      class="flex items-center justify-between bg-white px-4 py-3 border border-gray-200 rounded-xl sm:px-6"
    >
      <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
          <p class="text-sm text-gray-700">
            Mostrando pagina <span class="font-medium">{{ pagination.current_page }}</span> de <span class="font-medium">{{ pagination.last_page }}</span>
            (Total: {{ pagination.total }} barrios)
          </p>
        </div>
        <div>
          <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
            >
              <span class="sr-only">Anterior</span>
              <ChevronLeft class="h-5 w-5" />
            </button>

            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
            >
              <span class="sr-only">Siguiente</span>
              <ChevronRight class="h-5 w-5" />
            </button>
          </nav>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Search, RefreshCw, ChevronLeft, ChevronRight, ShieldCheck, ChevronDown } from 'lucide-vue-next';
import axios from '@/services/axios';

const loading = ref(false);
const cards = ref([]);
const search = ref('');
const errorMessage = ref('');
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 12,
  total: 0,
  from: 0,
  to: 0,
});

let searchDebounce = null;
const openNeighborhoodId = ref(null);
const activeSlateIndexes = ref({});

const toggleNeighborhood = (id) => {
  openNeighborhoodId.value = openNeighborhoodId.value === id ? null : id;
};

const getSlateCount = (card) => Array.isArray(card?.slates) ? card.slates.length : 0;

const getRepresentativeCount = (card) => {
  if (!Array.isArray(card?.slates)) return 0;
  return card.slates.reduce((acc, slate) => acc + (Array.isArray(slate?.representatives) ? slate.representatives.length : 0), 0);
};

const getActiveSlateIndex = (neighborhoodId) => Number(activeSlateIndexes.value[neighborhoodId] || 0);

const setActiveSlate = (neighborhoodId, index) => {
  activeSlateIndexes.value = {
    ...activeSlateIndexes.value,
    [neighborhoodId]: index,
  };
};

const getActiveSlate = (card) => {
  if (!Array.isArray(card?.slates) || card.slates.length === 0) return null;
  const index = getActiveSlateIndex(card.id);
  return card.slates[index] || card.slates[0];
};

const resolveBlockName = (position = '') => {
  const label = String(position).toUpperCase();
  if (label.includes('PRESIDENTE') || label.includes('VICEPRESIDENTE') || label.includes('TESORERO') || label.includes('SECRETARIO')) {
    return 'Directiva';
  }
  if (label.includes('DELEGADO')) {
    return 'Delegados Asojuntas';
  }
  if (label.includes('FISCAL')) {
    return 'Fiscal';
  }
  if (label.includes('CONCILIADOR') || label.includes('COMISION EMPRESARIAL')) {
    return 'Comision de convivencia';
  }
  return 'Otros cargos';
};

const groupRepresentativesByBlock = (representatives = []) => {
  const groups = {};

  representatives.forEach((rep) => {
    const block = resolveBlockName(rep?.position);
    if (!groups[block]) groups[block] = [];
    groups[block].push(rep);
  });

  return groups;
};

const toTitleCase = (text = '') => {
  if (!text) return 'Sin nombre';
  return String(text).toLowerCase().replace(/\b\w/g, (char) => char.toUpperCase());
};

const loadNeighborhoods = async (page = pagination.value.current_page) => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/secretary/planchas/by-neighborhood', {
      params: {
        page,
        per_page: pagination.value.per_page,
        search: search.value?.trim() || undefined,
      },
      skipGlobalLoading: true,
    });

    const payload = data?.data || {};
    cards.value = payload?.items || [];

    if (cards.value.length > 0 && !openNeighborhoodId.value) {
      openNeighborhoodId.value = cards.value[0].id;
    }

    const pageData = payload?.pagination || {};
    pagination.value = {
      current_page: Number(pageData?.current_page || 1),
      last_page: Number(pageData?.last_page || 1),
      per_page: Number(pageData?.per_page || pagination.value.per_page),
      total: Number(pageData?.total || 0),
      from: Number(pageData?.from || 0),
      to: Number(pageData?.to || 0),
    };
  } catch (error) {
    const backendMessage = error?.response?.data?.message || error?.message;
    errorMessage.value = `No se pudo cargar el modulo oficial: ${backendMessage}`;
    cards.value = [];
    pagination.value = {
      ...pagination.value,
      current_page: 1,
      last_page: 1,
      total: 0,
      from: 0,
      to: 0,
    };
  } finally {
    loading.value = false;
  }
};

const goToPage = async (page) => {
  const target = Number(page || 1);
  if (target < 1 || target > pagination.value.last_page) {
    return;
  }

  await loadNeighborhoods(target);
};

const handleSearch = () => {
  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }

  searchDebounce = setTimeout(() => {
    loadNeighborhoods(1);
  }, 500);
};

onMounted(() => {
  loadNeighborhoods(1);
});
</script>
