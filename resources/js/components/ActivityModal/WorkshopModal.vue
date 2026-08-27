<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    X,
    Calendar,
    Clock,
    MapPin,
    Users,
    UserCheck,
    Check,
    Download,
    Trash2,
    QrCode,
    Send,
} from 'lucide-vue-next';
import { computed, ref, onMounted, nextTick, watch } from 'vue';

const props = defineProps<{
    workshopId: number;
    url: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;
const currentUserId = computed(() => page.props.auth.user?.id);

const loading = ref(true);
const workshop = ref<any>(null);
const error = ref('');

const activeTab = ref<'enrolled' | 'cancelled'>('enrolled');
const qrCanvas = ref<HTMLCanvasElement | null>(null);
const sendForm = useForm({ user_id: null as number | null });

const isAssignedModerator = computed(() => {
    return workshop.value?.moderators?.some(
        (m: any) => m.id === currentUserId.value,
    );
});
const isInstructor = computed(() => {
    return workshop.value?.instructors?.some(
        (i: any) => i.id === currentUserId.value,
    );
});
const canManage = computed(
    () =>
        can('workshops.edit') ||
        isAssignedModerator.value ||
        isInstructor.value,
);
const canToggleAttendance = computed(
    () =>
        can('workshops.attendance') &&
        (can('workshops.view') || isAssignedModerator.value),
);

const filteredEnrollments = computed(() => {
    return (
        workshop.value?.enrollments?.filter(
            (e: any) => e.status === activeTab.value,
        ) || []
    );
});

const allEnrolled = computed(() => {
    return (
        workshop.value?.enrollments?.filter(
            (e: any) => e.status === 'enrolled',
        ) || []
    );
});

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const toggleAttendance = (userId: number) => {
    router.post(
        '/admin/workshops/' + workshop.value.id + '/attendance/' + userId,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                const enrollment = workshop.value.enrollments.find(
                    (e: any) => e.user?.id === userId,
                );
                if (enrollment) {
                    enrollment.has_attendance = !enrollment.has_attendance;
                }
            },
        },
    );
};

const downloadConstancia = (userId: number) => {
    window.open(
        '/admin/constancias/' + workshop.value.id + '/' + userId + '/download',
        '_blank',
    );
};

const removeEnrollment = (enrollmentId: number) => {
    if (confirm('¿Eliminar esta inscripción?')) {
        router.delete(
            '/admin/workshops/' +
                workshop.value.id +
                '/enrollments/' +
                enrollmentId,
            {
                preserveScroll: true,
                onSuccess: () => {
                    workshop.value.enrollments =
                        workshop.value.enrollments.filter(
                            (e: any) => e.id !== enrollmentId,
                        );
                },
            },
        );
    }
};

const toggleQrTimeRestricted = () => {
    router.put(
        '/workshops/' + workshop.value.id,
        {
            qr_time_restricted: !workshop.value.qr_time_restricted,
            name: workshop.value.name,
            capacity: workshop.value.capacity,
            location: workshop.value.location,
            day: workshop.value.day,
            start_time: workshop.value.start_time?.substring(0, 5),
            end_time: workshop.value.end_time?.substring(0, 5),
            instructors:
                workshop.value.instructors?.map((i: any) => ({
                    first_name: i.first_name,
                    last_name: i.last_name,
                    affiliation: i.affiliation,
                    email: i.email,
                })) || [],
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                workshop.value.qr_time_restricted =
                    !workshop.value.qr_time_restricted;
            },
        },
    );
};

const toggleInstructorConstancia = (userId: number) => {
    router.post(
        '/workshops/' +
            workshop.value.id +
            '/instructors/' +
            userId +
            '/activation',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                const instructor = workshop.value.instructors?.find(
                    (i: any) => i.id === userId,
                );
                if (instructor) {
                    instructor.pivot = {
                        ...(instructor.pivot ?? {}),
                        activated: !instructor.pivot?.activated,
                    };
                }
            },
        },
    );
};

const generateQR = async () => {
    await nextTick();
    if (!qrCanvas.value) return;

    const QRCode = await import('qrcode');
    const url =
        window.location.origin + '/workshops/' + workshop.value.id + '/scan';
    QRCode.toCanvas(qrCanvas.value, url, {
        width: 200,
        margin: 2,
        color: { dark: '#000000', light: '#ffffff' },
    });
};

