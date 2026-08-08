<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Plus, Pencil, Trash2, Users, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();
const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;

const props = defineProps<{
    roles: any[];
    permissions: any[];
    modules: string[];
}>();

const groupedPermissions = computed(() =>
    props.modules.map((module) => ({
        module,
        items: props.permissions.filter((p) => p.module === module),
    })),
);

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null as number | null,
    name: '',
    is_active: true,
    permissions: [] as string[],
});

const togglePermission = (key: string) => {
    const index = form.permissions.indexOf(key);
    if (index >= 0) {
        form.permissions.splice(index, 1);
    } else {
        form.permissions.push(key);
    }
};

const toggleModule = (module: string, checked: boolean) => {
    const keys = props.permissions
        .filter((p) => p.module === module)
        .map((p) => p.key);
    if (checked) {
        const set = new Set([...form.permissions, ...keys]);
        form.permissions = [...set];
    } else {
        form.permissions = form.permissions.filter((k) => !keys.includes(k));
    }
};

const moduleChecked = (module: string) => {
    const keys = props.permissions
        .filter((p) => p.module === module)
        .map((p) => p.key);
    return keys.length > 0 && keys.every((k) => form.permissions.includes(k));
};

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.permissions = [];
    showModal.value = true;
};

const openEditModal = (role: any) => {
    isEditing.value = true;
    form.reset();
    form.id = role.id;
    form.name = role.name;
    form.is_active = role.is_active;
    form.permissions = role.permissions?.map((p: any) => p.key) ?? [];
    showModal.value = true;
};

const saveRole = () => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    };

    if (isEditing.value && form.id) {
        form.put('/admin/roles/' + form.id, options);
    } else {
        form.post('/admin/roles', options);
    }
};

// Delete flow with users warning
const deleteTarget = ref<any>(null);
const affectedUsers = ref<any[]>([]);
const loadingUsers = ref(false);

