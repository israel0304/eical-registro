<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    participationTypes: any[];
    catalog: {
        event_kinds: Record<string, string>;
        roles: Record<string, string>;
        kinds: Record<string, Record<string, string>>;
        role_rules: Record<string, string[]>;
    };
}>();

const eventKindLabel = (kind: string) =>
    props.catalog.event_kinds[kind] ?? kind;

const roleLabel = (role: string | null) =>
    role ? (props.catalog.roles[role] ?? role) : '—';

const kindLabel = (kind: string | null) => {
    if (!kind) return '—';
    for (const group of Object.values(props.catalog.kinds)) {
        if (group[kind]) return group[kind];
    }
    return kind;
};

const kindsFor = (eventKind: string) =>
    Object.entries(props.catalog.kinds[eventKind] ?? {});

const allowedRolesFor = (eventKind: string) =>
    props.catalog.role_rules[eventKind] ?? [];

const roleOptions = computed(() =>
    Object.entries(props.catalog.roles).filter(([value]) =>
        allowedRolesFor(typeForm.event_kind).includes(value),
    ),
);

const typeForm = useForm({
    key: '',
    label: '',
    event_kind: 'workshop',
    kind: '',
    role: 'enrolled_attendance',
    is_active: true,
    manual_generable: false,
});

const editingId = ref<number | null>(null);
const editing = computed(() => editingId.value !== null);
const keyTouched = ref(false);
const modalOpen = ref(false);

const slugify = (s: string) =>
    s
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

watch(
    () => typeForm.label,
    (label) => {
        if (!keyTouched.value && !editing.value) {
            typeForm.key = slugify(label);
        }
    },
);

const onKeyInput = () => {
    keyTouched.value = true;
};

const saveType = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            typeForm.reset();
            editingId.value = null;
            keyTouched.value = false;
            modalOpen.value = false;
        },
    };
    if (editing.value) {
        typeForm.put('/admin/constancias/tipos/' + editingId.value, options);
    } else {
        typeForm.post('/admin/constancias/tipos', options);
    }
};

const openCreate = () => {
    editingId.value = null;
    typeForm.reset();
    typeForm.clearErrors();
    keyTouched.value = false;
    modalOpen.value = true;
};

const editType = (type: any) => {
    editingId.value = type.id;
    typeForm.clearErrors();
    typeForm.key = type.key;
    typeForm.label = type.label;
    typeForm.event_kind = type.event_kind;
    typeForm.kind = type.kind ?? '';
    typeForm.role = type.role ?? '';
    typeForm.is_active = type.is_active;
    typeForm.manual_generable = type.manual_generable;
    keyTouched.value = true;
    modalOpen.value = true;
};

const cancelEdit = () => {
    modalOpen.value = false;
    editingId.value = null;
    typeForm.reset();
    typeForm.clearErrors();
    keyTouched.value = false;
};

const toggleActive = (type: any, checked: boolean) => {
    router.put(
        '/admin/constancias/tipos/' + type.id,
        {
            key: type.key,
            label: type.label,
            event_kind: type.event_kind,
            kind: type.kind ?? null,
            role: type.role,
            is_active: checked,
        },
        { preserveScroll: true },
    );
};

