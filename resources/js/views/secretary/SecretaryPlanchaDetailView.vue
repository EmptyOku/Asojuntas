<template>
  <div class="max-w-screen-2xl mx-auto space-y-6 pb-10">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <button 
            @click="router.push({ name: 'secretary-planchas' })" 
            class="flex items-center justify-center w-7 h-7 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-500 hover:text-aso-primary transition-colors shadow-sm"
            title="Volver a la bandeja"
          >
            <ArrowLeft class="w-4 h-4" />
          </button>
          
          <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold uppercase tracking-wider rounded-lg border border-green-200">
            {{ planchaData.numero }}
          </span>
          <span class="text-sm font-medium text-gray-500">Junta de Acción Comunal</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-900">{{ planchaData.nombrePlancha }}</h2>
        <p class="text-sm text-gray-500 mt-1">Barrio: <strong class="text-gray-700">{{ planchaData.nombreBarrio }}</strong></p>
      </div>
      
      <div class="flex flex-wrap items-center gap-3">
        <span v-if="isFullyOfficial" class="badge-green" data-tooltip="Todos los candidatos de esta plancha ya son oficiales.">
          <span class="badge-dot"></span> Plancha oficial · solo lectura
        </span>
        <button
          v-else-if="!isEditing"
          @click="requestEditing"
          class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:border-aso-primary hover:text-aso-primary text-gray-700 text-sm font-bold rounded-xl shadow-sm transition-colors"
        >
          <Edit2 class="w-4 h-4" /> Habilitar Edición
        </button>
        
        <template v-else>
          <button @click="cancelEdit" class="px-4 py-2.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 text-sm font-bold rounded-xl transition-colors">
            Cancelar
          </button>
          <button @click="saveChanges" :disabled="isSaving" class="flex items-center gap-2 px-4 py-2.5 bg-aso-primary hover:bg-green-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
            <Save class="w-4 h-4" /> {{ isSaving ? 'Guardando...' : (isNewPlancha ? 'Registrar plancha' : 'Guardar cambios') }}
          </button>
        </template>

        <button
          v-if="currentBatchUuid"
          @click="confirmPromote = true"
          :disabled="isPromoting || promotableDraftCount === 0"
          class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors"
        >
          {{ isPromoting ? 'Promoviendo...' : `Promover Aprobados (${promotableDraftCount})` }}
        </button>
      </div>
    </div>

    <div v-if="isPollingOcr" class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-xl shadow-sm animate-in fade-in slide-in-from-top-4 mb-4">
      <div class="flex items-start">
        <div class="flex-shrink-0 mt-0.5">
          <svg class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 102 0V6zm-1 8a1.25 1.25 0 100-2.5 1.25 1.25 0 000 2.5z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 w-full">
          <h3 class="text-sm font-bold text-blue-800">Extrayendo los datos de la plancha</h3>
          <p class="text-sm text-blue-700 mt-1">{{ ocrPollMessage || 'Esperando el resultado de la extracción...' }}</p>
          <p class="text-xs text-blue-600 mt-2 font-medium">Puedes seguir navegando mientras termina el procesamiento.</p>
        </div>
      </div>
    </div>

    <div v-if="dataLoadError" class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-xl shadow-sm animate-in fade-in slide-in-from-top-4 mb-4">
      <div class="flex items-start">
        <div class="flex-shrink-0 mt-0.5">
          <svg class="h-5 w-5 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 w-full">
          <h3 class="text-sm font-bold text-orange-800">Registro manual</h3>
          <p class="text-sm text-orange-700 mt-1">{{ dataLoadError }}</p>
          <p class="text-xs text-orange-600 mt-2 font-medium">Completa el formulario manualmente y/o sube las imagenes para continuar.</p>
          <div class="mt-3">
            <button
              v-if="currentBatchUuid"
              @click="retryOcrHydration"
              :disabled="isPollingOcr"
              class="px-3 py-1.5 text-xs font-bold rounded-lg border border-orange-300 text-orange-700 hover:bg-orange-100 disabled:opacity-50"
            >
              Reintentar extracción
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="officialCount" class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-900 flex items-start gap-2">
      <Lock class="w-4 h-4 mt-0.5 shrink-0 text-aso-primary" />
      <p>
        <strong>{{ isFullyOfficial ? 'Esta plancha ya es oficial.' : `${officialCount} cargo(s) de esta plancha ya son oficiales.` }}</strong>
        Los datos oficiales no se modifican desde aquí: aparecen bloqueados. Para corregir el nombre o el documento de un candidato oficial, pídele al administrador que lo edite en Personas.
      </p>
    </div>

    <div v-if="blockingError" class="rounded-2xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm flex items-start gap-2" role="alert">
      <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0" />
      {{ blockingError }}
    </div>

    <!-- Aparece al elegir "Corregir datos" en la confirmación: guía de lo que falta. -->
    <div v-if="validationErrors.length > 0" class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
      <div class="flex items-start gap-2">
        <AlertTriangle class="w-4 h-4 mt-0.5 shrink-0 text-amber-600" />
        <div class="min-w-0 flex-1">
          <p class="font-bold">Datos por completar</p>
          <p class="mt-0.5 text-amber-800">Los cargos resaltados tienen datos incompletos o están vacíos. Complétalos, o vuelve a guardar para registrar la plancha así.</p>
          <ul v-if="validationDetails.length" class="mt-2 list-disc pl-5 space-y-0.5 text-amber-800">
            <li v-for="(detalle, idx) in validationDetails" :key="idx">
              <button type="button" class="font-semibold underline decoration-amber-400 underline-offset-2 hover:text-amber-950" @click="currentPage = detalle.pagina - 1">
                Pág. {{ detalle.pagina }} · {{ detalle.nombre }}
              </button>: falta {{ detalle.faltantes }}
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div v-if="docStore.extractionWarning" class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl shadow-sm animate-in fade-in slide-in-from-top-4">
      <p class="text-sm font-semibold text-amber-900">{{ docStore.extractionWarning }}</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 items-start">
      
      <div class="w-full lg:w-5/12 bg-[#1a1c23] rounded-2xl overflow-hidden relative lg:sticky lg:top-6 shadow-lg flex flex-col h-[50vh] lg:h-[calc(100vh-6rem)] z-10 border border-gray-800">
        
        <div class="p-4 flex justify-between items-center bg-black/80 z-20 absolute top-0 w-full border-b border-white/10 backdrop-blur-sm">
          <h3 class="text-white font-bold text-xs flex items-center gap-2 uppercase tracking-widest">
            <ImageIcon class="w-4 h-4" /> Evidencia Física
          </h3>
          <!-- Las fotos se pasan aparte del formulario: cambiar de foto no cambia de página de datos. -->
          <div class="flex items-center gap-3 bg-white/10 rounded-full px-3 py-1">
            <button @click="prevImage" :disabled="imagePage === 0" aria-label="Foto anterior" class="text-white hover:text-aso-yellow disabled:opacity-30 transition-colors"><ChevronLeft class="w-4 h-4" /></button>
            <span class="text-white text-xs font-bold w-16 text-center">Foto {{ imagePage + 1 }} / {{ totalImages }}</span>
            <button @click="nextImage" :disabled="imagePage >= totalImages - 1" aria-label="Foto siguiente" class="text-white hover:text-aso-yellow disabled:opacity-30 transition-colors"><ChevronRight class="w-4 h-4" /></button>
          </div>
        </div>

        <div class="flex-1 min-h-0 pt-14">
          <ImageViewer v-if="currentImage" :src="currentImage.url" :alt="`Foto ${imagePage + 1} de la plancha`" />
          <div v-else class="text-gray-500 text-sm flex flex-col items-center justify-center h-full gap-2">
            <ImageIcon class="w-10 h-10 opacity-30" />
            <p>No hay imágenes asociadas a esta plancha</p>
          </div>
        </div>

        <!-- Miniaturas: saltar a una foto y, antes de guardar, cambiar su orden. -->
        <div v-if="allEvidenceImages.length > 1" class="shrink-0 border-t border-white/10 bg-black/60 px-3 py-2">
          <div class="flex items-center gap-2 overflow-x-auto">
            <button
              v-for="(img, index) in allEvidenceImages"
              :key="img.id ?? img.url"
              type="button"
              class="relative h-14 w-11 shrink-0 overflow-hidden rounded-md ring-2 transition-all"
              :class="imagePage === index ? 'ring-aso-yellow' : 'ring-transparent opacity-60 hover:opacity-100'"
              :aria-label="`Ver foto ${index + 1}`"
              :aria-current="imagePage === index ? 'true' : undefined"
              @click="imagePage = index"
            >
              <img :src="img.url" alt="" class="h-full w-full object-cover">
              <span class="absolute bottom-0 inset-x-0 bg-black/70 text-[10px] font-bold text-white text-center">{{ index + 1 }}</span>
            </button>
          </div>
          <div v-if="canReorderImages" class="mt-2 flex items-center justify-between gap-2 text-xs text-gray-300">
            <span>Orden de la foto {{ imagePage + 1 }}</span>
            <span class="flex items-center gap-1">
              <button type="button" class="rounded-md bg-white/10 px-2 py-1 font-semibold hover:bg-white/20 disabled:opacity-30" :disabled="imagePage === 0" @click="moveImage(-1)">
                <ChevronLeft class="w-3.5 h-3.5 inline -mt-0.5" /> Mover antes
              </button>
              <button type="button" class="rounded-md bg-white/10 px-2 py-1 font-semibold hover:bg-white/20 disabled:opacity-30" :disabled="imagePage >= totalImages - 1" @click="moveImage(1)">
                Mover después <ChevronRight class="w-3.5 h-3.5 inline -mt-0.5" />
              </button>
            </span>
          </div>
        </div>
      </div>

      <div class="w-full lg:w-7/12 space-y-6">
        
        <div class="card p-4 sm:p-5 space-y-3">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h3 class="font-display text-lg font-bold text-gray-900">Datos de la plancha · página {{ currentPage + 1 }} de {{ FORM_PAGES.length }}</h3>
              <p class="text-xs text-gray-500 mt-0.5">Compara con las fotos. Las páginas del formulario y las fotos se pasan por separado.</p>
            </div>
            <div v-if="isEditing" class="flex items-center gap-2 text-xs text-orange-600 bg-orange-50 px-3 py-1.5 rounded-lg border border-orange-100 font-bold uppercase tracking-wide">
              <Edit2 class="w-3 h-3" /> Modo Edición
            </div>
          </div>

          <nav class="flex items-center gap-1 p-1 rounded-2xl bg-gray-100 overflow-x-auto" aria-label="Páginas del formulario">
            <button
              v-for="(label, index) in FORM_PAGES"
              :key="label"
              type="button"
              class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition-all"
              :class="currentPage === index ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
              :aria-current="currentPage === index ? 'step' : undefined"
              @click="currentPage = index"
            >
              <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px]" :class="currentPage === index ? 'bg-aso-primary text-white' : 'bg-gray-200 text-gray-600'">{{ index + 1 }}</span>
              {{ label }}
              <span v-if="pageHasPending(index)" class="h-2 w-2 rounded-full bg-amber-400" aria-label="Con datos por completar"></span>
            </button>
          </nav>
        </div>

        <div v-if="currentPage === 0" class="card p-6 animate-in fade-in duration-300">
          <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Users class="w-4 h-4"/> Bloque Directivo (Pág 1 de 2)</h4>
          <div class="space-y-5">
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque1.presidente')}">
              <CandidateCard cargo="Presidente (a)" :is-editing="isEditing" :estado="planchaData.bloque1.presidente.estado" v-model:nombre="planchaData.bloque1.presidente.nombre" v-model:identificacion="planchaData.bloque1.presidente.identificacion" v-model:celular="planchaData.bloque1.presidente.celular" v-model:correo="planchaData.bloque1.presidente.correo" />
            </div>
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque1.vicepresidente')}">
              <CandidateCard cargo="Vicepresidente (a)" :is-editing="isEditing" :estado="planchaData.bloque1.vicepresidente.estado" v-model:nombre="planchaData.bloque1.vicepresidente.nombre" v-model:identificacion="planchaData.bloque1.vicepresidente.identificacion" v-model:celular="planchaData.bloque1.vicepresidente.celular" v-model:correo="planchaData.bloque1.vicepresidente.correo" />
            </div>
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque1.tesorero')}">
              <CandidateCard cargo="Tesorero (a)" :is-editing="isEditing" :estado="planchaData.bloque1.tesorero.estado" v-model:nombre="planchaData.bloque1.tesorero.nombre" v-model:identificacion="planchaData.bloque1.tesorero.identificacion" v-model:celular="planchaData.bloque1.tesorero.celular" v-model:correo="planchaData.bloque1.tesorero.correo" />
            </div>
          </div>
        </div>

        <div v-if="currentPage === 1" class="space-y-6 animate-in fade-in duration-300">
          <div class="card p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Users class="w-4 h-4"/> Bloque Directivo (Fin)</h4>
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque1.secretario')}">
              <CandidateCard cargo="Secretario (a)" :is-editing="isEditing" :estado="planchaData.bloque1.secretario.estado" v-model:nombre="planchaData.bloque1.secretario.nombre" v-model:identificacion="planchaData.bloque1.secretario.identificacion" v-model:celular="planchaData.bloque1.secretario.celular" v-model:correo="planchaData.bloque1.secretario.correo" />
            </div>
          </div>

          <div class="card p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><UserPlus class="w-4 h-4"/> Delegados Asojuntas (Pág 1 de 2)</h4>
            <div class="space-y-5">
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque2.suplentePresidente')}">
                <CandidateCard class="border-l-4 border-amber-400" cargo="Suplente de Presidente" :is-editing="isEditing" :estado="planchaData.bloque2.suplentePresidente.estado" v-model:nombre="planchaData.bloque2.suplentePresidente.nombre" v-model:identificacion="planchaData.bloque2.suplentePresidente.identificacion" v-model:celular="planchaData.bloque2.suplentePresidente.celular" v-model:correo="planchaData.bloque2.suplentePresidente.correo" />
              </div>
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque2.delegado1')}">
                <CandidateCard cargo="Delegado (a) 1" :is-editing="isEditing" :estado="planchaData.bloque2.delegado1.estado" v-model:nombre="planchaData.bloque2.delegado1.nombre" v-model:identificacion="planchaData.bloque2.delegado1.identificacion" v-model:celular="planchaData.bloque2.delegado1.celular" v-model:correo="planchaData.bloque2.delegado1.correo" />
              </div>
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque2.suplente1')}">
                <CandidateCard cargo="Suplente Delegado 1" :is-editing="isEditing" :estado="planchaData.bloque2.suplente1.estado" v-model:nombre="planchaData.bloque2.suplente1.nombre" v-model:identificacion="planchaData.bloque2.suplente1.identificacion" v-model:celular="planchaData.bloque2.suplente1.celular" v-model:correo="planchaData.bloque2.suplente1.correo" />
              </div>
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque2.delegado2')}">
                <CandidateCard cargo="Delegado (a) 2" :is-editing="isEditing" :estado="planchaData.bloque2.delegado2.estado" v-model:nombre="planchaData.bloque2.delegado2.nombre" v-model:identificacion="planchaData.bloque2.delegado2.identificacion" v-model:celular="planchaData.bloque2.delegado2.celular" v-model:correo="planchaData.bloque2.delegado2.correo" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="currentPage === 2" class="space-y-6 animate-in fade-in duration-300">
          <div class="card p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><UserPlus class="w-4 h-4"/> Delegados Asojuntas (Fin)</h4>
            <div class="space-y-5">
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque2.suplente2')}">
                <CandidateCard cargo="Suplente Delegado 2" :is-editing="isEditing" :estado="planchaData.bloque2.suplente2.estado" v-model:nombre="planchaData.bloque2.suplente2.nombre" v-model:identificacion="planchaData.bloque2.suplente2.identificacion" v-model:celular="planchaData.bloque2.suplente2.celular" v-model:correo="planchaData.bloque2.suplente2.correo" />
              </div>
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque2.delegado3')}">
                <CandidateCard cargo="Delegado (a) 3" :is-editing="isEditing" :estado="planchaData.bloque2.delegado3.estado" v-model:nombre="planchaData.bloque2.delegado3.nombre" v-model:identificacion="planchaData.bloque2.delegado3.identificacion" v-model:celular="planchaData.bloque2.delegado3.celular" v-model:correo="planchaData.bloque2.delegado3.correo" />
              </div>
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque2.suplente3')}">
                <CandidateCard cargo="Suplente Delegado 3" :is-editing="isEditing" :estado="planchaData.bloque2.suplente3.estado" v-model:nombre="planchaData.bloque2.suplente3.nombre" v-model:identificacion="planchaData.bloque2.suplente3.identificacion" v-model:celular="planchaData.bloque2.suplente3.celular" v-model:correo="planchaData.bloque2.suplente3.correo" />
              </div>
            </div>
          </div>

          <div class="card p-6">
            <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Scale class="w-4 h-4"/> Bloque Fiscal</h4>
            <div class="space-y-5">
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque3.fiscal')}">
                <CandidateCard cargo="Fiscal" :is-editing="isEditing" :estado="planchaData.bloque3.fiscal.estado" v-model:nombre="planchaData.bloque3.fiscal.nombre" v-model:identificacion="planchaData.bloque3.fiscal.identificacion" v-model:celular="planchaData.bloque3.fiscal.celular" v-model:correo="planchaData.bloque3.fiscal.correo" />
              </div>
              <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque3.suplente')}">
                <CandidateCard cargo="Suplente Fiscal" :is-editing="isEditing" :estado="planchaData.bloque3.suplente.estado" v-model:nombre="planchaData.bloque3.suplente.nombre" v-model:identificacion="planchaData.bloque3.suplente.identificacion" v-model:celular="planchaData.bloque3.suplente.celular" v-model:correo="planchaData.bloque3.suplente.correo" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="currentPage === 3" class="card p-6 animate-in fade-in duration-300">
          <h4 class="text-sm font-black text-aso-primary uppercase tracking-widest mb-5 border-b border-gray-100 pb-2 flex items-center gap-2"><Handshake class="w-4 h-4"/> Convivencia y Empresarial</h4>
          <div class="space-y-5">
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque4.conciliador1')}">
              <CandidateCard cargo="Conciliador (a) 1" :is-editing="isEditing" :estado="planchaData.bloque4.conciliador1.estado" v-model:nombre="planchaData.bloque4.conciliador1.nombre" v-model:identificacion="planchaData.bloque4.conciliador1.identificacion" v-model:celular="planchaData.bloque4.conciliador1.celular" v-model:correo="planchaData.bloque4.conciliador1.correo" />
            </div>
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque4.conciliador2')}">
              <CandidateCard cargo="Conciliador (a) 2" :is-editing="isEditing" :estado="planchaData.bloque4.conciliador2.estado" v-model:nombre="planchaData.bloque4.conciliador2.nombre" v-model:identificacion="planchaData.bloque4.conciliador2.identificacion" v-model:celular="planchaData.bloque4.conciliador2.celular" v-model:correo="planchaData.bloque4.conciliador2.correo" />
            </div>
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque4.conciliador3')}">
              <CandidateCard cargo="Conciliador (a) 3" :is-editing="isEditing" :estado="planchaData.bloque4.conciliador3.estado" v-model:nombre="planchaData.bloque4.conciliador3.nombre" v-model:identificacion="planchaData.bloque4.conciliador3.identificacion" v-model:celular="planchaData.bloque4.conciliador3.celular" v-model:correo="planchaData.bloque4.conciliador3.correo" />
            </div>
            <div :class="{'ring-2 ring-amber-400 rounded-2xl shadow-sm': hasError('bloque4.empresarial')}">
              <CandidateCard cargo="Coord. Comisión Empresarial" :is-editing="isEditing" :estado="planchaData.bloque4.empresarial.estado" v-model:nombre="planchaData.bloque4.empresarial.nombre" v-model:identificacion="planchaData.bloque4.empresarial.identificacion" v-model:celular="planchaData.bloque4.empresarial.celular" v-model:correo="planchaData.bloque4.empresarial.correo" />
            </div>
          </div>
        </div>

        <!-- Anterior / siguiente del formulario (no mueve las fotos). -->
        <div class="flex items-center justify-between gap-3">
          <button type="button" class="btn-secondary" :disabled="currentPage === 0" @click="currentPage--">
            <ChevronLeft class="w-4 h-4" /> Anterior
          </button>
          <span class="text-xs text-gray-500">Página {{ currentPage + 1 }} de {{ FORM_PAGES.length }}</span>
          <button type="button" class="btn-secondary" :disabled="currentPage >= FORM_PAGES.length - 1" @click="currentPage++">
            Siguiente <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>

    </div>

    <ConfirmModal
      :open="confirmSave"
      :title="hasIncompleteData ? 'La plancha tiene datos incompletos' : (isNewPlancha ? '¿Registrar esta plancha?' : '¿Guardar los cambios de la plancha?')"
      :message="hasIncompleteData
        ? `${planchaData.numero} · ${planchaData.nombreBarrio}. Puedes ${isNewPlancha ? 'registrarla' : 'guardarla'} así y completar los datos después, o corregirlos ahora.`
        : `${planchaData.numero} · ${planchaData.nombreBarrio}`"
      :confirm-text="hasIncompleteData ? (isNewPlancha ? 'Registrar de todas formas' : 'Guardar de todas formas') : (isNewPlancha ? 'Registrar plancha' : 'Guardar cambios')"
      :cancel-text="hasIncompleteData ? 'Corregir datos' : 'Cancelar'"
      @confirm="persistPlancha"
      @cancel="hasIncompleteData ? reviewIncomplete() : (confirmSave = false)"
    >
      <!-- Qué falta, cargo por cargo -->
      <div v-if="hasIncompleteData" class="mb-3 max-h-56 overflow-y-auto rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-100 text-amber-900 space-y-3">
        <div v-if="incompleteItems.length">
          <p class="text-xs font-bold uppercase tracking-wide">Candidatos con datos incompletos ({{ incompleteItems.length }})</p>
          <ul class="mt-1 space-y-1">
            <li v-for="item in incompleteItems" :key="item.key">
              <strong>{{ item.nombre }}</strong> <span class="text-amber-700">(pág. {{ item.pagina }})</span>: falta {{ item.faltantes }}
              <span v-if="item.omitido" class="block text-xs font-semibold text-red-700">Sin nombre no se registra este cargo.</span>
            </li>
          </ul>
        </div>
        <div v-if="emptyCargos.length">
          <p class="text-xs font-bold uppercase tracking-wide">Cargos sin candidato ({{ emptyCargos.length }})</p>
          <p class="mt-1 text-sm">{{ emptyCargos.map((item) => item.nombre).join(', ') }}</p>
          <p class="text-xs text-amber-700">Quedarán vacantes en esta plancha.</p>
        </div>
      </div>

      <ul class="space-y-1.5 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-100">
        <li class="flex justify-between gap-3"><span>Cargos con candidato</span><strong class="tabular-nums">{{ saveSummary.filled }} de {{ saveSummary.total }}</strong></li>
        <li v-if="isNewPlancha" class="flex justify-between gap-3"><span>Imágenes de evidencia</span><strong class="tabular-nums">{{ saveSummary.images }}</strong></li>
        <li v-if="approvedCount" class="flex justify-between gap-3 text-emerald-800"><span>Ya aprobados (conservan la aprobación)</span><strong class="tabular-nums">{{ approvedCount }}</strong></li>
        <li v-if="officialCount" class="flex justify-between gap-3 text-gray-500"><span>Ya oficiales (no se modifican)</span><strong class="tabular-nums">{{ officialCount }}</strong></li>
        <li v-if="saveSummary.unknown" class="flex justify-between gap-3 text-amber-800"><span>Nombres ilegibles (&lt;Desconocido&gt;)</span><strong class="tabular-nums">{{ saveSummary.unknown }}</strong></li>
        <li v-if="saveSummary.provisional" class="flex justify-between gap-3 text-amber-800"><span>Sin documento (recibirán uno provisional)</span><strong class="tabular-nums">{{ saveSummary.provisional }}</strong></li>
      </ul>
      <p class="mt-2 text-xs text-gray-500">Quedará como borrador pendiente de aprobación en la bandeja de revisión.</p>
    </ConfirmModal>

    <ConfirmModal
      :open="confirmEdit"
      title="Esta plancha ya fue revisada"
      message="Puedes editarla, pero ten en cuenta lo siguiente:"
      confirm-text="Editar de todas formas"
      @confirm="startEditing"
      @cancel="confirmEdit = false"
    >
      <ul class="space-y-2 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-100 text-amber-900">
        <li v-if="approvedCount"><strong>{{ approvedCount }} candidato(s) ya aprobados:</strong> los cambios sí se guardan y siguen aprobados, listos para oficializar.</li>
        <li v-if="officialCount"><strong>{{ officialCount }} candidato(s) ya oficiales:</strong> están bloqueados y no se modifican desde aquí.</li>
      </ul>
    </ConfirmModal>

    <ConfirmModal
      :open="confirmDiscard"
      title="¿Descartar los cambios?"
      message="Hay cambios sin guardar en la plancha. Si sales de la edición se perderán."
      confirm-text="Descartar cambios"
      cancel-text="Seguir editando"
      danger
      @confirm="discardEdit"
      @cancel="confirmDiscard = false"
    />

    <ConfirmModal
      :open="confirmPromote"
      title="¿Oficializar los candidatos aprobados?"
      :message="`Se publicarán ${promotableDraftCount} candidato(s) aprobados de ${planchaData.nombreBarrio} en las planchas oficiales.`"
      confirm-text="Oficializar"
      :loading="isPromoting"
      @confirm="promoteApprovedBatch"
      @cancel="confirmPromote = false"
    />

    <ResultModal :open="result.open" :success="result.success" :title="result.title" :message="result.message" @close="closeResult" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertTriangle, Edit2, Lock, Save, Users, UserPlus, Scale, Handshake, ChevronLeft, ChevronRight, Image as ImageIcon, ArrowLeft } from 'lucide-vue-next';
