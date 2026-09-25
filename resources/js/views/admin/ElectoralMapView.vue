<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Mapa Electoral</h1>
        <p class="text-sm text-gray-500">Comunas de Girardot. Haz clic en una comuna para ver sus JAC y los resultados del barrio.</p>
      </div>
      <div class="flex flex-col items-end gap-1.5">
        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="refreshNow"
            :disabled="refreshing"
            class="flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-aso-primary disabled:opacity-50"
            title="Actualizar ahora"
          >
            <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': refreshing }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            {{ lastUpdated ? `Actualizado ${lastUpdated}` : 'Actualizar' }}
          </button>
          <label v-if="selected" class="flex items-center gap-2 text-sm text-gray-600 select-none">
            <input type="checkbox" v-model="showBarrios" class="rounded border-gray-300 text-aso-primary focus:ring-aso-primary" />
            Mostrar JAC ({{ selectedBarrios.length }})
          </label>
        </div>
        <p v-if="totalAtrasadas > 0" class="flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
          ⚠ {{ totalAtrasadas }} atrasada{{ totalAtrasadas === 1 ? '' : 's' }}
        </p>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-4">
      <!-- Mapa interactivo (izquierda) -->
      <div class="relative flex-1 min-h-[560px] rounded-xl overflow-hidden border border-gray-200 shadow-sm">
        <div ref="mapEl" class="absolute inset-0 z-0"></div>

        <div
          v-if="loading"
          class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm text-gray-600"
        >
          Cargando comunas…
        </div>
        <div
          v-else-if="error"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-white/80 text-sm text-red-600"
        >
          <span>{{ error }}</span>
          <button @click="load" class="px-3 py-1.5 rounded-lg bg-aso-primary text-white text-xs font-medium">
            Reintentar
          </button>
        </div>
      </div>

      <!-- Panel lateral (derecha) -->
      <aside class="lg:w-80 shrink-0 space-y-4">
        <div v-if="selected" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="h-1.5 w-full" :style="{ backgroundColor: communeColor(selected.code) }"></div>
          <div class="p-5">
          <div class="flex items-center justify-between gap-2">
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold"
              :style="{ backgroundColor: communeColor(selected.code) + '1a', color: communeColor(selected.code) }"
            >
              <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: communeColor(selected.code) }"></span>
              {{ selected.code }}
            </span>
            <button
              type="button"
              @click="clearSelection"
              class="flex shrink-0 items-center gap-1.5 rounded-full bg-aso-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-aso-primary/90"
              title="Volver a ver todas las comunas"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15l-6-6m0 0l6-6m-6 6h16" />
              </svg>
              Ver todas
            </button>
          </div>
          <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ selected.name }}</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500">Barrios</dt>
              <dd class="font-semibold text-gray-900">{{ selected.neighborhoods_count }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Actas recibidas</dt>
              <dd class="flex items-center gap-1.5 font-semibold text-gray-900">
                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: semaforoColor(selected.semaforo) }"></span>
                {{ selected.mesas_recibidas }}/{{ selected.mesas_total }} ({{ selected.actas_pct }}%)
              </dd>
            </div>
            <div v-if="selected.mesas_atrasadas > 0" class="flex justify-between">
              <dt class="text-red-500">⚠ Mesas atrasadas</dt>
              <dd class="font-semibold text-red-600">{{ selected.mesas_atrasadas }}</dd>
            </div>
          </dl>
          <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
            <div
              class="h-full rounded-full transition-all duration-500"
              :style="{ width: selected.actas_pct + '%', backgroundColor: semaforoColor(selected.semaforo) }"
            ></div>
          </div>

          <div v-if="selectedBarrios.length" class="mt-4 border-t border-gray-100 pt-3">
            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">Barrios (JAC)</p>
            <ul class="max-h-64 space-y-0.5 overflow-y-auto pr-1">
              <li v-for="b in selectedBarrios" :key="b.id">
                <button
                  @click="goToBarrio(b)"
                  class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-aso-primary/10 hover:text-aso-primary"
                  :title="barrioStatusLabel(b)"
                >
                  <span
                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                    :class="{ 'animate-pulse': b.atrasada }"
                    :style="{ backgroundColor: barrioStatusColor(b) }"
                  ></span>
                  <span class="flex-1 truncate">{{ b.name }}</span>
                  <span v-if="b.winner" class="shrink-0 text-xs text-gray-400">🏆</span>
                  <span v-else-if="b.atrasada" class="shrink-0 text-xs text-red-500">⚠</span>
                </button>
              </li>
            </ul>
          </div>
          </div>
        </div>
        <div v-else class="rounded-xl border border-dashed border-gray-300 bg-white p-5 text-sm text-gray-500">
          Selecciona una comuna en el mapa.
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
          <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Comunas</p>
          <ul class="space-y-1">
            <li v-for="c in communes" :key="c.id">
              <button
                @click="selectById(c.id)"
                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors"
                :class="selected && selected.id === c.id ? 'bg-aso-primary text-white' : 'text-gray-700 hover:bg-gray-100'"
              >
                <span class="flex items-center gap-2">
                  <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full ring-2 ring-offset-1"
                    :style="{ backgroundColor: communeColor(c.code), '--tw-ring-color': communeColor(c.code) + '33' }"
                  ></span>
                  {{ c.name }}
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: semaforoColor(c.semaforo) }" :title="`${c.actas_pct}% de actas`"></span>
                </span>
                <span class="flex items-center gap-1.5 text-xs" :class="selected && selected.id === c.id ? 'text-white/80' : 'text-gray-400'">
                  <span v-if="c.mesas_atrasadas > 0" class="rounded-full bg-red-100 px-1.5 py-0.5 font-medium text-red-700">⚠{{ c.mesas_atrasadas }}</span>
                  {{ c.neighborhoods_count }} · {{ c.actas_pct }}%
                </span>
              </button>
            </li>
          </ul>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
          <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Leyenda</p>
          <div class="grid grid-cols-2 gap-3 px-2 pb-1">
            <div>
              <p class="mb-1.5 text-[11px] font-medium text-gray-500">Puntos JAC</p>
              <div class="space-y-1 text-xs text-gray-600">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-full border-2 border-dashed border-gray-300" :style="{ backgroundColor: SEMAFORO_COLORS.rojo }"></span>
                  Atrasada
                </div>
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-full border-2 border-gray-300" :style="{ backgroundColor: SEMAFORO_COLORS.verde }"></span>
                  Recibida
                </div>
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-full border-2 border-gray-300 bg-white"></span>
                  Sin acta
                </div>
              </div>
            </div>
            <div>
              <p class="mb-1.5 text-[11px] font-medium text-gray-500">Comuna (% actas)</p>
              <div class="space-y-1 text-xs text-gray-600">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: SEMAFORO_COLORS.rojo }"></span>
                  Sin actas
                </div>
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: SEMAFORO_COLORS.amarillo }"></span>
                  Parcial
                </div>
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: SEMAFORO_COLORS.verde }"></span>
                  Completo
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="canEditLocation" class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
          <button
            type="button"
            @click="showLocateForm = !showLocateForm"
            class="flex w-full items-center justify-between text-xs font-semibold uppercase tracking-wide text-gray-400"
          >
            <span>📍 Ubicar barrio manualmente</span>
            <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': showLocateForm }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div v-if="showLocateForm" class="mt-3 space-y-2">
            <select
              v-model.number="locateCommuneId"
              class="w-full rounded-lg border-gray-300 text-sm focus:border-aso-primary focus:ring-aso-primary"
            >
              <option :value="null" disabled>Comuna…</option>
              <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>

            <div v-if="!creatingBarrio" class="flex items-center gap-2">
              <select
                v-model.number="locateNeighborhoodId"
                :disabled="!locateCommuneId"
                class="w-full rounded-lg border-gray-300 text-sm focus:border-aso-primary focus:ring-aso-primary disabled:bg-gray-50"
              >
                <option :value="null" disabled>Barrio…</option>
                <option v-for="n in locateNeighborhoods" :key="n.id" :value="n.id">
                  {{ n.has_coordinates ? '✓ ' : '' }}{{ n.name }}
                </option>
              </select>
              <button
                type="button"
                @click="startCreatingBarrio"
                :disabled="!locateCommuneId"
                title="Agregar un barrio nuevo"
                class="shrink-0 rounded-lg border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
              >
                + Nuevo
              </button>
            </div>

            <!-- Crear barrio nuevo: solo el nombre, sin depender de un GeoJSON. -->
            <div v-if="creatingBarrio" class="space-y-1.5 rounded-lg bg-gray-50 p-2">
              <div class="flex items-center gap-2">
                <input
                  v-model="newBarrioName"
                  type="text"
                  placeholder="Nombre del nuevo barrio"
                  class="w-full rounded-lg border-gray-300 text-sm focus:border-aso-primary focus:ring-aso-primary"
                  @keyup.enter="createBarrio"
                />
                <button type="button" @click="creatingBarrio = false" class="shrink-0 text-xs text-gray-400 hover:text-gray-600">✕</button>
              </div>
              <button
                type="button"
                @click="createBarrio"
                :disabled="!newBarrioName.trim() || locateSaving"
                class="w-full rounded-lg bg-aso-primary px-2 py-1.5 text-xs font-medium text-white disabled:opacity-50"
              >
                {{ locateSaving ? 'Creando…' : 'Crear barrio' }}
              </button>
            </div>

            <!-- Renombrar / eliminar el barrio seleccionado. -->
            <div v-if="!creatingBarrio && locateNeighborhoodId" class="flex items-center gap-2">
              <input
                v-model="renameValue"
                type="text"
                placeholder="Nombre del barrio"
                class="w-full rounded-lg border-gray-300 text-sm focus:border-aso-primary focus:ring-aso-primary"
                @keyup.enter="renameBarrio"
              />
              <button
                type="button"
                @click="renameBarrio"
                :disabled="!canRename || locateSaving"
                title="Guardar el nuevo nombre"
                class="shrink-0 rounded-lg border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
              >
                ✏️
              </button>
              <button
                type="button"
                @click="askDeleteBarrio"
                :disabled="locateSaving"
                title="Eliminar este barrio"
                class="shrink-0 rounded-lg border border-red-200 px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
              >
                🗑️
              </button>
            </div>

            <div v-if="!creatingBarrio" class="grid grid-cols-2 gap-2">
              <input
                v-model="locateLat"
                type="text"
                inputmode="decimal"
                placeholder="Latitud"
                class="w-full rounded-lg border-gray-300 text-sm focus:border-aso-primary focus:ring-aso-primary"
              />
              <input
                v-model="locateLng"
                type="text"
                inputmode="decimal"
                placeholder="Longitud"
                class="w-full rounded-lg border-gray-300 text-sm focus:border-aso-primary focus:ring-aso-primary"
              />
            </div>

            <div v-if="!creatingBarrio" class="flex gap-2">
              <button
                type="button"
                @click="toggleCapture"
                class="flex-1 rounded-lg border px-2 py-1.5 text-xs font-medium transition-colors"
                :class="captureFromMap ? 'border-aso-primary bg-aso-primary/10 text-aso-primary' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
              >
                {{ captureFromMap ? 'Haz clic en el mapa…' : '📍 Tomar del mapa' }}
              </button>
              <button
                type="button"
                @click="useMapCenter"
                class="flex-1 rounded-lg border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
              >
                Centro actual
              </button>
            </div>

            <div v-if="!creatingBarrio" class="flex gap-2">
              <button
                type="button"
                @click="saveLocation"
                :disabled="locateSaving || !locateNeighborhoodId || locateLat === '' || locateLng === ''"
                class="flex-1 rounded-lg bg-aso-primary px-2 py-1.5 text-xs font-medium text-white transition-opacity disabled:opacity-50"
              >
                {{ locateSaving ? 'Guardando…' : 'Guardar ubicación' }}
              </button>
              <button
                v-if="selectedLocateHasCoords"
                type="button"
                @click="removeLocation"
                :disabled="locateSaving"
                class="rounded-lg border border-red-200 px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
              >
                Quitar
              </button>
            </div>

            <p
              v-if="locateMessage"
              class="text-xs"
              :class="locateMessage.startsWith('No se pudo') ? 'text-red-600' : 'text-emerald-600'"
            >{{ locateMessage }}</p>
          </div>
        </div>
      </aside>
    </div>

    <ResultModal
      :open="resultModal.open"
      :success="resultModal.success"
      :title="resultModal.title"
      :message="resultModal.message"
      @close="resultModal.open = false"
    />

    <ConfirmModal
      :open="confirmDeleteBarrio"
      :title="`¿Eliminar “${barrioToDeleteName}”?`"
      message="Dejará de aparecer en el mapa. Si fue un error, se puede revisar con soporte."
      confirm-text="Eliminar"
      danger
      :loading="locateSaving"
      @confirm="deleteBarrio"
      @cancel="confirmDeleteBarrio = false"
    />
  </div>
