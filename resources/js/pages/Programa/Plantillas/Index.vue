<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, ListTree } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    templates: any[];
}>();

const showModal = ref(false);

const form = useForm({
    name: '',
    description: '',
    is_default: false,
    width: 816,
    height: 1056,
    background: null as File | null,
});

const basePath = '/programa/plantillas';

const openCreateModal = () => {
    form.reset();
    form.width = 816;
    form.height = 1056;
    showModal.value = true;
};

const onBackgroundChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.background = input.files?.[0] ?? null;
};

const saveTemplate = () => {
    form.post(basePath, {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const deleteTemplate = (id: number) => {
    if (confirm('¿Eliminar esta plantilla de programa?')) {
        router.delete(basePath + '/' + id, {
            preserveScroll: true,
        });
    }
};

const templateEditUrl = (id: number) => basePath + '/' + id + '/edit';

const toggleActive = (template: any) => {
    router.patch(basePath + '/' + template.id + '/activar', {
        is_active: !template.is_active,
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Programa', href: '/programa' },
            { title: 'Plantillas del programa', href: basePath },
        ]"
    >
        <Head title="Plantillas del Programa" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-10 px-8 py-8">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Plantillas del Programa
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Plantillas en tamaño carta (816×1056) con editor visual.
                        El bloque "Lista del programa" fluye y ocupa varias
                        páginas repitiendo el encabezado y el fondo.
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    <Plus class="h-4 w-4" />
                    Nueva plantilla
                </button>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full divide-y divide-gray-200 dark:divide-zinc-800"
                    >
                        <thead class="bg-gray-50 dark:bg-zinc-800/50">
                            <tr>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Nombre
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Dimensiones
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Elementos
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Activa
                                </th>
                                <th class="relative px-5 py-3">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-zinc-800"
                        >
                            <tr
                                v-for="template in templates"
                                :key="template.id"
                            >
                                <td class="px-5 py-3">
                                    <div
                                        class="max-w-[280px] truncate text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ template.name }}
                                    </div>
                                    <div
                                        v-if="template.description"
                                        class="max-w-[280px] truncate text-xs text-gray-400"
                                    >
                                        {{ template.description }}
                                    </div>
                                </td>
                                <td
                                    class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ template.width }} × {{ template.height }}
                                </td>
                                <td
                                    class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ template.elements_count }}
                                </td>
                                <td class="px-5 py-3">
                                    <button
                                        type="button"
                                        @click="toggleActive(template)"
                                        :title="
                                            template.is_active
                                                ? 'Desactivar plantilla'
                                                : 'Activar plantilla'
                                        "
                                        :class="
                                            template.is_active
                                                ? 'bg-green-600'
                                                : 'bg-gray-300 dark:bg-zinc-600'
                                        "
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                    >
                                        <span
                                            :class="
                                                template.is_active
                                                    ? 'translate-x-4'
                                                    : 'translate-x-0'
                                            "
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        />
                                    </button>
                                </td>
                                <td
                                    class="px-5 py-3 text-right whitespace-nowrap"
                                >
                                    <Link
                                        :href="templateEditUrl(template.id)"
                                        class="mr-1 inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 shadow-sm transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-indigo-400"
                                        title="Editar"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        @click="deleteTemplate(template.id)"
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                        title="Eliminar"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="templates.length === 0">
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <ListTree
                                        class="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600"
                                    />
                                    No hay plantillas de programa. Crea la
                                    primera con el botón "Nueva plantilla".
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="showModal"
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
                        @click="showModal = false"
                    ></div>

                    <div
                        class="relative z-10 inline-block w-full max-w-3xl overflow-hidden rounded-3xl border bg-white p-6 text-left shadow-2xl transition-all dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="mb-6 flex items-center justify-between">
                            <h2
                                id="modal-title"
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                Nueva plantilla de programa
                            </h2>
                            <button
                                @click="showModal = false"
                                type="button"
                                class="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-white"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="saveTemplate" class="space-y-4">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Nombre *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="ej. Programa ECIAL"
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
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Descripción
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    placeholder="Descripción opcional"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Ancho (px) *
                                    </label>
                                    <input
                                        v-model.number="form.width"
                                        type="number"
                                        required
                                        min="200"
                                        max="5000"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Alto (px) *
                                    </label>
                                    <input
                                        v-model.number="form.height"
                                        type="number"
                                        required
                                        min="200"
                                        max="5000"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Imagen de fondo (PNG)
                                </label>
                                <input
                                    type="file"
                                    accept="image/png"
                                    @change="onBackgroundChange"
                                    class="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-400"
                                />
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="showModal = false"
                                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    <Plus
                                        v-if="!form.processing"
                                        class="h-4 w-4"
                                    />
                                    <svg
                                        v-else
                                        class="h-4 w-4 animate-spin"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                            fill="none"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{
                                        form.processing
                                            ? 'Guardando...'
                                            : 'Crear plantilla'
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
