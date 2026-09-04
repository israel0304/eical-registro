<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Calendar,
    CalendarPlus,
    List,
    Pencil,
    Printer,
    Trash2,
    Code,
    LayoutTemplate,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
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
import ProgramCalendar, {
    type ActivityPerson,
    type CalendarItem,
} from '@/pages/Programa/ProgramCalendar.vue';

const props = defineProps<{
    groups: {
        label: string;
        items: {
            id: number;
            title: string;
            location: string | null;
            day: string | null;
            start_time: string | null;
            end_time: string | null;
            time_label: string;
            activity_type: string | null;
            block_type: string | null;
            block_label: string | null;
            activity_label: string | null;
            activity_name: string | null;
            kind: 'activity' | 'block';
            details: Record<string, any>;
        }[];
    }[];
    eventName: string;
    days: string[];
    blockTypes: Record<string, string>;
    canManage: boolean;
    canPrint: boolean;
    canManageTemplates: boolean;
}>();

const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const editing = computed(() => editingId.value !== null);
const embedOpen = ref(false);
const embedCopied = ref(false);
const embedCode = `<iframe src="${window.location.origin}/programa/publico" width="100%" height="800" frameborder="0" style="border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></iframe>`;

const copyEmbedCode = () => {
    navigator.clipboard.writeText(embedCode);
    embedCopied.value = true;
    setTimeout(() => (embedCopied.value = false), 2000);
};

const detailId = ref<number | null>(null);
const detailOpen = computed(() => detailId.value !== null);

const viewMode = ref<'list' | 'calendar'>('calendar');
const showList = computed(() => viewMode.value === 'list');
const showCalendar = computed(() => viewMode.value === 'calendar');

const calendarItems = computed<CalendarItem[]>(() =>
    props.groups.flatMap((g) => g.items),
);

const detailItem = computed<CalendarItem | null>(() => {
    if (detailId.value === null) return null;

    return calendarItems.value.find((i) => i.id === detailId.value) ?? null;
});

const resumen = computed<string | null>(() => {
    const details = detailItem.value?.details;

    return details?.description ?? details?.abstract ?? null;
});

const kindBadge = computed<string | null>(
    () => detailItem.value?.details?.kind_label ?? null,
);

const extraFacts = computed<{ label: string; value: string }[]>(() => {
    const details = detailItem.value?.details;
    if (!details) return [];

    const facts: { label: string; value: string }[] = [];

    if (details.capacity != null) {
        facts.push({ label: 'Capacidad', value: String(details.capacity) });
    }
    if (details.available_spots != null) {
        facts.push({
            label: 'Lugares disponibles',
            value: String(details.available_spots),
        });
    }
    if (details.discipline) {
        facts.push({ label: 'Disciplina', value: details.discipline });
    }
    if (details.keywords) {
        facts.push({ label: 'Palabras clave', value: details.keywords });
    }

    return facts;
});

const peopleSections = computed<{ label: string; people: ActivityPerson[] }[]>(
    () => {
        const details = detailItem.value?.details;
        if (!details) return [];

        const sections: { label: string; people: ActivityPerson[] }[] = [];

        if (details.instructors?.length) {
            sections.push({
                label: 'Instructores',
                people: details.instructors,
            });
        }
        if (details.authors?.length) {
            sections.push({ label: 'Ponentes', people: details.authors });
        }
        if (details.speakers?.length) {
            sections.push({ label: 'Expositores', people: details.speakers });
        }
        if (details.moderators?.length) {
            sections.push({
                label: 'Moderador(es)',
                people: details.moderators,
            });
        }

        return sections;
    },
);

const formatDay = (day: string | null): string => {
    if (!day) return '—';

    const date = new Date(`${day}T00:00:00`);

    if (Number.isNaN(date.getTime())) return day;

    const formatted = date.toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
};

const openDetail = (item: any) => {
    detailId.value = item.id;
};

