<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Search,
    Eye,
    Plus,
    X,
    UserPlus,
    UploadCloud,
    Pencil,
} from 'lucide-vue-next';
import { ref, watch, computed, reactive } from 'vue';
import DisciplineInput from '@/components/DisciplineInput.vue';
import TagInput from '@/components/TagInput.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role_id === 1);

const props = defineProps<{
    presentations: {
        data: any[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: any;
    tab: string;
}>();

const activeTab = ref(props.tab || 'list');

const formFilters = useForm({
    search: props.filters?.search || '',
});

let searchTimeout: ReturnType<typeof setTimeout>;
watch(
    () => formFilters.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            formFilters.get('/presentations', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
);

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    abstract: '',
    discipline: '',
    keywords: '',
    location: '',
    day: '',
    start_time: '',
    end_time: '',
    submission_id: '' as string | number,
    author_ids: [] as number[],
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.author_ids = [];
    selectedAuthors.value = [];
    showModal.value = true;
};

const openEditModal = (presentation: any) => {
    isEditing.value = true;
    editingId.value = presentation.id;
    form.reset();
    form.title = presentation.title || '';
    form.abstract = presentation.abstract || '';
    form.discipline = presentation.discipline || '';
    form.keywords = presentation.keywords || '';
    form.location = presentation.location || '';
    form.day = presentation.day || '';
    form.start_time = presentation.start_time || '';
    form.end_time = presentation.end_time || '';
    form.submission_id = presentation.submission_id || '';
    form.author_ids = presentation.authors?.map((a: any) => a.id) || [];
    selectedAuthors.value =
        presentation.authors?.map((a: any) => ({ ...a })) || [];
    showModal.value = true;
};

const savePresentation = () => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            selectedAuthors.value = [];
            isEditing.value = false;
            editingId.value = null;
        },
    };

    if (isEditing.value && editingId.value) {
        form.put('/presentations/' + editingId.value, options);
    } else {
        form.post('/presentations', options);
    }
};

const selectedAuthors = ref<any[]>([]);

const togglePresented = (
    presentationId: number,
    userId: number,
    presented: boolean,
) => {
    axios.put('/presentations/' + presentationId, {
        authors_presented: [{ user_id: userId, presented }],
    });
};

const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const showSearchResults = ref(false);
const searching = ref(false);

