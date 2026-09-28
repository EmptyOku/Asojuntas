<template>
  <div class="space-y-6 lg:space-y-8">

    <!-- Encabezado -->
    <section class="card p-5 sm:p-7 flex flex-col lg:flex-row lg:items-center gap-5">
      <button type="button" class="btn-secondary self-start shrink-0" @click="goBack">
        <ArrowLeft class="w-4 h-4" />
        Volver
      </button>

      <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-aso-primary">
          <span class="h-2 w-2 rounded-full bg-aso-primary"></span>
          Resultados del escrutinio
        </p>
        <h2 v-if="barrio" class="page-title mt-1 truncate">{{ barrio.name }}</h2>
        <div v-else class="mt-2 h-8 w-64 rounded-lg bg-gray-100 animate-pulse"></div>
        <p class="page-subtitle">Cuociente electoral, curules y dignatarios por bloque.</p>
      </div>

      <div v-if="barrio?.plancha_ganadora" class="shrink-0 flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-amber-500 shadow-sm">
          <Trophy class="w-5 h-5" />
        </span>
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Mayor votación</p>
          <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ barrio.plancha_ganadora.plancha }}</p>
        </div>
      </div>
    </section>

    <!-- Cargando -->
    <div v-if="loading" class="space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div v-for="n in 3" :key="n" class="card h-28 animate-pulse"></div>
      </div>
      <div class="card h-80 animate-pulse"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="card p-8 flex flex-col items-center text-center gap-3">
      <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-500"><AlertCircle class="w-6 h-6" /></span>
      <div>
        <p class="font-display text-lg font-semibold text-gray-900">No se pudieron cargar los resultados</p>
        <p class="text-sm text-gray-500">{{ error }}</p>
      </div>
      <button type="button" class="btn-primary" @click="fetchResultados">
        <RefreshCw class="w-4 h-4" />
        Reintentar
      </button>
    </div>

    <!-- Sin actas -->
    <div v-else-if="!barrio?.resultados?.length" class="card p-10 flex flex-col items-center text-center gap-3">
      <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><FileX class="w-7 h-7" /></span>
      <p class="font-display text-lg font-semibold text-gray-900">Sin actas procesadas</p>
      <p class="text-sm text-gray-500 max-w-sm">Este barrio aún no tiene actas de escrutinio registradas.</p>
    </div>

    <template v-else>
      <!-- Resumen -->
      <div class="stagger grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
        <article class="stat-card stat-card--green">
          <div class="flex items-start justify-between">
            <p class="text-sm font-semibold text-gray-600">Votos válidos</p>
            <span class="stat-icon"><Vote class="w-5 h-5" /></span>
          </div>
          <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ votosValidos.toLocaleString('es-CO') }}</p>
          <p class="mt-1 text-xs font-semibold text-emerald-700">Por bloque (cada persona vota en todos)</p>
        </article>
        <article class="stat-card stat-card--blue">
          <div class="flex items-start justify-between">
            <p class="text-sm font-semibold text-gray-600">Bloques electorales</p>
            <span class="stat-icon"><Layers class="w-5 h-5" /></span>
          </div>
          <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ barrio.resultados.length }}</p>
          <p class="mt-1 text-xs font-semibold text-sky-700">Directiva, delegados, fiscal…</p>
        </article>
        <article class="stat-card stat-card--amber">
          <div class="flex items-start justify-between">
            <p class="text-sm font-semibold text-gray-600">Cargos a proveer</p>
            <span class="stat-icon"><Users class="w-5 h-5" /></span>
          </div>
          <p class="mt-3 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ totalCargos }}</p>
          <p class="mt-1 text-xs font-semibold text-amber-700">
            {{ cargosSinCandidato ? `${cargosSinCandidato} sin candidato inscrito` : 'Todos con candidato' }}
          </p>
        </article>
      </div>

      <!-- Pestañas de bloques -->
      <nav class="flex items-center gap-1 p-1 rounded-2xl bg-white border border-gray-200/70 overflow-x-auto w-fit max-w-full shadow-sm" aria-label="Bloques electorales">
        <button
          v-for="(bloque, bIndex) in barrio.resultados"
          :key="bIndex"
          type="button"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200"
          :class="activeIndex === bIndex ? 'bg-aso-primary text-white shadow-md shadow-aso-primary/25' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100'"
          :aria-current="activeIndex === bIndex ? 'true' : undefined"
          @click="activeIndex = bIndex"
        >
          {{ blockTitle(bloque) }}
          <span
            class="rounded-full px-1.5 text-[11px] tabular-nums"
            :class="activeIndex === bIndex ? 'bg-white/20' : 'bg-gray-100 text-gray-500'"
          >{{ bloque.cargos_a_proveer }}</span>
        </button>
      </nav>

      <!-- Bloque activo -->
      <div v-if="activeBlock" :key="activeIndex" class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start animate-rise">

        <!-- Votación -->
        <section class="card xl:col-span-2 overflow-hidden">
          <div class="card-header">
            <div>
              <h3 class="card-title">Votación</h3>
              <p class="card-subtitle">
                Cuociente <span class="font-semibold text-gray-700">{{ formatQuotient(activeBlock.cuociente_electoral) }}</span>
                · {{ activeBlock.cargos_a_proveer }} {{ activeBlock.cargos_a_proveer === 1 ? 'cargo' : 'cargos' }}
              </p>
            </div>
            <span :class="winnerInfo(activeBlock).tie ? 'badge-amber' : 'badge-green'">
              <Trophy class="w-3 h-3" />
              {{ winnerInfo(activeBlock).label }}
            </span>
          </div>

          <ul class="p-5 sm:p-6 space-y-5">
            <li v-for="plancha in activeBlock.votos_planchas" :key="plancha.plancha">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                  <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: planchaColor(plancha.plancha) }"></span>
                  <span class="font-semibold text-gray-900 truncate">{{ plancha.plancha }}</span>
                </div>
                <p class="shrink-0 text-sm tabular-nums">
                  <span class="font-display font-bold text-gray-900">{{ plancha.votos.toLocaleString('es-CO') }}</span>
                  <span class="text-gray-400"> · {{ getPercent(plancha.votos, activeBlock.estadisticas.total) }}%</span>
                </p>
              </div>

              <div class="mt-2 h-2.5 w-full rounded-full bg-gray-100 overflow-hidden">
                <div
                  class="h-full rounded-full transition-[width] duration-700 ease-out"
                  :style="{ width: `${getPercent(plancha.votos, activeBlock.estadisticas.total)}%`, backgroundColor: planchaColor(plancha.plancha) }"
                ></div>
              </div>

              <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                <span class="flex items-center gap-1" :title="`${plancha.curules} curul(es)`">
                  <span
                    v-for="n in Math.max(plancha.curules, 0)"
                    :key="n"
                    class="h-2.5 w-2.5 rounded-sm"
                    :style="{ backgroundColor: planchaColor(plancha.plancha) }"
                  ></span>
                  <span class="font-semibold text-gray-700 ml-0.5">{{ plancha.curules }} {{ plancha.curules === 1 ? 'curul' : 'curules' }}</span>
                </span>
                <span>Entero {{ plancha.entero }}</span>
                <span>Residuo {{ Number(plancha.residuo).toFixed(3) }}</span>
              </div>
            </li>
          </ul>

          <div class="grid grid-cols-3 border-t border-gray-100 bg-gray-50/60 text-center">
            <div class="py-3.5">
              <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Válidos</p>
              <p class="font-display text-lg font-bold text-gray-900 tabular-nums">{{ activeBlock.estadisticas.validos }}</p>
            </div>
            <div class="py-3.5 border-x border-gray-100">
              <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Blancos</p>
              <p class="font-display text-lg font-bold text-gray-900 tabular-nums">{{ activeBlock.estadisticas.blancos }}</p>
            </div>
            <div class="py-3.5">
              <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Nulos</p>
              <p class="font-display text-lg font-bold text-gray-900 tabular-nums">{{ activeBlock.estadisticas.nulos }}</p>
            </div>
          </div>
        </section>

        <!-- Dignatarios -->
        <section class="card xl:col-span-3 overflow-hidden">
          <div class="card-header">
            <div>
              <h3 class="card-title">Dignatarios electos</h3>
              <p class="card-subtitle">Cada cargo se provee una vez, en orden, según las curules de cada plancha.</p>
            </div>
          </div>

          <ol v-if="activeBlock.cargos?.length" class="divide-y divide-gray-100">
            <li
              v-for="(item, cIndex) in activeBlock.cargos"
              :key="cIndex"
              class="flex flex-col sm:flex-row sm:items-center gap-4 px-5 sm:px-6 py-4 transition-colors hover:bg-gray-50/60"
            >
              <div class="flex items-center gap-3 sm:w-52 shrink-0">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 font-display text-sm font-bold text-gray-500">{{ cIndex + 1 }}</span>
                <div class="min-w-0">
                  <p class="font-display font-semibold text-gray-900 truncate">{{ item.cargo }}</p>
                  <span
                    class="inline-flex items-center gap-1.5 text-xs font-semibold"
                    :style="{ color: planchaColor(item.plancha) }"
                  >
                    <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: planchaColor(item.plancha) }"></span>
                    {{ item.plancha }}
                  </span>
                </div>
              </div>

              <div v-if="!item.sin_candidato" class="flex items-center gap-3 min-w-0 flex-1">
                <div class="avatar" :style="{ background: planchaColor(item.plancha) }">{{ initials(item.persona.nombre) }}</div>
                <div class="min-w-0">
                  <p class="font-semibold text-gray-900 truncate">{{ item.persona.nombre }}</p>
                  <p class="flex flex-wrap gap-x-3 text-xs text-gray-500">
                    <span class="inline-flex items-center gap-1"><IdCard class="w-3.5 h-3.5" />{{ item.persona.identificacion }}</span>
                    <span v-if="hasValue(item.persona.celular)" class="inline-flex items-center gap-1"><Phone class="w-3.5 h-3.5" />{{ item.persona.celular }}</span>
                    <span v-if="hasValue(item.persona.correo)" class="inline-flex items-center gap-1 truncate"><Mail class="w-3.5 h-3.5" />{{ item.persona.correo }}</span>
                  </p>
                  <p v-if="item.suplente" class="mt-0.5 text-xs text-gray-400">Suplente: <span class="text-gray-600">{{ item.suplente }}</span></p>
                </div>
              </div>

              <div v-else class="flex-1 rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-800 ring-1 ring-amber-100">
                <p class="font-semibold">Sin candidato inscrito</p>
                <p class="text-xs text-amber-700">{{ item.plancha }} ganó esta curul, pero no registró a nadie para {{ item.cargo }}.</p>
              </div>
            </li>
          </ol>

          <div v-else class="flex flex-col items-center gap-2 px-6 py-12 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><Users class="w-6 h-6" /></span>
            <p class="font-semibold text-gray-800">Sin dignatarios para este bloque</p>
            <p class="text-sm text-gray-500 max-w-sm">No hay cargos configurados o aún no hay votos suficientes para asignar curules.</p>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  AlertCircle, ArrowLeft, FileX, IdCard, Layers, Mail, Phone, RefreshCw, Trophy, Users, Vote
} from 'lucide-vue-next';
import axios from '@/services/axios';

