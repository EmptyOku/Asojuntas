<template>
  <div class="space-y-6 lg:space-y-8">

    <!-- Barra de la vista -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
      <div>
        <h2 class="page-title">Dashboard</h2>
        <p class="page-subtitle">Resumen en tiempo real del escrutinio electoral.</p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="lastUpdated" class="hidden sm:inline-flex items-center gap-2 text-xs font-medium text-gray-500">
          <span class="relative flex h-2 w-2">
            <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
          </span>
          Actualizado {{ lastUpdated }}
        </span>
        <button type="button" class="btn-secondary" :disabled="isLoading" @click="fetchDashboard">
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
          Actualizar
        </button>
        <router-link v-can="'scrutiny_records.view'" to="/admin/audit" class="btn-primary">
          <FileCheck class="w-4 h-4" />
          Auditar actas
        </router-link>
      </div>
    </div>

    <!-- Indicadores -->
    <div class="stagger grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-6">
      <article class="stat-card stat-card--green">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Actas escrutadas</p>
          <span class="stat-icon"><FileText class="w-5 h-5" /></span>
        </div>
        <p class="mt-4 font-display text-4xl font-bold text-gray-900 tabular-nums">
          {{ processedDisplay.toLocaleString('es-CO') }}
          <span class="text-base font-semibold text-gray-400">/ {{ stats.total_count.toLocaleString('es-CO') }}</span>
        </p>
        <p class="mt-2 text-xs font-semibold text-emerald-700">{{ processedPercent }}% del total recibido</p>
      </article>

      <article class="stat-card stat-card--blue">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Votos procesados</p>
          <span class="stat-icon"><Vote class="w-5 h-5" /></span>
        </div>
        <p class="mt-4 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ votesDisplay.toLocaleString('es-CO') }}</p>
        <p class="mt-2 text-xs font-semibold text-sky-700">Sumatoria de votos válidos</p>
      </article>

      <article class="stat-card stat-card--rose">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Actas pendientes</p>
          <span class="stat-icon"><ClipboardCheck class="w-5 h-5" /></span>
        </div>
        <p class="mt-4 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ reviewDisplay.toLocaleString('es-CO') }}</p>
        <p class="mt-2 text-xs font-semibold text-red-700">Requieren revisión manual</p>
      </article>

      <article class="stat-card stat-card--amber">
        <div class="flex items-start justify-between">
          <p class="text-sm font-semibold text-gray-600">Jurados activos</p>
          <span class="stat-icon"><Users class="w-5 h-5" /></span>
        </div>
        <p class="mt-4 font-display text-4xl font-bold text-gray-900 tabular-nums">{{ juriesDisplay.toLocaleString('es-CO') }}</p>
        <p class="mt-2 text-xs font-semibold text-amber-700">Con actas en revisión</p>
      </article>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">

      <!-- Actas pendientes -->
      <section class="card xl:col-span-2 overflow-hidden animate-rise">
        <div class="card-header">
          <div>
            <h3 class="card-title">Actas pendientes de revisión</h3>
            <p class="card-subtitle">{{ rows.length }} {{ rows.length === 1 ? 'registro visible' : 'registros visibles' }}</p>
          </div>
          <router-link to="/admin/audit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-aso-primary hover:text-aso-primary-dark">
            Ver todas
            <ArrowRight class="w-4 h-4" />
          </router-link>
        </div>

        <div class="table-wrap rounded-none">
          <table class="data-table">
            <thead>
              <tr>
                <th>Mesa</th>
                <th>Jurado</th>
                <th>Recepción</th>
                <th>Estado IA</th>
                <th class="text-right">Acción</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="isLoading && rows.length === 0">
                <tr v-for="n in 3" :key="`sk-${n}`">
                  <td colspan="5">
                    <div class="flex items-center gap-3 animate-pulse">
                      <div class="h-9 w-9 rounded-xl bg-gray-100"></div>
                      <div class="flex-1 space-y-2">
                        <div class="h-3 w-1/3 rounded bg-gray-100"></div>
                        <div class="h-3 w-1/2 rounded bg-gray-100"></div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-else-if="rows.length === 0">
                <td colspan="5">
                  <div class="flex flex-col items-center py-8 text-center">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                      <CheckCircle2 class="w-6 h-6" />
                    </div>
                    <p class="font-semibold text-gray-800">Todo al día</p>
                    <p class="text-sm text-gray-500">No hay actas pendientes de revisión.</p>
                  </div>
                </td>
              </tr>
              <tr v-for="row in rows" :key="row.id" class="row-enter">
                <td>
                  <p class="font-semibold text-gray-900">{{ row.polling_table?.name || row.polling_table?.code || ('Acta ' + row.id) }}</p>
                  <p class="text-xs text-gray-500">{{ row.commune_name || row.polling_table?.location || 'Ubicación no registrada' }}</p>
                </td>
                <td>
                  <div class="flex items-center gap-3">
                    <div class="avatar">{{ initials(row.jury_name) }}</div>
                    <span class="font-medium text-gray-800">{{ row.jury_name }}</span>
                  </div>
                </td>
                <td class="text-gray-500 whitespace-nowrap">{{ row.transmitted_at_human || 'Sin fecha' }}</td>
                <td>
                  <span :class="row.status_tag?.kind === 'ok' ? 'badge-green' : 'badge-amber'">
                    <span class="badge-dot"></span>
                    {{ row.status_tag?.text || 'Sin estado' }}
                  </span>
                </td>
                <td class="text-right">
                  <router-link :to="`/admin/audit/${row.id}`" class="icon-btn-blue" :aria-label="`Revisar acta ${row.id}`" data-tooltip="Revisar acta">
                    <Eye class="w-4 h-4" />
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Avance del escrutinio -->
      <section class="card p-6 animate-rise [animation-delay:120ms]">
        <h3 class="card-title">Avance del escrutinio</h3>
        <p class="card-subtitle">Actas procesadas frente al total recibido</p>

        <div class="relative mx-auto my-6 h-44 w-44">
          <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
            <circle cx="60" cy="60" r="52" fill="none" stroke="#eef1ec" stroke-width="12" />
            <circle
              cx="60" cy="60" r="52" fill="none"
              stroke="url(#progressGradient)" stroke-width="12" stroke-linecap="round"
              :stroke-dasharray="CIRCUMFERENCE"
              :stroke-dashoffset="ringOffset"
              class="transition-[stroke-dashoffset] duration-1000 ease-out"
            />
            <defs>
              <linearGradient id="progressGradient" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#a5d374" />
                <stop offset="100%" stop-color="#45821f" />
              </linearGradient>
            </defs>
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="font-display text-4xl font-bold text-gray-900 tabular-nums">{{ percentDisplay }}%</span>
            <span class="text-xs font-semibold text-gray-500">procesado</span>
          </div>
        </div>

        <dl class="space-y-3">
          <div class="flex items-center justify-between rounded-xl bg-emerald-50/70 px-4 py-3">
            <dt class="flex items-center gap-2 text-sm font-medium text-gray-700"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Procesadas</dt>
            <dd class="font-display font-bold text-gray-900 tabular-nums">{{ stats.processed_count }}</dd>
          </div>
          <div class="flex items-center justify-between rounded-xl bg-red-50/70 px-4 py-3">
            <dt class="flex items-center gap-2 text-sm font-medium text-gray-700"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>En revisión</dt>
            <dd class="font-display font-bold text-gray-900 tabular-nums">{{ stats.review_count }}</dd>
          </div>
          <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3">
            <dt class="flex items-center gap-2 text-sm font-medium text-gray-700"><span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>Total recibidas</dt>
            <dd class="font-display font-bold text-gray-900 tabular-nums">{{ stats.total_count }}</dd>
          </div>
        </dl>

        <div class="mt-6 grid grid-cols-2 gap-3">
          <router-link v-can="'map.view'" to="/admin/map" class="btn-secondary">
            <MapPin class="w-4 h-4" />
            Mapa
          </router-link>
          <router-link v-can="'audit_logs.view'" to="/admin/audit-logs" class="btn-secondary">
            <ClipboardList class="w-4 h-4" />
            Bitácora
          </router-link>
        </div>
      </section>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
  ArrowRight, CheckCircle2, ClipboardCheck, ClipboardList, Eye, FileCheck, FileText, MapPin, RefreshCw, Users, Vote
} from 'lucide-vue-next';
import axios from '@/services/axios';
import { useCountUp } from '@/composables/useCountUp';

