<template>
  <div class="results-page">

    <!-- ── Header ── -->
    <div class="results-header">
      <router-link to="/admin/candidates" class="back-btn">
        <ArrowLeft class="w-4 h-4" />
        <span>Volver</span>
      </router-link>
      <div class="header-content">
        <div class="header-eyebrow">
          <span class="eyebrow-dot"></span>
          Escrutinio Consolidado
        </div>
        <h1 class="header-title">
          <span v-if="barrio">{{ barrio.name }}</span>
          <span v-else class="skeleton-title"></span>
        </h1>
        <p class="header-subtitle">Resultados matemáticos de las actas procesadas</p>
      </div>
      <div v-if="barrio" class="header-badge">
        <BarChart2 class="w-4 h-4" />
        {{ totalVotos }} votos totales
      </div>
    </div>

    <!-- ── Loading ── -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner">
        <div class="spinner-ring"></div>
        <div class="spinner-ring delay-1"></div>
        <div class="spinner-ring delay-2"></div>
      </div>
      <p class="loading-text">Extrayendo resultados matemáticos...</p>
    </div>

    <!-- ── Error ── -->
    <div v-else-if="error" class="error-state">
      <div class="error-icon-wrap"><AlertCircle class="w-6 h-6" /></div>
      <div>
        <p class="error-title">No se pudieron cargar los resultados</p>
        <p class="error-message">{{ error }}</p>
      </div>
      <button @click="fetchResultados" class="retry-btn">
        <RefreshCw class="w-4 h-4" /> Reintentar
      </button>
    </div>

    <!-- ── Empty ── -->
    <div v-else-if="!barrio?.resultados?.length" class="empty-state">
      <div class="empty-icon"><FileX class="w-8 h-8" /></div>
      <p class="empty-title">Sin actas procesadas</p>
      <p class="empty-subtitle">Este barrio aún no tiene actas de escrutinio registradas.</p>
    </div>

    <!-- ── Results ── -->
    <div v-else class="results-content">

      <!-- Summary Bar -->
      <div class="summary-bar">
        <div class="summary-item">
          <span class="summary-label">Bloques electorales</span>
          <span class="summary-value">{{ barrio.resultados.length }}</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
          <span class="summary-label">Total votos válidos</span>
          <span class="summary-value highlight">{{ totalVotos }}</span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
          <span class="summary-label">Cargos a proveer</span>
          <span class="summary-value">{{ totalCargos }}</span>
        </div>
      </div>

      <!-- ── Bloque Card ── -->
      <div
        v-for="(bloque, bIndex) in barrio.resultados"
        :key="bIndex"
        class="bloque-card"
        :style="`--delay: ${bIndex * 80}ms`"
      >
        <!-- Block Header -->
        <div class="bloque-header">
          <div class="bloque-header-left">
            <div class="bloque-number">{{ String(bIndex + 1).padStart(2, '0') }}</div>
            <div>
              <p class="bloque-label">Bloque Electoral</p>
              <h3 class="bloque-name">{{ bloque.nombre_bloque }}</h3>
            </div>
          </div>
          <div class="bloque-header-right">
            <span class="consolidado-badge">Consolidado Final</span>
            <div class="bloque-total-badge">
              {{ bloque.cargos_a_proveer }}<span>cargos</span>
            </div>
            <div class="bloque-total-badge">
              {{ formatQuotient(bloque.cuociente_electoral) }}<span>cuociente</span>
            </div>
            <div class="bloque-total-badge">
              {{ formatWinnerLabel(bloque) }}<span>{{ bloque.plancha_ganadora ? 'ganadora' : 'resultado' }}</span>
            </div>
          </div>
        </div>

        <!-- Votos por plancha -->
        <div class="votos-section">
          <h4 class="section-label">
            <BarChart2 class="w-3.5 h-3.5" /> Resultados de Votación
          </h4>
          <p class="section-helper">
            Votos válidos: {{ bloque.votos_validos }} | Cuociente: {{ formatQuotient(bloque.cuociente_electoral) }}
          </p>
          <div class="planchas-list">
            <div
              v-for="(plancha, pIndex) in bloque.votos_planchas"
              :key="plancha.plancha"
              class="plancha-row"
              :class="{ winner: pIndex === 0 }"
            >
              <div class="plancha-info">
                <div class="plancha-rank" :class="{ winner: pIndex === 0 }">
                  <Trophy v-if="pIndex === 0" class="w-3 h-3" />
                  <span v-else>{{ pIndex + 1 }}</span>
                </div>
                <span class="plancha-name">{{ plancha.plancha }}</span>
                <span class="plancha-votes-inline">{{ plancha.votos }} votos</span>
              </div>
              <div class="plancha-allocation">
                <span>Ent. {{ plancha.entero }}</span>
                <span>Res. {{ plancha.residuo.toFixed(4) }}</span>
                <span>Curules {{ plancha.curules }}</span>
              </div>
              <div class="plancha-bar-wrap">
                <div class="plancha-bar-track">
                  <div
                    class="plancha-bar-fill"
                    :class="{ winner: pIndex === 0 }"
                    :style="`width: ${getPercent(plancha.votos, bloque.estadisticas.total)}%`"
                  ></div>
                </div>
                <span class="plancha-percent">
                  {{ getPercent(plancha.votos, bloque.estadisticas.total) }}%
                </span>
              </div>
            </div>
          </div>

          <!-- Stats -->
          <div class="stats-row">
            <div class="stat-chip valid">
              <CheckCircle2 class="w-3.5 h-3.5" />
              <span>Válidos</span><strong>{{ bloque.estadisticas.validos }}</strong>
            </div>
            <div class="stat-chip blank">
              <Minus class="w-3.5 h-3.5" />
              <span>Blancos</span><strong>{{ bloque.estadisticas.blancos }}</strong>
            </div>
            <div class="stat-chip null">
              <XCircle class="w-3.5 h-3.5" />
              <span>Nulos</span><strong>{{ bloque.estadisticas.nulos }}</strong>
            </div>
          </div>
        </div>

        <!-- ── Cargos electos (formato acta física) ── -->
        <div v-if="bloque.cargos && bloque.cargos.length > 0" class="cargos-section">
          <h4 class="section-label winners">
            <Trophy class="w-3.5 h-3.5" /> Dignatarios Electos — Asignación por Curules
          </h4>

          <div class="cargos-grid">
            <div
              v-for="(item, cIndex) in bloque.cargos"
              :key="cIndex"
              class="cargo-table"
            >
              <!-- Cargo Header (dark blue like the physical form) -->
              <div class="cargo-table-header">
                {{ item.cargo }}
              </div>

              <!-- Rows -->
              <div class="cargo-table-body">
                <div class="cargo-row">
                  <span class="cargo-row-num">1.</span>
                  <span class="cargo-row-label">Nombre</span>
                  <span class="cargo-row-value">{{ item.persona.nombre || '—' }}</span>
                </div>
                <div class="cargo-row alt">
                  <span class="cargo-row-num">2.</span>
                  <span class="cargo-row-label">No. Identificación</span>
                  <span class="cargo-row-value">{{ item.persona.identificacion || '—' }}</span>
                </div>
                <div class="cargo-row">
                  <span class="cargo-row-num">3.</span>
                  <span class="cargo-row-label">Celular</span>
                  <span class="cargo-row-value">{{ item.persona.celular || '—' }}</span>
                </div>
                <div class="cargo-row alt">
                  <span class="cargo-row-num">4.</span>
                  <span class="cargo-row-label">Correo Electrónico</span>
                  <span class="cargo-row-value">{{ item.persona.correo || '—' }}</span>
                </div>
                <div class="cargo-row">
                  <span class="cargo-row-num">5.</span>
                  <span class="cargo-row-label">Plancha</span>
                  <span class="cargo-row-value plancha-cell">{{ item.plancha || '—' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty cargos -->
        <div v-else class="cargos-empty">
          <Users class="w-5 h-5" />
          <p>No se pudieron determinar dignatarios con los datos disponibles para este bloque.</p>
        </div>

      </div><!-- end bloque-card -->
    </div><!-- end results-content -->
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import {
  ArrowLeft, BarChart2, AlertCircle, RefreshCw,
  FileX, Trophy, CheckCircle2, Minus, XCircle, Users
} from 'lucide-vue-next';
import axios from '@/services/axios';

const route  = useRoute();
const barrio  = ref(null);
const loading = ref(true);
const error   = ref(null);

const totalVotos = computed(() => {
  if (!barrio.value?.resultados) return 0;
  return barrio.value.resultados.reduce((s, b) => s + (b.estadisticas?.validos ?? 0), 0);
});

const totalCargos = computed(() => {
  if (!barrio.value?.resultados) return 0;
  return barrio.value.resultados.reduce((s, b) => s + (b.cargos_a_proveer ?? 0), 0);
});

const getPercent = (votos, total) => {
  if (!total) return 0;
  return Math.round((votos / total) * 100);
};

const formatQuotient = (value) => {
  if (!value) return '0';
  return Number(value).toLocaleString('es-CO', { maximumFractionDigits: 4 });
};

const formatWinnerLabel = (bloque) => {
  if (bloque?.plancha_ganadora?.plancha) {
    return bloque.plancha_ganadora.plancha;
  }

  const tied = Array.isArray(bloque?.planchas_ganadoras) ? bloque.planchas_ganadoras : [];
  if (tied.length > 1) {
    return `Empate (${tied.map((p) => p.plancha).join(' / ')})`;
  }

  return '—';
};

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

<style scoped>
/* ── Base ── */
.results-page {
  font-family: 'DM Sans', 'Outfit', system-ui, sans-serif;
  background: #f4f6f9;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
  min-height: 100vh;
}

/* ── Header ── */
.results-header {
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;
  background: #fff;
  border: 1px solid #e4e8ef;
  border-radius: 16px;
  padding: 1.5rem 1.75rem;
  box-shadow: 0 1px 4px rgba(0,0,0,.04);
}
.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 0.9rem;
  background: #f4f6f9;
  border: 1px solid #e4e8ef;
  border-radius: 8px;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: background .15s, color .15s;
  margin-top: .2rem;
  flex-shrink: 0;
}
.back-btn:hover { background: #eef0f5; color: #1e293b; }
.header-content { flex: 1; }
.header-eyebrow {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #94a3b8;
  margin-bottom: .4rem;
}
.eyebrow-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 3px rgba(34,197,94,.15);
}
.header-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -.02em;
  line-height: 1.2;
}
.skeleton-title {
  display: inline-block;
  width: 220px; height: 28px;
  border-radius: 6px;
  background: linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}
