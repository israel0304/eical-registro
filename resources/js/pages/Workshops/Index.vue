<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Search, Plus, Edit, Trash2, Eye, UserPlus } from 'lucide-vue-next';
import { ref, watch, computed, reactive } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();
const hasRole = (name: string) =>
    page.props.auth.user?.roles?.some((r: any) => r.name === name) ?? false;
const isAdmin = computed(() => hasRole('Administrator'));

const props = defineProps<{
    workshops: {
        data: any[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: any;
    enrollments?: number[];
}>();

const formFilters = useForm({
    search: props.filters?.search || '',
});

let searchTimeout: ReturnType<typeof setTimeout>;
watch(
    () => formFilters.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            formFilters.get('/workshops', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
);

const showModal = ref(false);
const isEditing = ref(false);

interface InstructorForm {
    first_name: string;
    last_name: string;
    affiliation: string;
    email: string;
}

const form = useForm({
    id: null as number | null,
    name: '',
    description: '',
    capacity: 30,
    location: '',
    day: '',
    start_time: '',
    end_time: '',
    qr_time_restricted: true,
    instructors: [] as InstructorForm[],
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.instructors = [];
    showModal.value = true;
};

const openEditModal = (workshop: any) => {
    isEditing.value = true;
    form.reset();
    form.id = workshop.id;
    form.name = workshop.name || '';
    form.description = workshop.description || '';
    form.capacity = workshop.capacity || 30;
    form.location = workshop.location || '';
    form.day = workshop.day || '';
    form.start_time = workshop.start_time ? workshop.start_time.slice(0, 5) : '';
    form.end_time = workshop.end_time ? workshop.end_time.slice(0, 5) : '';
    form.qr_time_restricted = workshop.qr_time_restricted ?? true;
    form.instructors = workshop.instructors?.length
        ? workshop.instructors.map((i: any) => ({
              first_name: i.first_name || '',
              last_name: i.last_name || '',
              affiliation: i.affiliation || '',
              email: i.email || '',
          }))
        : [];
    showModal.value = true;
};

const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const showSearchResults = ref(false);
const searching = ref(false);
let searchInstructorTimeout: ReturnType<typeof setTimeout>;

const searchInstructors = (query: string) => {
    searchQuery.value = query;
    clearTimeout(searchInstructorTimeout);
    if (!query.trim()) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }
    searching.value = true;
    searchInstructorTimeout = setTimeout(async () => {
        try {
            const res = await axios.get('/api/instructores', {
                params: { search: query },
            });
            searchResults.value = res.data.filter(
                (u: any) => !form.instructors.some((i) => i.email === u.email),
            );
            showSearchResults.value =
                searchResults.value.length > 0 || query.trim().length > 0;
        } catch {
            searchResults.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
};

const selectInstructor = (user: any) => {
    if (form.instructors.length >= 5) return;
    if (form.instructors.some((i) => i.email === user.email)) return;
    form.instructors.push({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        affiliation: user.affiliation || '',
        email: user.email,
    });
    searchQuery.value = '';
    searchResults.value = [];
    showSearchResults.value = false;
};

const clickOutsideSearch = () => {
    showSearchResults.value = false;
};

const showNewInstructorModal = ref(false);
const newInstructor = reactive({
    first_name: '',
    last_name: '',
    affiliation: '',
    email: '',
});

const resetNewInstructor = () => {
    newInstructor.first_name = '';
    newInstructor.last_name = '';
    newInstructor.affiliation = '';
    newInstructor.email = '';
};

const openNewInstructorModal = () => {
    resetNewInstructor();
    showNewInstructorModal.value = true;
};

const addNewInstructor = () => {
    if (form.instructors.length >= 5) return;
    if (
        !newInstructor.first_name.trim() ||
        !newInstructor.last_name.trim() ||
        !newInstructor.email.trim()
    ) {
        return;
    }
    form.instructors.push({
        first_name: newInstructor.first_name.trim(),
        last_name: newInstructor.last_name.trim(),
        affiliation: newInstructor.affiliation.trim(),
        email: newInstructor.email.trim(),
    });
    showNewInstructorModal.value = false;
    resetNewInstructor();
};

const removeInstructor = (index: number) => {
    form.instructors.splice(index, 1);
};

const saveWorkshop = () => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    };

    if (isEditing.value && form.id) {
        form.put('/workshops/' + form.id, options);
    } else {
        form.post('/workshops', options);
    }
};

const deleteWorkshop = (id: number) => {
    if (confirm('¿Estás seguro de eliminar este taller?')) {
        router.delete('/workshops/' + id, {
            preserveScroll: true,
        });
    }
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Talleres', href: '/workshops' }]">
        <Head title="Talleres" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Talleres
            </h1>

            <div
                class="mt-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end"
            >
                <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                    <div class="flex flex-col">
                        <label
                            class="text-[11px] font-medium tracking-wide text-gray-600 uppercase dark:text-gray-400"
                        >
                            Buscar
                        </label>
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="absolute top-[11px] left-3 h-4 w-4 text-gray-500"
                            />
                            <input
                                v-model="formFilters.search"
                                type="text"
                                class="w-full rounded-md border border-gray-300 py-2 pr-4 pl-9 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-white dark:focus:ring-white"
                                placeholder="Nombre del taller"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="isAdmin"
                    class="flex items-center gap-3 self-start xl:self-auto"
                >
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                    >
                        <Plus class="h-4 w-4" /> Nuevo Taller
                    </button>
                </div>
            </div>

            <div
                class="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
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
                                    Taller
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Instructor
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Horario
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Lugar
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Cupos
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
                                v-for="workshop in workshops.data"
                                :key="workshop.id"
                                class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ workshop.name }}
                                    </div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ formatDate(workshop.day) }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    <div
                                        class="flex items-center -space-x-2"
                                    >
                                        <span
                                            v-for="instructor in workshop.instructors"
                                            :key="instructor.id"
                                            :title="
                                                instructor.name +
                                                (instructor.affiliation
                                                    ? ' — ' +
                                                      instructor.affiliation
                                                    : '')
                                            "
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700 ring-2 ring-white dark:bg-indigo-900 dark:text-indigo-300 dark:ring-zinc-900"
                                        >
                                            {{ instructor.first_name?.[0]
                                            }}{{ instructor.last_name?.[0] }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="!workshop.instructors?.length"
                                        class="text-gray-400"
                                    >
                                        —
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.start_time }} -
                                    {{ workshop.end_time }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.location }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.enrolled_count || 0 }} /
                                    {{ workshop.capacity }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="'/workshops/' + workshop.id"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                        <template v-if="isAdmin">
                                            <button
                                                @click="openEditModal(workshop)"
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="
                                                    deleteWorkshop(workshop.id)
                                                "
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !workshops?.data ||
                                    workshops.data.length === 0
                                "
                            >
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No se encontraron talleres.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800"
                    v-if="workshops.total > 0"
                >
                    <Link
                        v-if="workshops.prev_page_url"
                        :href="workshops.prev_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Anterior</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Anterior</span
                    >
                    <Link
                        v-if="workshops.next_page_url"
                        :href="workshops.next_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Siguiente</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Siguiente</span
                    >
                </div>
            </div>
        </div>

        <!-- Modal -->
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
                    @click="showModal = false; clickOutsideSearch()"
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
                        {{ isEditing ? 'Editar Taller' : 'Nuevo Taller' }}
                    </h3>

                    <form @submit.prevent="saveWorkshop">
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
                                <div class="sm:col-span-2">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Nombre *</label
                                    >
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Descripción</label
                                    >
                                    <textarea
                                        v-model="form.description"
                                        rows="2"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    ></textarea>
                                    <p
                                        v-if="form.errors.description"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.description }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Capacidad *</label
                                    >
                                    <input
                                        v-model.number="form.capacity"
                                        type="number"
                                        min="1"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.capacity"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.capacity }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Lugar *</label
                                    >
                                    <input
                                        v-model="form.location"
                                        type="text"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.location"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.location }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Día *</label
                                    >
                                    <input
                                        v-model="form.day"
                                        type="date"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.day"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.day }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Hora Inicio *</label
                                    >
                                    <input
                                        v-model="form.start_time"
                                        type="time"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.start_time"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.start_time }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Hora Fin *</label
                                    >
                                    <input
                                        v-model="form.end_time"
                                        type="time"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.end_time"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.end_time }}
                                    </p>
                                </div>

                                <!-- Instructors -->
                                <div
                                    class="border-t border-gray-100 pt-4 sm:col-span-2 dark:border-zinc-800"
                                >
                                    <label
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Instructores *</label
                                    >
                                    <div class="relative mt-3">
                                        <input
                                            v-model="searchQuery"
                                            type="text"
                                            placeholder="Buscar instructores por nombre o correo..."
                                            :disabled="
                                                form.instructors.length >= 5
                                            "
                                            @input="
                                                searchInstructors(searchQuery)
                                            "
                                            @keydown.esc="
                                                showSearchResults = false
                                            "
                                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 pr-9 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                        />
                                        <Search
                                            class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-gray-400"
                                        />
                                        <p
                                            v-if="
                                                form.instructors.length >= 5
                                            "
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Límite de 5 instructores alcanzado.
                                        </p>
                                        <div
                                            v-if="
                                                showSearchResults &&
                                                searchResults.length > 0
                                            "
                                            class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                                        >
                                            <button
                                                v-for="user in searchResults"
                                                :key="user.id"
                                                type="button"
                                                @click="
                                                    selectInstructor(user)
                                                "
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-zinc-700"
                                            >
                                                <UserPlus class="h-3.5 w-3.5 shrink-0 text-indigo-500" />
                                                <span class="truncate">
                                                    {{ user.first_name }}
                                                    {{ user.last_name }}
                                                </span>
                                                <span
                                                    class="ml-auto truncate text-xs text-gray-400"
                                                >
                                                    {{ user.email }}
                                                </span>
                                            </button>
                                        </div>
                                        <div
                                            v-else-if="
                                                showSearchResults &&
                                                searchQuery.trim() &&
                                                !searching
                                            "
                                            class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                                        >
                                            <p
                                                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No se encontraron instructores.
                                            </p>
                                            <button
                                                v-if="
                                                    form.instructors.length < 5
                                                "
                                                type="button"
                                                @click="
                                                    openNewInstructorModal()
                                                "
                                                class="flex w-full items-center gap-2 border-t border-gray-100 px-3 py-2 text-left text-sm font-medium text-indigo-600 hover:bg-gray-50 dark:border-zinc-700 dark:text-indigo-400 dark:hover:bg-zinc-700"
                                            >
                                                <UserPlus class="h-3.5 w-3.5" />
                                                Registrar nuevo instructor
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-for="(
                                            instructor, index
                                        ) in form.instructors"
                                        :key="index"
                                        class="mt-3 mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-zinc-700 dark:bg-zinc-800"
                                    >
                                        <div
                                            class="mb-2 flex items-center justify-between"
                                        >
                                            <span
                                                class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                                >Instructor
                                                {{ index + 1 }}</span
                                            >
                                            <button
                                                type="button"
                                                @click="removeInstructor(index)"
                                                class="text-xs text-red-500 hover:text-red-700 dark:text-red-400"
                                            >
                                                × Quitar
                                            </button>
                                        </div>
                                        <div
                                            class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                        >
                                            <div>
                                                <input
                                                    v-model="
                                                        instructor.first_name
                                                    "
                                                    type="text"
                                                    placeholder="Nombre(s) *"
                                                    required
                                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.first_name'
                                                        ]
                                                    "
                                                    class="mt-1 text-xs text-red-500"
                                                >
                                                    {{
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.first_name'
                                                        ]
                                                    }}
                                                </p>
                                            </div>
                                            <div>
                                                <input
                                                    v-model="
                                                        instructor.last_name
                                                    "
                                                    type="text"
                                                    placeholder="Apellido(s) *"
                                                    required
                                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.last_name'
                                                        ]
                                                    "
                                                    class="mt-1 text-xs text-red-500"
                                                >
                                                    {{
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.last_name'
                                                        ]
                                                    }}
                                                </p>
                                            </div>
                                            <div>
                                                <input
                                                    v-model="
                                                        instructor.affiliation
                                                    "
                                                    type="text"
                                                    placeholder="Afiliación"
                                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.affiliation'
                                                        ]
                                                    "
                                                    class="mt-1 text-xs text-red-500"
                                                >
                                                    {{
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.affiliation'
                                                        ]
                                                    }}
                                                </p>
                                            </div>
                                            <div>
                                                <input
                                                    v-model="instructor.email"
                                                    type="email"
                                                    placeholder="Correo *"
                                                    required
                                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.email'
                                                        ]
                                                    "
                                                    class="mt-1 text-xs text-red-500"
                                                >
                                                    {{
                                                        form.errors[
                                                            'instructors.' +
                                                                index +
                                                                '.email'
                                                        ]
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <p
                                        v-if="form.errors.instructors"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.instructors }}
                                    </p>
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

        <div
            v-if="showNewInstructorModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <div
                class="fixed inset-0 bg-black/50 transition-opacity"
                @click="showNewInstructorModal = false"
            ></div>
            <div
                class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
            >
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    Nuevo instructor
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    El instructor se agregará a la lista de este taller.
                </p>
                <form class="mt-4 space-y-4" @submit.prevent="addNewInstructor">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Nombre(s) *</label
                            >
                            <input
                                v-model="newInstructor.first_name"
                                type="text"
                                required
                                placeholder="Nombre(s) del instructor"
                                class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                            />
                        </div>
                        <div>
                            <label
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Apellido(s) *</label
                            >
                            <input
                                v-model="newInstructor.last_name"
                                type="text"
                                required
                                placeholder="Apellido(s) del instructor"
                                class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                            />
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >Afiliación</label
                        >
                        <input
                            v-model="newInstructor.affiliation"
                            type="text"
                            placeholder="Institución"
                            class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                        />
                    </div>
                    <div>
                        <label
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >Correo *</label
                        >
                        <input
                            v-model="newInstructor.email"
                            type="email"
                            required
                            placeholder="correo@ejemplo.com"
                            class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                        />
                    </div>
                    <div
                        class="flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800"
                    >
                        <button
                            type="button"
                            @click="showNewInstructorModal = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                        >
                            Agregar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
