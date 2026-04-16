<template>
  <div class="space-y-6">
    <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
      <div class="flex items-start justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Módulo para crear personas antes que usuarios.</h1>
          <p class="text-sm text-gray-500 mt-1">Agrega las personas al programa antes de agregar sus usuarios</p>
        </div>
        <button
          type="button"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark transition-colors shrink-0"
          @click="loadAll"
          :disabled="loading"
        >
          Recargar
        </button>
      </div>

      <div v-if="errorMessage" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
        {{ errorMessage }}
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
          <h2 class="text-base font-semibold text-gray-900 mb-3">Crear Persona</h2>
          <form class="space-y-4" @submit.prevent="submitPerson">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-700 mb-1">Tipo de Doc.</label>
                <select v-model="formPerson.document_type_id" required class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
                  <option value="" disabled>Seleccione...</option>
                  <option value="1">Cédula de Ciudadanía (CC)</option>
                  <option value="2">Tarjeta de Identidad (TI)</option>
                  <option value="3">Cédula de Extranjería (CE)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Número de Doc.</label>
                <input v-model="formPerson.document_number" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Nombre</label>
                <input v-model="formPerson.first_name" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Nombre</label>
                <input v-model="formPerson.middle_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Primer Apellido</label>
                <input v-model="formPerson.last_name" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Segundo Apellido</label>
                <input v-model="formPerson.second_last_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
              </div>

              <div class="sm:col-span-2">
                <label class="block text-sm text-gray-700 mb-1">Comuna</label>
                <select
                  v-model="selectedCommune"
                  @change="handleCommuneChange"
                  class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm cursor-pointer focus:ring-2 focus:ring-aso-primary"
                >
                  <option value="">Seleccione una comuna...</option>
                  <option v-for="item in communes" :key="item.id" :value="String(item.id)">
                    {{ item.name }}
                  </option>
                </select>
              </div>
              
              <div class="sm:col-span-2">
                <label class="block text-sm text-gray-700 mb-1">Barrio de Residencia</label>
                <select 
                  v-model="formPerson.neighborhood_id" 
                  required 
                  :disabled="!selectedCommune || loadingNeighborhoods"
                  class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm cursor-pointer focus:ring-2 focus:ring-aso-primary"
                >
                  <option value="">{{ selectedCommune ? 'Seleccione un barrio...' : 'Primero seleccione una comuna...' }}</option>
                  <option v-for="item in neighborhoodsList" :key="item.id" :value="item.id">
                    {{ item.name }}
                  </option>
                </select>
              </div>
            </div>
            <button
              type="submit"
              class="w-full py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-60"
              :disabled="loading"
            >
              Registrar Persona
            </button>
          </form>
        </div>
      </div>
    </section>

    <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
      <div class="flex items-start justify-between gap-3 mb-4">
        <div>
          <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">Usuarios y Roles</h1>
          <p class="text-sm text-gray-500 mt-1">Gestiona cuentas y asignaciones de rol desde la API.</p>
        </div>
        <button
          type="button"
          class="px-3 py-2 text-sm font-medium rounded-lg bg-aso-primary text-white hover:bg-aso-primary-dark transition-colors"
          @click="loadAll"
          :disabled="loading"
        >
          Recargar
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
          <h2 class="text-base font-semibold text-gray-900 mb-3">Crear Usuario</h2>
          <form class="space-y-3" @submit.prevent="createUser">
            
            <div>
              <label class="block text-sm text-gray-700 mb-1 font-semibold">Persona Física</label>
              <div class="relative" ref="personSearchContainer">
                <div class="relative">
                  <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="text"
                    v-model="personSearchQuery"
                    @input="handlePersonSearch"
                    @focus="isPersonDropdownOpen = true"
                    placeholder="Buscar por cédula o nombre..."
                    class="w-full pl-9 pr-10 py-2 rounded-lg border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-aso-primary focus:border-aso-primary"
                  >
                  <button 
                    v-if="createForm.person_id" 
                    type="button"
                    @click="clearPersonSelection"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-red-500 rounded-full hover:bg-gray-100"
                  >
                    <X class="w-4 h-4" />
                  </button>
                  <Loader2 v-else-if="isSearchingPerson" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-aso-primary animate-spin" />
                </div>

                <div 
                  v-if="isPersonDropdownOpen && (personSearchResults.length > 0 || isSearchingPerson || personSearchQuery.length > 0)"
                  class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                  <div v-if="isSearchingPerson" class="p-3 text-sm text-gray-500 text-center">Buscando...</div>
                  <div v-else-if="personSearchResults.length === 0 && personSearchQuery.length >= 2" class="p-3 text-sm text-gray-500 text-center">No se encontraron personas</div>
                  <ul v-else-if="personSearchResults.length > 0" class="py-1">
                    <li 
                      v-for="person in personSearchResults" 
                      :key="person.id"
                      @click="selectPerson(person)"
                      class="px-4 py-2 hover:bg-aso-primary hover:text-white cursor-pointer text-sm border-b border-gray-50 last:border-0"
                      :class="{'bg-gray-50 text-aso-primary font-medium': createForm.person_id === person.id}"
                    >
                      {{ person.label }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Usuario</label>
              <input v-model="createForm.username" required class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Correo</label>
              <input v-model="createForm.email" required type="email" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Contraseña</label>
              <input v-model="createForm.password" required type="password" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Confirmar contraseña</label>
              <input v-model="createForm.password_confirmation" required type="password" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
            </div>

            <div>
              <label class="block text-sm text-gray-700 mb-1">Roles iniciales</label>
              <select v-model="createForm.roles" multiple class="w-full min-h-28 px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
                <option v-for="role in roles" :key="role.id" :value="role.id">
                  {{ role.display_name }} ({{ role.name }})
                </option>
              </select>
            </div>

            <button
              type="submit"
              class="w-full py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-black transition-colors disabled:opacity-60"
              :disabled="loading || createForm.roles.length === 0 || !createForm.person_id"
            >
              Crear cuenta
            </button>
          </form>
        </div>

        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
          <h2 class="text-base font-semibold text-gray-900 mb-3">Catálogo de Roles</h2>
          <div class="max-h-80 overflow-auto space-y-3 pr-1">
            <div v-for="role in roles" :key="role.id" class="rounded-lg border border-gray-200 bg-white p-3">
              <p class="font-medium text-gray-900">{{ role.display_name }}</p>
              <p class="text-xs text-gray-500">{{ role.name }}</p>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <span
                  v-for="perm in role.permissions || []"
                  :key="perm.id"
                  class="text-xs px-2 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  {{ perm.name }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <h2 class="text-base sm:text-lg font-semibold text-gray-900">Listado de Usuarios</h2>
        <input
          v-model.trim="search"
          class="w-full sm:w-72 px-3 py-2 rounded-lg border border-gray-200 text-sm"
          placeholder="Buscar por usuario/correo"
        />
      </div>

      <div class="overflow-auto border border-gray-100 rounded-xl">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600">
            <tr>
              <th class="text-left font-medium px-3 py-2.5">Usuario</th>
              <th class="text-left font-medium px-3 py-2.5">Correo</th>
              <th class="text-left font-medium px-3 py-2.5">Barrio</th>
              <th class="text-left font-medium px-3 py-2.5">Mesa Sugerida</th>
              <th class="text-left font-medium px-3 py-2.5">Estado</th>
              <th class="text-left font-medium px-3 py-2.5">Roles</th>
              <th class="text-left font-medium px-3 py-2.5">Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id" class="border-t border-gray-100">
              <td class="px-3 py-2.5 text-gray-900">{{ user.username }}</td>
              <td class="px-3 py-2.5 text-gray-700">{{ user.email }}</td>
              <td class="px-3 py-2.5 text-gray-700">
                <span v-if="user.person?.neighborhood">{{ user.person.neighborhood.name }}</span>
                <span v-else class="text-gray-400">Sin asignar</span>
              </td>
              <td class="px-3 py-2.5 text-gray-700">
                <template v-if="suggestedTableForUser(user)">
                  {{ suggestedTableForUser(user).name }} ({{ suggestedTableForUser(user).code }})
                </template>
                <span v-else class="text-gray-400">Sin mesa activa</span>
              </td>
              <td class="px-3 py-2.5">
                <span class="text-xs px-2 py-1 rounded-md" :class="user.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700'">
                  {{ user.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="px-3 py-2.5">
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="role in user.roles || []" :key="role.id" class="text-xs px-2 py-1 rounded-md bg-blue-50 text-blue-700 border border-blue-200">
                    {{ role.display_name }}
                  </span>
                </div>
              </td>
              <td class="px-3 py-2.5">
                <button type="button" class="text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-black" @click="openRoleEditor(user)">Editar Roles</button>
                <button type="button" class="text-xs px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50 ml-2" @click="openNeighborhoodEditor(user)">Asignar Barrio</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from '@/services/axios';
import { Search, Loader2, X } from 'lucide-vue-next';

const loading = ref(false);
const loadingNeighborhoods = ref(false);
const errorMessage = ref('');

const users = ref([]);
const roles = ref([]);
const search = ref('');
const communes = ref([]);
const selectedCommune = ref('');
const assignmentContext = ref([]);
const neighborhoodsList = ref([]); // 🔥 VARIABLE LIGERA PARA EL SELECT 🔥

const formPerson = ref({
  document_type_id: '',
  document_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  second_last_name: '',
  neighborhood_id: '',
  is_active: true
});

const createForm = ref({
  person_id: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  roles: [],
});

const editingUser = ref(null);
const editingRoles = ref([]);
const editingNeighborhoodUser = ref(null);
const editingNeighborhoodId = ref('');

// --- LÓGICA DE FILTRADO ---
const filteredUsers = computed(() => {
  if (!search.value) return users.value;
  const term = search.value.toLowerCase();
  return users.value.filter((u) =>
    (u.username || '').toLowerCase().includes(term) ||
    (u.email || '').toLowerCase().includes(term)
  );
});

// --- LÓGICA DE CARGA DE DATOS ---
const loadRoles = async () => {
  const { data } = await axios.get('/admin/roles');
  roles.value = data.data ?? [];
};

const loadUsers = async () => {
  const { data } = await axios.get('/admin/users');
  users.value = data.data?.data ?? data.data ?? [];
};

const loadCommunes = async () => {
  const { data } = await axios.get('/admin/neighborhoods/communes');
  communes.value = data.data ?? [];
};

const loadAssignmentContext = async () => {
  if (!selectedCommune.value) {
    assignmentContext.value = [];
    return;
  }

  const { data } = await axios.get('/admin/users/assignment-context', {
    params: { commune_id: selectedCommune.value },
  });
  assignmentContext.value = Array.isArray(data.data) ? data.data : [];
};

const loadNeighborhoodsForForms = async () => {
  if (!selectedCommune.value) {
    neighborhoodsList.value = [];
    return;
  }

  loadingNeighborhoods.value = true;
  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', {
      params: { commune_id: selectedCommune.value },
    });
    neighborhoodsList.value = data.data ?? [];
  } catch (error) {
    console.error('Error loading neighborhoods:', error);
    throw error;
  } finally {
    loadingNeighborhoods.value = false;
  }
};

