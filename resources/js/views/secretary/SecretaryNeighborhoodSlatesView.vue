<template>
  <div class="max-w-7xl mx-auto space-y-4 pb-10">
    <section class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-gray-900">Planchas Registradas por Barrio</h2>
          <p class="text-xs text-gray-500 mt-1">
            Visualiza barrios con planchas oficiales y sus representantes.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
          <input
            v-model="search"
            @keyup.enter="loadNeighborhoods(1)"
            type="text"
            placeholder="Buscar barrio o codigo..."
            class="w-full sm:w-64 px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:outline-none focus:ring-1 focus:ring-aso-primary focus:border-aso-primary"
          />

          <button
            @click="loadNeighborhoods(1)"
            class="px-3 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50"
          >
            Recargar
          </button>
        </div>
      </div>
    </section>

    <section v-if="loading" class="bg-white border border-gray-200 rounded-xl p-6 text-sm text-gray-500">
      Cargando barrios con planchas...
    </section>

    <section v-else-if="cards.length === 0" class="bg-white border border-gray-200 rounded-xl p-6 text-sm text-gray-500">
      No hay barrios con planchas registradas para este filtro.
    </section>

    <section v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <article
        v-for="card in cards"
        :key="card.id"
        class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden"
      >
        <header class="px-4 py-3 border-b border-gray-100 bg-gray-50">
          <p class="text-[11px] uppercase tracking-wide text-gray-500 font-bold">Barrio</p>
          <h3 class="text-base font-bold text-gray-900">{{ card.name }}</h3>
          <p class="text-xs text-gray-600 mt-1">
            <span v-if="card.code" class="mr-2">Codigo: {{ card.code }}</span>
            <span v-if="card.commune?.name">Comuna: {{ card.commune.name }}</span>
          </p>
        </header>

        <div class="p-4 space-y-3">
          <div
            v-for="slate in card.slates"
            :key="`${card.id}-${slate.id ?? slate.code ?? slate.label}`"
            class="rounded-lg border border-gray-200 p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <h4 class="text-sm font-semibold text-gray-900">{{ slate.label }}</h4>
              <span class="text-[11px] text-gray-500 font-semibold">
                {{ slate.representatives.length }} representantes
              </span>
            </div>

            <ul class="mt-2 space-y-1.5">
              <li
                v-for="rep in slate.representatives"
                :key="rep.id"
                class="flex items-start justify-between gap-2 text-xs"
              >
                <span class="font-semibold text-gray-800">{{ rep.position }}</span>
                <span class="text-gray-700 text-right">{{ rep.name }}</span>
              </li>
            </ul>
          </div>
        </div>
      </article>
    </section>

    <section
      v-if="!loading && (pagination.current_page > 1 || pagination.current_page < pagination.last_page)"
      class="bg-white border border-gray-200 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3"
    >
      <p class="text-xs text-gray-600">
        Mostrando {{ pagination.from }} - {{ pagination.to }} de {{ pagination.total }} barrios
      </p>

      <div class="flex items-center gap-2">
        <button
          @click="goToPage(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="px-3 py-1.5 rounded border border-gray-200 text-xs font-semibold text-gray-700 disabled:opacity-50"
        >
          Anterior
        </button>

        <span class="text-xs font-semibold text-gray-700">
          Pagina {{ pagination.current_page }} de {{ pagination.last_page }}
        </span>

        <button
          @click="goToPage(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="px-3 py-1.5 rounded border border-gray-200 text-xs font-semibold text-gray-700 disabled:opacity-50"
        >
          Siguiente
        </button>
      </div>
    </section>

    <p v-if="errorMessage" class="text-sm font-semibold text-red-600">{{ errorMessage }}</p>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
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
    });

    const payload = data?.data || {};
    cards.value = payload?.items || [];

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
    errorMessage.value = `No se pudo cargar el modulo: ${backendMessage}`;
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

watch(search, () => {
  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }

  searchDebounce = setTimeout(() => {
    loadNeighborhoods(1);
  }, 350);
});

onMounted(() => {
  loadNeighborhoods(1);
});
</script>