.header-subtitle { font-size: .82rem; color: #94a3b8; margin-top: .25rem; }
.header-badge {
  display: flex;
  align-items: center;
  gap: .45rem;
  padding: .45rem .9rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 999px;
  font-size: .78rem;
  font-weight: 700;
  color: #16a34a;
  white-space: nowrap;
  flex-shrink: 0;
}

/* ── Loading ── */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.25rem;
  padding: 5rem 2rem;
  background: #fff;
  border: 1px solid #e4e8ef;
  border-radius: 16px;
}
.loading-spinner { position: relative; width: 48px; height: 48px; }
.spinner-ring {
  position: absolute; inset: 0;
  border-radius: 50%;
  border: 3px solid transparent;
  border-top-color: #1d4ed8;
  animation: spin 1s linear infinite;
}
.spinner-ring.delay-1 { inset: 6px; border-top-color: #3b82f6; animation-delay: -.2s; }
.spinner-ring.delay-2 { inset: 12px; border-top-color: #93c5fd; animation-delay: -.4s; }
.loading-text { font-size: .875rem; font-weight: 500; color: #64748b; }

/* ── Error / Empty ── */
.error-state {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: #fff;
  border: 1px solid #fecaca;
  border-left: 4px solid #ef4444;
  border-radius: 12px;
  padding: 1.25rem 1.5rem;
}
.error-icon-wrap { flex-shrink:0; padding:.6rem; background:#fef2f2; border-radius:8px; color:#ef4444; }
.error-title { font-size:.9rem; font-weight:700; color:#1e293b; }
.error-message { font-size:.8rem; color:#64748b; margin-top:.2rem; }
.retry-btn {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem 1rem;
  background: #1e293b;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
}
.retry-btn:hover { background: #0f172a; }
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .75rem;
  padding: 5rem 2rem;
  background: #fff;
  border: 1px dashed #cbd5e1;
  border-radius: 16px;
  text-align: center;
}
.empty-icon { padding:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; color:#94a3b8; }
.empty-title { font-size:1rem; font-weight:700; color:#334155; }
.empty-subtitle { font-size:.82rem; color:#94a3b8; max-width:340px; }

/* ── Results ── */
.results-content { display: flex; flex-direction: column; gap: 2rem; }

/* Summary Bar */
.summary-bar {
  display: flex;
  align-items: center;
  background: #fff;
  border: 1px solid #e4e8ef;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.summary-item { flex:1; display:flex; flex-direction:column; gap:.2rem; padding:1rem 1.5rem; }
.summary-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#94a3b8; }
.summary-value { font-size:1.25rem; font-weight:800; color:#0f172a; letter-spacing:-.02em; }
.summary-value.highlight { color:#1d4ed8; }
.summary-divider { width:1px; height:40px; background:#e4e8ef; flex-shrink:0; }

/* ── Bloque Card ── */
.bloque-card {
  background: #fff;
  border: 1px solid #e4e8ef;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
  animation: fadeUp .4s ease both;
  animation-delay: var(--delay, 0ms);
}

/* Block Header */
.bloque-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.75rem;
  background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 60%, #3b82f6 100%);
  gap: 1rem;
}
.bloque-header-left { display:flex; align-items:center; gap:1rem; }
.bloque-number { font-size:2rem; font-weight:900; color:rgba(255,255,255,.2); letter-spacing:-.05em; line-height:1; }
.bloque-label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.6); }
.bloque-name { font-size:1.1rem; font-weight:800; color:#fff; margin-top:.1rem; }
.bloque-header-right { display:flex; flex-direction:column; align-items:flex-end; gap:.4rem; }
.consolidado-badge {
  padding:.25rem .75rem;
  background:rgba(255,255,255,.15);
  border:1px solid rgba(255,255,255,.25);
  border-radius:999px;
  font-size:.65rem; font-weight:700;
  letter-spacing:.06em; text-transform:uppercase;
  color:rgba(255,255,255,.9);
}
.bloque-total-badge { font-size:1.5rem; font-weight:900; color:#fff; letter-spacing:-.03em; line-height:1; text-align:right; }
.bloque-total-badge span { display:block; font-size:.65rem; font-weight:600; color:rgba(255,255,255,.6); letter-spacing:.05em; text-transform:uppercase; }

/* Votos Section */
.votos-section {
  padding: 1.5rem 1.75rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.section-label {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: #94a3b8;
  padding-bottom: .75rem;
  border-bottom: 1px solid #f1f5f9;
}
.section-label.winners { color: #b45309; }
.section-helper {
  margin-top: -0.75rem;
  font-size: 0.78rem;
  color: #64748b;
}

.planchas-list { display:flex; flex-direction:column; gap:1rem; }
.plancha-row { display:flex; flex-direction:column; gap:.5rem; }
.plancha-info { display:flex; align-items:center; gap:.6rem; }
.plancha-allocation {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  padding-left: 1.9rem;
  font-size: .72rem;
  color: #475569;
}
.plancha-allocation span {
  padding: .2rem .5rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
}
.plancha-rank {
  width:22px; height:22px; border-radius:50%;
  background:#f1f5f9; border:1px solid #e2e8f0;
  display:flex; align-items:center; justify-content:center;
  font-size:.65rem; font-weight:700; color:#64748b; flex-shrink:0;
}
.plancha-rank.winner { background:#fef9c3; border-color:#fde047; color:#854d0e; }
.plancha-name { font-size:.85rem; font-weight:600; color:#334155; flex:1; }
.plancha-row.winner .plancha-name { color:#0f172a; font-weight:700; }
.plancha-votes-inline { font-size:.8rem; font-weight:800; color:#475569; white-space:nowrap; }
.plancha-bar-wrap { display:flex; align-items:center; gap:.75rem; }
.plancha-bar-track { flex:1; height:8px; background:#f1f5f9; border-radius:999px; overflow:hidden; }
.plancha-bar-fill { height:100%; background:#cbd5e1; border-radius:999px; transition:width .8s cubic-bezier(.4,0,.2,1); min-width:4px; }
.plancha-bar-fill.winner { background:linear-gradient(90deg,#1d4ed8,#60a5fa); }
.plancha-percent { font-size:.72rem; font-weight:700; color:#94a3b8; width:32px; text-align:right; flex-shrink:0; }

.stats-row { display:flex; gap:.5rem; padding-top:1rem; border-top:1px solid #f1f5f9; }
.stat-chip { flex:1; display:flex; align-items:center; justify-content:center; gap:.3rem; padding:.5rem .4rem; border-radius:8px; font-size:.72rem; }
.stat-chip strong { font-weight:800; }
.stat-chip span { opacity:.75; font-weight:500; }
.stat-chip.valid { background:#f0fdf4; color:#16a34a; }
.stat-chip.blank { background:#f8fafc; color:#64748b; }
.stat-chip.null  { background:#fef2f2; color:#dc2626; }

/* ── Cargos Section (formato acta) ── */
.cargos-section {
  padding: 1.5rem 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  background: #fafbfc;
}

.cargos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.25rem;
}

/* Each cargo = one mini table like the physical form */
.cargo-table {
  border: 1px solid #d1d9e6;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.cargo-table-header {
  background: #1e3a5f;
  color: #fff;
  font-size: .72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .08em;
  text-align: center;
  padding: .6rem 1rem;
}

.cargo-table-body { display: flex; flex-direction: column; }

.cargo-row {
  display: grid;
  grid-template-columns: 1.4rem 7rem 1fr;
  align-items: center;
  gap: .5rem;
  padding: .45rem .85rem;
  border-bottom: 1px solid #eef1f7;
  background: #fff;
}
.cargo-row:last-child { border-bottom: none; }
.cargo-row.alt { background: #f7f9fc; }

.cargo-row-num {
  font-size: .7rem;
  font-weight: 700;
  color: #94a3b8;
}
.cargo-row-label {
  font-size: .72rem;
  font-weight: 700;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: .04em;
}
.cargo-row-value {
  font-size: .8rem;
  font-weight: 600;
  color: #0f172a;
  text-align: right;
  word-break: break-word;
}

.plancha-cell {
  color: #0f766e;
}

/* Empty cargos */
.cargos-empty {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 1.25rem 1.75rem;
  background: #fffbeb;
  border-top: 1px solid #fde68a;
  color: #92400e;
  font-size: .82rem;
  font-weight: 500;
}

/* ── Animations ── */
@keyframes spin    { to { transform: rotate(360deg); } }
@keyframes fadeUp  { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
@keyframes shimmer { 0% { background-position:200% 0; } 100% { background-position:-200% 0; } }

/* ── Responsive ── */
@media (max-width: 768px) {
  .results-page   { padding: 1rem; gap: 1rem; }
  .results-header { flex-wrap: wrap; }
  .header-badge   { width: 100%; justify-content: center; }
  .summary-bar    { flex-direction: column; }
  .summary-divider { width: 100%; height: 1px; }
  .bloque-header  { flex-direction: column; align-items: flex-start; }
  .bloque-header-right { align-items: flex-start; }
  .cargos-grid    { grid-template-columns: 1fr; }
  .cargo-row      { grid-template-columns: 1.2rem 6rem 1fr; }
}
</style>