const handleCommuneChange = async () => {
  formPerson.value.neighborhood_id = '';

  try {
    await Promise.all([
      loadNeighborhoodsForForms(),
      loadAssignmentContext(),
    ]);
  } catch (error) {
    errorMessage.value = 'No fue posible cargar los barrios de la comuna seleccionada.';
  }
};

const loadAll = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    // 🔥 CARGA TODO SIN CAUSAR TIMEOUT 🔥
    await Promise.all([
      loadCommunes(),
      loadRoles().catch(e => { 
        console.error('Error loading roles:', e?.response?.status, e?.message); 
        throw e; 
      }),
      loadUsers().catch(e => { 
        console.error('Error loading users:', e?.response?.status, e?.message); 
        throw e; 
      }),
    ]);

    if (selectedCommune.value) {
      await Promise.all([
        loadAssignmentContext(),
        loadNeighborhoodsForForms(),
      ]);
    }
  } catch (error) {
    console.error('Full error:', error);
    errorMessage.value = 'No fue posible cargar la información inicial.';
  } finally {
    loading.value = false;
  }
};

// --- LÓGICA DE MESA SUGERIDA ---
const getSuggestedTableByNeighborhood = (neighborhoodId) => {
  const targetId = Number(neighborhoodId || 0);
  if (!targetId) return null;
  const item = assignmentContext.value.find((row) => Number(row.id) === targetId);
  return item?.suggested_polling_table || null;
};