const route  = useRoute();
const router = useRouter();

// Vuelve a la pagina anterior real (mapa o directorio, lo que haya
// enlazado hasta aca) en vez de forzar siempre el directorio. Sin
// historial previo dentro de la app (ej. entro por URL directa), cae al
// directorio como destino por defecto.
function goBack() {
  if (window.history.state?.back) {
    router.back();
  } else {
    router.push('/admin/candidates');
  }
}

const barrio  = ref(null);
const loading = ref(true);
const error   = ref(null);
const activeIndex = ref(0);

const activeBlock = computed(() => barrio.value?.resultados?.[activeIndex.value] ?? null);

// Cada persona vota en todos los bloques: sumar los bloques contaria a la
// misma gente varias veces. Se muestra el total de un bloque (el mayor).
const votosValidos = computed(() => {
  const blocks = barrio.value?.resultados ?? [];
  return blocks.reduce((max, b) => Math.max(max, Number(b.estadisticas?.validos ?? 0)), 0);
});

const totalCargos = computed(() => (barrio.value?.resultados ?? [])
  .reduce((sum, b) => sum + (b.cargos_a_proveer ?? 0), 0));

const cargosSinCandidato = computed(() => (barrio.value?.resultados ?? [])
  .reduce((sum, b) => sum + (b.cargos ?? []).filter((c) => c.sin_candidato).length, 0));