import CandidateCard from '@/components/secretary/CandidateCard.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import ImageViewer from '@/components/ui/ImageViewer.vue';
import ResultModal from '@/components/ResultModal.vue';
import { formatPersonName, isUnknownName, normalizeUnknownName } from '@/utils/unknownCandidate';
import { useDocumentStore } from '@/stores/document';
import axios from '@/services/axios';

const route = useRoute();
const router = useRouter();
const docStore = useDocumentStore();

const isEditing = ref(false);
const isSaving = ref(false);
const evidenceFiles = ref([]);
const isPromoting = ref(false);

// Confirmaciones y resultados (antes eran window.alert).
const confirmSave = ref(false);
// Lo que falta en la plancha al momento de guardar (se muestra en la confirmación).
const incompleteItems = ref([]);
const emptyCargos = ref([]);
const blockingError = ref('');
const hasIncompleteData = computed(() => incompleteItems.value.length > 0 || emptyCargos.value.length > 0);
const confirmPromote = ref(false);
const confirmDiscard = ref(false);
const result = reactive({ open: false, success: true, title: '', message: '', goToInbox: false });
const showResult = (success, title, message, goToInbox = false) => Object.assign(result, { open: true, success, title, message, goToInbox });

// Después de guardar una plancha (nueva o editada) se vuelve a la bandeja de
// revisión, para seguir con la siguiente en vez de quedarse en esta.
const closeResult = () => {
  result.open = false;
  if (result.goToInbox) router.push({ name: 'secretary-planchas' });
};