const requestDelete = async (role: any) => {
    deleteTarget.value = role;
    affectedUsers.value = [];
    loadingUsers.value = true;
    try {
        const { data } = await axios.get('/admin/roles/' + role.id + '/users');
        affectedUsers.value = data;
    } finally {
        loadingUsers.value = false;
    }
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;
    const id = deleteTarget.value.id;
    deleteTarget.value = null;
    router.delete('/admin/roles/' + id, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Roles', href: '/admin/roles' }]">
        <Head title="Roles y Permisos" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Roles y Permisos
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Los módulos del sistema se detectan automáticamente
                        desde
                        <code
                            class="rounded bg-gray-100 px-1 py-0.5 dark:bg-zinc-800"
                            >config/permissions.php</code
                        >.
                    </p>
                </div>
                <button
                    v-if="can('roles.manage')"
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4" /> Nuevo Rol
                </button>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800"
                    >
                        <thead
                            class="border-b bg-white dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Rol
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Usuarios
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Permisos
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Estado
                                </th>
                                <th scope="col" class="relative px-6 py-4">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900"
                        >
                            <tr
                                v-for="role in roles"
                                :key="role.id"
                                class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        <ShieldCheck
                                            class="h-4 w-4 text-gray-400"
                                        />
                                        {{ role.name }}
                                        <span
                                            v-if="role.name === 'Administrator'"
                                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                        >
                                            Sistema
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ role.users_count }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="permission in role.permissions.slice(
                                                0,
                                                4,
                                            )"
                                            :key="permission.key"
                                            class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                                        >
                                            {{ permission.label }}
                                        </span>
                                        <span
                                            v-if="role.permissions.length > 4"
                                            class="text-[11px] text-gray-400"
                                        >
                                            +{{ role.permissions.length - 4 }}
                                            más
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="
                                            role.is_active
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'
                                        "
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    >
                                        {{
                                            role.is_active
                                                ? 'Activo'
                                                : 'Inactivo'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <template v-if="can('roles.manage')">
                                            <button
                                                v-if="
                                                    role.name !==
                                                    'Administrator'
                                                "
                                                @click="openEditModal(role)"
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                            <button
                                                v-if="
                                                    role.name !==
                                                    'Administrator'
                                                "
                                                @click="requestDelete(role)"
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create/Edit modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showModal = false"
                ></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle"
                    >&#8203;</span
                >
                <div
                    class="relative inline-block transform overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-8 sm:align-middle dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h3
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ isEditing ? 'Editar Rol' : 'Nuevo Rol' }}
                    </h3>

                    <form @submit.prevent="saveRole">
                        <div
                            v-if="Object.keys(form.errors).length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            <p class="font-medium">
                                Corrige los siguientes errores:
                            </p>
                            <ul class="mt-1 list-inside list-disc">
                                <li
                                    v-for="(message, key) in form.errors"
                                    :key="key"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Nombre *</label
                                    >
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        placeholder="ej. Coordinador"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Estado</label
                                    >
                                    <select
                                        v-model="form.is_active"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    >
                                        <option :value="true">Activo</option>
                                        <option :value="false">Inactivo</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Permisos</label
                                >
                                <div
                                    v-for="group in groupedPermissions"
                                    :key="group.module"
                                    class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-zinc-700 dark:bg-zinc-800"
                                >
                                    <label
                                        class="mb-2 flex cursor-pointer items-center justify-between"
                                    >
                                        <span
                                            class="text-xs font-bold tracking-wide text-gray-700 uppercase dark:text-gray-300"
                                        >
                                            {{ group.module }}
                                        </span>
                                        <input
                                            type="checkbox"
                                            :checked="
                                                moduleChecked(group.module)
                                            "
                                            @change="
                                                toggleModule(
                                                    group.module,
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).checked,
                                                )
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </label>
                                    <div
                                        class="grid grid-cols-1 gap-1.5 sm:grid-cols-2"
                                    >
                                        <label
                                            v-for="permission in group.items"
                                            :key="permission.key"
                                            class="flex cursor-pointer items-center gap-2 text-xs text-gray-600 dark:text-gray-400"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    form.permissions.includes(
                                                        permission.key,
                                                    )
                                                "
                                                @change="
                                                    togglePermission(
                                                        permission.key,
                                                    )
                                                "
                                                class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            {{ permission.label }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <button
                                type="button"
                                @click="showModal = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ isEditing ? 'Guardar' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <div
            v-if="deleteTarget"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="deleteTarget = null"
                ></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle"
                    >&#8203;</span
                >
                <div
                    class="relative inline-block transform overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-8 sm:align-middle dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
                        >
                            <Trash2
                                class="h-5 w-5 text-red-600 dark:text-red-400"
                            />
                        </div>
                        <div>
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                ¿Eliminar el rol {{ deleteTarget.name }}?
                            </h3>
                            <p
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                Esta acción es definitiva y no se puede
                                deshacer.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <p
                            class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            <Users class="h-4 w-4" />
                            {{
                                loadingUsers
                                    ? 'Consultando usuarios…'
                                    : affectedUsers.length +
                                      ' usuarios afectados'
                            }}
                        </p>
                        <ul
                            v-if="affectedUsers.length"
                            class="mt-2 max-h-40 space-y-1 overflow-y-auto"
                        >
                            <li
                                v-for="user in affectedUsers"
                                :key="user.id"
                                class="truncate text-xs text-gray-600 dark:text-gray-400"
                            >
                                {{ user.first_name }} {{ user.last_name }}
                                <span class="text-gray-400"
                                    >({{ user.email }})</span
                                >
                            </li>
                        </ul>
                        <p
                            v-if="affectedUsers.length"
                            class="mt-2 text-xs text-red-500"
                        >
                            Estos usuarios perderán el rol y sus permisos
                            asociados.
                        </p>
                    </div>

                    <div
                        class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800"
                    >
                        <button
                            type="button"
                            @click="deleteTarget = null"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            @click="confirmDelete"
                            class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-red-700"
                        >
                            <Trash2 class="h-4 w-4" /> Eliminar definitivamente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