</template>

<style scoped>
/* Sin recuadro de foco al hacer clic en una comuna. */
:deep(.leaflet-interactive:focus) {
  outline: none;
}

/* Tarjeta de una JAC: barra de color = comuna, texto de estado = semaforo. */
:deep(.leaflet-tooltip.jac-tooltip) {
  background: transparent;
  border: none;
  box-shadow: none;
  padding: 0;
  opacity: 1;
  pointer-events: none;
}
:deep(.leaflet-tooltip.jac-tooltip::before) {
  display: none;
}
:deep(.jac-card) {
  display: flex;
  align-items: stretch;
  min-width: 180px;
  max-width: 220px;
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 4px 8px -2px rgba(0, 0, 0, 0.08);
  font-family: inherit;
}
:deep(.jac-card-accent) {
  width: 4px;
  flex-shrink: 0;
  background: var(--c);
}
:deep(.jac-card-body) {
  flex: 1;
  min-width: 0;
  padding: 8px 10px;
}
:deep(.jac-card-title) {
  margin: 0;
  font-size: 13px;
  line-height: 1.2;
  font-weight: 700;
  color: #111827;
}
:deep(.jac-card-comuna) {
  display: inline-block;
  margin-top: 3px;
  font-size: 10.5px;
  font-weight: 600;
  line-height: 1.6;
  padding: 0 7px;
  border-radius: 999px;
  background: #f3f4f6;
  background: color-mix(in srgb, var(--c) 16%, white);
  color: var(--c);
}
:deep(.jac-card-status) {
  margin: 6px 0 0;
  font-size: 11.5px;
  font-weight: 600;
  line-height: 1.3;
}
:deep(.jac-status-verde) { color: #16a34a; }
:deep(.jac-status-rojo) { color: #dc2626; }
:deep(.jac-status-gris) { color: #9ca3af; }
:deep(.jac-card-link) {
  display: block;
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid #f3f4f6;
  font-size: 11.5px;
  font-weight: 600;
  color: #2563eb;
}

/* Punto JAC: circulo de estado (relleno) con anillo de identidad de comuna
   (borde) y halo blanco (box-shadow) para separarlo del relleno de la
   comuna y de sus vecinos, aunque compartan color. */
:deep(.jac-dot-wrap) {
  background: transparent;
  border: none;
}
:deep(.jac-dot) {
  display: block;
  width: 100%;
  height: 100%;
  box-sizing: border-box;
  border-radius: 50%;
  border: 2px solid var(--c);
  box-shadow: 0 0 0 2.5px #fff;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
:deep(.jac-dot-atrasada) { background: #dc2626; border-style: dashed; }
:deep(.jac-dot-recibida) { background: #16a34a; }
:deep(.jac-dot-pendiente) { background: #ffffff; }
:deep(.jac-dot-flash) {
  transform: scale(1.5);
  box-shadow: 0 0 0 3px #fff, 0 0 0 6px rgba(255, 255, 255, 0.55);
}

/* Insignias de comuna: el nombre (siempre visible) y el % de actas (solo
   de lejos) son dos elementos separados, apiladas sobre el centro. */
:deep(.comuna-badge-wrap) {
  background: transparent;
  border: none;
}
:deep(.comuna-name-pill) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border-radius: 999px;
  background: #fff;
  color: var(--name-c);
  border: 1.5px solid var(--name-c);
  font-size: 11px;
  font-weight: 700;
  font-family: inherit;
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  pointer-events: none;
}
:deep(.comuna-badge-pill) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border-radius: 999px;
  background: var(--badge-bg);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  font-family: inherit;
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25), 0 0 0 2px #fff;
  pointer-events: none;
}
</style>

<script setup>
import { ref, shallowRef, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import axios from '@/services/axios';
import { useAuthStore } from '@/stores/auth';
import ResultModal from '@/components/ResultModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';

const resultModal = ref({ open: false, success: true, title: '', message: '' });
const showResult = (success, title, message) => {
  resultModal.value = { open: true, success, title, message };
};

const router = useRouter();
const authStore = useAuthStore();
const canEditLocation = computed(() => authStore.permissions?.includes('elections.update'));

// Un color por comuna (identidad), deliberadamente lejos del rojo/verde/
// blanco que usa el semaforo (estado de actas), para no confundir "de que
// comuna es" con "como va esa comuna".
const COMMUNE_PALETTE = ['#EBCF23', '#7C3AED', '#F97316', '#73B1EA', '#78350F'];
function communeColor(code) {
  const idx = communes.value.findIndex((c) => c.code === code);
  return COMMUNE_PALETTE[(idx < 0 ? 0 : idx) % COMMUNE_PALETTE.length];
}

// Semaforizacion por comuna segun el envio de actas (backend: communesGeo).
const SEMAFORO_COLORS = { verde: '#16a34a', amarillo: '#f59e0b', rojo: '#dc2626' };
function semaforoColor(semaforo) {
  return SEMAFORO_COLORS[semaforo] || '#9ca3af';
}

// Numero de la comuna para la insignia (ej. "Comuna 3" -> "3"). Las pocas
// comunas sin numero (veredas) caen al codigo tal cual (ej. "VRD-N").
function communeNumber(feature) {
  const name = feature?.properties?.name ?? '';
  const match = name.match(/comuna\s+(\d+)/i);
  return match ? match[1] : (feature?.properties?.code ?? '');
}

// El borde y el relleno de cada comuna son su color de identidad — el mismo
// de sus puntos JAC y de la lista lateral. Relleno solido (no pastel) para
// que se distinga bien de lejos; el estado de actas (semaforo) no se lee
// aqui sino en la insignia de % (pctBadgeIconFor).
function baseStyleFor(feature) {
  const identity = communeColor(feature?.properties?.code);
  return { color: identity, weight: 3, fillColor: identity, fillOpacity: 0.6, opacity: 1 };
}
function hoverStyleFor(feature) {
  const identity = communeColor(feature?.properties?.code);
  return { color: identity, weight: 4, fillColor: identity, fillOpacity: 0.72, opacity: 1 };
}
// Comuna seleccionada: sin relleno (transparente), solo el contorno marcado
// bien grueso en su color de identidad.
function activeStyleFor(feature) {
  const identity = communeColor(feature?.properties?.code);
  return { color: identity, weight: 4.5, fillColor: identity, fillOpacity: 0, opacity: 1 };
}

// Insignia con el nombre de la comuna (siempre visible, tambien con la
// comuna seleccionada/acercada): sirve de referencia de "donde estoy"
// independiente del estado de actas.
function nameBadgeIconFor(feature) {
  const identity = communeColor(feature?.properties?.code);
  const number = communeNumber(feature);
  return L.divIcon({
    className: 'comuna-badge-wrap',
    html: `<div class="comuna-name-pill" style="--name-c:${identity}">Comuna ${number}</div>`,
    iconSize: [86, 20],
    iconAnchor: [43, 26],
  });
}

// Insignia con el % de actas y el color de semaforo — reemplaza al relleno
// como portador del estado. Solo tiene sentido de lejos (varias comunas a
// la vez); al seleccionar y acercarse a una comuna se oculta esa insignia
// puntual (ver updatePctBadgeVisibility), porque ya se ven sus JAC.
function pctBadgeIconFor(feature) {
  const color = semaforoColor(feature?.properties?.semaforo);
  const pct = feature?.properties?.actas_pct ?? 0;
  return L.divIcon({
    className: 'comuna-badge-wrap',
    html: `<div class="comuna-badge-pill" style="--badge-bg:${color}">${pct}%</div>`,
    iconSize: [46, 20],
    iconAnchor: [23, -6],
  });
}

const mapEl = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const lastUpdated = ref('');
const error = ref('');
const communes = ref([]);
const selected = ref(null);
const showBarrios = ref(true);
const barrios = ref([]); // lista plana de JAC, para listarlas por comuna en el orden entregado

const selectedCommuneId = computed(() => selected.value?.id ?? null);

const selectedBarrios = computed(() => {
  if (!selected.value) return [];
  return barrios.value
    .filter((b) => b.commune_id === selected.value.id)
    .sort((a, b) => (a.map_order ?? 999) - (b.map_order ?? 999));
});

const totalAtrasadas = computed(() => communes.value.reduce((sum, c) => sum + (c.mesas_atrasadas || 0), 0));

function barrioStatusColor(b) {
  if (b.atrasada) return SEMAFORO_COLORS.rojo;
  if (b.has_acta) return SEMAFORO_COLORS.verde;
  return '#d1d5db';
}

function barrioStatusLabel(b) {
  if (b.atrasada) return 'Acta atrasada';
  if (b.winner) return `Acta recibida — ganador: ${b.winner}`;
  if (b.has_acta) return 'Acta recibida';
  return 'Sin acta aún';
}

// --- Ubicar barrio manualmente (coordenada a mano, sin depender de un GeoJSON) ---
const showLocateForm = ref(false);
const locateCommuneId = ref(null);
const locateNeighborhoods = ref([]);
const locateNeighborhoodId = ref(null);
const locateLat = ref('');
const locateLng = ref('');
const locateSaving = ref(false);
const locateMessage = ref('');
const captureFromMap = ref(false);
const creatingBarrio = ref(false);
const newBarrioName = ref('');
const renameValue = ref('');

const selectedLocateHasCoords = computed(
  () => locateNeighborhoods.value.find((n) => n.id === locateNeighborhoodId.value)?.has_coordinates ?? false,
);

const canRename = computed(() => {
  const current = locateNeighborhoods.value.find((n) => n.id === locateNeighborhoodId.value);
  const name = renameValue.value.trim();
  return name.length > 0 && name !== current?.name;
});

// Recarga la lista de barrios de la comuna, conservando la seleccion actual.
async function refreshNeighborhoodOptions() {
  if (!locateCommuneId.value) {
    locateNeighborhoods.value = [];
    return;
  }

  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', {
      params: { commune_id: locateCommuneId.value },
    });
    locateNeighborhoods.value = data.data || [];
  } catch (e) {
    console.error('No se pudo cargar la lista de barrios', e);
  }
}

// Igual, pero al cambiar de comuna se limpia la seleccion anterior.
async function loadLocateNeighborhoods() {
  locateNeighborhoodId.value = null;
  locateLat.value = '';
  locateLng.value = '';
  creatingBarrio.value = false;
  await refreshNeighborhoodOptions();
}

watch(locateCommuneId, loadLocateNeighborhoods);
watch(showLocateForm, (open) => {
  if (open && !locateCommuneId.value) locateCommuneId.value = selected.value?.id ?? communes.value[0]?.id ?? null;
});

watch(locateNeighborhoodId, (id) => {
  const n = locateNeighborhoods.value.find((x) => x.id === id);
  locateLat.value = n?.has_coordinates ? String(n.latitude) : '';
  locateLng.value = n?.has_coordinates ? String(n.longitude) : '';
  renameValue.value = n?.name ?? '';
  locateMessage.value = '';
});

function startCreatingBarrio() {
  creatingBarrio.value = true;
  newBarrioName.value = '';
  locateMessage.value = '';
}

async function createBarrio() {
  const name = newBarrioName.value.trim();
  if (!name || !locateCommuneId.value) return;

  locateSaving.value = true;
  locateMessage.value = '';

  try {
    const { data } = await axios.post('/admin/neighborhoods', {
      commune_id: locateCommuneId.value,
      name,
    });
    creatingBarrio.value = false;
    await refreshNeighborhoodOptions();
    locateNeighborhoodId.value = data.data.id;
    showResult(true, 'Barrio creado', 'El barrio se creó correctamente. Ahora asígnale una ubicación.');
  } catch (e) {
    const message = e?.response?.data?.errors?.name?.[0] || e?.response?.data?.message || 'No se pudo crear el barrio.';
    showResult(false, 'No se pudo crear el barrio', message);
  } finally {
    locateSaving.value = false;
  }
}

async function renameBarrio() {
  if (!locateNeighborhoodId.value || !canRename.value) return;

  locateSaving.value = true;
  locateMessage.value = '';

  try {
    await axios.put(`/admin/neighborhoods/${locateNeighborhoodId.value}`, { name: renameValue.value.trim() });
    await refreshNeighborhoodOptions();
    await loadBarrios();
    showResult(true, 'Barrio renombrado', 'El nombre del barrio se actualizó con éxito.');
  } catch (e) {
    const message = e?.response?.data?.errors?.name?.[0] || e?.response?.data?.message || 'No se pudo renombrar el barrio.';
    showResult(false, 'No se pudo renombrar el barrio', message);
  } finally {
    locateSaving.value = false;
  }
}

// El botón abre el modal (askDeleteBarrio); deleteBarrio() elimina al confirmar.
const confirmDeleteBarrio = ref(false);
const barrioToDeleteName = computed(
  () => locateNeighborhoods.value.find((n) => n.id === locateNeighborhoodId.value)?.name ?? 'este barrio'
);

function askDeleteBarrio() {
  if (!locateNeighborhoodId.value) return;
  confirmDeleteBarrio.value = true;
}

async function deleteBarrio() {
  if (!locateNeighborhoodId.value) return;

  locateSaving.value = true;
  locateMessage.value = '';

  try {
    await axios.delete(`/admin/neighborhoods/${locateNeighborhoodId.value}`);
    locateNeighborhoodId.value = null;
    await refreshNeighborhoodOptions();
    await loadBarrios();
    showResult(true, 'Barrio eliminado', 'El barrio se eliminó con éxito.');
  } catch (e) {
    const message = e?.response?.data?.message || 'No se pudo eliminar el barrio.';
    showResult(false, 'No se pudo eliminar el barrio', message);
  } finally {
    locateSaving.value = false;
    confirmDeleteBarrio.value = false;
  }
}

// Ray casting: true si (lat, lng) cae dentro de un anillo [ [lng,lat], ... ].
function pointInRing(lat, lng, ring) {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i];
    const [xj, yj] = ring[j];
    const intersects = yi > lat !== yj > lat && lng < ((xj - xi) * (lat - yi)) / (yj - yi || 1e-12) + xi;
    if (intersects) inside = !inside;
  }
  return inside;
}

