<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Download,
    Award,
    BookOpen,
    Mic,
    Clock,
    Users,
    Mail,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();

const props = defineProps<{
    completedWorkshops: any[];
    instructorWorkshops?: any[];
    presentationCertificates?: any[];
    cartaPresentations?: any[];
    conferenceCertificates?: any[];
    eventCertificate?: any;
    eventAttendance?: {
        has: boolean;
        days_attended: number;
        required_days: number;
        total_days: number;
        qualifies: boolean;
        evento_nombre: string;
        fecha_inicio: string | null;
        fecha_fin: string | null;
    };
    invitationLetters?: {
        id: number;
        key: string;
        label: string;
        rol: string;
        folio: string | null;
        downloaded: boolean;
    }[];
    user: any;
}>();

const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;
const canSeePonencias = computed(() => can('constancias.download'));

const downloadCertificate = (workshopId: number) => {
    window.open('/constancias/' + workshopId + '/download', '_blank');
};

const downloadPonencia = (presentationId: number) => {
    window.open(
        '/constancias/ponencia/' + presentationId + '/download',
        '_blank',
    );
};

const downloadConferencia = (conferenceId: number) => {
    window.open(
        '/constancias/conferencia/' + conferenceId + '/download',
        '_blank',
    );
};

const downloadEvento = () => {
    window.open('/constancias/evento/download', '_blank');
};

const downloadInvitacion = (roleId: number) => {
    window.open('/constancias/invitacion/descargar?role=' + roleId, '_blank');
};

const downloadInvitacionPonencia = (presentationId: number) => {
    window.open(
        '/constancias/invitacion/ponencia/' + presentationId + '/download',
        '_blank',
    );
};

const roleLabel = (role: string | null) =>
    ({ speaker: 'Speaker', moderator: 'Moderador' })[role ?? ''] ?? role ?? '';

const hasAnyCertificates = computed(() => {
    return (
        !!props.completedWorkshops?.length ||
        !!props.instructorWorkshops?.length ||
        (!!canSeePonencias.value && !!props.presentationCertificates?.length) ||
        (!!canSeePonencias.value && !!props.cartaPresentations?.length) ||
        !!props.conferenceCertificates?.length ||
        !!props.eventCertificate ||
        !!props.eventAttendance?.has ||
        !!props.invitationLetters?.length
    );
});

const eventProgress = computed(() => {
    const attendance = props.eventAttendance;
    if (!attendance) return 0;
    if (attendance.required_days <= 0) return 0;
    return Math.min(
        100,
        Math.round((attendance.days_attended / attendance.required_days) * 100),
    );
});

const missingDays = computed(() => {
    const attendance = props.eventAttendance;
    if (!attendance) return 0;
    return Math.max(0, attendance.required_days - attendance.days_attended);
});
</script>