const suggestedTableForUser = (user) => {
  const neighborhoodId = user?.person?.neighborhood_id;
  return getSuggestedTableByNeighborhood(neighborhoodId);
};

const editingSuggestedTable = computed(() => getSuggestedTableByNeighborhood(editingNeighborhoodId.value));

// --- ACCIONES DE PERSONA ---
const submitPerson = async () => {
  if (!formPerson.value.neighborhood_id) {
    alert("Debes seleccionar un barrio.");
    return;
  }
  loading.value = true;
  try {
    await axios.post('/admin/persons', formPerson.value);
    formPerson.value = {
      document_type_id: '', document_number: '', first_name: '', middle_name: '',
      last_name: '', second_last_name: '', neighborhood_id: '', is_active: true
    };
    await loadAll();
    alert('Persona registrada con éxito.');
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'Error al crear persona.';
  } finally {
    loading.value = false;
  }
};

// --- ACCIONES DE USUARIO ---
const createUser = async () => {
  loading.value = true;
  try {
    await axios.post('/admin/users', createForm.value);
    resetCreateForm();
    await loadUsers();
    alert('Usuario creado con éxito.');
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'Error al crear usuario.';
  } finally {
    loading.value = false;
  }
};

const resetCreateForm = () => {
  createForm.value = { person_id: '', username: '', email: '', password: '', password_confirmation: '', roles: [] };
  personSearchQuery.value = '';
  personSearchResults.value = [];
};