// Plancha recién escaneada (aún sin guardar) o edición de una ya registrada.
const isNewPlancha = ref(route.query.preview === '1' || route.params.id === 'preview');

// Copia de los datos al empezar a editar: para saber si hay cambios sin guardar.
let editSnapshot = null;
const snapshotPlancha = () => JSON.stringify({
  bloque1: planchaData.bloque1, bloque2: planchaData.bloque2, bloque3: planchaData.bloque3, bloque4: planchaData.bloque4,
});

// Resumen para la confirmación de guardado.
const saveSummary = computed(() => {
  const cargos = ['bloque1', 'bloque2', 'bloque3', 'bloque4'].flatMap((key) => Object.values(planchaData[key]));
  return {
    filled: cargos.filter((cargo) => cargo.nombre?.trim()).length,
    total: cargos.length,
    unknown: cargos.filter((cargo) => isUnknownName(cargo.nombre)).length,
    provisional: cargos.filter((cargo) => cargo.nombre?.trim() && !cargo.identificacion?.trim()).length,
    images: localEvidenceImages.value.length,
  };
});
const currentBatchUuid = ref((route.query.batch ?? docStore.captureBatchUuid ?? null));
const promotableDraftCount = ref(0);

// Cómo está la plancha: sirve para avisar antes de editar algo ya aprobado y
// para bloquear lo que ya se oficializó (el servidor tampoco lo modifica).
const allCargos = () => ['bloque1', 'bloque2', 'bloque3', 'bloque4'].flatMap((key) => Object.values(planchaData[key]));
const officialCount = computed(() => allCargos().filter((cargo) => cargo.estado === 'official').length);
const approvedCount = computed(() => allCargos().filter((cargo) => cargo.estado === 'approved').length);
const editableCount = computed(() => allCargos().filter((cargo) => cargo.nombre?.trim() && cargo.estado !== 'official').length);
// Toda la plancha ya es oficial: solo lectura.
const isFullyOfficial = computed(() => officialCount.value > 0 && editableCount.value === 0);
const confirmEdit = ref(false);
const localEvidenceImages = ref([]);
const dataLoadError = ref(null);
const isFormManual = ref(false);
const isPollingOcr = ref(false);
const ocrPollMessage = ref('');
const MAX_FILE_SIZE = 5 * 1024 * 1024; 
const OCR_POLL_INTERVAL_MS = 4000;
const OCR_MAX_WAIT_MS = 120000;