let searchAuthorTimeout: ReturnType<typeof setTimeout>;
const searchAuthors = (query: string) => {
    searchQuery.value = query;
    clearTimeout(searchAuthorTimeout);
    if (!query.trim()) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }
    searching.value = true;
    searchAuthorTimeout = setTimeout(async () => {
        try {
            const res = await axios.get('/api/ponentes', {
                params: { search: query },
            });
            searchResults.value = res.data.filter(
                (u: any) => !selectedAuthors.value.some((a) => a.id === u.id),
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

const selectAuthor = (author: any) => {
    if (!selectedAuthors.value.some((a) => a.id === author.id)) {
        selectedAuthors.value.push(author);
        form.author_ids.push(author.id);
    }
    searchQuery.value = '';
    searchResults.value = [];
    showSearchResults.value = false;
};

const removeAuthor = (index: number) => {
    const author = selectedAuthors.value[index];
    selectedAuthors.value.splice(index, 1);
    form.author_ids = form.author_ids.filter((id) => id !== author.id);
};

const showCreatePonente = ref(false);
const creatingPonente = ref(false);
const newPonente = reactive({
    first_name: '',
    last_name: '',
    email: '',
    affiliation: '',
    country: '',
    state: '',
    semblanza: '',
});
const newPonenteErrors = reactive<Record<string, string>>({});

const createPonente = async () => {
    newPonenteErrors.first_name = '';
    newPonenteErrors.last_name = '';
    newPonenteErrors.email = '';

    if (
        !newPonente.first_name.trim() ||
        !newPonente.last_name.trim() ||
        !newPonente.email.trim()
    ) {
        return;
    }

    creatingPonente.value = true;
    try {
        const res = await axios.post('/api/ponentes', {
            first_name: newPonente.first_name,
            last_name: newPonente.last_name,
            email: newPonente.email,
            affiliation: newPonente.affiliation,
            country: newPonente.country,
            state: newPonente.state,
            semblanza: newPonente.semblanza,
        });
        selectAuthor(res.data);
        showCreatePonente.value = false;
        newPonente.first_name = '';
        newPonente.last_name = '';
        newPonente.email = '';
        newPonente.affiliation = '';
        newPonente.country = '';
        newPonente.state = '';
        newPonente.semblanza = '';
    } catch (err: any) {
        if (err.response?.data?.errors) {
            Object.assign(newPonenteErrors, err.response.data.errors);
        }
    } finally {
        creatingPonente.value = false;
    }
};

const clickOutsideSearch = () => {
    showSearchResults.value = false;
};

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);

const importForm = useForm({
    csv_file: null as File | null,
});

const triggerFileUpload = () => {
    fileInput.value?.click();
};

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        selectedFile.value = file;
        importForm.csv_file = file;
    }
};

const submitImport = () => {
    if (!importForm.csv_file) return;

    importForm.post('/admin/presentations/import', {
        onSuccess: () => {
            selectedFile.value = null;
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Ponencias', href: '/presentations' }]">
        <Head title="Ponencias" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Ponencias
            </h1>

            <div
                v-if="isAdmin"
                class="mb-6 border-b border-gray-200 dark:border-zinc-800"
            >
                <nav class="-mb-px flex gap-6">
                    <button
                        @click="activeTab = 'list'"
                        :class="[
                            'inline-flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'list'
                                ? 'border-black text-black dark:border-white dark:text-white'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                        ]"
                    >
                        <Search class="h-4 w-4" />
                        Ponencias
                    </button>
                    <button
                        @click="activeTab = 'import'"
                        :class="[
                            'inline-flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'import'
                                ? 'border-black text-black dark:border-white dark:text-white'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                        ]"
                    >
                        <UploadCloud class="h-4 w-4" />
                        Importar CSV
                    </button>
                </nav>
            </div>

            <template v-if="activeTab === 'list'">
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
                                    class="w-full rounded-md border border-gray-300 py-2 pr-4 pl-9 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="Título o disciplina"
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
                            <Plus class="h-4 w-4" /> Nueva Ponencia
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
                                        Título
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                    >
                                        Disciplina
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
                                        Presentó
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
                                    v-for="presentation in presentations.data"
                                    :key="presentation.id"
                                    class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                                >
                                    <td class="px-6 py-4">
                                        <div
                                            class="max-w-xs truncate text-sm font-medium text-gray-900 dark:text-white"
                                        >
                                            {{ presentation.title }}
                                        </div>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ presentation.discipline || '-' }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <div v-if="presentation.day">
                                            {{ presentation.day }}
                                            <div class="text-xs text-gray-400">
                                                {{ presentation.start_time }} -
                                                {{ presentation.end_time }}
                                            </div>
                                        </div>
                                        <span v-else class="text-gray-400"
                                            >Sin asignar</span
                                        >
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{
                                            presentation.location ||
                                            'Sin asignar'
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <div
                                            v-if="isAdmin"
                                            class="flex flex-wrap gap-2"
                                        >
                                            <label
                                                v-for="author in presentation.authors"
                                                :key="author.id"
                                                class="flex cursor-pointer items-center gap-1 text-xs"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :checked="
                                                        author.pivot?.presented
                                                    "
                                                    @change="
                                                        togglePresented(
                                                            presentation.id,
                                                            author.id,
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).checked,
                                                        )
                                                    "
                                                    class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                {{ author.first_name }}
                                                {{ author.last_name }}
                                            </label>
                                        </div>
                                        <span
                                            v-else
                                            class="text-xs text-gray-400"
                                        >
                                            {{
                                                presentation.authors?.some(
                                                    (a: any) =>
                                                        a.pivot?.presented,
                                                )
                                                    ? 'Sí'
                                                    : '—'
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                    >
                                        <div
                                            class="flex items-center justify-end gap-2"
                                        >
                                            <Link
                                                :href="
                                                    '/presentations/' +
                                                    presentation.id
                                                "
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                @click="
                                                    openEditModal(presentation)
                                                "
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !presentations?.data ||
                                        presentations.data.length === 0
                                    "
                                >
                                    <td
                                        colspan="6"
                                        class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        No se encontraron ponencias.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800"
                        v-if="presentations.total > 0"
                    >
                        <Link
                            v-if="presentations.prev_page_url"
                            :href="presentations.prev_page_url"
                            class="text-gray-900 hover:underline dark:text-white"
                            >Anterior</Link
                        >
                        <span v-else class="text-gray-400 dark:text-zinc-600"
                            >Anterior</span
                        >
                        <Link
                            v-if="presentations.next_page_url"
                            :href="presentations.next_page_url"
                            class="text-gray-900 hover:underline dark:text-white"
                            >Siguiente</Link
                        >
                        <span v-else class="text-gray-400 dark:text-zinc-600"
                            >Siguiente</span
                        >
                    </div>
                </div>
            </template>

            <template v-if="activeTab === 'import'">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="space-y-6">
                        <div>
                            <h3
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Formato esperado
                            </h3>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                            >
                                El archivo CSV debe contener las columnas del
                                sistema académico (OJS). Se importarán
                                automáticamente:
                            </p>
                            <ul
                                class="mt-2 list-inside list-disc text-sm text-gray-600 dark:text-gray-400"
                            >
                                <li>Ponencias con estado "Aceptada"</li>
                                <li>Hasta 10 autores por ponencia</li>
                                <li>
                                    Cada autor se crea como usuario con rol
                                    "Ponente"
                                </li>
                            </ul>
                        </div>

                        <div
                            class="border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <label
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Seleccionar archivo CSV
                            </label>
                            <input
                                type="file"
                                ref="fileInput"
                                class="hidden"
                                accept=".csv,.txt"
                                @change="handleFileUpload"
                            />
                            <div
                                @click="triggerFileUpload"
                                class="flex cursor-pointer items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-300 p-8 transition-colors hover:border-indigo-400 hover:bg-indigo-50/50 dark:border-zinc-700 dark:hover:border-indigo-500"
                            >
                                <UploadCloud class="h-8 w-8 text-gray-400" />
                                <div class="text-center">
                                    <p
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            selectedFile
                                                ? selectedFile.name
                                                : 'Haz clic para seleccionar archivo'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        Solo archivos .csv o .txt
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex justify-end border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <button
                                @click="submitImport"
                                :disabled="
                                    !selectedFile || importForm.processing
                                "
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <UploadCloud class="h-4 w-4" />
                                {{
                                    importForm.processing
                                        ? 'Importando...'
                                        : 'Importar'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            @click.self="clickOutsideSearch"
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
                        {{ isEditing ? 'Editar Ponencia' : 'Nueva Ponencia' }}
                    </h3>

                    <form @submit.prevent="savePresentation">
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
                                <div class="sm:col-span-2">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Resumen</label
                                    >
                                    <textarea
                                        v-model="form.abstract"
                                        rows="3"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    ></textarea>
                                    <p
                                        v-if="form.errors.abstract"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.abstract }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Disciplina</label
                                    >
                                    <DisciplineInput
                                        v-model="form.discipline"
                                    />
                                    <p
                                        v-if="form.errors.discipline"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.discipline }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Palabras clave</label
                                    >
                                    <TagInput v-model="form.keywords" />
                                    <p
                                        v-if="form.errors.keywords"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.keywords }}
                                    </p>
                                </div>
                                <div v-if="isAdmin || !isEditing">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >ID de envío</label
                                    >
                                    <input
                                        v-model="form.submission_id"
                                        type="text"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <p
                                        v-if="form.errors.submission_id"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.submission_id }}
                                    </p>
                                </div>
                                <div v-if="isAdmin || !isEditing">
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
                                <div v-if="isAdmin || !isEditing">
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
                                <div v-if="isAdmin || !isEditing">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Hora inicio</label
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
                                <div v-if="isAdmin || !isEditing">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Hora fin</label
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

                                <!-- Authors -->
                                <div
                                    class="border-t border-gray-100 pt-4 sm:col-span-2 dark:border-zinc-800"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between"
                                    >
                                        <label
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >Autores *</label
                                        >
                                        <button
                                            v-if="isAdmin || !isEditing"
                                            type="button"
                                            @click="showCreatePonente = true"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                        >
                                            <UserPlus class="h-3 w-3" /> Nuevo
                                            ponente
                                        </button>
                                    </div>

                                    <!-- Selected authors -->
                                    <div
                                        v-if="selectedAuthors.length > 0"
                                        class="mb-2 flex flex-wrap gap-2"
                                    >
                                        <span
                                            v-for="(
                                                author, idx
                                            ) in selectedAuthors"
                                            :key="author.id"
                                            class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                        >
                                            {{ author.first_name }}
                                            {{ author.last_name }}
                                            <button
                                                v-if="isAdmin || !isEditing"
                                                type="button"
                                                @click="removeAuthor(idx)"
                                                class="inline-flex items-center text-indigo-400 hover:text-indigo-600"
                                            >
                                                <X class="h-3 w-3" />
                                            </button>
                                        </span>
                                    </div>

                                    <!-- Author search -->
                                    <div
                                        v-if="isAdmin || !isEditing"
                                        class="relative"
                                    >
                                        <input
                                            v-model="searchQuery"
                                            @input="
                                                searchAuthors(
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                            @focus="searchAuthors(searchQuery)"
                                            type="text"
                                            placeholder="Buscar ponentes por nombre o correo..."
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
                                                v-for="author in searchResults"
                                                :key="author.id"
                                                type="button"
                                                @click="selectAuthor(author)"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-zinc-700"
                                            >
                                                <UserPlus
                                                    class="h-3.5 w-3.5 shrink-0 text-gray-400"
                                                />
                                                <span>
                                                    {{ author.first_name }}
                                                    {{ author.last_name }}
                                                </span>
                                                <span
                                                    class="ml-auto text-xs text-gray-400"
                                                >
                                                    {{ author.email }}
                                                </span>
                                            </button>
                                        </div>
                                        <div
                                            v-else-if="
                                                showSearchResults &&
                                                searchQuery.trim() &&
                                                !searching
                                            "
                                            class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 shadow-lg dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400"
                                        >
                                            No se encontraron ponentes.
                                        </div>
                                    </div>

                                    <p
                                        v-if="
                                            isEditing &&
                                            !isAdmin &&
                                            selectedAuthors.length
                                        "
                                        class="mt-2 text-xs text-gray-500"
                                    >
                                        Para modificar los autores, contacta al
                                        administrador.
                                    </p>

                                    <p
                                        v-if="form.errors.author_ids"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.author_ids }}
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
                                {{ isEditing ? 'Guardar Cambios' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create ponente inline modal -->
        <div
            v-if="showCreatePonente"
            class="fixed inset-0 z-[60] overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showCreatePonente = false"
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
                        Nuevo Ponente
                    </h3>

                    <form @submit.prevent="createPonente">
                        <div
                            v-if="Object.keys(newPonenteErrors).length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            <p class="font-medium">
                                Corrige los siguientes errores:
                            </p>
                            <ul class="mt-1 list-inside list-disc">
                                <li
                                    v-for="(message, key) in newPonenteErrors"
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
                                    v-model="newPonente.first_name"
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
                                    v-model="newPonente.last_name"
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
                                    v-model="newPonente.email"
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
                                        v-model="newPonente.affiliation"
                                        type="text"
                                        placeholder="Institución / Afiliación"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <input
                                        v-model="newPonente.country"
                                        type="text"
                                        placeholder="País"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <input
                                        v-model="newPonente.state"
                                        type="text"
                                        placeholder="Estado"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <textarea
                                        v-model="newPonente.semblanza"
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
                                @click="showCreatePonente = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="creatingPonente"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{
                                    creatingPonente
                                        ? 'Creando...'
                                        : 'Crear ponente'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
