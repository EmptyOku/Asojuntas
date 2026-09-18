<template>
  <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <h2 class="text-base sm:text-lg font-semibold text-gray-900">Listado de Personas</h2>
      <input
        v-model.trim="search"
        @input="handleSearchInput"
        class="w-full sm:w-80 px-3 py-2 rounded-lg border border-gray-200 text-sm"
        placeholder="Buscar por documento, nombre o correo..."
      />
    </div>

    <div class="overflow-auto border border-gray-100 rounded-xl">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-left font-medium px-3 py-2.5">Documento</th>
            <th class="text-left font-medium px-3 py-2.5">Nombre completo</th>
            <th class="text-left font-medium px-3 py-2.5">Barrio</th>
            <th class="text-left font-medium px-3 py-2.5">Estado</th>
            <th class="text-left font-medium px-3 py-2.5">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="person in persons" :key="person.id" class="border-t border-gray-100">
            <td class="px-3 py-2.5 text-gray-900">{{ person.document_type?.code || 'Doc.' }} {{ person.document_number }}</td>
            <td class="px-3 py-2.5 text-gray-700">{{ fullName(person) }}</td>
            <td class="px-3 py-2.5 text-gray-700">
              <span v-if="person.neighborhood">{{ person.neighborhood.name }}</span>
              <span v-else class="text-gray-400">Sin asignar</span>
            </td>
            <td class="px-3 py-2.5">
              <span class="text-xs px-2 py-1 rounded-md" :class="person.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'">
                {{ person.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-3 py-2.5">
              <button type="button" class="text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-black" @click="$emit('edit-person', person)">Editar</button>
            </td>
          </tr>
          <tr v-if="!persons.length">
            <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-400">
              No se encontraron personas{{ search ? ' para "' + search + '"' : '' }}.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4">
      <p class="text-sm text-gray-500">
        <template v-if="personsTotal > 0">Mostrando {{ personsFrom }}–{{ personsTo }} de {{ personsTotal }}</template>
        <template v-else>Sin resultados</template>
      </p>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-gray-600">
          Mostrar
          <select v-model.number="perPageModel" class="px-2 py-1.5 rounded-lg border border-gray-200 bg-white text-sm">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
        </label>
        <div class="flex items-center gap-1">
          <button
            type="button"
            @click="$emit('page-change', personsCurrentPage - 1)"
            :disabled="personsCurrentPage <= 1"
            class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
          >
            Anterior
          </button>
          <span class="px-2 text-sm text-gray-600 whitespace-nowrap">Página {{ personsCurrentPage }} de {{ personsLastPage }}</span>
          <button
            type="button"
            @click="$emit('page-change', personsCurrentPage + 1)"
            :disabled="personsCurrentPage >= personsLastPage"
            class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  persons: { type: Array, default: () => [] },
  personsPerPage: { type: Number, default: 20 },
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
</script>