const CIRCUMFERENCE = 2 * Math.PI * 52;

const isLoading = ref(false);
const rows = ref([]);
const lastUpdated = ref('');

const stats = ref({
  total_count: 0,
  processed_count: 0,
  review_count: 0,
  active_juries_count: 0,
  valid_votes_total: 0,
});

const processedPercent = computed(() => {
  if (!stats.value.total_count) return 0;
  return Math.round((stats.value.processed_count / stats.value.total_count) * 100);
});

const processedDisplay = useCountUp(() => stats.value.processed_count);
const votesDisplay = useCountUp(() => stats.value.valid_votes_total);
const reviewDisplay = useCountUp(() => stats.value.review_count);
const juriesDisplay = useCountUp(() => stats.value.active_juries_count);
const percentDisplay = useCountUp(() => processedPercent.value, 1000);

const ringOffset = computed(() => CIRCUMFERENCE * (1 - processedPercent.value / 100));

const initials = (name) => String(name || '?')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part.charAt(0).toUpperCase())
  .join('');

const fetchDashboard = async () => {
  isLoading.value = true;

  try {
    const { data } = await axios.get('/admin/audit-records', {
      params: {
        filter: 'review',
        per_page: 5,
      },
      skipGlobalLoading: true,
    });

    const payload = data?.data || {};
    stats.value = {
      total_count: Number(payload?.stats?.total_count || 0),
      processed_count: Number(payload?.stats?.processed_count || 0),
      review_count: Number(payload?.stats?.review_count || 0),
      active_juries_count: Number(payload?.stats?.active_juries_count || 0),
      valid_votes_total: Number(payload?.stats?.valid_votes_total || 0),
    };

    rows.value = Array.isArray(payload?.records?.data) ? payload.records.data : [];
    lastUpdated.value = new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
  } catch (error) {
    console.error('No fue posible cargar el dashboard:', error);
    rows.value = [];
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchDashboard();
});
</script>