const closeDetail = () => {
    detailId.value = null;
};

const editFromDetail = () => {
    if (detailItem.value === null) return;
    openEdit(detailItem.value);
    closeDetail();
};

const removeFromDetail = () => {
    if (detailItem.value === null) return;
    remove(detailItem.value);
    closeDetail();
};

const editingItem = computed(() => {
    if (editingId.value === null) return null;
    return (
        props.groups
            .flatMap((g) => g.items)
            .find((i) => i.id === editingId.value) ?? null
    );
});

const linkedEdit = computed(() => editingItem.value?.kind === 'activity');

const form = useForm({
    day: '',
    block_type: '',
    title: '',
    start_time: '',
    end_time: '',
    location: '',
    moderator_ids: [] as number[],
});

const dayOptions = computed<string[]>(() => props.days);

const openCreate = (day?: string, time?: string) => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    selectedModerators.value = [];
    form.moderator_ids = [];
    if (day) form.day = day;
    if (time) form.start_time = time;
    modalOpen.value = true;
};

const openEdit = (item: any) => {
    editingId.value = item.id;
    form.clearErrors();
    form.day = item.day ?? '';
    form.block_type = item.block_type ?? '';
    form.title = item.title ?? '';
    form.start_time = item.start_time ?? '';
    form.end_time = item.end_time ?? '';
    form.location = item.location ?? '';
    selectedModerators.value = [...(item.details?.moderators ?? [])];
    form.moderator_ids = selectedModerators.value.map(
        (m: any) => m.id as number,
    );
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    selectedModerators.value = [];
    form.moderator_ids = [];
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

const save = () => {
    const options = {
        preserveScroll: true,
        onSuccess: closeModal,
    };
    if (editing.value) {
        form.put('/programa/' + editingId.value, options);
    } else {
        form.post('/programa', options);
    }
};

const remove = (item: any) => {
    if (confirm(`¿Eliminar el bloque "${item.title}" del programa?`)) {
        router.delete('/programa/' + item.id, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Programa', href: '/programa' }]">
        <Head title="Programa del Evento" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Programa del Evento
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ eventName }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <button
                            type="button"
                            @click="viewMode = 'list'"
                            :class="
                                viewMode === 'list'
                                    ? 'bg-white text-gray-900 shadow-sm dark:bg-zinc-700 dark:text-white'
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            "
                            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                        >
                            <List class="h-4 w-4" />
                            Lista
                        </button>
                        <button
                            type="button"
                            @click="viewMode = 'calendar'"
                            :class="
                                viewMode === 'calendar'
                                    ? 'bg-white text-gray-900 shadow-sm dark:bg-zinc-700 dark:text-white'
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            "
                            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                        >
                            <Calendar class="h-4 w-4" />
                            Calendario
                        </button>
                    </div>
                    <Button
                        v-if="canPrint"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a href="/programa/imprimir" target="_blank">
                            <Printer class="h-4 w-4" />
                            Imprimir
                        </a>
                    </Button>
                    <Button
                        v-if="canManageTemplates"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="'/programa/plantillas'">
                            <LayoutTemplate class="h-4 w-4" />
                            Plantilla
                        </Link>
                    </Button>
                    <Button
                        v-if="canManage"
                        variant="outline"
                        size="sm"
                        @click="embedOpen = true"
                    >
                        <Code class="h-4 w-4" />
                        Embed
                    </Button>
                    <Button v-if="canManage" size="sm" @click="openCreate()">
                        <CalendarPlus class="h-4 w-4" />
                        Agregar bloque
                    </Button>
                </div>
            </div>

            <div v-if="showList" class="space-y-4">
                <div v-if="groups.length === 0" class="py-16 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Aún no hay actividades con horario en el programa.
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Los talleres, ponencias y conferencias se agregan
                        automáticamente al asignarles día y horario.
                    </p>
                </div>

                <div
                    v-for="group in groups"
                    :key="group.label"
                    class="space-y-2"
                >
                    <h2
                        class="text-sm font-bold tracking-wider text-indigo-700 uppercase dark:text-indigo-300"
                    >
                        {{ group.label }}
                    </h2>
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
                                            Hora
                                        </th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                        >
                                            Actividad
                                        </th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                        >
                                            Ubicación
                                        </th>
                                        <th
                                            v-if="canManage"
                                            class="relative px-5 py-3"
                                        >
                                            <span class="sr-only"
                                                >Acciones</span
                                            >
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-gray-100 dark:divide-zinc-800"
                                >
                                    <tr
                                        v-for="item in group.items"
                                        :key="item.id"
                                        @click="openDetail(item)"
                                        class="cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-zinc-800/50"
                                    >
                                        <td
                                            class="px-5 py-3 text-sm whitespace-nowrap text-gray-600 tabular-nums dark:text-gray-400"
                                        >
                                            {{ item.time_label || '—' }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <span
                                                v-if="
                                                    item.kind === 'activity' &&
                                                    item.activity_label
                                                "
                                                class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300"
                                            >
                                                {{ item.activity_label }}
                                            </span>
                                            <span
                                                v-else-if="item.block_label"
                                                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                                            >
                                                {{ item.block_label }}
                                            </span>
                                            <div
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ item.title }}
                                            </div>
                                            <div
                                                v-if="item.activity_name"
                                                class="text-xs text-gray-400"
                                            >
                                                {{ item.activity_name }}
                                            </div>
                                        </td>
                                        <td
                                            class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            {{ item.location || '—' }}
                                        </td>
                                        <td
                                            v-if="canManage"
                                            class="px-5 py-3 text-right whitespace-nowrap"
                                        >
                                            <button
                                                @click.stop="openEdit(item)"
                                                class="mr-1 rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-indigo-400"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                            <button
                                                v-if="item.kind === 'block'"
                                                @click.stop="remove(item)"
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

            <ProgramCalendar
                v-if="showCalendar"
                :items="calendarItems"
                :days="days"
                :can-manage="canManage"
                @detail="openDetail"
                @create="(day, time) => openCreate(day, time)"
                @show-list="viewMode = 'list'"
            />

            <Dialog
                :open="modalOpen"
                @update:open="(open) => (open ? null : closeModal())"
            >
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editing
                                    ? linkedEdit
                                        ? 'Editar horario de la actividad'
                                        : 'Editar bloque'
                                    : 'Agregar bloque al programa'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                linkedEdit
                                    ? 'Los cambios se reflejarán también en la actividad original.'
                                    : editing
                                      ? 'Modifica los datos del bloque.'
                                      : 'Ej. registro, inauguración, receso, clausura.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="save" class="mt-4 space-y-3">
                        <template v-if="linkedEdit">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Actividad
                                </label>
                                <input
                                    :value="
                                        editingItem?.title ||
                                        'Actividad enlazada'
                                    "
                                    type="text"
                                    disabled
                                    class="w-full rounded-md border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400"
                                />
                                <p class="mt-1 text-[11px] text-gray-400">
                                    El título viene de la actividad original.
                                </p>
                            </div>
                        </template>

                        <template v-else>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Tipo de bloque *
                                </label>
                                <select
                                    v-model="form.block_type"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option value="" disabled>
                                        Selecciona el tipo
                                    </option>
                                    <option
                                        v-for="[value, label] in Object.entries(
                                            blockTypes,
                                        )"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.block_type"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ form.errors.block_type }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Título *
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    required
                                    placeholder="ej. Registro de asistentes"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p
                                    v-if="form.errors.title"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ form.errors.title }}
                                </p>
                            </div>
                        </template>

                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Día *
                            </label>
                            <select
                                v-model="form.day"
                                required
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            >
                                <option value="" disabled>
                                    Selecciona un día
                                </option>
                                <option
                                    v-for="day in dayOptions"
                                    :key="day"
                                    :value="day"
                                >
                                    {{ day }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.day"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ form.errors.day }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Hora inicio
                                    <span v-if="linkedEdit" class="text-red-400"
                                        >*</span
                                    >
                                </label>
                                <input
                                    v-model="form.start_time"
                                    type="time"
                                    :required="linkedEdit"
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
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Hora fin
                                    <span v-if="linkedEdit" class="text-red-400"
                                        >*</span
                                    >
                                </label>
                                <input
                                    v-model="form.end_time"
                                    type="time"
                                    :required="linkedEdit"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p
                                    v-if="form.errors.end_time"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ form.errors.end_time }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Ubicación
                            </label>
                            <input
                                v-model="form.location"
                                type="text"
                                placeholder="ej. Auditorio principal"
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
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Moderadores
                            </label>
                            <p
                                class="mb-2 text-[11px] text-gray-400 dark:text-gray-500"
                            >
                                Personas que moderarán esta actividad o bloque
                                (opcional).
                            </p>

                            <div
                                v-if="selectedModerators.length > 0"
                                class="mb-2 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="(mod, idx) in selectedModerators"
                                    :key="mod.id"
                                    class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                >
                                    {{ mod.name || (mod.first_name + ' ' + mod.last_name).trim() }}
                                    <button
                                        type="button"
                                        @click="removeModerator(idx)"
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
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                    @focus="
                                        searchModerators(moderatorSearchQuery)
                                    "
                                    type="text"
                                    placeholder="Buscar moderadores por nombre o correo..."
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <div
                                    v-if="
                                        showModeratorSearchResults &&
                                        moderatorSearchResults.length > 0
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
                                        <span
                                            >{{ user.first_name }}
                                            {{ user.last_name }}</span
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
                                        @click="showCreateModerator = true"
                                        class="flex w-full items-center gap-2 border-t border-gray-100 px-3 py-2 text-left text-sm font-medium text-indigo-600 hover:bg-gray-50 dark:border-zinc-700 dark:text-indigo-400 dark:hover:bg-zinc-700"
                                    >
                                        <UserPlus class="h-3.5 w-3.5" />
                                        Registrar nuevo moderador
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-2">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cancelar
                                </Button>
                            </DialogClose>
                            <Button type="submit" :disabled="form.processing">
                                {{
                                    editing
                                        ? 'Guardar cambios'
                                        : 'Agregar bloque'
                                }}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="detailOpen"
                @update:open="(open) => (open ? null : closeDetail())"
            >
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <template v-if="detailItem">
                        <DialogHeader>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    v-if="
                                        detailItem.kind === 'activity' &&
                                        detailItem.activity_label
                                    "
                                    class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300"
                                >
                                    {{ detailItem.activity_label }}
                                </span>
                                <span
                                    v-else-if="detailItem.block_label"
                                    class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                                >
                                    {{ detailItem.block_label }}
                                </span>
                                <span
                                    v-if="kindBadge"
                                    class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 dark:border-violet-800 dark:bg-violet-900/30 dark:text-violet-300"
                                >
                                    {{ kindBadge }}
                                </span>
                            </div>
                            <DialogTitle class="text-xl">
                                {{ detailItem.title }}
                            </DialogTitle>
                        </DialogHeader>

                        <div class="mt-4 space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div
                                    class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50"
                                >
                                    <p
                                        class="text-[11px] font-medium text-gray-400 uppercase"
                                    >
                                        Día
                                    </p>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-100"
                                    >
                                        {{ formatDay(detailItem.day) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50"
                                >
                                    <p
                                        class="text-[11px] font-medium text-gray-400 uppercase"
                                    >
                                        Hora
                                    </p>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-gray-800 tabular-nums dark:text-gray-100"
                                    >
                                        {{ detailItem.time_label || '—' }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50"
                                >
                                    <p
                                        class="text-[11px] font-medium text-gray-400 uppercase"
                                    >
                                        Ubicación
                                    </p>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-100"
                                    >
                                        {{ detailItem.location || '—' }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="resumen">
                                <h3
                                    class="mb-1 text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Resumen
                                </h3>
                                <p
                                    class="text-sm leading-relaxed whitespace-pre-wrap text-gray-700 dark:text-gray-300"
                                >
                                    {{ resumen }}
                                </p>
                            </div>

                            <div
                                v-if="extraFacts.length > 0"
                                class="grid grid-cols-2 gap-3"
                            >
                                <div
                                    v-for="fact in extraFacts"
                                    :key="fact.label"
                                    class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50"
                                >
                                    <p
                                        class="text-[11px] font-medium text-gray-400 uppercase"
                                    >
                                        {{ fact.label }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-100"
                                    >
                                        {{ fact.value }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-for="section in peopleSections"
                                :key="section.label"
                            >
                                <h3
                                    class="mb-2 text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    {{ section.label }}
                                </h3>
                                <ul class="space-y-2">
                                    <li
                                        v-for="(
                                            person, index
                                        ) in section.people"
                                        :key="index"
                                        class="flex items-start gap-2.5"
                                    >
                                        <span
                                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                        >
                                            {{
                                                person.name
                                                    .split(' ')
                                                    .slice(0, 2)
                                                    .map((n) => n.charAt(0))
                                                    .join('')
                                                    .toUpperCase()
                                            }}
                                        </span>
                                        <div class="min-w-0">
                                            <p
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ person.name }}
                                            </p>
                                            <p
                                                v-if="person.affiliation"
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                {{ person.affiliation }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-2">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cerrar
                                </Button>
                            </DialogClose>
                            <Button
                                v-if="canManage && detailItem.kind === 'block'"
                                type="button"
                                variant="outline"
                                class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                @click="removeFromDetail"
                            >
                                <Trash2 class="h-4 w-4" />
                                Eliminar
                            </Button>
                            <Button
                                v-if="canManage"
                                type="button"
                                @click="editFromDetail"
                            >
                                <Pencil class="h-4 w-4" />
                                Editar
                            </Button>
                        </div>
                    </template>
                </DialogContent>
            </Dialog>

            <!-- Embed Modal -->
            <Dialog
                :open="embedOpen"
                @update:open="(open) => (embedOpen = open)"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Codigo de incrustacion</DialogTitle>
                        <DialogDescription>
                            Copia el siguiente codigo HTML para incrustar el
                            programa del evento en tu pagina web.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-4">
                        <pre
                            class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs break-all whitespace-pre-wrap text-gray-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >{{ embedCode }}</pre
                        >
                        <div class="mt-3 flex justify-end gap-3">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                >
                                    Cerrar
                                </Button>
                            </DialogClose>
                            <Button
                                type="button"
                                size="sm"
                                @click="copyEmbedCode"
                            >
                                {{ embedCopied ? 'Copiado!' : 'Copiar codigo' }}
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Nuevo moderador modal -->
            <Dialog
                :open="showCreateModerator"
                @update:open="(open) => (showCreateModerator = open)"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Nuevo Moderador</DialogTitle>
                        <DialogDescription>
                            El moderador se agregará a esta actividad o bloque.
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="createModerator" class="space-y-4">
                        <div
                            v-if="Object.keys(newModeratorErrors).length > 0"
                            class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            <p class="font-medium">
                                Corrige los siguientes errores:
                            </p>
                            <ul class="mt-1 list-inside list-disc">
                                <li
                                    v-for="(
                                        message, key
                                    ) in newModeratorErrors"
                                    :key="key"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                        </div>

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

                        <div class="flex justify-end gap-3">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cancelar
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                :disabled="creatingModerator"
                            >
                                {{ creatingModerator ? 'Guardando...' : 'Guardar' }}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