<template>
    <AppLayout
        :breadcrumbs="[{ title: 'Mis Constancias', href: '/constancias' }]"
    >
        <Head title="Mis Constancias" />

        <div
            class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-4 py-6 sm:space-y-8 sm:px-8 sm:py-8"
        >
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Mis Constancias
            </h1>

            <!-- Cartas de Invitación -->
            <div v-if="invitationLetters?.length">
                <h2
                    class="mb-4 text-xl font-normal tracking-tight text-gray-800 dark:text-gray-200"
                >
                    Cartas de Invitación
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="letter in invitationLetters"
                        :key="letter.id"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900/30"
                            >
                                <Mail
                                    class="h-6 w-6 text-sky-600 dark:text-sky-400"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ letter.label }}
                                </h2>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Invitación como {{ letter.rol }}.
                                </p>
                                <p
                                    v-if="letter.folio"
                                    class="mt-1 font-mono text-[11px] text-sky-600 dark:text-sky-400"
                                >
                                    Folio: {{ letter.folio }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                @click="downloadInvitacion(letter.id)"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-700 transition-colors hover:bg-sky-100 sm:w-auto dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300"
                            >
                                <Download class="h-4 w-4" /> Descargar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Constancia de asistencia al evento -->
            <div v-if="eventAttendance?.has || eventCertificate">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900/30"
                        >
                            <Users
                                class="h-6 w-6 text-cyan-600 dark:text-cyan-400"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Constancia de asistencia al evento
                            </h2>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Por tu asistencia verificada al evento general.
                            </p>
                            <p
                                v-if="eventCertificate?.folio"
                                class="mt-1 font-mono text-[11px] text-cyan-600 dark:text-cyan-400"
                            >
                                Folio: {{ eventCertificate.folio }}
                            </p>

                            <div v-if="eventAttendance" class="mt-4">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2 text-xs"
                                >
                                    <span
                                        class="font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ eventAttendance.days_attended }}
                                        de
                                        {{ eventAttendance.required_days }}
                                        días requeridos
                                    </span>
                                    <span
                                        class="min-w-0 text-right text-gray-400 dark:text-gray-500"
                                    >
                                        {{
                                            eventAttendance.total_days > 0
                                                ? eventAttendance.total_days +
                                                  ' días de evento'
                                                : 'Evento: ' +
                                                  eventAttendance.evento_nombre
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-cyan-500 transition-all"
                                        :style="{
                                            width: eventProgress + '%',
                                        }"
                                    ></div>
                                </div>
                                <p
                                    class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        eventAttendance.qualifies
                                            ? '¡Cumples con los días mínimos! Puedes descargar tu constancia.'
                                            : 'Te faltan ' +
                                              missingDays +
                                              (missingDays === 1
                                                  ? ' día'
                                                  : ' días') +
                                              ' de asistencia para poder emitir tu constancia.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button
                            v-if="
                                eventAttendance?.qualifies || eventCertificate
                            "
                            @click="downloadEvento"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-medium text-cyan-700 transition-colors hover:bg-cyan-100 sm:w-auto dark:border-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300"
                        >
                            <Download class="h-4 w-4" /> Descargar Constancia
                        </button>
                        <button
                            v-else
                            disabled
                            class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400 sm:w-auto dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-500"
                        >
                            <Clock class="h-4 w-4" /> Pendiente
                        </button>
                    </div>
                </div>
            </div>

            <!-- Talleres -->
            <div v-if="completedWorkshops && completedWorkshops.length > 0">
                <h2
                    class="mb-4 text-xl font-normal tracking-tight text-gray-800 dark:text-gray-200"
                >
                    Talleres completados
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="workshop in completedWorkshops"
                        :key="workshop.id"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                            >
                                <Award
                                    class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                                />
                            </div>
                            <div class="flex-1">
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ workshop.name }}
                                </h3>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        workshop.instructors
                                            ?.map(
                                                (i) =>
                                                    [i.first_name, i.last_name]
                                                        .filter(Boolean)
                                                        .join(' ') +
                                                    (i.affiliation
                                                        ? ' (' +
                                                          i.affiliation +
                                                          ')'
                                                        : ''),
                                            )
                                            .join(', ') || '—'
                                    }}
                                </p>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ workshop.day }} | {{ workshop.location }}
                                </p>
                                <p
                                    v-if="workshop.folio"
                                    class="mt-1 font-mono text-[11px] text-indigo-600 dark:text-indigo-400"
                                >
                                    Folio: {{ workshop.folio }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button
                                @click="downloadCertificate(workshop.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                            >
                                <Download class="h-4 w-4" /> Descargar
                                Constancia
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Talleres impartidos -->
            <div v-if="instructorWorkshops && instructorWorkshops.length > 0">
                <h2
                    class="mb-4 text-xl font-normal tracking-tight text-gray-800 dark:text-gray-200"
                >
                    Talleres impartidos
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="workshop in instructorWorkshops"
                        :key="'i-' + workshop.id"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/30"
                            >
                                <Users
                                    class="h-6 w-6 text-violet-600 dark:text-violet-400"
                                />
                            </div>
                            <div class="flex-1">
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ workshop.name }}
                                </h3>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ workshop.day }} | {{ workshop.location }}
                                </p>
                                <p
                                    v-if="workshop.folio"
                                    class="mt-1 font-mono text-[11px] text-violet-600 dark:text-violet-400"
                                >
                                    Folio: {{ workshop.folio }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button
                                v-if="
                                    workshop.activated &&
                                    can('constancias.download')
                                "
                                @click="downloadCertificate(workshop.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-medium text-violet-700 transition-colors hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-900/30 dark:text-violet-300"
                            >
                                <Download class="h-4 w-4" /> Descargar
                                Constancia
                            </button>
                            <div
                                v-else
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-500"
                            >
                                <Clock class="h-4 w-4" /> Pendiente de
                                activación
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ponencias (solo ponente y admin) -->
            <div v-if="canSeePonencias && presentationCertificates?.length">
                <h2
                    class="mb-4 text-xl font-normal tracking-tight text-gray-800 dark:text-gray-200"
                >
                    Constancias de Ponencia
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="presentation in presentationCertificates"
                        :key="presentation.id"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                            >
                                <BookOpen
                                    class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                />
                            </div>
                            <div class="flex-1">
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ presentation.title }}
                                </h3>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ presentation.discipline || '—' }}
                                </p>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ presentation.day || '—' }}
                                    {{
                                        presentation.day &&
                                        presentation.location
                                            ? '|'
                                            : ''
                                    }}
                                    {{ presentation.location || '' }}
                                </p>
                                <p
                                    v-if="presentation.folio"
                                    class="mt-1 font-mono text-[11px] text-emerald-600 dark:text-emerald-400"
                                >
                                    Folio: {{ presentation.folio }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button
                                @click="downloadPonencia(presentation.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300"
                            >
                                <Download class="h-4 w-4" /> Descargar
                                Constancia
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cartas de Invitación por Ponencia -->
            <div v-if="canSeePonencias && cartaPresentations?.length">
                <h2
                    class="mb-4 text-xl font-normal tracking-tight text-gray-800 dark:text-gray-200"
                >
                    Cartas de Invitación por Ponencia
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="presentation in cartaPresentations"
                        :key="'carta-' + presentation.id"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900/30"
                            >
                                <Mail
                                    class="h-6 w-6 text-sky-600 dark:text-sky-400"
                                />
                            </div>
                            <div class="flex-1">
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ presentation.title }}
                                </h3>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ presentation.discipline || '—' }}
                                </p>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ presentation.day || '—' }}
                                    {{
                                        presentation.day &&
                                        presentation.location
                                            ? '|'
                                            : ''
                                    }}
                                    {{ presentation.location || '' }}
                                </p>
                                <p
                                    v-if="presentation.cartaFolio"
                                    class="mt-1 font-mono text-[11px] text-sky-600 dark:text-sky-400"
                                >
                                    Carta: {{ presentation.cartaFolio }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button
                                @click="downloadInvitacionPonencia(presentation.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-700 transition-colors hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300"
                            >
                                <Mail class="h-4 w-4" /> Carta de
                                Invitación
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conferencias -->
            <div v-if="conferenceCertificates?.length">
                <h2
                    class="mb-4 text-xl font-normal tracking-tight text-gray-800 dark:text-gray-200"
                >
                    Constancias de Conferencia
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="conference in conferenceCertificates"
                        :key="conference.id"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
                            >
                                <Mic
                                    class="h-6 w-6 text-amber-600 dark:text-amber-400"
                                />
                            </div>
                            <div class="flex-1">
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ conference.title }}
                                </h3>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        roleLabel(conference.member_role) ||
                                        'Participante'
                                    }}
                                </p>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ conference.day || '—' }}
                                </p>
                                <p
                                    v-if="conference.folio"
                                    class="mt-1 font-mono text-[11px] text-amber-600 dark:text-amber-400"
                                >
                                    Folio: {{ conference.folio }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button
                                v-if="conference.activated"
                                @click="downloadConferencia(conference.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                            >
                                <Download class="h-4 w-4" /> Descargar
                                Constancia
                            </button>
                            <div
                                v-else
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-500"
                            >
                                <Clock class="h-4 w-4" /> Pendiente de
                                activación
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="!hasAnyCertificates"
                class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <Award
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-4 text-gray-500 dark:text-gray-400">
                    Aún no tienes constancias disponibles.
                </p>
                <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                    Las constancias se generan cuando completas un taller
                    (asistencia verificada), impartes un taller, presentas una
                    ponencia o participas en una conferencia.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