// Color estable por plancha: el mismo en barras, curules y dignatarios.
// Colores del escudo primero (verde, azul, amarillo, rojo) y luego neutros de apoyo.
const PLANCHA_COLORS = ['#45821f', '#3576d1', '#dcae0c', '#d0141d', '#7b5ea7', '#1f8a8a'];
const planchaColor = (name) => {
  const match = String(name ?? '').match(/(\d+)/);
  const index = match ? Number(match[1]) - 1 : 0;
  return PLANCHA_COLORS[((index % PLANCHA_COLORS.length) + PLANCHA_COLORS.length) % PLANCHA_COLORS.length];
};

// "COMISIÓN DE CONVIVENCIA..." en mayúsculas desde el OCR: se muestra en formato título.
const blockTitle = (bloque) => {
  const name = String(bloque?.nombre_bloque ?? 'Bloque').toLowerCase();
  return name.charAt(0).toUpperCase() + name.slice(1);
};

// Con empate en curules, la plancha con más votos es la que provee la
// presidencia y los primeros cargos: se dice así en vez de solo "Empate".
const winnerInfo = (bloque) => {
  if (bloque?.plancha_ganadora?.plancha) {
    return { label: bloque.plancha_ganadora.plancha, tie: false };
  }

  const tied = Array.isArray(bloque?.planchas_ganadoras) ? bloque.planchas_ganadoras : [];
  if (tied.length > 1) {
    const top = [...tied].sort((a, b) => (b.votos ?? 0) - (a.votos ?? 0))[0];
    return { label: `${top.plancha} · empate en curules`, tie: true };
  }

  return { label: 'Sin resultado', tie: true };
};

const getPercent = (votos, total) => {
  if (!total) return 0;
  return Math.round((votos / total) * 100);
};

const formatQuotient = (value) => {
  if (!value) return '0';
  return Number(value).toLocaleString('es-CO', { maximumFractionDigits: 2 });
};

const hasValue = (value) => value && value !== '—' && value !== 'N/A';

const initials = (name) => String(name || '?')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part.charAt(0).toUpperCase())
  .join('');

const fetchResultados = async () => {
  const barrioId = route.params.id;
  loading.value  = true;
  error.value    = null;
  try {
    const response = await axios.get(`/admin/neighborhoods/${barrioId}`, {
      skipGlobalLoading: true,
    });
    if (response.data?.success) {
      barrio.value = response.data.data;
      activeIndex.value = 0;
    } else {
      error.value = response.data?.message || 'No se encontraron resultados.';
    }
  } catch (err) {
    console.error('Error cargando resultados:', err);
    error.value = 'Error de conexión con el servidor.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => fetchResultados());
</script>