function communeName(id) {
  return communes.value.find((c) => c.id === id)?.name ?? 'la comuna seleccionada';
}

// Si no se conoce el contorno de la comuna, no se puede validar: se deja pasar.
function isWithinSelectedCommune(lat, lng) {
  if (!locateCommuneId.value) return true;
  const geometry = layersById.get(locateCommuneId.value)?.feature?.geometry;
  if (!geometry || geometry.type !== 'Polygon') return true;
  return pointInRing(lat, lng, geometry.coordinates[0]);
}

function useMapCenter() {
  if (!map) return;
  const c = map.getCenter();
  if (!isWithinSelectedCommune(c.lat, c.lng)) {
    locateMessage.value = `El centro actual del mapa está fuera de ${communeName(locateCommuneId.value)}. Desplázate hacia esa comuna primero.`;
    return;
  }
  locateLat.value = c.lat.toFixed(6);
  locateLng.value = c.lng.toFixed(6);
  locateMessage.value = '';
}

function toggleCapture() {
  captureFromMap.value = !captureFromMap.value;
  locateMessage.value = '';
}

async function saveLocation() {
  if (!locateNeighborhoodId.value || locateLat.value === '' || locateLng.value === '') return;

  const lat = parseFloat(locateLat.value);
  const lng = parseFloat(locateLng.value);

  if (!isWithinSelectedCommune(lat, lng)) {
    locateMessage.value = `Esa coordenada está fuera de ${communeName(locateCommuneId.value)}. Solo se permite ubicar el barrio dentro de su propia comuna.`;
    return;
  }

  locateSaving.value = true;
  locateMessage.value = '';

  try {
    await axios.put(`/admin/neighborhoods/${locateNeighborhoodId.value}/location`, {
      latitude: lat,
      longitude: lng,
    });
    await refreshNeighborhoodOptions();
    await loadBarrios();
    showResult(true, 'Ubicación guardada', 'La ubicación del barrio se guardó con éxito.');
  } catch (e) {
    const message = e?.response?.data?.message || 'No se pudo guardar la ubicación.';
    showResult(false, 'No se pudo guardar la ubicación', message);
  } finally {
    locateSaving.value = false;
  }
}