const deleteType = (type: any) => {
    const refs = type.templates_count + type.certificates_count;
    if (refs > 0) {
        alert(
            `No se puede eliminar este tipo: tiene ${type.templates_count} plantilla(s) y ${type.certificates_count} constancia(s) asociadas.`,
        );
        return;
    }
    if (confirm('¿Eliminar este tipo de participación?')) {
        router.delete('/admin/constancias/tipos/' + type.id, {
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
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Tipos de Participación
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Catálogo de perfiles que reciben constancias. Agregar un
                        tipo nuevo no requiere cambios de código.
                    </p>
                </div>
                <Button variant="default" size="sm" @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Nuevo tipo
                </Button>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800"
                    >
                        <thead class="bg-gray-50 dark:bg-zinc-800/50">
                            <tr>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Tipo
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Evento
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Plantillas
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Constancias
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Manual
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Estado
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
                                v-for="type in participationTypes"
                                :key="type.id"
                            >
                                <td class="px-5 py-3">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ type.label }}
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ type.key }}
                                    </div>
                                </td>
                                <td
                                    class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ eventKindLabel(type.event_kind) }}
                                    <span
                                        v-if="type.kind"
                                        class="text-gray-400"
                                    >
                                        · {{ kindLabel(type.kind) }}
                                    </span>
                                    ·
                                    {{ roleLabel(type.role) }}
                                </td>
                                <td
                                    class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ type.templates_count }}
                                </td>
                                <td
                                    class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ type.certificates_count }}
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        v-if="type.manual_generable"
                                        class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                                    >
                                        Sí
                                    </span>
                                    <span v-else class="text-xs text-gray-400"
                                        >—</span
                                    >
                                </td>
                                <td class="px-5 py-3">
                                    <label
                                        class="inline-flex cursor-pointer items-center"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="type.is_active"
                                            @change="
                                                toggleActive(
                                                    type,
                                                    $event.target.checked,
                                                )
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span
                                            class="ml-2 text-xs text-gray-500"
                                        >
                                            {{
                                                type.is_active
                                                    ? 'Activo'
                                                    : 'Inactivo'
                                            }}
                                        </span>
                                    </label>
                                </td>
                                <td
                                    class="px-5 py-3 text-right whitespace-nowrap"
                                >
                                    <button
                                        @click="editType(type)"
                                        class="mr-1 rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-indigo-400"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="deleteType(type)"
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

            <Dialog
                :open="modalOpen"
                @update:open="(open) => (open ? null : cancelEdit())"
            >
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{
                            editing ? 'Editar tipo' : 'Nuevo tipo'
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                editing
                                    ? 'Modifica los datos del tipo de participación.'
                                    : 'Crea un nuevo tipo de participación.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="saveType" class="mt-4 space-y-3">
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Clave (key) *
                            </label>
                            <input
                                v-model="typeForm.key"
                                @input="onKeyInput"
                                type="text"
                                required
                                placeholder="ej. conferencia_magistral"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            <p
                                v-if="typeForm.errors.key"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ typeForm.errors.key }}
                            </p>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Etiqueta *
                            </label>
                            <input
                                v-model="typeForm.label"
                                type="text"
                                required
                                placeholder="ej. Conferencista magistral"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            <p
                                v-if="typeForm.errors.label"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ typeForm.errors.label }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Tipo de evento *
                                </label>
                                <select
                                    v-model="typeForm.event_kind"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option
                                        v-for="[value, label] in Object.entries(
                                            catalog.event_kinds,
                                        )"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <p
                                    v-if="typeForm.errors.event_kind"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ typeForm.errors.event_kind }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Rol *
                                </label>
                                <select
                                    v-model="typeForm.role"
                                    :required="
                                        allowedRolesFor(typeForm.event_kind)
                                            .length > 0
                                    "
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option
                                        v-if="
                                            allowedRolesFor(typeForm.event_kind)
                                                .length === 0
                                        "
                                        value=""
                                    >
                                        Sin rol
                                    </option>
                                    <option v-else value="" disabled>
                                        Selecciona un rol
                                    </option>
                                    <option
                                        v-for="[value, label] in roleOptions"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <p
                                    v-if="typeForm.errors.role"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ typeForm.errors.role }}
                                </p>
                            </div>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Sub-tipo (kind)
                                <span class="text-gray-400">— opcional</span>
                            </label>
                            <select
                                v-model="typeForm.kind"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            >
                                <option value="">
                                    Ninguno (aplica a todos)
                                </option>
                                <option
                                    v-if="
                                        typeForm.kind &&
                                        !kindsFor(typeForm.event_kind).some(
                                            ([value]) =>
                                                value === typeForm.kind,
                                        )
                                    "
                                    :value="typeForm.kind"
                                >
                                    {{ kindLabel(typeForm.kind) }}
                                </option>
                                <option
                                    v-for="[value, label] in kindsFor(
                                        typeForm.event_kind,
                                    )"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                            <p
                                v-if="typeForm.errors.kind"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ typeForm.errors.kind }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-400">
                                Si se deja vacío aplica a cualquier sub-tipo del
                                evento.
                            </p>
                        </div>
                        <label
                            class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            <input
                                type="checkbox"
                                v-model="typeForm.manual_generable"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span
                                class="text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                Generable manualmente (admin)
                            </span>
                        </label>
                        <p class="text-[11px] text-gray-400">
                            Los tipos marcados aparecen en el botón "Constancia"
                            de la sección de usuarios para generarse a mano.
                        </p>

                        <div class="mt-6 flex justify-end gap-3 pt-2">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cancelar
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                :disabled="typeForm.processing"
                            >
                                {{ editing ? 'Guardar cambios' : 'Crear tipo' }}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
