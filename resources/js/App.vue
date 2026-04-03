<template>
  <div class="min-h-screen">
    <router-view></router-view>

    <transition name="fade">
      <div v-if="isGlobalLoading" class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-950/35 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-4 rounded-3xl bg-white/95 px-8 py-7 shadow-2xl border border-white/70">
          <div class="relative h-14 w-14">
            <div class="absolute inset-0 rounded-full border-4 border-slate-200"></div>
            <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-aso-primary border-r-aso-primary animate-spin"></div>
          </div>
          <div class="text-center">
            <p class="text-sm font-bold text-slate-900">Cargando</p>
            <p class="text-xs text-slate-500 mt-1">Sincronizando datos con el servidor</p>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { isGlobalLoading } from '@/state/loading';
</script>