async function removeLocation() {
  if (!locateNeighborhoodId.value) return;

  locateSaving.value = true;
  locateMessage.value = '';

  try {
    await axios.delete(`/admin/neighborhoods/${locateNeighborhoodId.value}/location`);
    locateLat.value = '';
    locateLng.value = '';
    await refreshNeighborhoodOptions();
    await loadBarrios();
    showResult(true, 'Ubicación eliminada', 'La ubicación del barrio se eliminó con éxito.');
  } catch (e) {
    const message = e?.response?.data?.message || 'No se pudo eliminar la ubicación.';
    showResult(false, 'No se pudo eliminar la ubicación', message);
  } finally {
    locateSaving.value = false;
  }
}

// Recuadro de Girardot (bbox de las comunas + margen). El mapa nunca se
// puede alejar ni desplazar fuera de aqui.
const GIRARDOT_BOUNDS = L.latLngBounds([
  [4.278, -74.842],
  [4.351, -74.750],
]);

let map = null;
const geoLayer = shallowRef(null);
const barriosLayer = shallowRef(null);
const nameBadgesLayer = shallowRef(null);
const pctBadgesLayer = shallowRef(null);
const layersById = new Map();
const barrioMarkersById = new Map();
const nameBadgeMarkersById = new Map();
const pctBadgeMarkersById = new Map();
let activeLayer = null;
let openBarrioMarker = null;
let allBounds = null;
let resizeObserver = null;
let refreshTimer = null;

