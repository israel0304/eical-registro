<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    XCircle,
    Download,
    FileBadge,
    UserRound,
} from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    settings: {
        evento_nombre: string;
        evento_checkin_enabled: boolean;
        evento_min_dias: number;
    };
    attendances: any[];
    total_checked_in: number;
    constancias_issued: number;
    total_users: number;
}>();

const form = useForm({
    evento_nombre: props.settings.evento_nombre,
    evento_checkin_enabled: props.settings.evento_checkin_enabled,
    evento_min_dias: props.settings.evento_min_dias,
});

const saving = ref(false);

const save = () => {
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
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Evento', href: '/admin/evento' }]">
        <Head title="Gestión del Evento" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-8 px-8 py-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Gestión del Evento
                    </h1>
                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Configura el check-in y las constancias de asistencia al
                        evento.
                    </p>
                </div>
                <CalendarDays
                    class="h-8 w-8 text-gray-300 dark:text-gray-600"
                />
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Settings -->
                <div class="space-y-6 lg:col-span-1">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <h2
                            class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Configuración
                        </h2>
                        <form
                            class="space-y-4"
                            @submit.prevent="save"
                        >
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
                                    max="31"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p
                                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                                >
                                    Límite de días para editar/registrar
                                    asistencias pasadas.
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
                            <div
                                class="flex items-center justify-between"
                            >
                                <dt
                                    class="text-gray-500 dark:text-gray-400"
                                >
                                    Asistentes registrados
                                </dt>
                                <dd
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ total_checked_in }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between"
                            >
                                <dt
                                    class="text-gray-500 dark:text-gray-400"
                                >
                                    Constancias emitidas
                                </dt>
                                <dd
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ constancias_issued }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between"
                            >
                                <dt
                                    class="text-gray-500 dark:text-gray-400"
                                >
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
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Asistentes al evento
                    </h2>

                    <div
                        v-if="attendances.length > 0"
                        class="overflow-x-auto"
                    >
                        <table
                            class="w-full text-left text-sm"
                        >
                            <thead>
                                <tr
                                    class="border-b border-gray-100 text-xs text-gray-400 uppercase dark:border-zinc-800"
                                >
                                    <th
                                        class="px-3 py-2 font-medium"
                                    >
                                        Participante
                                    </th>
                                    <th
                                        class="px-3 py-2 font-medium"
                                    >
                                        DNI
                                    </th>
                                    <th
                                        class="px-3 py-2 font-medium"
                                    >
                                        Registrado por
                                    </th>
                                    <th
                                        class="px-3 py-2 font-medium"
                                    >
                                        Constancia
                                    </th>
                                    <th
                                        class="px-3 py-2 font-medium"
                                    >
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
                                    <td
                                        class="flex items-center gap-2 px-3 py-2.5"
                                    >
                                        <UserRound
                                            class="h-4 w-4 text-gray-400"
                                        />
                                        <span
                                            class="font-medium text-gray-900 dark:text-white"
                                        >
                                            {{ attendance.user?.first_name }}
                                            {{
                                                attendance.user?.last_name
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-3 py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ attendance.user?.dni }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            attendance.registered_by?.first_name
                                        }}
                                        {{
                                            attendance.registered_by?.last_name
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5"
                                    >
                                        <span
                                            v-if="attendance.certificate_issued"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                        >
                                            <CheckCircle2
                                                class="h-3 w-3"
                                            />
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
                                    <td
                                        class="px-3 py-2.5"
                                    >
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