const downloadQR = () => {
    if (!qrCanvas.value) return;
    const link = document.createElement('a');
    link.download = 'qr_taller_' + workshop.value.id + '.png';
    link.href = qrCanvas.value.toDataURL('image/png');
    link.click();
};

const printQR = () => {
    if (!qrCanvas.value) return;
    const dataUrl = qrCanvas.value.toDataURL('image/png');
    const win = window.open('', '_blank');
    if (win) {
        win.document.write(`
            <html><head><title>QR - ${workshop.value.name}</title></head>
            <body style="text-align:center;padding:40px;font-family:sans-serif">
                <h2>${workshop.value.name}</h2>
                <p>${formatDate(workshop.value.day)} | ${workshop.value.start_time} - ${workshop.value.end_time}</p>
                <img src="${dataUrl}" width="300" height="300" />
                <p style="margin-top:20px;font-size:12px;color:#666">${window.location.origin}/workshops/${workshop.value.id}/scan</p>
            </body></html>
        `);
        win.document.close();
        win.print();
    }
};

const sendQRToInstructor = (instructorId: number) => {
    sendForm.user_id = instructorId;
    sendForm.post('/admin/workshops/' + workshop.value.id + '/send-qr', {
        preserveScroll: true,
        onSuccess: () => {
            sendForm.reset();
        },
    });
};

const sendQRToAll = () => {
    sendForm.post('/admin/workshops/' + workshop.value.id + '/send-qr-all', {
        preserveScroll: true,
        onSuccess: () => {
            sendForm.reset();
        },
    });
};

const fetchData = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(
            '/mis-asignaciones/workshop/' + props.workshopId,
        );
        if (!response.ok) throw new Error('Error al cargar');
        workshop.value = await response.json();
    } catch (e: any) {
        error.value = e.message || 'Error al cargar los datos';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchData();
});