// --- VALIDACIONES DE FORMULARIO ---
const validationErrors = ref([]);
const validationDetails = ref([]);
const hasError = (key) => validationErrors.value.includes(key);

const currentPage = ref(0);

const allEvidenceImages = computed(() => {
  if (localEvidenceImages.value.length > 0) return localEvidenceImages.value;
  if (evidenceFiles.value.length > 0) return evidenceFiles.value.map(f => ({ url: f.download_url, id: f.id }));
  return [];
});

// currentPage es la página del FORMULARIO; imagePage, la foto que se ve. Antes
// eran lo mismo: con 2 fotos no se podía llegar a las páginas 3 y 4 de datos.
const FORM_PAGES = ['Directiva', 'Secretario y delegados', 'Delegados y fiscal', 'Convivencia'];
const imagePage = ref(0);
const totalImages = computed(() => allEvidenceImages.value.length || 1);
const currentImage = computed(() => allEvidenceImages.value[imagePage.value] || null);

const prevImage = () => { if (imagePage.value > 0) imagePage.value--; };
const nextImage = () => { if (imagePage.value < totalImages.value - 1) imagePage.value++; };

// Si cambia la cantidad de fotos, la foto activa no puede quedar fuera de rango.
watch(() => allEvidenceImages.value.length, (length) => {
  if (imagePage.value >= length) imagePage.value = Math.max(0, length - 1);
});

