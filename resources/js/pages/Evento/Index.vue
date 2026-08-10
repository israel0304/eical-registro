<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    XCircle,
    Download,
    FileBadge,
    Trash2,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    settings: {
        evento_nombre: string;
        evento_checkin_enabled: boolean;
        evento_checkin_time_restricted: boolean;
        evento_checkin_grace_hours: number;
        evento_min_dias: number;
        evento_fecha_inicio: string | null;
        evento_fecha_fin: string | null;
        total_days: number;
    };
    attendances: any[];
    days: { date: string; label: string; count: number }[];
    selected_day: string | null;
    total_checked_in: number;
    constancias_issued: number;
    total_users: number;
}>();

const form = useForm({
    evento_nombre: props.settings.evento_nombre,
    evento_checkin_enabled: props.settings.evento_checkin_enabled,
    evento_checkin_time_restricted:
        props.settings.evento_checkin_time_restricted,
    evento_checkin_grace_hours: props.settings.evento_checkin_grace_hours,
    evento_min_dias: props.settings.evento_min_dias,
    evento_fecha_inicio: props.settings.evento_fecha_inicio ?? '',
    evento_fecha_fin: props.settings.evento_fecha_fin ?? '',
});

const saving = ref(false);

const selectedDay = computed(() => props.selected_day ?? '');

const formTotalDays = computed(() => {
    const start = form.evento_fecha_inicio;
    const end = form.evento_fecha_fin;
    if (!start || !end) return 0;
    const startDate = new Date(start + 'T00:00:00');
    const endDate = new Date(end + 'T00:00:00');
    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) return 0;
    const diff = Math.round(
        (endDate.getTime() - startDate.getTime()) / 86_400_000,
    );
    return diff >= 0 ? diff + 1 : 0;
});

const daysError = computed(() => {
    if (formTotalDays.value <= 0) return null;
    if (form.evento_min_dias > formTotalDays.value) {
        return (
            'El número de días mínimos no puede ser mayor a ' +
            formTotalDays.value +
            ' días del evento.'
        );
    }
    return null;
});

