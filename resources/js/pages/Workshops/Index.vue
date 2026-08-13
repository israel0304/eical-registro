<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Search, Plus, Edit, Trash2, Eye, UserPlus, RotateCcw, AlertTriangle } from 'lucide-vue-next';
import { ref, watch, reactive } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();
const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;

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
    status: props.filters?.status || 'active',
});

let searchTimeout: ReturnType<typeof setTimeout>;
watch(
    () => [formFilters.search, formFilters.status],
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            formFilters.get('/workshops', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
    { deep: true },
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
    moderator_ids: [] as number[],
});

const selectedModerators = ref<any[]>([]);
const moderatorSearchQuery = ref('');
const moderatorSearchResults = ref<any[]>([]);
const showModeratorSearchResults = ref(false);
const searchingModerator = ref(false);
let searchModeratorTimeout: ReturnType<typeof setTimeout>;

const searchModerators = (query: string) => {
    moderatorSearchQuery.value = query;
    clearTimeout(searchModeratorTimeout);
    if (!query.trim()) {
        moderatorSearchResults.value = [];
        showModeratorSearchResults.value = false;
        return;
    }
    searchingModerator.value = true;
    searchModeratorTimeout = setTimeout(async () => {
        try {
            const res = await axios.get('/api/moderadores', {
                params: { search: query },
            });
            moderatorSearchResults.value = res.data.filter(
                (u: any) => !form.moderator_ids.includes(u.id),
            );
            showModeratorSearchResults.value =
                moderatorSearchResults.value.length > 0 ||
                query.trim().length > 0;
        } catch {
            moderatorSearchResults.value = [];
        } finally {
            searchingModerator.value = false;
        }
    }, 300);
};

const selectModerator = (user: any) => {
    if (!selectedModerators.value.some((m) => m.id === user.id)) {
        selectedModerators.value.push(user);
        form.moderator_ids.push(user.id);
    }
    moderatorSearchQuery.value = '';
    moderatorSearchResults.value = [];
    showModeratorSearchResults.value = false;
};

const removeModerator = (index: number) => {
    const mod = selectedModerators.value[index];
    selectedModerators.value.splice(index, 1);
    form.moderator_ids = form.moderator_ids.filter((id) => id !== mod.id);
};

const showCreateModerator = ref(false);
const creatingModerator = ref(false);
const newModerator = reactive({
    first_name: '',
    last_name: '',
    email: '',
    affiliation: '',
    country: '',
    state: '',
    semblanza: '',
});
const newModeratorErrors = reactive<Record<string, string>>({});

const createModerator = async () => {
    Object.keys(newModeratorErrors).forEach(
        (key) => delete newModeratorErrors[key],
    );
    if (
        !newModerator.first_name.trim() ||
        !newModerator.last_name.trim() ||
        !newModerator.email.trim()
    )
        return;

    creatingModerator.value = true;
    try {
        const res = await axios.post('/api/moderadores', {
            first_name: newModerator.first_name,
            last_name: newModerator.last_name,
            email: newModerator.email,
            affiliation: newModerator.affiliation,
            country: newModerator.country,
            state: newModerator.state,
            semblanza: newModerator.semblanza,
        });
        selectModerator(res.data);
        showCreateModerator.value = false;
        newModerator.first_name = '';
        newModerator.last_name = '';
        newModerator.email = '';
        newModerator.affiliation = '';
    } catch (err: any) {
        if (err.response?.data?.errors) {
            Object.assign(newModeratorErrors, err.response.data.errors);
        }
    } finally {
        creatingModerator.value = false;
    }
};

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.instructors = [];
    form.moderator_ids = [];
    selectedModerators.value = [];
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
    form.start_time = workshop.start_time
        ? workshop.start_time.slice(0, 5)
        : '';
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
    form.moderator_ids = workshop.moderators?.map((m: any) => m.id) || [];
    selectedModerators.value =
        workshop.moderators?.map((m: any) => ({ ...m })) || [];
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

const restoreWorkshop = (id: number) => {
    if (confirm('¿Estás seguro de restaurar este taller?')) {
        router.post('/workshops/' + id + '/restore', {
            preserveScroll: true,
        });
    }
};

