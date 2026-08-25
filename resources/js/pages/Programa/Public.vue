<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Calendar, List, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
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
}>();

const viewMode = ref<'list' | 'calendar'>('list');
const showList = computed(() => viewMode.value === 'list');
const detailId = ref<number | null>(null);
const detailOpen = computed(() => detailId.value !== null);

const allItems = computed(() =>
    props.groups.flatMap((g) => g.items),
);

const calendarItems = computed<CalendarItem[]>(() =>
    allItems.value.map((item) => ({
        ...item,
        details: item.details ?? {},
    })),
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
            sections.push({ label: 'Instructores', people: details.instructors });
        }
        if (details.authors?.length) {
            sections.push({ label: 'Ponentes', people: details.authors });
        }
        if (details.speakers?.length) {
            sections.push({ label: 'Expositores', people: details.speakers });
        }
        if (details.moderators?.length) {
            sections.push({ label: 'Moderador(es)', people: details.moderators });
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
</script>

<template>
    <Head :title="`${eventName} - Programa`" />

    <div class="min-h-screen bg-white dark:bg-zinc-950">
        <header class="border-b border-gray-200 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-5xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                        Programa del Evento
                    </h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
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
                    <Button variant="outline" size="sm" @click="window.print()">
                        <Printer class="h-4 w-4" />
                        Imprimir
                    </Button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-6">
            <!-- Lista -->
            <div v-if="showList" class="space-y-4">
                <div v-if="groups.length === 0" class="py-16 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Aun no hay actividades en el programa.
                    </p>
                </div>

                <div
                    v-for="group in groups"
                    :key="group.label"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">
                            {{ group.label }}
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                            <thead class="bg-gray-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                        Hora
                                    </th>
                                    <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                        Actividad
                                    </th>
                                    <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                        Ubicacion
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr
                                    v-for="item in group.items"
                                    :key="item.id"
                                    @click="openDetail(item)"
                                    class="cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-zinc-800/50"
                                >
                                    <td class="px-5 py-3 text-sm whitespace-nowrap text-gray-600 tabular-nums dark:text-gray-400">
                                        {{ item.time_label || '---' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span
                                            v-if="item.kind === 'activity' && item.activity_label"
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
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ item.title }}
                                        </div>
                                        <div v-if="item.activity_name" class="text-xs text-gray-400">
                                            {{ item.activity_name }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ item.location || '---' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Calendario -->
            <div v-else>
                <ProgramCalendar
                    :items="calendarItems"
                    :days="days"
                    :can-manage="false"
                    @detail="openDetail"
                />
            </div>
        </main>

        <!-- Detail Modal -->
        <Dialog :open="detailOpen" @update:open="(open) => (open ? null : closeDetail())">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <template v-if="detailItem">
                    <DialogHeader>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="detailItem.kind === 'activity' && detailItem.activity_label"
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
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <p class="text-[11px] font-medium text-gray-400 uppercase">Dia</p>
                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ formatDay(detailItem.day) }}
                                </p>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <p class="text-[11px] font-medium text-gray-400 uppercase">Hora</p>
                                <p class="mt-0.5 text-sm font-medium text-gray-800 tabular-nums dark:text-gray-100">
                                    {{ detailItem.time_label || '---' }}
                                </p>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <p class="text-[11px] font-medium text-gray-400 uppercase">Ubicacion</p>
                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ detailItem.location || '---' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="resumen">
                            <h3 class="mb-1 text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                Resumen
                            </h3>
                            <p class="text-sm leading-relaxed whitespace-pre-wrap text-gray-700 dark:text-gray-300">
                                {{ resumen }}
                            </p>
                        </div>

                        <div v-if="extraFacts.length > 0" class="grid grid-cols-2 gap-3">
                            <div
                                v-for="fact in extraFacts"
                                :key="fact.label"
                                class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50"
                            >
                                <p class="text-[11px] font-medium text-gray-400 uppercase">{{ fact.label }}</p>
                                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-100">{{ fact.value }}</p>
                            </div>
                        </div>

                        <div v-for="section in peopleSections" :key="section.label">
                            <h3 class="mb-2 text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                {{ section.label }}
                            </h3>
                            <ul class="space-y-2">
                                <li v-for="(person, index) in section.people" :key="index" class="flex items-start gap-2.5">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        {{ person.name.split(' ').slice(0, 2).map((n) => n.charAt(0)).join('').toUpperCase() }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ person.name }}</p>
                                        <p v-if="person.affiliation" class="text-xs text-gray-500 dark:text-gray-400">{{ person.affiliation }}</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end pt-2">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">Cerrar</Button>
                        </DialogClose>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
@media print {
    header { display: none !important; }
    main { padding: 0 !important; }
    .space-y-4 > div { break-inside: avoid; }
}
</style>
