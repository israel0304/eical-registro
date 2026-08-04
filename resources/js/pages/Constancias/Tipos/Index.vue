<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    participationTypes: any[];
}>();

const eventKindLabel = (kind: string) =>
    ({ workshop: 'Taller', presentation: 'Ponencia', conference: 'Conferencia' })[kind] ??
    kind;

const roleLabel = (role: string) =>
    ({
        enrolled_attendance: 'Asistente',
        instructor: 'Instructor',
        presented_author: 'Ponente presentado',
        speaker: 'Speaker',
        moderator: 'Moderador',
    })[role] ?? role;

const kindLabel = (kind: string | null) =>
    ({ magistral: 'Magistral', especial: 'Especial', simposio: 'Simposio', mesa_dialogo: 'Mesa de diálogo' })[
        kind ?? ''
    ] ?? kind ?? '—';

const typeForm = useForm({
    key: '',
    label: '',
    event_kind: 'workshop',
    kind: '',
    role: 'enrolled_attendance',
    is_active: true,
});

const saveType = () => {
    typeForm.post('/admin/constancias/tipos', {
        preserveScroll: true,
        onSuccess: () => typeForm.reset(),
    });
};

const updateType = (type: any) => {
    router.put(
        '/admin/constancias/tipos/' + type.id,
        {
            key: type.key,
            label: type.label,
            event_kind: type.event_kind,
            kind: type.kind ?? null,
            role: type.role,
            is_active: type.is_active,
        },
        { preserveScroll: true },
    );
};

const deleteType = (id: number) => {
    if (confirm('¿Eliminar este tipo de participación?')) {
        router.delete('/admin/constancias/tipos/' + id, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Constancias', href: '/constancias' },
            { title: 'Tipos', href: '/admin/constancias/tipos' },
        ]"
    >
        <Head title="Tipos de Participación" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white">
                        Tipos de Participación
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Catálogo de perfiles que reciben constancias. Agregar un
                        tipo nuevo no requiere cambios de código.
                    </p>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-5">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
                        Nuevo tipo
                    </h3>
                    <form @submit.prevent="saveType" class="space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Clave (key) *
                            </label>
                            <input
                                v-model="typeForm.key"
                                type="text"
                                required
                                placeholder="ej. conferencia_magistral"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            <p v-if="typeForm.errors.key" class="mt-1 text-xs text-red-500">
                                {{ typeForm.errors.key }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Etiqueta *
                            </label>
                            <input
                                v-model="typeForm.label"
                                type="text"
                                required
                                placeholder="ej. Conferencista magistral"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            <p v-if="typeForm.errors.label" class="mt-1 text-xs text-red-500">
                                {{ typeForm.errors.label }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Tipo de evento *
                                </label>
                                <select
                                    v-model="typeForm.event_kind"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option value="workshop">Taller</option>
                                    <option value="presentation">Ponencia</option>
                                    <option value="conference">Conferencia</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Rol *
                                </label>
                                <select
                                    v-model="typeForm.role"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option value="enrolled_attendance">Asistente</option>
                                    <option value="instructor">Instructor</option>
                                    <option value="presented_author">Ponente presentado</option>
                                    <option value="speaker">Speaker</option>
                                    <option value="moderator">Moderador</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Sub-tipo (kind) <span class="text-gray-400">— opcional</span>
                            </label>
                            <input
                                v-model="typeForm.kind"
                                type="text"
                                placeholder="ej. magistral, especial, simposio, mesa_dialogo"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            <p class="mt-1 text-[11px] text-gray-400">
                                Si se deja vacío aplica a cualquier sub-tipo del
                                evento.
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="typeForm.processing"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        >
                            <Plus class="h-4 w-4" /> Crear tipo
                        </button>
                    </form>
                </div>

                <div
                    class="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-3 dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                            <thead class="bg-gray-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                        Tipo
                                    </th>
                                    <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                        Evento
                                    </th>
                                    <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                        Plantillas
                                    </th>
                                    <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                        Estado
                                    </th>
                                    <th class="relative px-5 py-3">
                                        <span class="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="type in participationTypes" :key="type.id">
                                    <td class="px-5 py-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ type.label }}
                                        </div>
                                        <div class="text-xs text-gray-400">{{ type.key }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ eventKindLabel(type.event_kind) }}
                                        <span v-if="type.kind" class="text-gray-400">
                                            · {{ kindLabel(type.kind) }}
                                        </span>
                                        ·
                                        {{ roleLabel(type.role) }}
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ type.templates_count }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <label class="inline-flex cursor-pointer items-center">
                                            <input
                                                type="checkbox"
                                                :checked="type.is_active"
                                                @change="
                                                    updateType({
                                                        ...type,
                                                        is_active: $event.target.checked,
                                                    })
                                                "
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span class="ml-2 text-xs text-gray-500">
                                                {{ type.is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </label>
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <button
                                            @click="deleteType(type.id)"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