watch(
    () => workshop.value,
    (val) => {
        if (val && canManage.value) {
            generateQR();
        }
    },
);
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="fixed inset-0 bg-black/50 transition-opacity"
            @click="emit('close')"
        ></div>

        <div
            class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-zinc-800"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Detalle del Taller
                </h2>
                <button
                    @click="emit('close')"
                    class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Loading -->
                <div
                    v-if="loading"
                    class="flex items-center justify-center py-12"
                >
                    <div
                        class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-600 border-t-transparent"
                    ></div>
                </div>

                <!-- Error -->
                <div
                    v-else-if="error"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-center text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                >
                    {{ error }}
                </div>

                <!-- Workshop Details -->
                <template v-else-if="workshop">
                    <!-- Title & Info -->
                    <div class="mb-6">
                        <h3
                            class="text-xl font-semibold text-gray-900 dark:text-white"
                        >
                            {{ workshop.name }}
                        </h3>

                        <div
                            class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-lg bg-gray-50 p-4 text-sm dark:bg-zinc-800"
                        >
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <Calendar class="h-4 w-4" />
                                {{ formatDate(workshop.day) }}
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <Clock class="h-4 w-4" />
                                {{ workshop.start_time }} -
                                {{ workshop.end_time }}
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <MapPin class="h-4 w-4" />
                                {{ workshop.location || '—' }}
                            </div>
                        </div>

                        <!-- Capacity -->
                        <div class="mt-3 flex items-center gap-2 text-sm">
                            <Users class="h-4 w-4 text-gray-400" />
                            <span class="text-gray-900 dark:text-white">
                                {{ workshop.enrolled_count || 0 }} /
                                {{ workshop.capacity }}
                            </span>
                            <div
                                class="h-2 flex-1 rounded-full bg-gray-200 dark:bg-zinc-700"
                            >
                                <div
                                    class="h-2 rounded-full bg-indigo-600"
                                    :style="{
                                        width:
                                            Math.min(
                                                ((workshop.enrolled_count ||
                                                    0) /
                                                    workshop.capacity) *
                                                    100,
                                                100,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div
                            v-if="workshop.description"
                            class="mt-3 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <span
                                class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400"
                                >Descripción</span
                            >
                            <p
                                class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-300"
                            >
                                {{ workshop.description }}
                            </p>
                        </div>

                        <!-- Instructors -->
                        <div class="mt-3 text-sm">
                            <span
                                class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400"
                                >Instructores</span
                            >
                            <div class="mt-2 space-y-2">
                                <template
                                    v-if="workshop.instructors?.length"
                                >
                                    <div
                                        v-for="instructor in workshop.instructors"
                                        :key="instructor.id"
                                        class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <UserCheck
                                            class="h-4 w-4 shrink-0 text-gray-400"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <span
                                                class="text-gray-900 dark:text-white"
                                            >
                                                {{ instructor.first_name }}
                                                {{ instructor.last_name }}
                                            </span>
                                            <span
                                                v-if="
                                                    instructor.affiliation
                                                "
                                                class="text-xs text-gray-500"
                                            >
                                                ({{
                                                    instructor.affiliation
                                                }})
                                            </span>
                                        </div>
                                        <div
                                            v-if="
                                                can('workshops.activate')
                                            "
                                            class="flex items-center gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                @click="
                                                    toggleInstructorConstancia(
                                                        instructor.id,
                                                    )
                                                "
                                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                                                :class="
                                                    instructor.pivot
                                                        ?.activated
                                                        ? 'bg-indigo-600'
                                                        : 'bg-gray-300 dark:bg-zinc-600'
                                                "
                                            >
                                                <span
                                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                                    :class="
                                                        instructor.pivot
                                                            ?.activated
                                                            ? 'translate-x-6'
                                                            : 'translate-x-1'
                                                    "
                                                ></span>
                                            </button>
                                            <span
                                                class="text-xs text-gray-600 dark:text-gray-400"
                                                >Constancia activada</span
                                            >
                                        </div>
                                        <span
                                            v-else-if="
                                                instructor.pivot?.activated
                                            "
                                            class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-200"
                                        >
                                            Constancia activada
                                        </span>
                                    </div>
                                </template>
                                <span v-else class="text-gray-400">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Enrollments List -->
                    <div
                        v-if="canManage"
                        class="rounded-xl border border-gray-200 dark:border-zinc-800"
                    >
                        <div
                            class="border-b border-gray-200 dark:border-zinc-800"
                        >
                            <div
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <nav class="flex gap-4">
                                    <button
                                        @click="activeTab = 'enrolled'"
                                        class="border-b-2 pb-2 text-sm font-medium transition-colors"
                                        :class="
                                            activeTab === 'enrolled'
                                                ? 'border-black text-black dark:border-white dark:text-white'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                        "
                                    >
                                        Inscritos ({{ allEnrolled.length }})
                                    </button>
                                    <button
                                        @click="activeTab = 'cancelled'"
                                        class="border-b-2 pb-2 text-sm font-medium transition-colors"
                                        :class="
                                            activeTab === 'cancelled'
                                                ? 'border-black text-black dark:border-white dark:text-white'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                        "
                                    >
                                        Cancelados ({{
                                            workshop.enrollments?.filter(
                                                (e: any) =>
                                                    e.status === 'cancelled',
                                            ).length || 0
                                        }})
                                    </button>
                                </nav>
                                <a
                                    v-if="can('reportes.view')"
                                    :href="
                                        '/admin/reportes/workshops/' +
                                        workshop.id +
                                        '/csv'
                                    "
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    CSV
                                </a>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table
                                class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800"
                            >
                                <thead class="bg-gray-50 dark:bg-zinc-800">
                                    <tr>
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            #
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Nombre
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Email
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            DNI
                                        </th>
                                        <th
                                            v-if="activeTab === 'enrolled'"
                                            scope="col"
                                            class="px-4 py-2.5 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Asistencia
                                        </th>
                                        <th
                                            scope="col"
                                            class="relative px-4 py-2.5"
                                        >
                                            <span class="sr-only"
                                                >Acciones</span
                                            >
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-gray-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900"
                                >
                                    <tr
                                        v-for="(
                                            enrollment, index
                                        ) in filteredEnrollments"
                                        :key="enrollment.id"
                                        class="hover:bg-gray-50 dark:hover:bg-zinc-800"
                                    >
                                        <td
                                            class="px-4 py-3 text-sm text-gray-500"
                                        >
                                            {{ index + 1 }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white"
                                        >
                                            {{ enrollment.user?.first_name }}
                                            {{ enrollment.user?.last_name }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            {{ enrollment.user?.email }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            {{ enrollment.user?.dni }}
                                        </td>
                                        <td
                                            v-if="activeTab === 'enrolled'"
                                            class="px-4 py-3 text-sm"
                                        >
                                            <button
                                                v-if="canToggleAttendance"
                                                @click="
                                                    toggleAttendance(
                                                        enrollment.user?.id,
                                                    )
                                                "
                                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium transition-colors"
                                                :class="
                                                    enrollment.has_attendance
                                                        ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-300'
                                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-zinc-700 dark:text-zinc-400'
                                                "
                                            >
                                                <Check
                                                    v-if="
                                                        enrollment.has_attendance
                                                    "
                                                    class="h-3 w-3"
                                                />
                                                <X v-else class="h-3 w-3" />
                                                {{
                                                    enrollment.has_attendance
                                                        ? 'Asistio'
                                                        : 'Pendiente'
                                                }}
                                            </button>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right text-sm font-medium whitespace-nowrap"
                                        >
                                            <div
                                                class="flex items-center justify-end gap-2"
                                            >
                                                <button
                                                    v-if="
                                                        activeTab ===
                                                            'enrolled' &&
                                                        can(
                                                            'constancias.download',
                                                        )
                                                    "
                                                    @click="
                                                        downloadConstancia(
                                                            enrollment.user?.id,
                                                        )
                                                    "
                                                    class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400"
                                                    title="Descargar constancia"
                                                >
                                                    <Download class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-if="
                                                        activeTab ===
                                                            'enrolled' &&
                                                        can(
                                                            'workshops.enrollments',
                                                        )
                                                    "
                                                    @click="
                                                        removeEnrollment(
                                                            enrollment.id,
                                                        )
                                                    "
                                                    class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400"
                                                    title="Eliminar inscripcion"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredEnrollments.length === 0">
                                        <td
                                            :colspan="
                                                activeTab === 'enrolled' ? 6 : 5
                                            "
                                            class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                activeTab === 'enrolled'
                                                    ? 'No hay inscritos.'
                                                    : 'No hay inscripciones canceladas.'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- QR Section -->
                    <div
                        v-if="canManage"
                        class="mt-6 rounded-xl border border-gray-200 p-6 dark:border-zinc-800"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3
                                class="flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white"
                            >
                                <QrCode class="h-5 w-5" /> Codigo QR de
                                asistencia
                            </h3>
                            <div
                                v-if="can('workshops.edit')"
                                class="flex items-center gap-3"
                            >
                                <button
                                    @click="toggleQrTimeRestricted"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                    :class="
                                        workshop.qr_time_restricted
                                            ? 'bg-indigo-600'
                                            : 'bg-gray-300 dark:bg-zinc-600'
                                    "
                                >
                                    <span
                                        class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                                        :class="
                                            workshop.qr_time_restricted
                                                ? 'translate-x-5'
                                                : 'translate-x-0.5'
                                        "
                                    ></span>
                                </button>
                                <span
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                    >Restriccion horaria</span
                                >
                            </div>
                        </div>

                        <div class="flex flex-col items-center gap-4">
                            <div
                                class="rounded-lg bg-gray-50 p-4 dark:bg-zinc-800"
                            >
                                <canvas ref="qrCanvas"></canvas>
                            </div>

                            <div class="flex gap-3">
                                <button
                                    @click="downloadQR"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                                >
                                    <Download class="h-4 w-4" /> Descargar
                                </button>
                                <button
                                    @click="printQR"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                                >
                                    Imprimir QR
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="
                                workshop.instructors?.length > 0 &&
                                can('workshops.qr.send')
                            "
                            class="mt-4 border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <p
                                class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Enviar QR al instructor:
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    @click="sendQRToAll"
                                    :disabled="sendForm.processing"
                                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    <Send class="h-3 w-3" /> Enviar a todos
                                </button>
                                <button
                                    v-for="instructor in workshop.instructors"
                                    :key="instructor.id"
                                    @click="sendQRToInstructor(instructor.id)"
                                    :disabled="sendForm.processing"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                                >
                                    <Send class="h-3 w-3" />
                                    {{ instructor.first_name }}
                                    {{ instructor.last_name }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-between border-t border-gray-200 px-6 py-4 dark:border-zinc-800"
            >
                <a
                    :href="url"
                    class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                >
                    Ver pagina completa
                </a>
                <button
                    @click="emit('close')"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>
