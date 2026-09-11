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
                <div class="flex flex-wrap gap-2">
                  <button type="button" class="text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-black" @click="openRoleEditor(user)">Editar Roles</button>
                  <button type="button" class="text-xs px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50" @click="openUserEditor(user)">Editar Usuario</button>
                  <button type="button" class="text-xs px-3 py-1.5 rounded-md border border-amber-200 text-amber-700 hover:bg-amber-50" @click="openPasswordReset(user)">Restablecer Contraseña</button>
                  <button
                    type="button"
                    class="text-xs px-3 py-1.5 rounded-md border"
                    :class="user.is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                    @click="toggleUserStatus(user)"
                  >
                    {{ user.is_active ? 'Deshabilitar' : 'Habilitar' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="editingUser" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Editar Roles</p>
              <h2 class="mt-1 text-lg font-bold text-gray-900">{{ editingUser.username }}</h2>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700" @click="closeRoleEditor">
              <X class="w-5 h-5" />
            </button>
          </div>

          <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

          <div class="px-6 py-5 space-y-2 max-h-72 overflow-y-auto">
            <label
              v-for="role in roles"
              :key="role.id"
              class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
            >
              <input type="checkbox" :value="role.id" v-model="editingRoles" class="border-gray-300 text-aso-primary focus:ring-aso-primary" />
              <span>{{ role.display_name }} <span class="text-xs text-gray-400">({{ role.name }})</span></span>
            </label>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="closeRoleEditor">
              Cancelar
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark transition-colors disabled:opacity-60"
              :disabled="loading || editingRoles.length === 0"
              @click="saveRoles"
            >
              Guardar
            </button>
          </div>
        </div>
      </div>

      <div v-if="editingUserData" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Editar Usuario</p>
              <h2 class="mt-1 text-lg font-bold text-gray-900">{{ editingUserData.username }}</h2>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700" @click="closeUserEditor">
              <X class="w-5 h-5" />
            </button>
          </div>

          <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

          <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Datos de la persona</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Tipo de Doc.</label>
                  <select v-model="editingUserForm.document_type_id" required class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
                    <option value="" disabled>Seleccione...</option>
                    <option value="1">Cédula de Ciudadanía (CC)</option>
                    <option value="2">Tarjeta de Identidad (TI)</option>
                    <option value="3">Cédula de Extranjería (CE)</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Número de Doc.</label>
                  <input v-model="editingUserForm.document_number" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Primer Nombre</label>
                  <input v-model="editingUserForm.first_name" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Segundo Nombre</label>
                  <input v-model="editingUserForm.middle_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Primer Apellido</label>
                  <input v-model="editingUserForm.last_name" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Segundo Apellido</label>
                  <input v-model="editingUserForm.second_last_name" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" placeholder="Opcional" />
                </div>
              </div>
            </div>

            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Ubicación</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Comuna</label>
                  <select v-model="editorSelectedCommune" @change="handleEditorCommuneChange" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm">
                    <option value="">Seleccione una comuna...</option>
                    <option v-for="item in communes" :key="item.id" :value="String(item.id)">
                      {{ item.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Barrio</label>
                  <div class="relative">
                    <select
                      v-model="editingUserForm.neighborhood_id"
                      :disabled="!editorSelectedCommune || loadingEditorNeighborhoods"
                      class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm disabled:opacity-60"
                    >
                      <option value="">{{ editorSelectedCommune ? 'Sin asignar' : 'Primero seleccione una comuna...' }}</option>
                      <option v-for="item in editorNeighborhoods" :key="item.id" :value="String(item.id)">
                        {{ item.name }}
                      </option>
                    </select>
                    <Loader2 v-if="loadingEditorNeighborhoods" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-aso-primary animate-spin" />
                  </div>
                  <p v-if="loadingEditorNeighborhoods" class="text-xs text-gray-400 mt-1">Cargando barrios...</p>
                </div>
              </div>
            </div>

            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Cuenta</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Usuario</label>
                  <input v-model="editingUserForm.username" required type="text" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
                </div>
                <div>
                  <label class="block text-sm text-gray-700 mb-1">Correo</label>
                  <input v-model="editingUserForm.email" required type="email" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm" />
                </div>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="closeUserEditor">
              Cancelar
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-aso-primary hover:bg-aso-primary-dark transition-colors disabled:opacity-60"
              :disabled="loading || !editingUserForm.document_type_id || !editingUserForm.document_number || !editingUserForm.first_name || !editingUserForm.last_name || !editingUserForm.username || !editingUserForm.email"
              @click="saveUserData"
            >
              Guardar
            </button>
          </div>
        </div>
      </div>

      <div v-if="resettingUser" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Restablecer Contraseña</p>
              <h2 class="mt-1 text-lg font-bold text-gray-900">{{ resettingUser.username }}</h2>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700" @click="closePasswordReset">
              <X class="w-5 h-5" />
            </button>
          </div>

          <div v-if="modalError" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">{{ modalError }}</div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Nueva contraseña</label>
              <div class="relative">
                <input
                  v-model="passwordForm.password"
                  required
                  :type="showPassword ? 'text' : 'password'"
                  class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
                  :class="passwordTooShort ? 'border-red-300' : ''"
                />
                <button
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                  @click="showPassword = !showPassword"
                  tabindex="-1"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <p v-if="passwordTooShort" class="text-xs text-red-600 mt-1">La contraseña debe tener al menos 8 caracteres.</p>
              <p v-else-if="passwordForm.password.length >= 8" class="text-xs text-emerald-600 mt-1">Longitud válida.</p>
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Confirmar contraseña</label>
              <div class="relative">
                <input
                  v-model="passwordForm.password_confirmation"
                  required
                  :type="showPasswordConfirm ? 'text' : 'password'"
                  class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-200 bg-white text-sm"
                  :class="passwordMismatch ? 'border-red-300' : ''"
                />
                <button
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                  @click="showPasswordConfirm = !showPasswordConfirm"
                  tabindex="-1"
                >
                  <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <p v-if="passwordMismatch" class="text-xs text-red-600 mt-1">Las contraseñas no coinciden.</p>
              <p v-else-if="passwordForm.password_confirmation.length > 0" class="text-xs text-emerald-600 mt-1">Las contraseñas coinciden.</p>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-white transition-colors" @click="closePasswordReset">
              Cancelar
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 transition-colors disabled:opacity-60"
              :disabled="loading || passwordForm.password.length < 8 || passwordForm.password !== passwordForm.password_confirmation"
              @click="saveNewPassword"
            >
              Restablecer
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from '@/services/axios';
import { Search, Loader2, X, Eye, EyeOff } from 'lucide-vue-next';

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

const modalError = ref('');
const editingUser = ref(null);
const editingRoles = ref([]);
const editorSelectedCommune = ref('');
const editorNeighborhoods = ref([]);
const loadingEditorNeighborhoods = ref(false);
const editingUserData = ref(null);
const editingUserForm = ref({
  document_type_id: '',
  document_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  second_last_name: '',
  neighborhood_id: '',
  username: '',
  email: '',
});
const resettingUser = ref(null);
const passwordForm = ref({ password: '', password_confirmation: '' });
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const passwordTooShort = computed(() => passwordForm.value.password.length > 0 && passwordForm.value.password.length < 8);
const passwordMismatch = computed(() => passwordForm.value.password_confirmation.length > 0 && passwordForm.value.password !== passwordForm.value.password_confirmation);

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
  const { data } = await axios.get('/admin/roles', { skipGlobalLoading: true });
  roles.value = data.data ?? [];
};

const loadUsers = async () => {
  const { data } = await axios.get('/admin/users', { skipGlobalLoading: true });
  users.value = data.data?.data ?? data.data ?? [];
};

const loadCommunes = async () => {
  const { data } = await axios.get('/admin/neighborhoods/communes', { skipGlobalLoading: true });
  communes.value = data.data ?? [];
};

const loadAssignmentContext = async () => {
  if (!selectedCommune.value) {
    assignmentContext.value = [];
    return;
  }

  const { data } = await axios.get('/admin/users/assignment-context', {
    params: { commune_id: selectedCommune.value },
    skipGlobalLoading: true,
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
      skipGlobalLoading: true,
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
      const response = await axios.get('/admin/users/search-persons', {
        params: { q: personSearchQuery.value },
        skipGlobalLoading: true,
      });
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
  modalError.value = '';
  editingUser.value = user;
  editingRoles.value = (user.roles || []).map((role) => role.id);
};

const closeRoleEditor = () => { editingUser.value = null; editingRoles.value = []; modalError.value = ''; };

const loadEditorNeighborhoods = async (communeId) => {
  if (!communeId) {
    editorNeighborhoods.value = [];
    return;
  }

  loadingEditorNeighborhoods.value = true;
  try {
    const { data } = await axios.get('/admin/neighborhoods/list-for-forms', {
      params: { commune_id: communeId },
      skipGlobalLoading: true,
    });
    editorNeighborhoods.value = data.data ?? [];
  } catch (error) {
    console.error('Error loading neighborhoods for editor:', error);
  } finally {
    loadingEditorNeighborhoods.value = false;
  }
};

const handleEditorCommuneChange = () => {
  editingUserForm.value.neighborhood_id = '';
  loadEditorNeighborhoods(editorSelectedCommune.value);
};

const saveRoles = async () => {
  if (!editingUser.value || editingRoles.value.length === 0) return;
  loading.value = true;
  modalError.value = '';
  try {
    await axios.put(`/admin/users/${editingUser.value.id}/roles`, { roles: editingRoles.value });
    await loadUsers();
  } catch (error) {
    modalError.value = error?.response?.data?.message || 'Error al actualizar roles.';
    return;
  } finally {
    loading.value = false;
  }
  closeRoleEditor();
};

const emptyUserForm = () => ({
  document_type_id: '',
  document_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  second_last_name: '',
  neighborhood_id: '',
  username: '',
  email: '',
});

const openUserEditor = async (user) => {
  modalError.value = '';
  editingUserData.value = user;
  const person = user.person ?? null;
  const currentNeighborhood = person?.neighborhood ?? null;

  editingUserForm.value = {
    document_type_id: person?.document_type_id ? String(person.document_type_id) : '',
    document_number: person?.document_number ?? '',
    first_name: person?.first_name ?? '',
    middle_name: person?.middle_name ?? '',
    last_name: person?.last_name ?? '',
    second_last_name: person?.second_last_name ?? '',
    neighborhood_id: currentNeighborhood?.id ? String(currentNeighborhood.id) : '',
    username: user.username,
    email: user.email,
  };

  editorSelectedCommune.value = currentNeighborhood?.commune_id ? String(currentNeighborhood.commune_id) : '';
  await loadEditorNeighborhoods(editorSelectedCommune.value);

  // Garantiza que el barrio actual del usuario aparezca en el select aunque
  // no esté entre los primeros resultados devueltos por el backend.
  if (currentNeighborhood && !editorNeighborhoods.value.some((item) => Number(item.id) === Number(currentNeighborhood.id))) {
    editorNeighborhoods.value = [{ id: currentNeighborhood.id, name: currentNeighborhood.name }, ...editorNeighborhoods.value];
  }
};

const closeUserEditor = () => {
  editingUserData.value = null;
  editingUserForm.value = emptyUserForm();
  editorSelectedCommune.value = '';
  editorNeighborhoods.value = [];
  modalError.value = '';
};

const saveUserData = async () => {
  if (!editingUserData.value) return;
  loading.value = true;
  modalError.value = '';
  try {
    const form = editingUserForm.value;
    await axios.put(`/admin/users/${editingUserData.value.id}`, {
      document_type_id: Number(form.document_type_id),
      document_number: form.document_number,
      first_name: form.first_name,
      middle_name: form.middle_name || null,
      last_name: form.last_name,
      second_last_name: form.second_last_name || null,
      neighborhood_id: form.neighborhood_id ? Number(form.neighborhood_id) : null,
      username: form.username,
      email: form.email,
    });
    await loadUsers();
  } catch (error) {
    modalError.value = error?.response?.data?.message || 'Error al actualizar usuario.';
    return;
  } finally {
    loading.value = false;
  }
  closeUserEditor();
};

const toggleUserStatus = async (user) => {
  const action = user.is_active ? 'deshabilitar' : 'habilitar';
  if (!confirm(`¿Seguro que deseas ${action} a "${user.username}"?`)) return;

  loading.value = true;
  errorMessage.value = '';
  try {
    await axios.patch(`/admin/users/${user.id}/toggle-active`);
    await loadUsers();
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'Error al cambiar el estado del usuario.';
  } finally {
    loading.value = false;
  }
};

const openPasswordReset = (user) => {
  modalError.value = '';
  resettingUser.value = user;
  passwordForm.value = { password: '', password_confirmation: '' };
  showPassword.value = false;
  showPasswordConfirm.value = false;
};

const closePasswordReset = () => {
  resettingUser.value = null;
  passwordForm.value = { password: '', password_confirmation: '' };
  showPassword.value = false;
  showPasswordConfirm.value = false;
  modalError.value = '';
};

const saveNewPassword = async () => {
  if (!resettingUser.value) return;
  loading.value = true;
  modalError.value = '';
  try {
    await axios.post(`/admin/users/${resettingUser.value.id}/reset-password`, passwordForm.value);
    closePasswordReset();
    alert('Contraseña restablecida con éxito.');
  } catch (error) {
    modalError.value = error?.response?.data?.message || 'Error al restablecer la contraseña.';
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