// Reordenar solo las fotos recién tomadas (aún sin guardar): ese orden es el
// que se guarda como página 1, 2, 3… de la evidencia.
const canReorderImages = computed(() => localEvidenceImages.value.length > 1);
const moveImage = (direction) => {
  const from = imagePage.value;
  const to = from + direction;
  if (to < 0 || to >= localEvidenceImages.value.length) return;
  const images = [...localEvidenceImages.value];
  [images[from], images[to]] = [images[to], images[from]];
  localEvidenceImages.value = images;
  docStore.setImages(images, 'plancha');
  imagePage.value = to;
};

// Páginas del formulario con cargos resaltados (tras "Corregir datos").
const PAGE_BY_KEY = {
  'bloque1.presidente': 0, 'bloque1.vicepresidente': 0, 'bloque1.tesorero': 0,
  'bloque1.secretario': 1, 'bloque2.suplentePresidente': 1, 'bloque2.delegado1': 1, 'bloque2.suplente1': 1, 'bloque2.delegado2': 1,
  'bloque2.suplente2': 2, 'bloque2.delegado3': 2, 'bloque2.suplente3': 2, 'bloque3.fiscal': 2, 'bloque3.suplente': 2,
  'bloque4.conciliador1': 3, 'bloque4.conciliador2': 3, 'bloque4.conciliador3': 3, 'bloque4.empresarial': 3,
};
const pageHasPending = (index) => validationErrors.value.some((key) => PAGE_BY_KEY[key] === index);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// --- ESTRUCTURA: AHORA SÍ CON CELULAR Y CORREO EN TODOS ---
// estado: '' (sin guardar), 'pending', 'approved', 'rejected' u 'official' (ya publicado).
const createCargo = () => ({ nombre: '', identificacion: '', celular: '', correo: '', estado: '' });

const planchaData = reactive({
  numero: route.query.plancha_number ? `Plancha No. ${route.query.plancha_number}` : 'Plancha No. 1',
  nombreBarrio: route.query.neighborhood_name || 'Cargando territorio...',
  nombrePlancha: 'Transparencia Comunal',
  bloque1: {
    presidente: createCargo(),
    vicepresidente: createCargo(),
    tesorero: createCargo(),
    secretario: createCargo(),
  },
  bloque2: {
    suplentePresidente: createCargo(),
    delegado1: createCargo(), suplente1: createCargo(),
    delegado2: createCargo(), suplente2: createCargo(),
    delegado3: createCargo(), suplente3: createCargo(),
  },
  bloque3: {
    fiscal: createCargo(),
    suplente: createCargo(),
  },
  bloque4: {
    conciliador1: createCargo(),
    conciliador2: createCargo(),
    conciliador3: createCargo(),
    empresarial: createCargo()
  }
});

const normalizeCargoLabel = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().replace(/\s+/g, ' ').trim();

const buildCandidateLookup = () => {
  const lookup = {};
  const pages = Object.values(docStore.extractedData || {});
  for (const page of pages) {
    for (const block of page?.bloques || []) {
      for (const cargo of block?.cargos || []) {
        const key = normalizeCargoLabel(cargo?.puesto);
        if (!key || lookup[key]) continue;
        lookup[key] = cargo;
      }
    }
  }
  return lookup;
};

const fillFromLookup = (target, source = {}) => {
  // "<Unknown>" del OCR se muestra y se guarda como "<DESCONOCIDO>".
  target.nombre = normalizeUnknownName(source.nombre) || target.nombre || '';
  target.identificacion = source.identificacion || target.identificacion || '';
  target.celular = source.celular || target.celular || '';
  target.correo = source.correo || target.correo || '';
};

const extractDraftArray = (resData) => {
  if (Array.isArray(resData?.data?.data)) return resData.data.data;
  if (Array.isArray(resData?.data)) return resData.data;
  if (Array.isArray(resData)) return resData;
  return [];
};

const isRetryableDraftsError = (error, hasBatchUuid) => {
  if (!hasBatchUuid) return false;
  const status = Number(error?.response?.status ?? 0);
  return status === 422 || status === 404 || status === 409 || status === 429 || status >= 500 || status === 0;
};

const composeDraftFullName = (draft) => {
  const rawName = [draft?.first_name, draft?.middle_name, draft?.last_name, draft?.second_last_name]
    .filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
  return formatPersonName(rawName);
};
const extractCargoFromNotes = (notes) => { const match = String(notes || '').match(/Cargo:\s*(.+)$/i); return match ? match[1].trim() : ''; };
// El suplente comparte cargo con su principal (p. ej. ambos "Presidente"): se
// distingue con is_substitute. Sin esto el suplente caía en la casilla del
// principal y la suya quedaba vacía.
const resolveDraftCargoLabel = (draft) => {
  const fromNotes = extractCargoFromNotes(draft?.notes);
  const base = /^\s*suplente/i.test(fromNotes)
    ? fromNotes
    : (draft?.position?.name || fromNotes || draft?.position?.code || '');
  const label = normalizeCargoLabel(base).replace(/^SUPLENTE DE /, 'SUPLENTE ');
  if (draft?.is_substitute && !label.startsWith('SUPLENTE')) return `SUPLENTE ${label}`;
  return label;
};

const applyDraftToPlancha = (draft) => {
  const label = resolveDraftCargoLabel(draft);
  const lookup = {
    PRESIDENTE: planchaData.bloque1.presidente,
    VICEPRESIDENTE: planchaData.bloque1.vicepresidente,
    TESORERO: planchaData.bloque1.tesorero,
    SECRETARIO: planchaData.bloque1.secretario,
    'SUPLENTE DE PRESIDENTE': planchaData.bloque2.suplentePresidente,
    'SUPLENTE PRESIDENTE': planchaData.bloque2.suplentePresidente,
    'DELEGADO ASOJUNTAS 1': planchaData.bloque2.delegado1,
    'SUPLENTE DELEGADO ASOJUNTAS 1': planchaData.bloque2.suplente1,
    'DELEGADO ASOJUNTAS 2': planchaData.bloque2.delegado2,
    'SUPLENTE DELEGADO ASOJUNTAS 2': planchaData.bloque2.suplente2,
    'DELEGADO ASOJUNTAS 3': planchaData.bloque2.delegado3,
    'SUPLENTE DELEGADO ASOJUNTAS 3': planchaData.bloque2.suplente3,
    FISCAL: planchaData.bloque3.fiscal,
    'SUPLENTE FISCAL': planchaData.bloque3.suplente,
    'CONCILIADOR 1': planchaData.bloque4.conciliador1,
    'CONCILIADOR 2': planchaData.bloque4.conciliador2,
    'CONCILIADOR 3': planchaData.bloque4.conciliador3,
    'COMISION EMPRESARIAL': planchaData.bloque4.empresarial,
  };

  const target = lookup[label];
  if (!target) return;

  fillFromLookup(target, {
    nombre: composeDraftFullName(draft),
    identificacion: draft?.document_number || '',
    celular: draft?.phone || '',
    correo: draft?.email || '',
  });
  target.estado = draft?.is_processed && draft?.review_status !== 'rejected' ? 'official' : (draft?.review_status || '');
};

