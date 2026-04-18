<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    DownloadCloud,
    UploadCloud,
    Plus,
    Search,
    Edit,
    Trash2,
    CheckCircle2,
    X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const getRoleName = (roleId: number | string) => {
    const role = props.roles?.find((r: any) => r.id == roleId);
    return role?.name || 'Sin asignar';
};

interface Unit {
    id: number;
    name: string;
}
interface Department {
    id: number;
    unit_id: number;
    name: string;
}

const photoPreview = ref<string | null>(null);
const profilePhotoInput = ref<HTMLInputElement | null>(null);

const triggerProfilePhotoUpload = () => {
    profilePhotoInput.value?.click();
};

const handleProfilePhotoUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;
    userForm.photo = file as any;
    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
};

const props = defineProps<{
    users: {
        data: any[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    roles: any[];
    stands: any[];
    units: Unit[];
    departments: Department[];
    filters: any;
}>();

// Filtros Reactivos
const formFilters = useForm({
    search: props.filters?.search || '',
    role_id: props.filters?.role_id || '',
    status: props.filters?.status || 'active',
});

// Auto-submit para los filtros (con pequeño retraso)
let searchTimeout: ReturnType<typeof setTimeout>;
watch(
    () => [formFilters.search, formFilters.role_id, formFilters.status],
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            formFilters.get('/users', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
    { deep: true },
);

// Controladores de Modales
const showUserModal = ref(false);
const showResetModal = ref(false);
const isEditing = ref(false);

const userForm = useForm({
    id: null,
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    extension: '',
    tshirt_size: '',
    role_id: '',
    stand_id: '',
    unit_id: '',
    department_id: '',
    custom_unit: '',
    custom_department: '',
    is_active: true,
    photo: null,
    delete_photo: false,
    dni: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    userForm.reset();
    userForm.delete_photo = false;
    photoPreview.value = null;
    showUserModal.value = true;
};

const removePhoto = () => {
    userForm.photo = null;
    userForm.delete_photo = true;
    photoPreview.value = null;
};

const openEditModal = (user: any) => {
    isEditing.value = true;
    userForm.reset();
    userForm.id = user.id;

    userForm.first_name = user.first_name || '';
    userForm.last_name = user.last_name || '';

    userForm.email = user.email || '';
    userForm.phone = user.phone || '';
    userForm.extension = user.extension || '';
    userForm.tshirt_size = user.tshirt_size || '';
    userForm.role_id = user.role_id || '';
    userForm.stand_id = user.stand_id || '';
    userForm.unit_id = user.unit_id || (user.custom_unit ? 'other' : '');
    userForm.department_id =
        user.department_id || (user.custom_department ? 'other' : '');
    userForm.custom_unit = user.custom_unit || '';
    userForm.custom_department = user.custom_department || '';
    userForm.is_active = !!user.is_active;
    userForm.delete_photo = false;
    userForm.dni = user.dni || '';
    photoPreview.value = user.profile_photo_path
        ? '/storage/' + user.profile_photo_path
        : null;

    showUserModal.value = true;
};

const saveUser = () => {
    const data: any = { ...userForm.data() };
    if (data.unit_id === 'other') data.unit_id = null;
    if (data.department_id === 'other') data.department_id = null;

    if (isEditing.value) {
        data._method = 'put';
        router.post('/users/' + userForm.id, data, {
            onSuccess: () => {
                showUserModal.value = false;
                userForm.reset();
                photoPreview.value = null;
            },
        });
    } else {
        router.post('/users', data, {
            onSuccess: () => {
                showUserModal.value = false;
                userForm.reset();
                photoPreview.value = null;
            },
        });
    }
};

const deleteUser = (id: number) => {
    if (confirm('¿Estás seguro de desactivar este usuario?')) {
        router.delete('/users/' + id, {
            preserveScroll: true,
        });
    }
};

const sendResetPassword = () => {
    router.post(
        '/users/' + userForm.id + '/reset-password',
        {},
        {
            onSuccess: () => {
                showResetModal.value = true;
            },
        },
    );
};

const confirmForceDelete = (user: any) => {
    if (
        confirm(
            `¿Estás seguro de eliminar a ${user.name} PERMANENTEMENTE? Esta acción no se puede deshacer y borrará todos sus datos del sistema.`,
        )
    ) {
        router.delete(`/users/${user.id}/force-delete`, {
            onSuccess: () => alert('Usuario eliminado de forma definitiva.'),
        });
    }
};

// Carga CSV Múltiple
const fileInput = ref<HTMLInputElement | null>(null);
const triggerFileUpload = () => {
    fileInput.value?.click();
};
const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;
    const form = useForm({ csv_file: file });
    form.post('/users/import/csv', {
        onSuccess: () => {
            alert('¡Carga de plantilla procesada exitosamente!');
            target.value = '';
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Usuarios', href: '/users' }]">
        <Head title="Administrador de usuarios" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Administrador de usuarios
            </h1>

            <div
                class="mt-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end"
            >
                <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                    <div class="flex flex-col">
                        <label
                            class="text-[11px] font-medium tracking-wide text-gray-600 uppercase dark:text-gray-400"
                            >Nombre / Correo / DNI</label
                        >
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="absolute top-[11px] left-3 h-4 w-4 text-gray-500"
                            />
                            <input
                                v-model="formFilters.search"
                                type="text"
                                class="w-full rounded-md border border-gray-300 py-2 pr-4 pl-9 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-white dark:focus:ring-white"
                                placeholder="Buscar"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label
                            class="text-[11px] font-medium tracking-wide text-gray-600 uppercase dark:text-gray-400"
                            >Perfil de usuario</label
                        >
                        <select
                            v-model="formFilters.role_id"
                            class="w-full rounded-md border border-gray-300 py-2 pr-8 pl-3 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:w-48 sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-white dark:focus:ring-white"
                        >
                            <option value="">Todo</option>
                            <option
                                v-for="role in roles"
                                :key="role.id"
                                :value="role.id"
                            >
                                {{ role.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3 self-start xl:self-auto">
                    <input
                        type="file"
                        ref="fileInput"
                        class="hidden"
                        accept=".csv"
                        @change="handleFileUpload"
                    />

                    <button
                        @click="triggerFileUpload"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50"
                    >
                        <UploadCloud class="h-4 w-4" /> Multiple
                    </button>

                    <a
                        href="/users/export/csv"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50"
                    >
                        <DownloadCloud class="h-4 w-4" /> CSV
                    </a>

                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                    >
                        <Plus class="h-4 w-4" /> Añadir
                    </button>
                </div>
            </div>

            <div
                class="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="border-b border-gray-200">
                    <nav class="flex px-4" aria-label="Tabs">
                        <button
                            @click="formFilters.status = 'active'"
                            :class="[
                                formFilters.status === 'active'
                                    ? 'border-black text-black'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                                'border-b-2 px-6 py-3 text-sm font-medium whitespace-nowrap transition-colors duration-200',
                            ]"
                        >
                            Activos
                            <span class="ml-1 text-xs"
                                >({{
                                    formFilters.status === 'active'
                                        ? users?.total || 0
                                        : '--'
                                }})</span
                            >
                        </button>
                        <button
                            @click="formFilters.status = 'inactive'"
                            :class="[
                                formFilters.status === 'inactive'
                                    ? 'border-black text-black'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                                'border-b-2 px-6 py-3 text-sm font-medium whitespace-nowrap transition-colors duration-200',
                            ]"
                        >
                            Inactivos
                            <span class="ml-1 text-xs"
                                >({{
                                    formFilters.status === 'inactive'
                                        ? users?.total || 0
                                        : '--'
                                }})</span
                            >
                        </button>
                    </nav>
                </div>

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
                                    Nombre
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Perfil
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Unidad / Depto
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Stand
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Playera
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    DNI
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Status
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
                                v-for="user in users.data"
                                :key="user.id"
                                class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="flex h-[38px] w-[38px] flex-shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-300 bg-white dark:border-zinc-700 dark:bg-zinc-800"
                                        >
                                            <img
                                                v-if="user.profile_photo_path"
                                                :src="
                                                    '/storage/' +
                                                    user.profile_photo_path
                                                "
                                                class="h-full w-full object-cover"
                                            />
                                            <svg
                                                v-else
                                                class="h-6 w-6 text-gray-600 dark:text-gray-400"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                />
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <div
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ user.first_name }}
                                                {{ user.last_name }}
                                            </div>
                                            <div
                                                class="text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                {{ user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-gray-600 dark:text-gray-400"
                                >
                                    {{ user.role?.name || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-gray-600 dark:text-gray-400"
                                >
                                    <div class="flex flex-col">
                                        <span>{{
                                            user.unit?.name ||
                                            user.custom_unit ||
                                            '-'
                                        }}</span>
                                        <span
                                            class="text-[10px] text-gray-400 dark:text-zinc-600"
                                            >{{
                                                user.department?.name ||
                                                user.custom_department ||
                                                '-'
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-gray-600 dark:text-gray-400"
                                >
                                    {{ user.stand?.name || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-gray-600 dark:text-gray-400"
                                >
                                    {{ user.tshirt_size || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{ user.dni }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-gray-200"
                                >
                                    {{ user.is_active ? 'Activo' : 'Inactivo' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <button
                                            @click="openEditModal(user)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="user.is_active"
                                            @click="deleteUser(user.id)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-else
                                            @click="confirmForceDelete(user)"
                                            class="rounded border border-red-200 bg-red-50 p-1.5 text-red-600 shadow-sm transition-colors hover:text-red-900 dark:border-red-900 dark:bg-red-900/20 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-if="
                                    !users ||
                                    !users.data ||
                                    users.data.length === 0
                                "
                            >
                                <td
                                    colspan="8"
                                    class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No se encontraron registros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800"
                    v-if="users.total > 0"
                >
                    <Link
                        v-if="users.prev_page_url"
                        :href="users.prev_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Anterior</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Anterior</span
                    >

                    <Link
                        v-if="users.next_page_url"
                        :href="users.next_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Siguiente</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Siguiente</span
                    >
                </div>
            </div>
        </div>

        <div
            v-if="showUserModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showUserModal = false"
                ></div>

                <span
                    class="hidden sm:inline-block sm:h-screen sm:align-middle"
                    aria-hidden="true"
                    >&#8203;</span
                >

                <div
                    class="relative inline-block transform overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-8 sm:align-middle dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div
                        class="mb-6 grid grid-cols-[auto_1fr_auto] items-center gap-4 sm:grid-cols-[auto_1fr_auto] sm:gap-6"
                    >
                        <!-- Columna 1: Avatar -->
                        <div
                            class="group relative order-1 flex-shrink-0 cursor-pointer"
                            @click="triggerProfilePhotoUpload"
                        >
                            <input
                                type="file"
                                ref="profilePhotoInput"
                                class="hidden"
                                accept="image/*"
                                @change="handleProfilePhotoUpload"
                            />
                            <div
                                class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border-2 border-gray-200 bg-gray-50 sm:h-24 sm:w-24 dark:border-zinc-600 dark:bg-zinc-800"
                            >
                                <img
                                    v-if="photoPreview"
                                    :src="photoPreview"
                                    class="h-full w-full object-cover"
                                />
                                <svg
                                    v-else
                                    class="h-8 w-8 text-gray-400 sm:h-12 sm:w-12 dark:text-zinc-500"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                            </div>
                            <div
                                class="absolute -right-1 -bottom-1 flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 bg-white transition-colors group-hover:bg-gray-100 sm:right-0 sm:bottom-0 sm:h-7 sm:w-7 dark:border-zinc-600 dark:bg-zinc-700 dark:group-hover:bg-zinc-600"
                            >
                                <svg
                                    class="h-2.5 w-2.5 text-gray-600 sm:h-3.5 sm:w-3.5 dark:text-gray-300"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                            </div>
                            <button
                                v-if="photoPreview"
                                @click.stop.prevent="removePhoto"
                                class="absolute -right-1 -bottom-1 flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-100 sm:right-0 sm:bottom-0 sm:h-7 sm:w-7 dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-600"
                                title="Quitar foto"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Columna 2: Datos centrales -->
                        <div
                            class="order-2 flex min-w-0 flex-col justify-center text-left"
                        >
                            <div
                                v-if="userForm.first_name || userForm.last_name"
                                class="text-base font-semibold text-gray-900 sm:text-lg dark:text-white"
                            >
                                {{ userForm.first_name }}
                                {{ userForm.last_name }}
                            </div>
                            <div
                                v-else
                                class="text-base font-medium text-gray-400 sm:text-lg dark:text-zinc-500"
                            >
                                Nuevo usuario
                            </div>
                            <div
                                v-if="userForm.email"
                                class="text-xs text-gray-500 sm:text-sm dark:text-zinc-400"
                            >
                                {{ userForm.email }}
                            </div>
                            <div v-if="userForm.role_id" class="mt-1">
                                <span
                                    class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                                >
                                    {{ getRoleName(userForm.role_id) }}
                                </span>
                            </div>
                            <div
                                v-else
                                class="text-xs text-gray-400 dark:text-zinc-500"
                            >
                                Sin asignar perfil
                            </div>
                        </div>

                        <!-- Columna 3: DNI -->
                        <div class="order-3 flex-shrink-0 text-right">
                            <span
                                v-if="userForm.dni"
                                class="inline-flex items-center gap-1 rounded-lg border-2 border-indigo-200 bg-indigo-50 px-2 py-1 font-mono text-base font-bold text-indigo-700 sm:gap-2 sm:px-4 sm:py-2 sm:text-lg dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                            >
                                <svg
                                    class="h-4 w-4 sm:h-5 sm:w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-4 0v5a2 2 0 104 0m4 0V5a2 2 0 114 0v1"
                                    />
                                </svg>
                                {{ userForm.dni }}
                            </span>
                            <span
                                v-else
                                class="text-sm text-gray-400 dark:text-zinc-500"
                            >
                                Sin DNI
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="saveUser">
                        <div class="space-y-6">
                            <div>
                                <h4
                                    class="mb-4 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-zinc-400"
                                >
                                    Datos personales
                                </h4>
                                <div
                                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Nombre(s) *</label
                                        >
                                        <input
                                            v-model="userForm.first_name"
                                            type="text"
                                            required
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                            placeholder="Jhon"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Apellido(s) *</label
                                        >
                                        <input
                                            v-model="userForm.last_name"
                                            type="text"
                                            required
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                            placeholder="Due"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Correo *</label
                                        >
                                        <input
                                            v-model="userForm.email"
                                            type="email"
                                            required
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                            placeholder="jhon@mail.com"
                                        />
                                        <p
                                            class="mt-1 text-xs text-red-500"
                                            v-if="userForm.errors.email"
                                        >
                                            {{ userForm.errors.email }}
                                        </p>
                                    </div>
                                    <div class="flex gap-3">
                                        <div class="flex-1">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Teléfono</label
                                            >
                                            <input
                                                v-model="userForm.phone"
                                                type="text"
                                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                                placeholder="+52 55..."
                                            />
                                        </div>
                                        <div class="w-16">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Ext.</label
                                            >
                                            <input
                                                v-model="userForm.extension"
                                                type="text"
                                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                                placeholder="6062"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4
                                    class="mb-4 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-zinc-400"
                                >
                                    Área
                                </h4>
                                <div
                                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Unidad de adscripción</label
                                        >
                                        <select
                                            v-model="userForm.unit_id"
                                            required
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                        >
                                            <option value="">
                                                Seleccionar
                                            </option>
                                            <option
                                                v-for="unit in units"
                                                :key="unit.id"
                                                :value="unit.id"
                                            >
                                                {{ unit.name }}
                                            </option>
                                            <option value="other">Otra</option>
                                        </select>
                                        <input
                                            v-if="userForm.unit_id === 'other'"
                                            v-model="userForm.custom_unit"
                                            type="text"
                                            required
                                            class="mt-2 w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                            placeholder="Especifique"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Departamento</label
                                        >
                                        <select
                                            v-model="userForm.department_id"
                                            required
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                        >
                                            <option value="">
                                                Seleccionar
                                            </option>
                                            <template
                                                v-if="
                                                    userForm.unit_id &&
                                                    userForm.unit_id !== 'other'
                                                "
                                            >
                                                <option
                                                    v-for="dept in (
                                                        departments || []
                                                    ).filter(
                                                        (d) =>
                                                            d.unit_id?.toString() ==
                                                            userForm.unit_id.toString(),
                                                    )"
                                                    :key="dept.id"
                                                    :value="dept.id"
                                                >
                                                    {{ dept.name }}
                                                </option>
                                            </template>
                                            <option value="other">Otro</option>
                                        </select>
                                        <input
                                            v-if="
                                                userForm.department_id ===
                                                'other'
                                            "
                                            v-model="userForm.custom_department"
                                            type="text"
                                            required
                                            class="mt-2 w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                            placeholder="Especifique"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4
                                    class="mb-4 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-zinc-400"
                                >
                                    Asignación
                                </h4>
                                <div
                                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Tamaño playera</label
                                        >
                                        <select
                                            v-model="userForm.tshirt_size"
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                        >
                                            <option value="">
                                                Seleccionar
                                            </option>
                                            <option value="S">CH (S)</option>
                                            <option value="M">M</option>
                                            <option value="L">G (L)</option>
                                            <option value="XL">XL</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Perfil *</label
                                        >
                                        <select
                                            v-model="userForm.role_id"
                                            required
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                        >
                                            <option value="">
                                                Seleccionar
                                            </option>
                                            <option
                                                v-for="role in roles"
                                                :key="role.id"
                                                :value="role.id"
                                            >
                                                {{ role.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Stand</label
                                        >
                                        <select
                                            v-model="userForm.stand_id"
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                        >
                                            <option value="">
                                                Seleccionar
                                            </option>
                                            <option
                                                v-for="stand in stands || []"
                                                :key="stand.id"
                                                :value="stand.id"
                                            >
                                                {{ stand.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="isEditing">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Status</label
                                        >
                                        <select
                                            v-model="userForm.is_active"
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-indigo-500"
                                        >
                                            <option :value="true">
                                                Activo
                                            </option>
                                            <option :value="false">
                                                Inactivo
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

<div
                            class="mt-6 sm:mt-8 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <button
                                type="button"
                                @click="showUserModal = false"
                                class="w-full sm:w-auto order-1 sm:order-none rounded-md border border-red-200 bg-red-50 px-3 py-2 sm:px-4 text-sm font-medium text-red-600 transition-colors hover:bg-red-100 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30"
                            >
                                Cancelar
                            </button>
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 order-2 sm:order-none">
                                <button
                                    v-if="isEditing"
                                    type="button"
                                    @click.prevent="sendResetPassword"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                                        />
                                    </svg>
                                    <span class="hidden sm:inline">Restablecer</span>
                                    <span class="sm:hidden">🔑</span>
                                </button>
                                <button
                                    type="submit"
                                    :disabled="userForm.processing"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                                >
                                    <Plus v-if="!isEditing" class="h-4 w-4" />
                                    <svg
                                        v-else
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"
                                        />
                                        <path
                                            d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"
                                        />
                                        <path d="M7 3v4a1 1 0 0 0 1 1h7" /></svg>
                                    <span v-if="!isEditing">Añadir</span>
                                    <span v-else">Guardar</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showResetModal"
            class="fixed inset-0 z-[60] overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-center justify-center px-4 text-center sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showResetModal = false"
                ></div>

                <div
                    class="relative z-10 inline-block transform overflow-hidden rounded-3xl border bg-white p-10 text-center text-left shadow-2xl transition-all sm:w-full sm:max-w-md dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <button
                        @click="showResetModal = false"
                        class="absolute top-5 right-5 text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>

                    <div
                        class="mx-auto mb-6 flex h-[70px] w-[70px] items-center justify-center rounded-full border border-gray-400 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <CheckCircle2
                            class="h-[34px] w-[34px] text-gray-800 dark:text-gray-200"
                        />
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3
                            class="text-[17px] font-medium text-gray-900 dark:text-white"
                            id="modal-title"
                        >
                            Se envió un correo al usuario para<br />restablecer
                            su contraseña
                        </h3>
                    </div>
                    <div class="mt-8 flex justify-center">
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-6 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            @click="showResetModal = false"
                        >
                            Cerrar mensaje
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
