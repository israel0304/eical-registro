<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Search, Plus, Edit, Trash2, Eye, UserPlus } from 'lucide-vue-next';
import { ref, watch, reactive } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();
const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;

const props = defineProps<{
    conferences: {
        data: any[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: any;
    kinds: string[];
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
            formFilters.get('/conferences', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
);

interface MemberForm {
    id: number;
    name: string;
    role: 'speaker' | 'moderator';
}

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null as number | null,
    title: '',
    kind: 'especial',
    description: '',
    location: '',
    day: '',
    start_time: '',
    end_time: '',
    members: [] as MemberForm[],
    moderator_ids: [] as number[],
});

const kindLabel = (kind: string) =>
    ({
        magistral: 'Magistral',
        especial: 'Especial',
        simposio: 'Simposio',
        mesa_dialogo: 'Mesa de diálogo',
    })[kind] ?? kind;

const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const showSearchResults = ref(false);
const searching = ref(false);
let searchMemberTimeout: ReturnType<typeof setTimeout>;

const searchUsers = (query: string) => {
    searchQuery.value = query;
    clearTimeout(searchMemberTimeout);
    if (!query.trim()) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }
    searching.value = true;
    searchMemberTimeout = setTimeout(async () => {
        try {
            const res = await axios.get('/api/usuarios', {
                params: { search: query },
            });
            searchResults.value = res.data.filter(
                (u: any) => !form.members.some((m) => m.id === u.id),
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

const selectUser = (user: any) => {
    if (form.members.some((m) => m.id === user.id)) return;
    form.members.push({
        id: user.id,
        name: `${user.first_name} ${user.last_name}`,
        role: 'speaker',
    });
    searchQuery.value = '';
    searchResults.value = [];
    showSearchResults.value = false;
};

const clickOutsideSearch = () => {
    showSearchResults.value = false;
};

const showNewMemberModal = ref(false);
const creatingMember = ref(false);
const newMember = reactive({
    first_name: '',
    last_name: '',
    email: '',
    affiliation: '',
    country: '',
    state: '',
    semblanza: '',
});
const newMemberErrors = reactive<Record<string, string>>({});

const resetNewMember = () => {
    newMember.first_name = '';
    newMember.last_name = '';
    newMember.email = '';
    newMember.affiliation = '';
    newMember.country = '';
    newMember.state = '';
    newMember.semblanza = '';
};

const openNewMemberModal = () => {
    resetNewMember();
    Object.keys(newMemberErrors).forEach((key) => delete newMemberErrors[key]);
    showNewMemberModal.value = true;
};

const createMember = async () => {
    Object.keys(newMemberErrors).forEach((key) => delete newMemberErrors[key]);

    if (
        !newMember.first_name.trim() ||
        !newMember.last_name.trim() ||
        !newMember.email.trim()
    ) {
        return;
    }

    creatingMember.value = true;
    try {
        const res = await axios.post('/api/usuarios', {
            first_name: newMember.first_name,
            last_name: newMember.last_name,
            email: newMember.email,
            affiliation: newMember.affiliation,
            country: newMember.country,
            state: newMember.state,
            semblanza: newMember.semblanza,
        });
        form.members.push({
            id: res.data.id,
            name: `${res.data.first_name} ${res.data.last_name}`,
            role: 'speaker',
        });
        showNewMemberModal.value = false;
        resetNewMember();
    } catch (err: any) {
        if (err.response?.data?.errors) {
            Object.assign(newMemberErrors, err.response.data.errors);
        }
    } finally {
        creatingMember.value = false;
    }
};

const removeMember = (index: number) => {
    form.members.splice(index, 1);
};

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
    ) {
        return;
    }

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
        newModerator.country = '';
        newModerator.state = '';
        newModerator.semblanza = '';
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
    form.kind = 'especial';
    form.members = [];
    form.moderator_ids = [];
    selectedModerators.value = [];
    showModal.value = true;
};

const openEditModal = (conference: any) => {
    isEditing.value = true;
    form.reset();
    form.id = conference.id;
    form.title = conference.title || '';
    form.kind = conference.kind || 'especial';
    form.description = conference.description || '';
    form.location = conference.location || '';
    form.day = conference.day || '';
    form.start_time = conference.start_time
        ? conference.start_time.slice(0, 5)
        : '';
    form.end_time = conference.end_time ? conference.end_time.slice(0, 5) : '';
    const allMembers = conference.members ?? [];
    const moderators = allMembers.filter(
        (m: any) => m.pivot?.role === 'moderator',
    );
    form.members = allMembers
        .filter((m: any) => m.pivot?.role !== 'moderator')
        .map((m: any) => ({
            id: m.id,
            name: `${m.first_name} ${m.last_name}`,
            role: 'speaker',
        }));
    form.moderator_ids = moderators.map((m: any) => m.id);
    selectedModerators.value = moderators.map((m: any) => ({ ...m }));
    showModal.value = true;
};

const saveConference = () => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    };

    if (isEditing.value && form.id) {
        form.put('/conferences/' + form.id, options);
    } else {
        form.post('/conferences', options);
    }
};

const deleteConference = (id: number) => {
    if (confirm('¿Estás seguro de eliminar esta conferencia?')) {
        router.delete('/conferences/' + id, {
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
    <AppLayout :breadcrumbs="[{ title: 'Conferencias', href: '/conferences' }]">
        <Head title="Conferencias" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Conferencias
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
                                placeholder="Título de la conferencia"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="can('conferences.create')"
                    class="flex items-center gap-3 self-start xl:self-auto"
                >
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                    >
                        <Plus class="h-4 w-4" /> Nueva Conferencia
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
                                    Conferencia
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Tipo
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
                                    Miembros
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
                                v-for="conference in conferences.data"
                                :key="conference.id"
                                class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ conference.title }}
                                    </div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ formatDate(conference.day) }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    <span
                                        class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                    >
                                        {{ kindLabel(conference.kind) }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ conference.start_time }} -
                                    {{ conference.end_time }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ conference.location || '—' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    <div
                                        v-if="conference.members?.length"
                                        class="flex items-center -space-x-2"
                                    >
                                        <span
                                            v-for="member in conference.members.slice(
                                                0,
                                                5,
                                            )"
                                            :key="member.id"
                                            :title="
                                                member.name +
                                                (member.affiliation
                                                    ? ' — ' + member.affiliation
                                                    : '')
                                            "
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700 ring-2 ring-white dark:bg-indigo-900 dark:text-indigo-300 dark:ring-zinc-900"
                                        >
                                            {{ member.first_name?.[0]
                                            }}{{ member.last_name?.[0] }}
                                        </span>
                                        <span
                                            v-if="conference.members.length > 5"
                                            :title="
                                                conference.members
                                                    .map((m: any) => m.name)
                                                    .join(', ')
                                            "
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-600 ring-2 ring-white dark:bg-zinc-700 dark:text-gray-300 dark:ring-zinc-900"
                                        >
                                            +{{ conference.members.length - 5 }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="
                                                '/conferences/' + conference.id
                                            "
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                        <button
                                            v-if="can('conferences.edit')"
                                            @click="openEditModal(conference)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="can('conferences.delete')"
                                            @click="
                                                deleteConference(conference.id)
                                            "
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !conferences?.data ||
                                    conferences.data.length === 0
                                "
                            >
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No se encontraron conferencias.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800"
                    v-if="conferences.total > 0"
                >
                    <Link
                        v-if="conferences.prev_page_url"
                        :href="conferences.prev_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Anterior</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Anterior</span
                    >
                    <Link
                        v-if="conferences.next_page_url"
                        :href="conferences.next_page_url"
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
                        {{
                            isEditing
                                ? 'Editar Conferencia'
                                : 'Nueva Conferencia'
                        }}
                    </h3>

                    <form @submit.prevent="saveConference">
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
                                        >Título *</label
                                    >
                                    <input
                                        v-model="form.title"
                                        type="text"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.title"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.title }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Tipo *</label
                                    >
                                    <select
                                        v-model="form.kind"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    >
                                        <option
                                            v-for="kind in kinds"
                                            :key="kind"
                                            :value="kind"
                                        >
                                            {{ kindLabel(kind) }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.kind"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.kind }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Lugar</label
                                    >
                                    <input
                                        v-model="form.location"
                                        type="text"
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
                                        >Día</label
                                    >
                                    <input
                                        v-model="form.day"
                                        type="date"
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
                                        >Hora Inicio</label
                                    >
                                    <input
                                        v-model="form.start_time"
                                        type="time"
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
                                        >Hora Fin</label
                                    >
                                    <input
                                        v-model="form.end_time"
                                        type="time"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.end_time"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.end_time }}
                                    </p>
                                </div>

                                <div
                                    class="border-t border-gray-100 pt-4 sm:col-span-2 dark:border-zinc-800"
                                >
                                    <label
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Miembros (Speaker)</label
                                    >
                                    <div class="relative mt-3">
                                        <input
                                            v-model="searchQuery"
                                            @input="
                                                searchUsers(
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                            @focus="searchUsers(searchQuery)"
                                            type="text"
                                            placeholder="Buscar usuarios por nombre o correo..."
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                        <div
                                            v-if="
                                                showSearchResults &&
                                                searchResults.length > 0
                                            "
                                            class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                                        >
                                            <button
                                                v-for="user in searchResults"
                                                :key="user.id"
                                                type="button"
                                                @click="selectUser(user)"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-zinc-700"
                                            >
                                                <UserPlus
                                                    class="h-3.5 w-3.5 shrink-0 text-gray-400"
                                                />
                                                <span>
                                                    {{ user.first_name }}
                                                    {{ user.last_name }}
                                                </span>
                                                <span
                                                    class="ml-auto text-xs text-gray-400"
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
                                                No se encontraron usuarios.
                                            </p>
                                            <button
                                                type="button"
                                                @click="openNewMemberModal"
                                                class="flex w-full items-center gap-2 border-t border-gray-100 px-3 py-2 text-left text-sm font-medium text-indigo-600 hover:bg-gray-50 dark:border-zinc-700 dark:text-indigo-400 dark:hover:bg-zinc-700"
                                            >
                                                <Plus class="h-3.5 w-3.5" />
                                                Registrar nuevo speaker
                                            </button>
                                        </div>
                                    </div>
                                    <div
                                        v-if="form.members.length"
                                        class="mt-3 space-y-2"
                                    >
                                        <div
                                            v-for="(
                                                member, index
                                            ) in form.members"
                                            :key="member.id"
                                            class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-zinc-700 dark:bg-zinc-800"
                                        >
                                            <span
                                                class="flex-1 truncate text-sm text-gray-700 dark:text-gray-300"
                                            >
                                                {{ member.name }}
                                            </span>
                                            <span
                                                class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                                            >
                                                Speaker
                                            </span>
                                            <button
                                                type="button"
                                                @click="removeMember(index)"
                                                class="text-xs text-red-500 hover:text-red-700 dark:text-red-400"
                                            >
                                                × Quitar
                                            </button>
                                        </div>
                                    </div>
                                    <p
                                        v-if="form.errors['members.0.id']"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors['members.0.id'] }}
                                    </p>
                                </div>

                                <!-- Moderator section -->
                                <div
                                    class="border-t border-gray-100 pt-4 sm:col-span-2 dark:border-zinc-800"
                                >
                                    <label
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Moderadores</label
                                    >
                                    <div class="relative mt-3">
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
                                                @click="selectModerator(user)"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-zinc-700"
                                            >
                                                <UserPlus
                                                    class="h-3.5 w-3.5 shrink-0 text-gray-400"
                                                />
                                                <span>
                                                    {{ user.first_name }}
                                                    {{ user.last_name }}
                                                </span>
                                                <span
                                                    class="ml-auto text-xs text-gray-400"
                                                >
                                                    {{ user.email }}
                                                </span>
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
                                                No se encontraron moderadores.
                                            </p>
                                            <button
                                                type="button"
                                                @click="
                                                    showCreateModerator = true
                                                "
                                                class="flex w-full items-center gap-2 border-t border-gray-100 px-3 py-2 text-left text-sm font-medium text-indigo-600 hover:bg-gray-50 dark:border-zinc-700 dark:text-indigo-400 dark:hover:bg-zinc-700"
                                            >
                                                <Plus class="h-3.5 w-3.5" />
                                                Registrar nuevo moderador
                                            </button>
                                        </div>
                                    </div>
                                    <div
                                        v-if="selectedModerators.length > 0"
                                        class="mt-3 space-y-2"
                                    >
                                        <div
                                            v-for="(
                                                mod, idx
                                            ) in selectedModerators"
                                            :key="mod.id"
                                            class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-2 dark:border-emerald-900/40 dark:bg-emerald-950/30"
                                        >
                                            <span
                                                class="flex-1 truncate text-sm text-gray-700 dark:text-gray-300"
                                            >
                                                {{ mod.first_name }}
                                                {{ mod.last_name }}
                                            </span>
                                            <span
                                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                            >
                                                Moderador
                                            </span>
                                            <button
                                                type="button"
                                                @click="removeModerator(idx)"
                                                class="text-xs text-red-500 hover:text-red-700 dark:text-red-400"
                                            >
                                                × Quitar
                                            </button>
                                        </div>
                                    </div>
                                    <p
                                        v-if="form.errors['moderator_ids.0']"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors['moderator_ids.0'] }}
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

        <!-- Nuevo Miembro modal -->
        <div
            v-if="showNewMemberModal"
            class="fixed inset-0 z-[60] overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showNewMemberModal = false"
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
                        Nuevo Miembro
                    </h3>

                    <form @submit.prevent="createMember">
                        <div
                            v-if="Object.keys(newMemberErrors).length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            <p class="font-medium">
                                Corrige los siguientes errores:
                            </p>
                            <ul class="mt-1 list-inside list-disc">
                                <li
                                    v-for="(message, key) in newMemberErrors"
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
                                    v-model="newMember.first_name"
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
                                    v-model="newMember.last_name"
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
                                    v-model="newMember.email"
                                    type="email"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>

                            <div
                                class="border-t border-gray-100 pt-4 dark:border-zinc-800"
                            >
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Datos adicionales</label
                                >
                                <div class="space-y-3">
                                    <input
                                        v-model="newMember.affiliation"
                                        type="text"
                                        placeholder="Institución / Afiliación"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <input
                                        v-model="newMember.country"
                                        type="text"
                                        placeholder="País"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <input
                                        v-model="newMember.state"
                                        type="text"
                                        placeholder="Estado"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <textarea
                                        v-model="newMember.semblanza"
                                        rows="3"
                                        placeholder="Semblanza / Breve currículum"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <button
                                type="button"
                                @click="showNewMemberModal = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="creatingMember"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ creatingMember ? 'Guardando…' : 'Agregar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Nuevo Moderador modal -->
        <div
            v-if="showCreateModerator"
            class="fixed inset-0 z-[70] overflow-y-auto"
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

                            <div
                                class="border-t border-gray-100 pt-4 dark:border-zinc-800"
                            >
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Datos adicionales</label
                                >
                                <div class="space-y-3">
                                    <input
                                        v-model="newModerator.affiliation"
                                        type="text"
                                        placeholder="Institución / Afiliación"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <input
                                        v-model="newModerator.country"
                                        type="text"
                                        placeholder="País"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <input
                                        v-model="newModerator.state"
                                        type="text"
                                        placeholder="Estado"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <textarea
                                        v-model="newModerator.semblanza"
                                        rows="3"
                                        placeholder="Semblanza / Breve currículum"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    ></textarea>
                                </div>
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
                                    creatingModerator ? 'Guardando…' : 'Agregar'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