const hydratePlanchaFromDrafts = async () => {
  const params = { per_page: 100 };
  if (currentBatchUuid.value) params.capture_batch_uuid = currentBatchUuid.value;
  const hasBatchUuid = Boolean(params.capture_batch_uuid);

  const startedAt = Date.now();
  let attempt = 0;
  let lastContextMessage = '';

  isPollingOcr.value = hasBatchUuid;
  ocrPollMessage.value = hasBatchUuid ? 'Esperando el resultado de la extracción...' : '';
  dataLoadError.value = null;

  while (Date.now() - startedAt <= OCR_MAX_WAIT_MS) {
    attempt += 1;
    if (hasBatchUuid) {
      ocrPollMessage.value = `Extrayendo los datos de la plancha... intento ${attempt}`;
    }

    try {
      const response = await axios.get('/secretary/planchas/drafts', {
        params,
        timeout: 30000,
        skipGlobalLoading: true,
      });
      const apiDrafts = extractDraftArray(response.data);

      if (apiDrafts.length > 0) {
        const first = apiDrafts[0];

        if (!route.query.neighborhood_name) {
          planchaData.nombreBarrio = first?.neighborhood_name || first?.election?.neighborhood?.name || 'JAC Sin Identificar';
        }

        if (!currentBatchUuid.value && first?.capture_batch_uuid) {
          currentBatchUuid.value = first.capture_batch_uuid;
          docStore.setCaptureBatchUuid(first.capture_batch_uuid);
        }

        apiDrafts.forEach((draft) => applyDraftToPlancha(draft));
        dataLoadError.value = null;
        isFormManual.value = false;
        isPollingOcr.value = false;
        ocrPollMessage.value = '';
        return true;
      }

      lastContextMessage = hasBatchUuid
        ? 'Aún no hay datos extraídos para esta plancha.'
        : 'No hay borradores disponibles para precargar.';
    } catch (error) {
      console.error('Auditoria - Error al cargar datos:', error);

      if (!isRetryableDraftsError(error, hasBatchUuid)) {
        const detail = error?.response?.data?.message || error?.message || 'Error desconocido';
        dataLoadError.value = `Error al cargar datos: ${detail}`;
        isFormManual.value = true;
        isPollingOcr.value = false;
        ocrPollMessage.value = '';
        return false;
      }

      if (Number(error?.response?.status) === 422) {
        lastContextMessage = 'La extracción sigue en proceso en el servidor.';
      } else {
        lastContextMessage = 'Esperando respuesta del servidor de extracción.';
      }
    }

    if (!hasBatchUuid) break;
    if ((Date.now() - startedAt) + OCR_POLL_INTERVAL_MS > OCR_MAX_WAIT_MS) break;
    await sleep(OCR_POLL_INTERVAL_MS);
  }

  isPollingOcr.value = false;
  ocrPollMessage.value = '';
  isFormManual.value = true;

  if (hasBatchUuid) {
    const seconds = Math.round(OCR_MAX_WAIT_MS / 1000);
    dataLoadError.value = `La extracción aún no termina tras ${seconds}s de espera. Puedes llenar los datos a mano o reintentar la extracción.${lastContextMessage ? ` ${lastContextMessage}` : ''}`;
  } else {
    dataLoadError.value = 'No se encontró una plancha capturada para consultar. Puedes llenar los datos a mano.';
  }

  return false;
};

const hydratePlanchaFromExtraction = () => {
  const lookup = buildCandidateLookup();
  if (Object.keys(lookup).length === 0) return false;

  fillFromLookup(planchaData.bloque1.presidente, lookup.PRESIDENTE);
  fillFromLookup(planchaData.bloque1.vicepresidente, lookup.VICEPRESIDENTE);
  fillFromLookup(planchaData.bloque1.tesorero, lookup.TESORERO);
  fillFromLookup(planchaData.bloque1.secretario, lookup.SECRETARIO);

  fillFromLookup(
    planchaData.bloque2.suplentePresidente,
    lookup['SUPLENTE DE PRESIDENTE'] || lookup['SUPLENTE PRESIDENTE']
  );
  fillFromLookup(planchaData.bloque2.delegado1, lookup['DELEGADO ASOJUNTAS 1']);
  fillFromLookup(planchaData.bloque2.suplente1, lookup['SUPLENTE DELEGADO ASOJUNTAS 1']);
  fillFromLookup(planchaData.bloque2.delegado2, lookup['DELEGADO ASOJUNTAS 2']);
  fillFromLookup(planchaData.bloque2.suplente2, lookup['SUPLENTE DELEGADO ASOJUNTAS 2']);
  fillFromLookup(planchaData.bloque2.delegado3, lookup['DELEGADO ASOJUNTAS 3']);
  fillFromLookup(planchaData.bloque2.suplente3, lookup['SUPLENTE DELEGADO ASOJUNTAS 3']);

  fillFromLookup(planchaData.bloque3.fiscal, lookup.FISCAL);
  fillFromLookup(planchaData.bloque3.suplente, lookup['SUPLENTE FISCAL']);

  fillFromLookup(planchaData.bloque4.conciliador1, lookup['CONCILIADOR 1']);
  fillFromLookup(planchaData.bloque4.conciliador2, lookup['CONCILIADOR 2']);
  fillFromLookup(planchaData.bloque4.conciliador3, lookup['CONCILIADOR 3']);
  fillFromLookup(planchaData.bloque4.empresarial, lookup['COMISION EMPRESARIAL']);

  return true;
};

onMounted(async () => {
  if (route.query.edit === 'true') isEditing.value = true;
  if (route.query.neighborhood_name) planchaData.nombreBarrio = route.query.neighborhood_name; 
  
  localEvidenceImages.value = docStore.capturedImages || [];

  const batchFromRoute = route.query.batch ?? docStore.captureBatchUuid ?? null;
  if (batchFromRoute) {
    currentBatchUuid.value = batchFromRoute;
    docStore.setCaptureBatchUuid(batchFromRoute);
    await loadEvidence(batchFromRoute);
    await loadPromotableCount(batchFromRoute);
  }

  if (route.query.preview === '1' || route.params.id === 'preview') {
    const hydratedFromStore = hydratePlanchaFromExtraction();
    if (!hydratedFromStore) {
      await hydratePlanchaFromDrafts();
    }
    // Una plancha recién escaneada aún no está guardada: todo lo leído cuenta como cambio.
    return;
  }

  await hydratePlanchaFromDrafts();
  // Se abrió directo en edición (desde la bandeja): punto de partida para detectar cambios.
  if (isEditing.value) {
    isEditing.value = false;
    requestEditing();
  }

  if (currentBatchUuid.value) {
    await loadPromotableCount(currentBatchUuid.value);
  }
});

