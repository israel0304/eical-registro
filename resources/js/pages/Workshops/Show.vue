<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    UserPlus,
    UserMinus,
    Download,
    Trash2,
    QrCode,
    Send,
    Check,
    X,
    Calendar,
    Clock,
    MapPin,
    UserCheck,
    Users,
} from 'lucide-vue-next';
import { computed, ref, onMounted, nextTick, watch } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();
const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;
const currentUserId = computed(() => page.props.auth.user?.id);
const isAssignedModerator = computed(() => {
    return props.workshop.moderators?.some(
        (m: any) => m.id === currentUserId.value,
    );
});
const isInstructor = computed(() => {
    return props.workshop.instructors?.some(
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

const props = defineProps<{
    workshop: any;
}>();

const breadcrumbs = computed(() =>
    can('workshops.view')
        ? [
              { title: 'Talleres', href: '/workshops' },
              {
                  title: props.workshop.name,
                  href: '/workshops/' + props.workshop.id,
              },
          ]
        : [],
);

const activeTab = ref<'enrolled' | 'cancelled'>('enrolled');
const qrCanvas = ref<HTMLCanvasElement | null>(null);
const sendForm = useForm({ user_id: null as number | null });

const isEnrolled = computed(() => {
    return props.workshop.enrollments?.some(
        (e: any) =>
            e.user?.id === currentUserId.value && e.status === 'enrolled',
    );
});

const isFull = computed(() => {
    return (props.workshop.enrolled_count || 0) >= props.workshop.capacity;
});

const toggleInstructorConstancia = (userId: number) => {
    router.post(
        '/workshops/' +
            props.workshop.id +
            '/instructors/' +
            userId +
            '/activation',
        {},
        {
            preserveScroll: true,
        },
    );
};

const myEnrollment = computed(() => {
    return props.workshop.enrollments?.find(
        (e: any) =>
            e.user?.id === currentUserId.value && e.status === 'enrolled',
    );
});

const canCancel = computed(() => {
    if (!myEnrollment.value) return false;
    if (myEnrollment.value.has_attendance) return false;
    const start = new Date(
        props.workshop.day + 'T' + props.workshop.start_time,
    );
    const now = new Date();
    const diffMs = start.getTime() - now.getTime();
    if (diffMs <= 0) return false;
    const diffMin = diffMs / 60000;
    return diffMin > 10;
});

const filteredEnrollments = computed(() => {
    return (
        props.workshop.enrollments?.filter(
            (e: any) => e.status === activeTab.value,
        ) || []
    );
});

const allEnrolled = computed(() => {
    return (
        props.workshop.enrollments?.filter(
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

const goBack = () => {
    router.get('/workshops');
};

const enroll = () => {
    router.post(
        '/workshops/' + props.workshop.id + '/enroll',
        {},
        { preserveScroll: true },
    );
};

const unenroll = () => {
    if (confirm('¿Cancelar tu inscripción en este taller?')) {
        router.delete('/workshops/' + props.workshop.id + '/unenroll', {
            preserveScroll: true,
        });
    }
};

const removeEnrollment = (enrollmentId: number) => {
    if (confirm('¿Eliminar esta inscripción?')) {
        router.delete(
            '/admin/workshops/' +
                props.workshop.id +
                '/enrollments/' +
                enrollmentId,
            {
                preserveScroll: true,
            },
        );
    }
};

const toggleAttendance = (userId: number) => {
    router.post(
        '/admin/workshops/' + props.workshop.id + '/attendance/' + userId,
        {},
        {
            preserveScroll: true,
        },
    );
};

const downloadConstancia = (userId: number) => {
    window.open(
        '/admin/constancias/' + props.workshop.id + '/' + userId + '/download',
        '_blank',
    );
};

const toggleQrTimeRestricted = () => {
    router.put(
        '/workshops/' + props.workshop.id,
        {
            qr_time_restricted: !props.workshop.qr_time_restricted,
            name: props.workshop.name,
            capacity: props.workshop.capacity,
            location: props.workshop.location,
            day: props.workshop.day,
            start_time: props.workshop.start_time?.substring(0, 5),
            end_time: props.workshop.end_time?.substring(0, 5),
            instructors:
                props.workshop.instructors?.map((i: any) => ({
                    first_name: i.first_name,
                    last_name: i.last_name,
                    affiliation: i.affiliation,
                    email: i.email,
                })) || [],
        },
        { preserveScroll: true },
    );
};

const generateQR = async () => {
    await nextTick();
    if (!qrCanvas.value) return;

    const QRCode = await import('qrcode');
    const url =
        window.location.origin + '/workshops/' + props.workshop.id + '/scan';
    QRCode.toCanvas(qrCanvas.value, url, {
        width: 250,
        margin: 2,
        color: { dark: '#000000', light: '#ffffff' },
    });
};

const downloadQR = () => {
    if (!qrCanvas.value) return;
    const link = document.createElement('a');
    link.download = 'qr_taller_' + props.workshop.id + '.png';
    link.href = qrCanvas.value.toDataURL('image/png');
    link.click();
};

const printQR = () => {
    if (!qrCanvas.value) return;
    const dataUrl = qrCanvas.value.toDataURL('image/png');
    const win = window.open('', '_blank');
    if (win) {
        win.document.write(`
            <html><head><title>QR - ${props.workshop.name}</title></head>
            <body style="text-align:center;padding:40px;font-family:sans-serif">
                <h2>${props.workshop.name}</h2>
                <p>${formatDate(props.workshop.day)} | ${props.workshop.start_time} - ${props.workshop.end_time}</p>
                <img src="${dataUrl}" width="300" height="300" />
                <p style="margin-top:20px;font-size:12px;color:#666">${window.location.origin}/workshops/${props.workshop.id}/scan</p>
            </body></html>
        `);
        win.document.close();
        win.print();
    }
};

const sendQRToInstructor = (instructorId: number) => {
    sendForm.user_id = instructorId;
    sendForm.post('/admin/workshops/' + props.workshop.id + '/send-qr', {
        preserveScroll: true,
        onSuccess: () => {
            sendForm.reset();
        },
    });
};

const sendQRToAll = () => {
    sendForm.post('/admin/workshops/' + props.workshop.id + '/send-qr-all', {
        preserveScroll: true,
        onSuccess: () => {
            sendForm.reset();
        },
    });
};

onMounted(() => {
    if (canManage.value) {
        generateQR();
    }
});

watch(
    () => props.workshop.id,
    () => {
        if (canManage.value) {
            generateQR();
        }
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="workshop.name" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <button
                v-if="can('workshops.view')"
                @click="goBack"
                class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver a talleres
            </button>

            <!-- Workshop details -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    {{ workshop.name }}
                </h1>

                <div
                    class="mt-4 space-y-3 rounded-lg bg-gray-50 p-4 text-sm dark:bg-zinc-800"
                >
                    <div
                        class="flex flex-wrap items-center gap-x-5 gap-y-1 text-gray-700 dark:text-gray-300"
                    >
                        <div class="flex items-center gap-1.5">
                            <Calendar class="h-4 w-4 shrink-0" />
                            <span class="text-gray-900 dark:text-white">{{
                                formatDate(workshop.day)
                            }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Clock class="h-4 w-4 shrink-0" />
                            <span class="text-gray-900 dark:text-white"
                                >{{ workshop.start_time }}
                                -
                                {{ workshop.end_time }}</span
                            >
                        </div>
                        <div class="flex items-center gap-1.5">
                            <MapPin class="h-4 w-4 shrink-0" />
                            <span class="text-gray-900 dark:text-white">{{
                                workshop.location
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-3 dark:border-zinc-700"
                    >
                        <span
                            class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400"
                            >Cupos</span
                        >
                        <div class="mt-1 flex items-center gap-2">
                            <Users class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="text-gray-900 dark:text-white">
                                {{ workshop.enrolled_count || 0 }} /
                                {{ workshop.capacity }}</span
                            >
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
                    </div>

                    <div
                        v-if="workshop.description"
                        class="border-t border-gray-200 pt-3 dark:border-zinc-700"
                    >
                        <span
                            class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400"
                            >Descripción</span
                        >
                        <p
                            class="mt-1 text-sm leading-relaxed whitespace-pre-line text-gray-700 dark:text-gray-300"
                        >
                            {{ workshop.description }}
                        </p>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-3 dark:border-zinc-700"
                    >
                        <span
                            class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400"
                            >Instructores</span
                        >
                        <div class="mt-2 space-y-2">
                            <template v-if="workshop.instructors?.length">
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
                                            v-if="instructor.affiliation"
                                            class="text-xs text-gray-500"
                                        >
                                            ({{ instructor.affiliation }})
                                        </span>
                                    </div>
                                    <div
                                        v-if="can('workshops.activate')"
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
                                                instructor.pivot?.activated
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
                                        v-else-if="instructor.pivot?.activated"
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

                <!-- Enrollment buttons (non-admin/non-moderator) -->
                <div
                    v-if="!canManage"
                    class="mt-4 border-t border-gray-100 pt-4 dark:border-zinc-800"
                >
                    <button
                        v-if="canCancel"
                        @click="unenroll"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-700 transition-colors hover:bg-red-50 dark:border-red-800 dark:bg-zinc-800 dark:text-red-400 dark:hover:bg-red-950"
                    >
                        <UserMinus class="h-4 w-4" /> Cancelar inscripción
                    </button>
                    <button
                        v-else-if="!isEnrolled && !isFull"
                        @click="enroll"
                        class="inline-flex items-center gap-2 rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-gray-800 disabled:opacity-50"
                    >
                        <UserPlus class="h-4 w-4" /> Inscribirse
                    </button>
                    <button
                        v-else
                        disabled
                        class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-gray-300 px-4 py-2.5 text-sm font-medium text-gray-500 dark:bg-zinc-700 dark:text-zinc-400"
                    >
                        Cupo lleno
                    </button>
                </div>
            </div>

            <!-- Enrollments List (Admin/Moderator with tabs) -->
            <div
                v-if="canManage"
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="border-b border-gray-200 dark:border-zinc-800">
                    <div class="flex items-center justify-between px-6 py-3">
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
                                        (e: any) => e.status === 'cancelled',
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
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            <Download class="h-4 w-4" /> Descargar CSV
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
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    #
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Nombre
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Email
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    DNI
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Fecha Inscripción
                                </th>
                                <th
                                    v-if="activeTab === 'enrolled'"
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Asistencia
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Acciones</span>
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
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ index + 1 }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ enrollment.user?.first_name }}
                                    {{ enrollment.user?.last_name }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ enrollment.user?.email }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ enrollment.user?.dni }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ enrollment.enrolled_at }}
                                </td>
                                <td
                                    v-if="activeTab === 'enrolled'"
                                    class="px-6 py-4 text-sm"
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
                                            v-if="enrollment.has_attendance"
                                            class="h-3 w-3"
                                        />
                                        <X v-else class="h-3 w-3" />
                                        {{
                                            enrollment.has_attendance
                                                ? 'Asistió'
                                                : 'Pendiente'
                                        }}
                                    </button>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <button
                                            v-if="
                                                activeTab === 'enrolled' &&
                                                can('constancias.download')
                                            "
                                            @click="
                                                downloadConstancia(
                                                    enrollment.user?.id,
                                                )
                                            "
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-indigo-400"
                                            title="Descargar constancia"
                                        >
                                            <Download class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="
                                                activeTab === 'enrolled' &&
                                                can('workshops.enrollments')
                                            "
                                            @click="
                                                removeEnrollment(enrollment.id)
                                            "
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                            title="Eliminar inscripción"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredEnrollments.length === 0">
                                <td
                                    :colspan="activeTab === 'enrolled' ? 7 : 6"
                                    class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
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

            <!-- QR Section (Admin/Moderator) - Always visible, at the bottom -->
            <div
                v-if="canManage"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2
                        class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        <QrCode class="h-5 w-5" /> Código QR de asistencia
                    </h2>
                    <div
                        v-if="can('workshops.edit')"
                        class="flex items-center gap-3"
                    >
                        <button
                            @click="toggleQrTimeRestricted"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="
                                workshop.qr_time_restricted
                                    ? 'bg-indigo-600'
                                    : 'bg-gray-300 dark:bg-zinc-600'
                            "
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="
                                    workshop.qr_time_restricted
                                        ? 'translate-x-6'
                                        : 'translate-x-1'
                                "
                            ></span>
                        </button>
                        <span class="text-sm text-gray-600 dark:text-gray-400"
                            >Restricción horaria</span
                        >
                    </div>
                </div>

                <div class="flex flex-col items-center gap-4">
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-zinc-800">
                        <canvas ref="qrCanvas"></canvas>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="downloadQR"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <Download class="h-4 w-4" /> Descargar PNG
                        </button>
                        <button
                            @click="printQR"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
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
        </div>
    </AppLayout>
</template>