function fitAll() {
  if (!map || !allBounds || !allBounds.isValid() || selected.value) return;
  map.invalidateSize();
  // minZoom fijo y bajo mientras encuadra; se sube al valor real despues.
  map.setMinZoom(3);
  map.fitBounds(allBounds, { padding: [20, 20] });
  // El encuadre de todas las comunas pasa a ser el nivel mas alejado permitido.
  map.setMinZoom(map.getZoom());
}

// Al redimensionar el panel (o la ventana), Leaflet debe recalcular el
// tamano del contenedor SIEMPRE, aunque haya una comuna seleccionada; si no,
// el area nueva queda gris porque el mapa sigue creyendo que mide lo de antes.
function handleResize() {
  if (!map) return;
  if (selected.value) {
    map.invalidateSize();
  } else {
    fitAll();
  }
}

// true si el conjunto de IDs de `layersById`/`markersById` es exactamente
// el de las features nuevas (mismo tamano, mismos ids). Cuando coincide, un
// refresco silencioso puede actualizar las capas existentes en vez de
// destruirlas y recrearlas (evita el parpadeo de todo el mapa cada 45s).
function sameIdSet(existingMap, features, idFn) {
  if (existingMap.size !== features.length) return false;
  return features.every((f) => existingMap.has(idFn(f)));
}

