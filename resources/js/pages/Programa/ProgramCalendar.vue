<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

export interface ActivityPerson {
    name: string;
    affiliation: string | null;
}

export interface ActivityDetails {
    description?: string | null;
    abstract?: string | null;
    capacity?: number | null;
    enrolled_count?: number;
    available_spots?: number;
    discipline?: string | null;
    keywords?: string | null;
    kind?: string | null;
    kind_label?: string | null;
    instructors?: ActivityPerson[];
    authors?: ActivityPerson[];
    speakers?: ActivityPerson[];
    moderators?: ActivityPerson[];
}

export interface CalendarItem {
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
    details: ActivityDetails;
}

const props = defineProps<{
    items: CalendarItem[];
    days: string[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    (e: 'detail', item: CalendarItem): void;
    (e: 'create', day: string, time: string): void;
    (e: 'show-list'): void;
}>();

const calendarReady = ref(false);
const FullCalendarComp = ref<any>(null);
const plugins = ref<any[]>([]);
const locales = ref<any[]>([]);

onMounted(async () => {
    const [
        { default: FullCalendar },
        { default: dayGridPlugin },
        { default: timeGridPlugin },
        { default: interactionPlugin },
        esLocale,
    ] = await Promise.all([
        import('@fullcalendar/vue3'),
        import('@fullcalendar/daygrid'),
        import('@fullcalendar/timegrid'),
        import('@fullcalendar/interaction'),
        import('@fullcalendar/core/locales/es'),
    ]);

    FullCalendarComp.value = FullCalendar;
    plugins.value = [dayGridPlugin, timeGridPlugin, interactionPlugin];
    locales.value = [esLocale];
    calendarReady.value = true;
});

const pad = (n: number) => String(n).padStart(2, '0');
const toMinutes = (t: string) => {
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
};
const formatMinutes = (mins: number) =>
    `${pad(Math.floor(mins / 60))}:${pad(mins % 60)}:00`;
const toDay = (d: Date) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fmtTime = (d: Date) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const roundTime = (d: Date) => {
    const mins = d.getMinutes();

    return `${pad(d.getHours())}:${pad(mins < 30 ? 0 : 30)}`;
};

const firstDay = computed(() => props.days[0] ?? undefined);
const lastDay = computed(() => props.days[props.days.length - 1] ?? undefined);

const initialDate = computed(() =>
    firstDay.value ? `${firstDay.value}T00:00:00` : undefined,
);

const validRange = computed(() => {
    if (!firstDay.value || !lastDay.value) return undefined;

    const end = new Date(`${lastDay.value}T00:00:00`);
    end.setDate(end.getDate() + 1);

    return {
        start: `${firstDay.value}T00:00:00`,
        end: end.toISOString().slice(0, 10),
    };
});

const hiddenDays = computed(() => {
    if (props.days.length === 0) return [0, 1, 2, 3, 4, 5, 6];

    const present = new Set(
        props.days.map((d) => new Date(`${d}T00:00:00`).getDay()),
    );

    return [0, 1, 2, 3, 4, 5, 6].filter((w) => !present.has(w));
});

const slotMinTime = computed(() => {
    const minutes = props.items
        .filter((i) => i.start_time)
        .map((i) => toMinutes(i.start_time!));

    if (minutes.length === 0) return '08:00:00';

    return formatMinutes(Math.floor(Math.min(...minutes) / 60) * 60);
});

const slotMaxTime = computed(() => {
    const minutes = props.items
        .filter((i) => i.start_time)
        .map((i) => toMinutes(i.end_time ?? i.start_time!));

    if (minutes.length === 0) return '20:00:00';

    return formatMinutes(Math.ceil((Math.max(...minutes) + 60) / 60) * 60);
});

const calendarEvents = computed(() =>
    props.items
        .filter((i) => i.day && i.start_time)
        .map((item) => {
            const start = `${item.day}T${item.start_time}:00`;
            const end = item.end_time
                ? `${item.day}T${item.end_time}:00`
                : `${item.day}T${item.start_time}:59`;

            return {
                id: String(item.id),
                title: item.title,
                start,
                end,
                editable: props.canManage,
                ...eventColorsFor(item),
                textColor: '#ffffff',
                extendedProps: { item },
            };
        }),
);

const unscheduledCount = computed(
    () => props.items.filter((i) => i.day && !i.start_time).length,
);

const EVENT_COLORS: Record<
    string,
    { backgroundColor: string; borderColor: string; label: string }
> = {
    workshop: {
        backgroundColor: '#0ea5e9',
        borderColor: '#0369a1',
        label: 'Talleres',
    },
    presentation: {
        backgroundColor: '#10b981',
        borderColor: '#047857',
        label: 'Ponencias',
    },
    'conference:magistral': {
        backgroundColor: '#8b5cf6',
        borderColor: '#6d28d9',
        label: 'Conferencias magistrales',
    },
    'conference:especial': {
        backgroundColor: '#d946ef',
        borderColor: '#a21caf',
        label: 'Conferencias especiales',
    },
    'conference:simposio': {
        backgroundColor: '#14b8a6',
        borderColor: '#0f766e',
        label: 'Simposios',
    },
    'conference:mesa_dialogo': {
        backgroundColor: '#f97316',
        borderColor: '#c2410c',
        label: 'Mesas de diálogo',
    },
    conference: {
        backgroundColor: '#6366f1',
        borderColor: '#4338ca',
        label: 'Conferencias',
    },
    block: {
        backgroundColor: '#f59e0b',
        borderColor: '#b45309',
        label: 'Bloques',
    },
};

const eventColorKey = (item: CalendarItem): string => {
    if (item.activity_type === 'conference' && item.details?.kind) {
        return `conference:${item.details.kind}`;
    }

    return item.activity_type ?? 'block';
};

const eventColorsFor = (item: CalendarItem) =>
    EVENT_COLORS[eventColorKey(item)] ??
    (item.kind === 'block' ? EVENT_COLORS.block : EVENT_COLORS.conference);

const legendItems = computed(() => {
    const keys = new Set(props.items.map(eventColorKey));

    return [...keys]
        .map((key) => EVENT_COLORS[key] ?? EVENT_COLORS.conference)
        .map((color, index) => ({ ...color, id: index }));
});

const headerToolbar = {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridWeek,timeGridDay',
};

const buttonText = { today: 'Hoy', week: 'Semana', day: 'Día' };
const dayHeaderFormat = { weekday: 'short', day: 'numeric' };
const titleFormat = { year: 'numeric', month: 'long', day: 'numeric' };
const eventTimeFormat = { hour: '2-digit', minute: '2-digit', hour12: false };

const calendarOptions = computed(() => ({
    plugins: plugins.value,
    locales: locales.value,
    locale: 'es',
    initialView: 'timeGridWeek',
    initialDate: initialDate.value,
    timeZone: 'local',
    events: calendarEvents.value,
    editable: props.canManage,
    selectable: props.canManage,
    slotMinTime: slotMinTime.value,
    slotMaxTime: slotMaxTime.value,
    allDaySlot: false,
    hiddenDays: hiddenDays.value,
    validRange: validRange.value,
    firstDay: 1,
    headerToolbar,
    buttonText,
    dayHeaderFormat,
    titleFormat,
    eventTimeFormat,
    dateClick: onDateClick,
    eventClick: onEventClick,
    eventDrop: onEventDrop,
    eventResize: onEventResize,
}));

const onEventClick = (info: any) => {
    emit('detail', info.event.extendedProps.item as CalendarItem);
};

const onDateClick = (info: any) => {
    if (!props.canManage) return;
    emit('create', toDay(info.date), roundTime(info.date));
};

const persistEventChange = (info: any) => {
    const item: CalendarItem = info.event.extendedProps.item;
    const start: Date = info.event.start;
    const end: Date | null = info.event.end ?? null;

    const payload: Record<string, any> = {
        day: toDay(start),
        start_time: fmtTime(start),
        end_time: end ? fmtTime(end) : null,
        location: item.location ?? null,
    };

    if (item.kind === 'block') {
        payload.title = item.title;
        payload.block_type = item.block_type;
    }

    router.put(`/programa/${item.id}`, payload, {
        preserveScroll: true,
        onError: () => info.revert(),
    });
};

const onEventDrop = (info: any) => persistEventChange(info);
const onEventResize = (info: any) => persistEventChange(info);
</script>

<template>
    <div class="program-calendar space-y-3">
        <div
            v-if="unscheduledCount > 0"
            class="flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20"
        >
            <p class="text-sm text-amber-800 dark:text-amber-300">
                {{ unscheduledCount }} actividad(es) tiene(n) día asignado pero
                aún no tienen hora, así que no aparecen en el calendario.
            </p>
            <button
                type="button"
                @click="emit('show-list')"
                class="shrink-0 rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-transparent dark:text-amber-300 dark:hover:bg-amber-900/40"
            >
                Ver en Lista
            </button>
        </div>

        <div
            v-if="calendarReady"
            class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <component :is="FullCalendarComp" :options="calendarOptions" />

            <div
                v-if="legendItems.length > 0"
                class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-gray-100 pt-3 dark:border-zinc-800"
            >
                <span
                    class="text-[11px] font-bold tracking-wider text-gray-400 uppercase"
                >
                    Leyenda
                </span>
                <span
                    v-for="item in legendItems"
                    :key="item.id"
                    class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300"
                >
                    <span
                        class="h-3 w-3 shrink-0 rounded-sm"
                        :style="{ backgroundColor: item.backgroundColor }"
                    ></span>
                    {{ item.label }}
                </span>
            </div>
        </div>

        <div
            v-else
            class="flex h-64 items-center justify-center rounded-xl border border-gray-200 bg-white text-sm text-gray-400 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            Cargando calendario…
        </div>
    </div>
</template>

<style scoped>
.program-calendar :deep(.fc) {
    --fc-border-color: rgb(229 231 235);
    font-family: inherit;
}

.program-calendar :deep(.fc .fc-button-primary) {
    background-color: #4f46e5;
    border-color: #4f46e5;
    font-size: 0.8rem;
    padding: 0.3rem 0.7rem;
    box-shadow: none;
}

.program-calendar :deep(.fc .fc-button-primary:hover) {
    background-color: #4338ca;
    border-color: #4338ca;
}

.program-calendar
    :deep(.fc .fc-button-primary:not(:disabled).fc-button-active) {
    background-color: #3730a3;
    border-color: #3730a3;
}

.program-calendar :deep(.fc .fc-toolbar-title) {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgb(17 24 39);
}

.program-calendar :deep(.fc .fc-col-header-cell-cushion),
.program-calendar :deep(.fc .fc-timegrid-slot-label-cushion) {
    color: rgb(75 85 99);
    text-decoration: none;
}

.program-calendar :deep(.fc .fc-event) {
    border-radius: 6px;
    font-size: 0.78rem;
    cursor: pointer;
    box-shadow: none;
    padding: 1px 2px;
}

.program-calendar :deep(.fc .fc-event-main) {
    white-space: normal;
}
</style>