const applyDayFilter = (day: string) => {
    router.get('/admin/evento', day ? { day } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const save = () => {
    if (daysError.value) {
        form.setError('evento_min_dias', daysError.value);
        return;
    }
    form.clearErrors('evento_min_dias');
    saving.value = true;
    form.put('/admin/evento', {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
};

const generateConstancias = () => {
    if (
        confirm(
            '¿Generar/verificar las constancias de asistencia al evento para todos los asistentes registrados?',
        )
    ) {
        router.post('/admin/evento/generar-constancias', undefined, {
            preserveScroll: true,
        });
    }
};

const downloadConstancia = (userId: number) => {
    window.open('/admin/constancias/evento/' + userId + '/download', '_blank');
};

const deleteAttendance = (attendance: any) => {
    if (
        !confirm(
            '¿Eliminar el registro de asistencia de ' +
                (attendance.user?.first_name ?? '') +
                ' ' +
                (attendance.user?.last_name ?? '') +
                ' del día ' +
                (attendance.day_label || attendance.event_day) +
                '?',
        )
    ) {
        return;
    }
    router.delete('/admin/evento/attendance/' + attendance.id, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Evento', href: '/admin/evento' }]">
        <Head title="Gestión del Evento" />

        <div
            class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-4 py-6 sm:space-y-8 sm:px-8 sm:py-8"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1
                        class="text-2xl font-normal tracking-tight text-gray-900 sm:text-3xl dark:text-white"
                    >
                        Gestión del Evento
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Configura el check-in y las constancias de asistencia al
                        evento.
                    </p>
                </div>
                <CalendarDays
                    class="h-8 w-8 text-gray-300 dark:text-gray-600"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                <!-- Settings -->
                <div class="min-w-0 space-y-6 lg:col-span-1">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <h2
                            class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Configuración
                        </h2>
                        <form class="space-y-4" @submit.prevent="save">
                            <div
                                v-if="Object.keys(form.errors).length > 0"
                                class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                            >
                                <ul class="list-inside list-disc">
                                    <li
                                        v-for="(message, key) in form.errors"
                                        :key="key"
                                    >
                                        {{ message }}
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Nombre del evento
                                </label>
                                <input
                                    v-model="form.evento_nombre"
                                    type="text"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Fecha de inicio
                                    </label>
                                    <input
                                        v-model="form.evento_fecha_inicio"
                                        type="date"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Fecha de fin
                                    </label>
                                    <input
                                        v-model="form.evento_fecha_fin"
                                        type="date"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="settings.total_days > 0"
                                class="text-xs text-gray-400 dark:text-gray-500"
                            >
                                El evento dura
                                {{ settings.total_days }}
                                {{
                                    settings.total_days === 1 ? 'día' : 'días'
                                }}. El check-in será por cada día del rango.
                            </p>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Días mínimos para constancia
                                </label>
                                <input
                                    v-model.number="form.evento_min_dias"
                                    type="number"
                                    min="1"
                                    :max="
                                        formTotalDays > 0 ? formTotalDays : 31
                                    "
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p
                                    v-if="daysError"
                                    class="mt-1 text-xs text-red-500 dark:text-red-400"
                                >
                                    {{ daysError }}
                                </p>
                                <p
                                    v-else-if="formTotalDays > 0"
                                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                                >
                                    No puede ser mayor a
                                    {{ formTotalDays }}
                                    {{ formTotalDays === 1 ? 'día' : 'días' }}
                                    del evento.
                                </p>
                                <p
                                    v-else
                                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                                >
                                    Días de asistencia necesarios para poder
                                    emitir la constancia del evento.
                                </p>
                            </div>
                            <label
                                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="form.evento_checkin_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Check-in habilitado
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="
                                        form.evento_checkin_time_restricted
                                    "
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Restricción horaria
                            </label>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Margen de restricción horaria (horas)
                                </label>
                                <input
                                    v-model.number="
                                        form.evento_checkin_grace_hours
                                    "
                                    type="number"
                                    min="0"
                                    max="24"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p
                                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                                >
                                    Permite registrar asistencia por QR N horas
                                    antes del inicio y N horas después del fin
                                    del taller. También aplica a la cancelación
                                    de inscripciones.
                                </p>
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Desactiva para permitir registrar asistencias
                                fuera de las fechas del evento (pruebas).
                            </p>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Guardar configuración
                            </button>
                        </form>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <h2
                            class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Resumen
                        </h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    Asistentes registrados
                                </dt>
                                <dd
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ total_checked_in }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    Constancias emitidas
                                </dt>
                                <dd
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ constancias_issued }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    Usuarios totales
                                </dt>
                                <dd
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ total_users }}
                                </dd>
                            </div>
                        </dl>
                        <button
                            @click="generateConstancias"
                            class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-300"
                        >
                            <FileBadge class="h-4 w-4" />
                            Generar constancias de todos
                        </button>
                    </div>
                </div>

                <!-- Attendance list -->
                <div
                    class="min-w-0 rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Asistentes al evento
                    </h2>

                    <div
                        v-if="days.length > 0"
                        class="mb-4 flex flex-wrap items-center gap-2"
                    >
                        <button
                            @click="applyDayFilter('')"
                            :class="[
                                'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                selectedDay === ''
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300',
                            ]"
                        >
                            Todos ({{ total_checked_in }})
                        </button>
                        <button
                            v-for="day in days"
                            :key="day.date"
                            @click="applyDayFilter(day.date)"
                            :class="[
                                'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                selectedDay === day.date
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300',
                            ]"
                        >
                            {{ day.label }} ({{ day.count }})
                        </button>
                    </div>

                    <div v-if="attendances.length > 0" class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead>
                                <tr
                                    class="border-b border-gray-100 text-xs text-gray-400 uppercase dark:border-zinc-800"
                                >
                                    <th class="px-3 py-2 font-medium">
                                        Participante
                                    </th>
                                    <th class="px-3 py-2 font-medium">DNI</th>
                                    <th class="px-3 py-2 font-medium">Día</th>
                                    <th class="px-3 py-2 font-medium">Días</th>
                                    <th class="px-3 py-2 font-medium">
                                        Registrado por
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Constancia
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="attendance in attendances"
                                    :key="attendance.id"
                                    class="border-b border-gray-50 dark:border-zinc-800/60"
                                >
                                    <td class="px-3 py-2.5 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <UserRound
                                                class="h-4 w-4 shrink-0 text-gray-400"
                                            />
                                            <span
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{
                                                    attendance.user?.first_name
                                                }}
                                                {{ attendance.user?.last_name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td
                                        class="px-3 py-2.5 font-mono text-xs whitespace-nowrap text-gray-500 dark:text-gray-400"
                                    >
                                        {{ attendance.user?.dni }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-xs whitespace-nowrap text-gray-500 dark:text-gray-400"
                                    >
                                        {{ attendance.day_label }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-xs whitespace-nowrap"
                                    >
                                        <span
                                            :class="[
                                                'font-semibold',
                                                attendance.qualifies
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-gray-500 dark:text-gray-400',
                                            ]"
                                        >
                                            {{ attendance.days_attended }}
                                            /
                                            {{ settings.evento_min_dias }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-xs whitespace-nowrap text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            attendance.registered_by?.first_name
                                        }}
                                        {{
                                            attendance.registered_by?.last_name
                                        }}
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">
                                        <span
                                            v-if="attendance.certificate_issued"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                        >
                                            <CheckCircle2 class="h-3 w-3" />
                                            Emitida
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:bg-zinc-800 dark:text-gray-400"
                                        >
                                            <XCircle class="h-3 w-3" />
                                            Pendiente
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">
                                        <button
                                            @click="
                                                downloadConstancia(
                                                    attendance.user_id,
                                                )
                                            "
                                            title="Descargar constancia"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-indigo-400"
                                        >
                                            <Download class="h-3.5 w-3.5" />
                                            Constancia
                                        </button>
                                        <button
                                            @click="
                                                deleteAttendance(attendance)
                                            "
                                            title="Eliminar registro"
                                            class="ml-2 inline-flex items-center rounded-md border border-red-200 bg-white px-2.5 py-1 text-xs font-medium text-red-500 transition-colors hover:bg-red-50 dark:border-red-900/50 dark:bg-zinc-800 dark:text-red-400 dark:hover:bg-red-950"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="py-12 text-center">
                        <UserRound
                            class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"
                        />
                        <p
                            class="mt-2 text-sm text-gray-400 dark:text-gray-500"
                        >
                            Aún no hay asistentes registrados al evento.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
