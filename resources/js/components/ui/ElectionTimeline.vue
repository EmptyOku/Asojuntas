<template>
  <section class="card overflow-hidden">
    <div class="card-header">
      <div>
        <h3 class="card-title">{{ title }}</h3>
        <p class="card-subtitle">Cuándo se oficializaron las planchas, cuándo llegó el acta y cuándo se cerró la elección.</p>
      </div>
      <span :class="timeline?.abierta ? 'badge-green' : 'badge-gray'" class="shrink-0">
        <span class="badge-dot"></span>
        {{ timeline?.abierta ? 'Elección abierta' : 'Elección cerrada' }}
      </span>
    </div>

    <ol class="relative px-5 sm:px-6 py-5 space-y-5">
      <li v-for="(event, index) in events" :key="index" class="relative flex gap-3.5">
        <!-- Línea que une los pasos -->
        <span
          v-if="index < events.length - 1"
          class="absolute left-[15px] top-8 bottom-[-20px] w-px"
          :class="event.done ? 'bg-emerald-200' : 'bg-gray-200'"
          aria-hidden="true"
        ></span>

        <span
          class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white"
          :class="event.done ? TONES[event.tone] : 'bg-gray-100 text-gray-400'"
        >
          <component :is="event.icon" class="w-4 h-4" />
        </span>

        <div class="min-w-0 flex-1 pt-0.5">
          <p class="text-sm font-semibold" :class="event.done ? 'text-gray-900' : 'text-gray-500'">{{ event.title }}</p>
          <p v-if="event.at" class="text-sm text-gray-700">
            <time :datetime="event.at.iso">{{ event.at.texto }}</time>
            <span class="text-gray-400"> · {{ event.at.relativo }}</span>
          </p>
          <p v-if="event.detail" class="text-xs text-gray-500">{{ event.detail }}</p>
        </div>
      </li>
    </ol>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { BadgeCheck, CalendarCheck, CalendarClock, FileUp, FileX, Files } from 'lucide-vue-next';

const props = defineProps({
  // { abierta, cierre, acta: { subida, subida_por, decidida, estado }, planchas: [{ nombre, oficializada }] }
  timeline: { type: Object, default: null },
  title: { type: String, default: 'Línea de tiempo de la elección' },
});

const TONES = {
  green: 'bg-emerald-50 text-aso-primary',
  blue: 'bg-sky-50 text-sky-600',
  red: 'bg-red-50 text-red-500',
  gray: 'bg-gray-100 text-gray-600',
};

const DECISION = {
  approved: { title: 'Acta aprobada: sus votos cuentan', tone: 'green', icon: BadgeCheck },
  reviewed: { title: 'Acta revisada: sus votos cuentan', tone: 'green', icon: BadgeCheck },
  consolidated: { title: 'Acta consolidada', tone: 'green', icon: BadgeCheck },
  rejected: { title: 'Acta rechazada: sus votos no cuentan', tone: 'red', icon: FileX },
};

// Los pasos van en el orden del proceso: planchas, acta, decisión y cierre.
const events = computed(() => {
  const timeline = props.timeline ?? {};
  const list = [];

  const planchas = timeline.planchas ?? [];
  if (planchas.length) {
    planchas.forEach((plancha) => list.push({
      title: `${plancha.nombre} oficializada`, at: plancha.oficializada, done: true, tone: 'green', icon: Files,
    }));
  } else {
    list.push({ title: 'Ninguna plancha oficializada todavía', done: false, icon: Files });
  }

  const acta = timeline.acta;
  if (acta?.subida) {
    list.push({
      title: 'Acta subida por el jurado',
      at: acta.subida,
      detail: acta.subida_por ? `Por ${acta.subida_por}${acta.numero ? ` · acta ${acta.numero}` : ''}` : null,
      done: true,
      tone: 'blue',
      icon: FileUp,
    });
    const decision = DECISION[acta.estado];
    if (decision && acta.decidida) {
      list.push({ ...decision, at: acta.decidida, done: true });
    } else {
      list.push({ title: 'Acta pendiente de revisión', done: false, icon: BadgeCheck });
    }
  } else {
    list.push({ title: 'El acta todavía no se ha subido', done: false, icon: FileUp });
  }

  if (timeline.abierta) {
    list.push({ title: 'Elección abierta (aún no se cierra)', done: false, icon: CalendarClock });
  } else {
    list.push({
      title: 'Elección cerrada',
      at: timeline.cierre,
      detail: timeline.cierre ? null : 'No quedó registrada la hora del cierre.',
      done: true,
      tone: 'gray',
      icon: CalendarCheck,
    });
  }

  return list;
});
</script>