const forceDeleteWorkshop = (workshop: any) => {
    const enrolledCount = workshop.enrolled_count || 0;
    if (enrolledCount > 0) {
        alert(
            `No se puede eliminar definitivamente. El taller tiene ${enrolledCount} inscripción(es) activa(s). Elimínelas primero desde la vista de inscripciones del taller.`
        );
        return;
    }
    if (confirm('¿Estás seguro de eliminar DEFINITIVAMENTE este taller? Esta acción no se puede deshacer.')) {
        router.post('/workshops/' + workshop.id + '/force-delete', {
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
                    <div class="flex flex-col">
                        <label
                            class="text-[11px] font-medium tracking-wide text-gray-600 uppercase dark:text-gray-400"
                        >
                            Estado
                        </label>
                        <select
                            v-model="formFilters.status"
                            class="w-full sm:w-48 rounded-md border border-gray-300 py-2 px-3 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-white dark:focus:ring-white"
                        >
                            <option value="active">Activos</option>
                            <option value="deleted">Eliminados</option>
                            <option value="all">Todos</option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="can('workshops.create')"
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
                                :class="[
                                    'transition-colors duration-150',
                                    workshop.deleted_at
                                        ? 'opacity-50 hover:bg-gray-50 dark:hover:bg-zinc-800'
                                        : 'hover:bg-gray-50 dark:hover:bg-zinc-800',
                                ]"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        :class="[
                                            'text-sm font-medium',
                                            workshop.deleted_at
                                                ? 'line-through text-gray-500 dark:text-gray-400'
                                                : 'text-gray-900 dark:text-white',
                                        ]"
                                    >
                                        {{ workshop.name }}
                                        <span
                                            v-if="workshop.deleted_at"
                                            class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-300"
                                        >
                                            Eliminado
                                        </span>
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
                                    <div class="flex items-center -space-x-2">
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
                                        <button
                                            v-if="!workshop.deleted_at && can('workshops.edit')"
                                            @click="openEditModal(workshop)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="!workshop.deleted_at && can('workshops.delete')"
                                            @click="deleteWorkshop(workshop.id)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="workshop.deleted_at && can('workshops.delete')"
                                            @click="restoreWorkshop(workshop.id)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-emerald-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-emerald-400"
                                            title="Restaurar"
                                        >
                                            <RotateCcw class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="workshop.deleted_at && can('workshops.delete')"
                                            @click="forceDeleteWorkshop(workshop)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                            title="Eliminar definitivamente"
                                            :disabled="(workshop.enrolled_count || 0) > 0"
                                        >
                                            <AlertTriangle class="h-4 w-4" />
                                        </button>
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
                    @click="
                        showModal = false;
                        clickOutsideSearch();
                    "
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
                                            v-if="form.instructors.length >= 5"
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
                                                @click="selectInstructor(user)"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-zinc-700"
                                            >
                                                <UserPlus
                                                    class="h-3.5 w-3.5 shrink-0 text-indigo-500"
                                                />
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

                                    <!-- Moderator section -->
                                    <div
                                        class="mt-6 border-t border-gray-100 pt-4 dark:border-zinc-800"
                                    >
                                        <div
                                            class="mb-3 flex items-center justify-between"
                                        >
                                            <label
                                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Moderadores
                                            </label>
                                        </div>

                                        <div
                                            v-if="selectedModerators.length > 0"
                                            class="mb-2 flex flex-wrap gap-2"
                                        >
                                            <span
                                                v-for="(
                                                    mod, idx
                                                ) in selectedModerators"
                                                :key="mod.id"
                                                class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                            >
                                                {{ mod.first_name }}
                                                {{ mod.last_name }}
                                                <button
                                                    type="button"
                                                    @click="
                                                        removeModerator(idx)
                                                    "
                                                    class="inline-flex items-center text-emerald-400 hover:text-emerald-600"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </div>

                                        <div class="relative">
                                            <input
                                                v-model="moderatorSearchQuery"
                                                @input="
                                                    searchModerators(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                                @focus="
                                                    searchModerators(
                                                        moderatorSearchQuery,
                                                    )
                                                "
                                                type="text"
                                                placeholder="Buscar moderadores por nombre o correo..."
                                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                            />
                                            <div
                                                v-if="
                                                    showModeratorSearchResults &&
                                                    moderatorSearchResults.length >
                                                        0
                                                "
                                                class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                                            >
                                                <button
                                                    v-for="user in moderatorSearchResults"
                                                    :key="user.id"
                                                    type="button"
                                                    @click="
                                                        selectModerator(user)
                                                    "
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-zinc-700"
                                                >
                                                    <UserPlus
                                                        class="h-3.5 w-3.5 shrink-0 text-gray-400"
                                                    />
                                                    <span
                                                        >{{ user.first_name }}
                                                        {{
                                                            user.last_name
                                                        }}</span
                                                    >
                                                    <span
                                                        class="ml-auto text-xs text-gray-400"
                                                        >{{ user.email }}</span
                                                    >
                                                </button>
                                            </div>
                                            <div
                                                v-else-if="
                                                    showModeratorSearchResults &&
                                                    moderatorSearchQuery.trim() &&
                                                    !searchingModerator
                                                "
                                                class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                                            >
                                                <p
                                                    class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                                                >
                                                    No se encontraron usuarios.
                                                </p>
                                                <button
                                                    type="button"
                                                    @click="
                                                        showCreateModerator = true
                                                    "
                                                    class="flex w-full items-center gap-2 border-t border-gray-100 px-3 py-2 text-left text-sm font-medium text-indigo-600 hover:bg-gray-50 dark:border-zinc-700 dark:text-indigo-400 dark:hover:bg-zinc-700"
                                                >
                                                    <UserPlus
                                                        class="h-3.5 w-3.5"
                                                    />
                                                    Registrar nuevo moderador
                                                </button>
                                            </div>
                                        </div>
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

        <!-- Create moderator inline modal -->
        <div
            v-if="showCreateModerator"
            class="fixed inset-0 z-[60] overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showCreateModerator = false"
                ></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle"
                    >&#8203;</span
                >
                <div
                    class="relative inline-block transform overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-8 sm:align-middle dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h3
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Nuevo Moderador
                    </h3>

                    <form @submit.prevent="createModerator">
                        <div
                            v-if="Object.keys(newModeratorErrors).length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            <p class="font-medium">
                                Corrige los siguientes errores:
                            </p>
                            <ul class="mt-1 list-inside list-disc">
                                <li
                                    v-for="(message, key) in newModeratorErrors"
                                    :key="key"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Nombre(s) *</label
                                >
                                <input
                                    v-model="newModerator.first_name"
                                    type="text"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Apellido(s) *</label
                                >
                                <input
                                    v-model="newModerator.last_name"
                                    type="text"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Correo electrónico *</label
                                >
                                <input
                                    v-model="newModerator.email"
                                    type="email"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                        </div>
                        <div
                            class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <button
                                type="button"
                                @click="showCreateModerator = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="creatingModerator"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{
                                    creatingModerator
                                        ? 'Creando...'
                                        : 'Crear moderador'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