function applySelection(layer, props) {
  const previous = activeLayer;
  activeLayer = layer;

  // Se reasigna activeLayer ANTES de restaurar la anterior, para que
  // polygonStyleFor() ya no la trate como "seleccionada".
  if (previous && previous !== layer) {
    previous.setStyle(polygonStyleFor(previous));
  }

  layer.setStyle(activeStyleFor(layer.feature));
  selected.value = props;
  map.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 16 });
}

function selectById(id) {
  const layer = layersById.get(id);
  if (layer) applySelection(layer, layer.feature.properties);
}

// Vuelve a la vista de todas las comunas, como si no se hubiera hecho zoom:
// quita la seleccion (restaura el estilo de la comuna activa) y reencuadra.
function clearSelection() {
  if (activeLayer) {
    const layer = activeLayer;
    activeLayer = null;
    layer.setStyle(polygonStyleFor(layer));
  }
  selected.value = null;
  fitAll();
}

// La insignia de % de una comuna solo tiene sentido de lejos; se oculta la
// de la comuna seleccionada (ya se ven sus JAC) y se restauran las demas.
function updatePctBadgeVisibility() {
  if (!map) return;
  pctBadgeMarkersById.forEach((marker, id) => {
    const shouldShow = id !== selectedCommuneId.value;
    const isOnMap = map.hasLayer(marker);
    if (shouldShow && !isOnMap) marker.addTo(map);
    if (!shouldShow && isOnMap) marker.remove();
  });
}

// De lejos (sin comuna seleccionada) el mapa solo muestra el contorno y la
// insignia de % por comuna; los puntos JAC de una comuna solo aparecen al
// seleccionarla (clic en el mapa o en la lista), que ya hace zoom a su
// area. Evita mostrar los ~100 puntos superpuestos de una sola vez.
function updateVisibleBarrios() {
  if (!map) return;

  if (barriosLayer.value) {
    barriosLayer.value.remove();
    barriosLayer.value = null;
  }

  if (!selected.value || !showBarrios.value) return;

  const visible = barrios.value
    .filter((b) => b.commune_id === selected.value.id)
    .map((b) => barrioMarkersById.get(b.id))
    .filter(Boolean);

  if (!visible.length) return;

  barriosLayer.value = L.layerGroup(visible).addTo(map);
}

