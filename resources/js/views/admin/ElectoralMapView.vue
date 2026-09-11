<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Mapa Electoral</h1>
        <p class="text-sm text-gray-500">Comunas de Girardot. Haz clic en un punto (JAC) para ver los resultados del barrio.</p>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-600 select-none">
        <input type="checkbox" v-model="showBarrios" class="rounded border-gray-300 text-aso-primary focus:ring-aso-primary" />
        Mostrar JAC ({{ barriosCount }})
      </label>
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
        <div v-if="selected" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-aso-primary">{{ selected.code }}</p>
          <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ selected.name }}</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500">Barrios</dt>
              <dd class="font-semibold text-gray-900">{{ selected.neighborhoods_count }}</dd>
            </div>
          </dl>
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
                  <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: communeColor(c.code) }"></span>
                  {{ c.name }}
                </span>
                <span
                  class="text-xs"
                  :class="selected && selected.id === c.id ? 'text-white/80' : 'text-gray-400'"
                >{{ c.neighborhoods_count }}</span>
              </button>
            </li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
/* Sin recuadro de foco al hacer clic en una comuna. */
:deep(.leaflet-interactive:focus) {
  outline: none;
}
</style>

<script setup>
import { ref, shallowRef, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import axios from '@/services/axios';

const router = useRouter();

const PRIMARY = '#1e8f4d';

// Un color por comuna para los puntos de las JAC.
const COMMUNE_PALETTE = ['#1e8f4d', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#65a30d'];
function communeColor(code) {
  const idx = communes.value.findIndex((c) => c.code === code);
  return COMMUNE_PALETTE[(idx < 0 ? 0 : idx) % COMMUNE_PALETTE.length];
}

const baseStyle = { color: PRIMARY, weight: 2, fillColor: PRIMARY, fillOpacity: 0.12 };
const hoverStyle = { weight: 3, fillOpacity: 0.28 };
// Comuna seleccionada: sin relleno (transparente), solo el contorno marcado.
const activeStyle = { color: PRIMARY, weight: 3, fillColor: PRIMARY, fillOpacity: 0 };

const mapEl = ref(null);
const loading = ref(true);
const error = ref('');
const communes = ref([]);
const selected = ref(null);
const showBarrios = ref(true);
const barriosCount = ref(0);

// Recuadro de Girardot (bbox de las comunas + margen). El mapa nunca se
// puede alejar ni desplazar fuera de aqui.
const GIRARDOT_BOUNDS = L.latLngBounds([
  [4.278, -74.842],
  [4.351, -74.750],
]);

let map = null;
const geoLayer = shallowRef(null);
const barriosLayer = shallowRef(null);
const layersById = new Map();
let activeLayer = null;
let allBounds = null;
let resizeObserver = null;

function fitAll() {
  if (!map || !allBounds || !allBounds.isValid() || selected.value) return;
  map.invalidateSize();
  // minZoom fijo y bajo mientras encuadra; se sube al valor real despues.
  map.setMinZoom(3);
  map.fitBounds(allBounds, { padding: [20, 20] });
  // El encuadre de todas las comunas pasa a ser el nivel mas alejado permitido.
  map.setMinZoom(map.getZoom());
}

function applySelection(layer, props) {
  if (activeLayer && activeLayer !== layer) {
    geoLayer.value.resetStyle(activeLayer);
  }
  activeLayer = layer;
  layer.setStyle(activeStyle);
  selected.value = props;
  map.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 16 });
}

function selectById(id) {
  const layer = layersById.get(id);
  if (layer) applySelection(layer, layer.feature.properties);
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/admin/neighborhoods/communes-geo');
    const features = data.features || [];
    communes.value = features
      .map((f) => f.properties)
      .sort((a, b) => a.name.localeCompare(b.name));

    if (geoLayer.value) {
      geoLayer.value.remove();
      layersById.clear();
      activeLayer = null;
      selected.value = null;
    }

    geoLayer.value = L.geoJSON(data, {
      style: baseStyle,
      onEachFeature: (feature, layer) => {
        layersById.set(feature.properties.id, layer);
        layer.bindTooltip(feature.properties.name, { sticky: true });
        layer.on('mouseover', () => {
          if (layer !== activeLayer) layer.setStyle(hoverStyle);
        });
        layer.on('mouseout', () => {
          if (layer !== activeLayer) geoLayer.value.resetStyle(layer);
        });
        layer.on('click', () => applySelection(layer, feature.properties));
      },
    }).addTo(map);

    if (features.length) {
      allBounds = geoLayer.value.getBounds();
      fitAll();
      setTimeout(fitAll, 300);
      setTimeout(fitAll, 800);
    }

    await loadBarrios();
  } catch (e) {
    error.value = 'No se pudieron cargar las comunas.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function loadBarrios() {
  try {
    const { data } = await axios.get('/admin/neighborhoods/geo');
    const features = data.features || [];
    barriosCount.value = features.length;

    if (barriosLayer.value) {
      barriosLayer.value.remove();
      barriosLayer.value = null;
    }

    if (!features.length) return;

    barriosLayer.value = L.layerGroup(
      features.map((f) => {
        const [lng, lat] = f.geometry.coordinates;
        const p = f.properties;
        const marker = L.circleMarker([lat, lng], {
          radius: 5,
          weight: 1.5,
          color: '#ffffff',
          fillColor: communeColor(p.commune_code),
          fillOpacity: 0.95,
        });
        marker.bindTooltip(
          `<strong>${p.name}</strong><br><span style="color:#6b7280">${p.commune_name ?? ''} · ver resultados →</span>`,
          { direction: 'top' },
        );
        // Cada punto lleva directo a la vista de resultados del barrio.
        marker.on('click', () => {
          router.push(`/admin/neighborhood/${p.id}/results`);
        });
        return marker;
      }),
    );

    if (showBarrios.value) barriosLayer.value.addTo(map);
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
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(map);

  await nextTick();
  map.invalidateSize();
  await load();

  // El layout del panel tiene transiciones CSS: reajusta el encuadre cada vez
  // que el contenedor cambia de tamano (hasta que el usuario elige una comuna).
  resizeObserver = new ResizeObserver(() => fitAll());
  resizeObserver.observe(mapEl.value);
});

watch(showBarrios, (show) => {
  if (!map || !barriosLayer.value) return;
  if (show) barriosLayer.value.addTo(map);
  else barriosLayer.value.remove();
});

onBeforeUnmount(() => {
  if (resizeObserver) resizeObserver.disconnect();
  if (map) {
    map.remove();
    map = null;
  }
});
</script>