// --- BUSCADOR DINÁMICO DE PERSONAS (TYPEAHEAD) ---
const personSearchQuery = ref('');
const personSearchResults = ref([]);
const isPersonDropdownOpen = ref(false);
const isSearchingPerson = ref(false);
const personSearchContainer = ref(null);
let personSearchTimeout = null;

const handlePersonSearch = () => {
  if (personSearchQuery.value.length < 2) {
    personSearchResults.value = [];
    return;
  }
  isSearchingPerson.value = true;
  isPersonDropdownOpen.value = true;
  clearTimeout(personSearchTimeout);
  personSearchTimeout = setTimeout(async () => {
    try {
      const response = await axios.get('/admin/users/search-persons', { params: { q: personSearchQuery.value } });
      if (response.data.success) personSearchResults.value = response.data.data;
    } catch (error) {
      console.error(error);
    } finally {
      isSearchingPerson.value = false;
    }
  }, 300);
};

const selectPerson = (person) => {
  createForm.value.person_id = person.id;
  personSearchQuery.value = person.label;
  isPersonDropdownOpen.value = false;
};

const clearPersonSelection = () => {
  createForm.value.person_id = '';
  personSearchQuery.value = '';
  personSearchResults.value = [];
};

// --- MODALES Y EDITORES ---
const openRoleEditor = (user) => {
  editingUser.value = user;
  editingRoles.value = (user.roles || []).map((r) => r.id);
};

const closeRoleEditor = () => { editingUser.value = null; editingRoles.value = []; };

const openNeighborhoodEditor = (user) => {
  editingNeighborhoodUser.value = user;
  editingNeighborhoodId.value = user?.person?.neighborhood_id ? String(user.person.neighborhood_id) : '';
};

const closeNeighborhoodEditor = () => { editingNeighborhoodUser.value = null; editingNeighborhoodId.value = ''; };

const saveRoles = async () => {
  if (!editingUser.value) return;
  loading.value = true;
  try {
    await axios.put(`/admin/users/${editingUser.value.id}/roles`, { roles: editingRoles.value });
    await loadUsers();
    closeRoleEditor();
  } catch (error) {
    errorMessage.value = 'Error al actualizar roles.';
  } finally {
    loading.value = false;
  }
};

const saveNeighborhood = async () => {
  if (!editingNeighborhoodUser.value) return;
  loading.value = true;
  try {
    await axios.put(`/admin/users/${editingNeighborhoodUser.value.id}/neighborhood`, {
      neighborhood_id: editingNeighborhoodId.value ? Number(editingNeighborhoodId.value) : null,
    });
    await loadUsers();
    closeNeighborhoodEditor();
  } catch (error) {
    errorMessage.value = 'Error al actualizar barrio.';
  } finally {
    loading.value = false;
  }
};

const handleClickOutside = (event) => {
  if (personSearchContainer.value && !personSearchContainer.value.contains(event.target)) {
    isPersonDropdownOpen.value = false;
  }
};

onMounted(() => {
  loadAll();
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>