// Icono de un punto JAC: el anillo siempre es el color de su comuna (para
// distinguir una comuna de otra de un vistazo); el relleno indica el estado
// del acta — verde ya llegó, rojo atrasada (borde discontinuo), blanco aún
// se espera. El halo blanco va incluido en el CSS del icono (jac-dot) para
// separarlo del relleno de la comuna y de sus vecinos, aunque compartan color.
function markerIconFor(b, { flash = false } = {}) {
  const identity = communeColor(b.commune_code);
  const status = b.atrasada ? 'atrasada' : b.has_acta ? 'recibida' : 'pendiente';
  const size = b.atrasada ? 16 : b.has_acta ? 14 : 12;
  const cls = ['jac-dot', `jac-dot-${status}`, flash ? 'jac-dot-flash' : ''].filter(Boolean).join(' ');
  return L.divIcon({
    className: 'jac-dot-wrap',
    html: `<span class="${cls}" style="--c:${identity}"></span>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  });
}

function markerTooltipHtml(b) {
  const identity = communeColor(b.commune_code);
  let status = { cls: 'gris', icon: '•', text: 'Sin acta aún' };

  if (b.atrasada) {
    status = { cls: 'rojo', icon: '⚠', text: 'Acta atrasada' };
  } else if (b.winner) {
    status = { cls: 'verde', icon: '✓', text: `Acta recibida — 🏆 ${b.winner}` };
  } else if (b.has_acta) {
    status = { cls: 'verde', icon: '✓', text: 'Acta recibida' };
  }

  return `<div class="jac-card" style="--c:${identity}">
    <span class="jac-card-accent"></span>
    <div class="jac-card-body">
      <p class="jac-card-title">${b.name}</p>
      <span class="jac-card-comuna">${b.commune_name ?? ''}</span>
      <p class="jac-card-status jac-status-${status.cls}">${status.icon} ${status.text}</p>
      <span class="jac-card-link">ver resultados →</span>
    </div>
  </div>`;
}

// Unica fuente de verdad del estilo de una comuna: seleccionada (activa),
// resaltada por hover, o su estilo base. mouseover, mouseout y clic usan
// todos esta misma funcion para no desincronizarse entre si.
function polygonStyleFor(layer, { hover = false } = {}) {
  const feature = layer.feature;

  if (layer === activeLayer) {
    return activeStyleFor(feature);
  }

  return hover ? hoverStyleFor(feature) : baseStyleFor(feature);
}

// Lleva el mapa hasta el punto de un barrio y lo resalta un momento.
function goToBarrio(b) {
  const marker = barrioMarkersById.get(b.id);
  if (!marker || !map) return;

  map.flyTo([b.lat, b.lng], 17, { duration: 0.8 });
  marker.openTooltip();

  const original = markerIconFor(b);
  marker.setIcon(markerIconFor(b, { flash: true }));
  setTimeout(() => marker.setIcon(original), 1500);
}

async function load(silent = false) {
  if (!silent) {
    loading.value = true;
    error.value = '';
  } else {
    refreshing.value = true;
  }

  try {
    const { data } = await axios.get('/admin/neighborhoods/communes-geo', { skipGlobalLoading: silent });
    const features = data.features || [];
    communes.value = features
      .map((f) => f.properties)
      .sort((a, b) => a.name.localeCompare(b.name));

    const selectedId = selected.value?.id ?? null;

    // Refresco silencioso con la misma geografia de siempre: se actualizan
    // las comunas existentes en su lugar (estilo + tooltip) en vez de
    // destruir y recrear toda la capa, para no hacer parpadear el mapa.
    if (silent && geoLayer.value && sameIdSet(layersById, features, (f) => f.properties.id)) {
      features.forEach((feature) => {
        const layer = layersById.get(feature.properties.id);
        layer.feature = feature;
        layer.setStyle(polygonStyleFor(layer));
        layer.setTooltipContent(
          `<strong>${feature.properties.name}</strong><br><span style="color:#6b7280">Actas: ${feature.properties.mesas_recibidas}/${feature.properties.mesas_total} (${feature.properties.actas_pct}%)</span>`,
        );

        const nameBadge = nameBadgeMarkersById.get(feature.properties.id);
        if (nameBadge) nameBadge.setIcon(nameBadgeIconFor(feature));

        const pctBadge = pctBadgeMarkersById.get(feature.properties.id);
        if (pctBadge) pctBadge.setIcon(pctBadgeIconFor(feature));
      });

      if (selectedId && layersById.has(selectedId)) {
        selected.value = layersById.get(selectedId).feature.properties;
      }
    } else {
      if (geoLayer.value) {
        geoLayer.value.remove();
        layersById.clear();
        activeLayer = null;
      }
      if (nameBadgesLayer.value) {
        nameBadgesLayer.value.remove();
        nameBadgeMarkersById.clear();
      }
      if (pctBadgesLayer.value) {
        pctBadgesLayer.value.remove();
        pctBadgeMarkersById.clear();
      }

      geoLayer.value = L.geoJSON(data, {
        style: baseStyleFor,
        onEachFeature: (feature, layer) => {
          layersById.set(feature.properties.id, layer);
          layer.bindTooltip(
            `<strong>${feature.properties.name}</strong><br><span style="color:#6b7280">Actas: ${feature.properties.mesas_recibidas}/${feature.properties.mesas_total} (${feature.properties.actas_pct}%)</span>`,
            { sticky: true },
          );
          layer.on('mouseover', () => layer.setStyle(polygonStyleFor(layer, { hover: true })));
          layer.on('mouseout', () => layer.setStyle(polygonStyleFor(layer)));
          layer.on('click', () => applySelection(layer, feature.properties));

          const center = layer.getBounds().getCenter();
          nameBadgeMarkersById.set(
            feature.properties.id,
            L.marker(center, { icon: nameBadgeIconFor(feature), interactive: false, keyboard: false }),
          );
          pctBadgeMarkersById.set(
            feature.properties.id,
            L.marker(center, { icon: pctBadgeIconFor(feature), interactive: false, keyboard: false }),
          );
        },
      }).addTo(map);

      nameBadgesLayer.value = L.layerGroup([...nameBadgeMarkersById.values()]).addTo(map);
      pctBadgesLayer.value = L.layerGroup([...pctBadgeMarkersById.values()]).addTo(map);
      updatePctBadgeVisibility();

      if (features.length) {
        allBounds = geoLayer.value.getBounds();
      }

      if (!silent && features.length) {
        fitAll();
        setTimeout(fitAll, 300);
        setTimeout(fitAll, 800);
      }

      // Si habia una comuna seleccionada, se reaplica con los datos frescos.
      if (selectedId) {
        const layer = layersById.get(selectedId);
        if (layer) {
          activeLayer = layer;
          layer.setStyle(activeStyleFor(layer.feature));
          selected.value = layer.feature.properties;
        } else {
          selected.value = null;
        }
      }
    }

    await loadBarrios(silent);
    lastUpdated.value = new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
  } catch (e) {
    if (!silent) error.value = 'No se pudieron cargar las comunas.';
    console.error(e);
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

function refreshNow() {
  load(true);
}

async function loadBarrios(silent = false) {
  try {
    const { data } = await axios.get('/admin/neighborhoods/geo', { skipGlobalLoading: silent });
    const features = data.features || [];

    const nextBarrios = features.map((f) => {
      const [lng, lat] = f.geometry.coordinates;
      const p = f.properties;
      return {
        id: p.id,
        name: p.name,
        commune_id: p.commune_id,
        commune_code: p.commune_code,
        commune_name: p.commune_name,
        map_order: p.map_order,
        has_acta: p.has_acta,
        atrasada: p.atrasada,
        winner: p.winner,
        lat,
        lng,
      };
    });

    // Refresco silencioso con los mismos barrios de siempre: se actualiza el
    // estilo/tooltip de cada marcador existente en vez de recrearlos todos,
    // para no hacer parpadear los puntos JAC en el mapa. El chequeo es sobre
    // los marcadores creados, no sobre si estan visibles en el mapa ahora
    // mismo (con una comuna seleccionada, la mayoria no lo estan).
    if (silent && sameIdSet(barrioMarkersById, features, (f) => f.properties.id)) {
      barrios.value = nextBarrios;
      nextBarrios.forEach((b) => {
        const marker = barrioMarkersById.get(b.id);
        marker.setIcon(markerIconFor(b));
        marker.setTooltipContent(markerTooltipHtml(b));
      });
      return;
    }

    barrios.value = nextBarrios;
    barrioMarkersById.clear();

    nextBarrios.forEach((b) => {
      const marker = L.marker([b.lat, b.lng], { icon: markerIconFor(b) });
      marker.bindTooltip(markerTooltipHtml(b), { direction: 'top', offset: [0, -6], className: 'jac-tooltip' });
      // Solo una cajita a la vez: al abrirse una, cierra la anterior (hover o clic).
      marker.on('tooltipopen', () => {
        if (openBarrioMarker && openBarrioMarker !== marker) {
          openBarrioMarker.closeTooltip();
        }
        openBarrioMarker = marker;
      });
      // Cada punto lleva directo a la vista de resultados del barrio.
      marker.on('click', () => {
        router.push(`/admin/neighborhood/${b.id}/results`);
      });
      barrioMarkersById.set(b.id, marker);
    });

    // Solo se agregan al mapa los de la comuna seleccionada (si hay alguna).
    updateVisibleBarrios();
  } catch (e) {
    console.error('No se pudieron cargar los barrios', e);
  }
}

onMounted(async () => {
  map = L.map(mapEl.value, {
    scrollWheelZoom: true,
    maxBounds: GIRARDOT_BOUNDS,
    maxBoundsViscosity: 1.0,
    minZoom: 11,
    maxZoom: 18,
  }).fitBounds(GIRARDOT_BOUNDS);
  // Basemap neutro (gris claro, sin key): deja que el rojo/colores de
  // identidad de la capa temática sean lo unico que resalte en el mapa.
  // Esri solo tiene tiles nativos hasta z16; maxNativeZoom escala esos
  // tiles para los niveles de zoom mas cercanos (ej. al ubicar un JAC).
  const esriTileOptions = { maxZoom: 18, maxNativeZoom: 16, attribution: 'Tiles &copy; Esri' };
  L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
    esriTileOptions,
  ).addTo(map);
  L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}',
    { ...esriTileOptions, attribution: '' },
  ).addTo(map);

  // Modo "tomar del mapa": el siguiente clic llena Latitud/Longitud, pero
  // solo si cae dentro de la comuna del barrio que se esta ubicando.
  map.on('click', (e) => {
    if (!captureFromMap.value) return;

    const { lat, lng } = e.latlng;

    if (!isWithinSelectedCommune(lat, lng)) {
      locateMessage.value = `Ese punto está fuera de ${communeName(locateCommuneId.value)}. Haz clic dentro de su contorno (línea de color en el mapa).`;
      return; // se mantiene el modo captura para que puedan intentar de nuevo
    }

    locateLat.value = lat.toFixed(6);
    locateLng.value = lng.toFixed(6);
    captureFromMap.value = false;
    locateMessage.value = '';
  });

  await nextTick();
  map.invalidateSize();
  await load();

  // El layout del panel tiene transiciones CSS: reajusta el encuadre cada vez
  // que el contenedor cambia de tamano (hasta que el usuario elige una comuna).
  resizeObserver = new ResizeObserver(() => handleResize());
  resizeObserver.observe(mapEl.value);
  window.addEventListener('resize', handleResize);

  // Sala de control: el mapa se refresca solo, sin recargar la pagina. Se
  // omite mientras la pestana esta oculta (nadie la esta viendo) y se
  // refresca de inmediato al volver, por si quedo desactualizada.
  refreshTimer = setInterval(() => {
    if (document.hidden) return;
    load(true);
  }, 45000);
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

function handleVisibilityChange() {
  if (!document.hidden) load(true);
}

// Cambiar de comuna (o des/re-marcar "Mostrar JAC") actualiza que puntos
// estan agregados al mapa — ver updateVisibleBarrios(). La insignia de %
// de la comuna seleccionada se oculta con el mismo cambio de seleccion.
watch(selectedCommuneId, updateVisibleBarrios);
watch(selectedCommuneId, updatePctBadgeVisibility);
watch(showBarrios, updateVisibleBarrios);

onBeforeUnmount(() => {
  if (resizeObserver) resizeObserver.disconnect();
  window.removeEventListener('resize', handleResize);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
  if (refreshTimer) clearInterval(refreshTimer);
  if (map) {
    map.remove();
    map = null;
  }
});
</script>