const retryOcrHydration = async () => {
  if (isPollingOcr.value || isSaving.value) return;
  await hydratePlanchaFromDrafts();
  if (currentBatchUuid.value) {
    await loadPromotableCount(currentBatchUuid.value);
  }
};

const cancelEdit = () => {
  if (editSnapshot && editSnapshot !== snapshotPlancha()) {
    confirmDiscard.value = true;
    return;
  }
  discardEdit();
};

// Vuelve a los datos que había al empezar a editar.
const discardEdit = () => {
  confirmDiscard.value = false;
  if (editSnapshot) {
    const saved = JSON.parse(editSnapshot);
    ['bloque1', 'bloque2', 'bloque3', 'bloque4'].forEach((key) => {
      Object.entries(saved[key]).forEach(([cargo, values]) => Object.assign(planchaData[key][cargo], values));
    });
  }
  isEditing.value = false;
  validationErrors.value = [];
  validationDetails.value = [];
};

// Editar algo ya aprobado u oficial se permite, pero avisando antes qué va a pasar.
const requestEditing = () => {
  if (isFullyOfficial.value) return;
  if (approvedCount.value > 0 || officialCount.value > 0) {
    confirmEdit.value = true;
    return;
  }
  startEditing();
};

const startEditing = () => {
  confirmEdit.value = false;
  editSnapshot = snapshotPlancha();
  isEditing.value = true;
};

