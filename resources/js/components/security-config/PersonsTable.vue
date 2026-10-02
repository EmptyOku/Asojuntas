<template>
  <section class="card overflow-hidden">
    <div class="card-header">
      <div>
        <h2 class="card-title">Listado de personas</h2>
        <p class="card-subtitle">{{ personsTotal }} {{ personsTotal === 1 ? 'registro' : 'registros' }}</p>
      </div>
      <div class="relative w-full sm:w-80">
        <Search class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          v-model.trim="search"
          @input="handleSearchInput"
          class="field field-search"
          placeholder="Buscar documento, nombre o correo"
        />
      </div>
    </div>

    <div v-if="legendItems.length" class="px-5 sm:px-6 py-2.5 border-b border-gray-100 bg-gray-50/60">
      <ActionLegend :items="legendItems" />
    </div>

    <div class="table-wrap rounded-none">
      <table class="data-table">
        <thead>
          <tr>
            <th>Persona</th>
            <th>Documento</th>
            <th>Barrio / Sector</th>
            <th>Estado</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="person in persons" :key="person.id" class="row-enter">
            <td>
              <div class="flex items-center gap-3 min-w-0">
                <div class="avatar" :class="{ 'grayscale opacity-60': !person.is_active }">{{ initialsOf(person) }}</div>
                <p class="font-semibold text-gray-900 truncate">{{ fullName(person) }}</p>
              </div>
            </td>
            <td class="whitespace-nowrap">
              <span class="text-xs font-semibold text-gray-400">{{ person.document_type?.code || 'Doc.' }}</span>
              <span class="text-gray-800 tabular-nums"> {{ person.document_number }}</span>
            </td>
            <td>
              <span v-if="person.neighborhood" class="text-gray-800">{{ person.neighborhood.name }}</span>
              <span v-else class="text-gray-400">Sin asignar</span>
            </td>
            <td>
              <span :class="person.is_active ? 'badge-green' : 'badge-gray'">
                <span class="badge-dot"></span>
                {{ person.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td>
              <div class="flex justify-end">
                <button v-can="'users.update'" type="button" class="icon-btn-blue" data-tooltip="Editar persona" aria-label="Editar persona" @click="$emit('edit-person', person)">
                  <Pencil class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!persons.length">
            <td colspan="5" class="py-10 text-center text-sm text-gray-400">
              No se encontraron personas{{ search ? ' para "' + search + '"' : '' }}.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 sm:px-6 py-4 border-t border-gray-100 bg-gray-50/50">
      <p class="text-sm text-gray-500">
        <template v-if="personsTotal > 0">Mostrando <span class="font-semibold text-gray-700">{{ personsFrom }}–{{ personsTo }}</span> de {{ personsTotal }}</template>
        <template v-else>Sin resultados</template>
      </p>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-gray-600">
          Mostrar
          <select v-model.number="perPageModel" class="field w-20 py-1.5">
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </label>
        <div class="flex items-center gap-1.5">
          <button
            type="button"
            class="pager-btn"
            aria-label="Página anterior"
            :disabled="personsCurrentPage <= 1"
            @click="$emit('page-change', personsCurrentPage - 1)"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <span class="px-2 text-sm text-gray-600 whitespace-nowrap">{{ personsCurrentPage }} / {{ personsLastPage }}</span>
          <button
            type="button"
            class="pager-btn"
            aria-label="Página siguiente"
            :disabled="personsCurrentPage >= personsLastPage"
            @click="$emit('page-change', personsCurrentPage + 1)"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight, Pencil, Search } from 'lucide-vue-next';
import ActionLegend from '@/components/ui/ActionLegend.vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const legendItems = computed(() => (authStore.can('users.update')
  ? [{ icon: Pencil, label: 'Editar datos de la persona', tone: 'blue' }]
  : []));

const props = defineProps({
  persons: { type: Array, default: () => [] },
  personsPerPage: { type: Number, default: 10 },
  personsCurrentPage: { type: Number, default: 1 },
  personsLastPage: { type: Number, default: 1 },
  personsTotal: { type: Number, default: 0 },
  personsFrom: { type: Number, default: 0 },
  personsTo: { type: Number, default: 0 },
});

const emit = defineEmits(['search-change', 'per-page-change', 'page-change', 'edit-person']);

const search = ref('');
const perPageModel = computed({
  get: () => props.personsPerPage,
  set: (value) => emit('per-page-change', value),
});
let searchTimeout = null;

const handleSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    emit('search-change', search.value);
  }, 400);
};

const fullName = (person) => [person.first_name, person.middle_name, person.last_name, person.second_last_name]
  .filter(Boolean)
  .join(' ');

const initialsOf = (person) => [person.first_name, person.last_name]
  .filter(Boolean)
  .map((part) => part.charAt(0).toUpperCase())
  .join('') || '?';
</script>