const saveChanges = () => {
  validationErrors.value = [];
  validationDetails.value = [];
  
  // MAPA DE VALIDACIÓN DETALLADO
  const cargosAChequear = [
    { key: 'bloque1.presidente', obj: planchaData.bloque1.presidente, nombre: 'Presidente', pagina: 1 },
    { key: 'bloque1.vicepresidente', obj: planchaData.bloque1.vicepresidente, nombre: 'Vicepresidente', pagina: 1 },
    { key: 'bloque1.tesorero', obj: planchaData.bloque1.tesorero, nombre: 'Tesorero', pagina: 1 },
    { key: 'bloque1.secretario', obj: planchaData.bloque1.secretario, nombre: 'Secretario', pagina: 2 },
    { key: 'bloque2.suplentePresidente', obj: planchaData.bloque2.suplentePresidente, nombre: 'Suplente de Presidente', pagina: 2 },
    { key: 'bloque2.delegado1', obj: planchaData.bloque2.delegado1, nombre: 'Delegado 1', pagina: 2 },
    { key: 'bloque2.suplente1', obj: planchaData.bloque2.suplente1, nombre: 'Suplente Delegado 1', pagina: 2 },
    { key: 'bloque2.delegado2', obj: planchaData.bloque2.delegado2, nombre: 'Delegado 2', pagina: 2 },
    { key: 'bloque2.suplente2', obj: planchaData.bloque2.suplente2, nombre: 'Suplente Delegado 2', pagina: 3 },
    { key: 'bloque2.delegado3', obj: planchaData.bloque2.delegado3, nombre: 'Delegado 3', pagina: 3 },
    { key: 'bloque2.suplente3', obj: planchaData.bloque2.suplente3, nombre: 'Suplente Delegado 3', pagina: 3 },
    { key: 'bloque3.fiscal', obj: planchaData.bloque3.fiscal, nombre: 'Fiscal', pagina: 3 },
    { key: 'bloque3.suplente', obj: planchaData.bloque3.suplente, nombre: 'Suplente Fiscal', pagina: 3 },
    { key: 'bloque4.conciliador1', obj: planchaData.bloque4.conciliador1, nombre: 'Conciliador 1', pagina: 4 },
    { key: 'bloque4.conciliador2', obj: planchaData.bloque4.conciliador2, nombre: 'Conciliador 2', pagina: 4 },
    { key: 'bloque4.conciliador3', obj: planchaData.bloque4.conciliador3, nombre: 'Conciliador 3', pagina: 4 },
    { key: 'bloque4.empresarial', obj: planchaData.bloque4.empresarial, nombre: 'Coord. Comisión Empresarial', pagina: 4 },
  ];

  // Los datos incompletos ya no impiden guardar: se listan en la ventana de
  // confirmación y la secretaria decide si registra así o los corrige.
  const incomplete = [];
  const empty = [];

  for (const cargo of cargosAChequear) {
    if (cargo.obj.estado === 'official') continue; // bloqueado: no se corrige desde aquí
    const has = (field) => Boolean(String(cargo.obj[field] ?? '').trim());
    const hasName = has('nombre');

    if (!hasName && !has('identificacion') && !has('celular') && !has('correo')) {
      empty.push({ key: cargo.key, pagina: cargo.pagina, nombre: cargo.nombre });
      continue;
    }

    // Candidato ilegible (<Desconocido>): es normal que no tenga más datos.
    const ilegible = isUnknownName(cargo.obj.nombre);
    const faltantes = [];
    if (!hasName) faltantes.push('Nombre');
    if (!ilegible && !has('identificacion')) faltantes.push('No. Identificación');
    if (!ilegible && !has('celular')) faltantes.push('Celular');
    if (!ilegible && !has('correo')) faltantes.push('Correo Electrónico');

    if (faltantes.length > 0) {
      incomplete.push({
        key: cargo.key,
        pagina: cargo.pagina,
        nombre: cargo.nombre,
        faltantes: faltantes.join(', '),
        // Sin nombre no hay a quién registrar: ese cargo se omite al guardar.
        omitido: !hasName,
      });
    }
  }

  incompleteItems.value = incomplete;
  emptyCargos.value = empty;

  // Lo único que sí impide guardar: una plancha sin ningún candidato con nombre.
  if (officialCount.value === 0 && empty.length + incomplete.filter((item) => item.omitido).length === cargosAChequear.length) {
    blockingError.value = 'La plancha no tiene ningún candidato con nombre. Escribe al menos uno para registrarla.';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  blockingError.value = '';
  confirmSave.value = true;
};

// "Corregir datos": cierra la ventana, resalta los cargos incompletos y lleva
// a la primera página que tiene alguno.
const reviewIncomplete = () => {
  confirmSave.value = false;
  const pending = [...incompleteItems.value, ...emptyCargos.value];
  validationErrors.value = pending.map((item) => item.key);
  validationDetails.value = incompleteItems.value;
  if (pending.length) {
    currentPage.value = Math.min(...pending.map((item) => item.pagina)) - 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

const persistPlancha = () => {
  confirmSave.value = false;

  const generatedBatchUuid = currentBatchUuid.value || (globalThis.crypto?.randomUUID?.() ?? null);
  const captureBatchUuid = generatedBatchUuid || `batch-${Date.now()}`;

  currentBatchUuid.value = captureBatchUuid;
  docStore.setCaptureBatchUuid(captureBatchUuid);

  const slateCodeMatch = String(planchaData.numero || '').match(/(\d+)/);
  const slateCode = slateCodeMatch ? `P${slateCodeMatch[1]}` : null;

  const reviewPageData = {
    bloques: [
      { titulo: 'Bloque - Directiva', cargos: [
        { puesto: 'PRESIDENTE', ...planchaData.bloque1.presidente },
        { puesto: 'VICEPRESIDENTE', ...planchaData.bloque1.vicepresidente },
        { puesto: 'TESORERO', ...planchaData.bloque1.tesorero },
        { puesto: 'SECRETARIO', ...planchaData.bloque1.secretario },
      ]},
      { titulo: 'Bloque - Delegados Asojuntas', cargos: [
        { puesto: 'SUPLENTE DE PRESIDENTE', ...planchaData.bloque2.suplentePresidente },
        { puesto: 'DELEGADO ASOJUNTAS 1', ...planchaData.bloque2.delegado1 },
        { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 1', ...planchaData.bloque2.suplente1 },
        { puesto: 'DELEGADO ASOJUNTAS 2', ...planchaData.bloque2.delegado2 },
        { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 2', ...planchaData.bloque2.suplente2 },
        { puesto: 'DELEGADO ASOJUNTAS 3', ...planchaData.bloque2.delegado3 },
        { puesto: 'SUPLENTE DELEGADO ASOJUNTAS 3', ...planchaData.bloque2.suplente3 },
      ]},
      { titulo: 'Bloque - Fiscal', cargos: [
        { puesto: 'FISCAL', ...planchaData.bloque3.fiscal },
        { puesto: 'SUPLENTE FISCAL', ...planchaData.bloque3.suplente },
      ]},
      { titulo: 'Bloque - Comisión de convivencia y conciliación', cargos: [
        { puesto: 'CONCILIADOR 1', ...planchaData.bloque4.conciliador1 },
        { puesto: 'CONCILIADOR 2', ...planchaData.bloque4.conciliador2 },
        { puesto: 'CONCILIADOR 3', ...planchaData.bloque4.conciliador3 },
        { puesto: 'COMISION EMPRESARIAL', ...planchaData.bloque4.empresarial },
      ]},
    ],
  };

  isSaving.value = true;

  // PASO 1: Intentar guardar los datos
  let dataSaveSuccess = false;
  let rejectedMessage = '';
  let lockedOfficial = [];
  axios.post('/secretary/planchas/drafts', {
    source_type: 'ocr',
    capture_batch_uuid: captureBatchUuid,
    slate_code: slateCode,
    review_page_data: reviewPageData,
    replace_pending: route.query.preview === '1',
    election_id: route.query.election_id 
  }, {
    timeout: 240000,
    skipGlobalLoading: true,
  })
    .then(async (response) => {
      dataSaveSuccess = true;
      lockedOfficial = response?.data?.data?.locked_official ?? [];
      console.log('✅ Datos guardados exitosamente');
    })
    .catch((error) => {
      console.error('❌ Error al guardar datos:', error);
      dataSaveSuccess = false;
      // El servidor rechazó la plancha (p. ej. el barrio ya tiene un acta aprobada).
      if (Number(error?.response?.status) === 422) {
        rejectedMessage = error.response.data?.message || 'El servidor rechazó los datos de la plancha.';
      }
    })
    .finally(async () => {
      if (rejectedMessage) {
        // Sin plancha no tiene sentido subir las imágenes.
        showResult(false, 'No se pudo registrar la plancha', rejectedMessage);
        isSaving.value = false;
        return;
      }

      // PASO 2: SIEMPRE intentar guardar las imágenes, independientemente de si los datos se guardaron
      let evidenceWarning = '';
      let evidenceSaveSuccess = false;

      if (localEvidenceImages.value.length > 0) {
        try {
          const form = new FormData();
          form.append('capture_batch_uuid', captureBatchUuid);

          localEvidenceImages.value.forEach((img, index) => {
            if (img.file instanceof File) {
              // Validar tamaño máximo de 5MB
              if (img.file.size > MAX_FILE_SIZE) {
                throw new Error(`El archivo "${img.file.name}" excede 5MB. Tamaño: ${(img.file.size / 1024 / 1024).toFixed(2)}MB`);
              }
              form.append('document_files[]', img.file);
              form.append('page_numbers[]', String(index + 1));
            }
          });

          await axios.post('/secretary/planchas/evidence', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            timeout: 240000,
            skipGlobalLoading: true,
          });
          evidenceSaveSuccess = true;
          console.log('✅ Evidencia guardada exitosamente');
        } catch (error) {
          evidenceWarning = ` La evidencia no se guardó: ${error?.response?.data?.message || error?.message}`;
          console.error('❌ Error al guardar evidencia:', error);
        }
      }

      // PASO 3: Recargar y redirigir
      if (dataSaveSuccess || evidenceSaveSuccess) {
        await loadEvidence(captureBatchUuid);
        await loadPromotableCount(captureBatchUuid);
        isEditing.value = false;

        router.replace({
          name: 'secretary-plancha-detail',
          params: { id: route.params.id || 'preview' },
          query: { batch: captureBatchUuid },
        });

        let message = '';
        if (dataSaveSuccess && evidenceSaveSuccess) {
          message = 'Plancha e imágenes guardadas en borradores.';
        } else if (dataSaveSuccess) {
          message = `Plancha guardada en borradores.${evidenceWarning}`;
        } else if (evidenceSaveSuccess) {
          message = 'Imágenes guardadas en borradores (datos no se procesaron).';
        }
        if (dataSaveSuccess) {
          if (lockedOfficial.length) {
            message += `\n\n${lockedOfficial.length} cargo(s) ya oficiales no se modificaron.`;
          }
          showResult(true, isNewPlancha.value ? 'Plancha registrada' : 'Cambios guardados', `${message}\n\nAl cerrar este aviso volverás a la bandeja de revisión.`, true);
          isNewPlancha.value = false;
          editSnapshot = null;
          allCargos().forEach((cargo) => {
            if (cargo.nombre?.trim() && !cargo.estado) cargo.estado = 'pending';
          });
          validationErrors.value = [];
          validationDetails.value = [];
        } else {
          showResult(false, 'Solo se guardaron las imágenes', message);
        }
      } else {
        showResult(false, 'No se pudo guardar', `No se pudo guardar la plancha ni las imágenes.${evidenceWarning}`);
      }
      
      isSaving.value = false;
    });
};

const loadEvidence = async (batchUuid) => {
  if (!batchUuid) return;
  try {
    const { data } = await axios.get(`/secretary/planchas/evidence/${batchUuid}`, {
      skipGlobalLoading: true,
    });
    evidenceFiles.value = data?.data?.files ?? [];
  } catch {
    evidenceFiles.value = [];
  }
};

const loadPromotableCount = async (batchUuid) => {
  if (!batchUuid) {
    promotableDraftCount.value = 0;
    return;
  }
  try {
    const { data } = await axios.get('/secretary/planchas/drafts', {
      params: { capture_batch_uuid: batchUuid, review_status: 'approved', is_processed: 0 },
      skipGlobalLoading: true,
    });
    promotableDraftCount.value = Number(data?.data?.total ?? 0);
  } catch {
    promotableDraftCount.value = 0;
  }
};

const promoteApprovedBatch = async () => {
  confirmPromote.value = false;
  if (!currentBatchUuid.value || isPromoting.value) return;
  if (promotableDraftCount.value === 0) {
    showResult(false, 'Nada para oficializar', 'No hay borradores aprobados pendientes en este lote.');
    return;
  }
  isPromoting.value = true;
  try {
    await axios.post('/secretary/planchas/drafts/promote', { capture_batch_uuid: currentBatchUuid.value }, {
      timeout: 240000,
      skipGlobalLoading: true,
    });
    await loadPromotableCount(currentBatchUuid.value);
    await hydratePlanchaFromDrafts();
    isEditing.value = false;
    showResult(true, 'Plancha oficializada', 'Los candidatos aprobados ya aparecen en "Planchas por Barrio" y salieron de la bandeja de revisión.');
  } catch (error) {
    showResult(false, 'No se pudo oficializar', error?.response?.data?.message || error?.message || 'Error de conexión.');
  } finally {
    isPromoting.value = false;
  }
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #1a1c23;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #4b5563;
  border-radius: 10px;
}
</style>