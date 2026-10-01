<script setup lang="ts">
import { Search, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import type {
    AudienceCourse,
    AudienceSegment,
    AudienceUser,
} from '@/types/notificaciones';

const props = defineProps<{
    modelValue: AudienceSegment[];
    roles: { id: number; name: string }[];
    conferenceKinds: Record<string, string | null>;
    courses: AudienceCourse[];
    canManageAll: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: AudienceSegment[]];
}>();

const segments = computed<AudienceSegment[]>(() => props.modelValue ?? []);

const update = (next: AudienceSegment[]) => emit('update:modelValue', next);

const segmentOf = <T extends AudienceSegment['type']>(
    type: T,
): Extract<AudienceSegment, { type: T }> | undefined =>
    segments.value.find((segment) => segment.type === type) as
        | Extract<AudienceSegment, { type: T }>
        | undefined;

const addSegment = (segment: AudienceSegment) => {
    if (segmentOf(segment.type)) return;
    update([...segments.value, segment]);
};

const dropSegment = (type: AudienceSegment['type']) =>
    update(segments.value.filter((segment) => segment.type !== type));

const replaceSegment = (segment: AudienceSegment) =>
    update(
        segments.value.map((current) =>
            current.type === segment.type ? segment : current,
        ),
    );

// ── Todos los usuarios ───────────────────────────────────────────────────────

const allUsersSelected = computed(() => !!segmentOf('all_users'));

const toggleAllUsers = (checked: boolean) =>
    checked
        ? addSegment({ type: 'all_users' })
        : dropSegment('all_users');

// ── Roles ────────────────────────────────────────────────────────────────────

const selectedRoleIds = computed(() => segmentOf('role')?.role_ids ?? []);

const toggleRole = (roleId: number, checked: boolean) => {
    if (!checked) {
        const roleIds = selectedRoleIds.value.filter((id) => id !== roleId);
        return roleIds.length
            ? replaceSegment({ type: 'role', role_ids: roleIds })
            : dropSegment('role');
    }

    if (segmentOf('role')) {
        replaceSegment({
            type: 'role',
            role_ids: [...selectedRoleIds.value, roleId],
        });
    } else {
        addSegment({ type: 'role', role_ids: [roleId] });
    }
};

// ── Tipos de conferencia ─────────────────────────────────────────────────────

const selectedKinds = computed(
    () => segmentOf('speakers_by_kind')?.kinds ?? [],
);

const toggleKind = (kind: string, checked: boolean) => {
    if (!checked) {
        const kinds = selectedKinds.value.filter((value) => value !== kind);
        return kinds.length
            ? replaceSegment({ type: 'speakers_by_kind', kinds })
            : dropSegment('speakers_by_kind');
    }

    if (segmentOf('speakers_by_kind')) {
        replaceSegment({
            type: 'speakers_by_kind',
            kinds: [...selectedKinds.value, kind],
        });
    } else {
        addSegment({ type: 'speakers_by_kind', kinds: [kind] });
    }
};

// ── Talleres (inscritos e instructores) ─────────────────────────────────────

const coursesByDay = computed(() => {
    const groups = new Map<string, AudienceCourse[]>();

    for (const course of props.courses) {
        const key = course.day ?? 'Sin fecha';
        groups.set(key, [...(groups.get(key) ?? []), course]);
    }

    return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b));
});

const workshopSelection = (type: 'workshop_enrollment' | 'workshop_instructors') => ({
    ids: segmentOf(type)?.workshop_ids ?? [],
    all: segmentOf(type)?.all_workshops ?? false,
});

const toggleCourse = (
    type: 'workshop_enrollment' | 'workshop_instructors',
    courseId: number,
    checked: boolean,
) => {
    const { ids, all } = workshopSelection(type);
    const nextIds = checked
        ? [...ids, courseId]
        : ids.filter((id) => id !== courseId);

    if (!nextIds.length && !all) return dropSegment(type);

    replaceSegment({ type, workshop_ids: nextIds, all_workshops: all });
};

const toggleAllCourses = (
    type: 'workshop_enrollment' | 'workshop_instructors',
    checked: boolean,
) => {
    if (!checked) return dropSegment(type);

    replaceSegment({ type, workshop_ids: [], all_workshops: true });
};

const formatDay = (day: string) => {
    if (day === 'Sin fecha') return day;

    const [year, month, date] = day.split('-').map(Number);
    if (!year || !month || !date) return day;

    return new Date(year, month - 1, date).toLocaleDateString('es-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
};

const monthNames = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];

/** "23 y 24 de septiembre de 2026" a partir de los días ISO del curso. */
const courseDates = (course: AudienceCourse) => {
    const days = [...new Set(course.days)].sort();

    if (!days.length) return '';

    const parse = (day: string) => {
        const [year, month, date] = day.split('-').map(Number);
        return { year, month, date };
    };

    if (days.length === 1) {
        const { year, month, date } = parse(days[0]);
        if (!year || !month) return '';
        return `${date} de ${monthNames[month - 1]} de ${year}`;
    }

    const first = parse(days[0]);
    const last = parse(days[days.length - 1]);

    if (!first.year || !first.month || !last.year || !last.month) return '';

    if (first.year === last.year && first.month === last.month) {
        return `${first.date} y ${last.date} de ${monthNames[first.month - 1]} de ${first.year}`;
    }

    return `${formatDay(days[0])} y ${formatDay(days[days.length - 1])}`;
};

const courseHint = (course: AudienceCourse) => {
    const parts: string[] = [];

    if (course.total_sessions > 1) {
        parts.push(
            `${course.total_sessions} sesiones`,
        );
    }

    const dates = courseDates(course);
    if (dates) parts.push(dates);

    return parts.join(' · ');
};

// ── Usuarios individuales ────────────────────────────────────────────────────

const pickedUsers = ref<AudienceUser[]>([]);
const userSearch = ref('');
const searchResults = ref<AudienceUser[]>([]);
const searching = ref(false);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const selectedUserIds = computed(
    () => segmentOf('individual')?.user_ids ?? [],
);

const isPicked = (userId: number) =>
    selectedUserIds.value.includes(userId);

const runUserSearch = () => {
    const keyword = userSearch.value.trim();

    if (keyword === '') {
        searchResults.value = [];
        return;
    }

    searching.value = true;
    fetch(`/admin/notificaciones/users?search=${encodeURIComponent(keyword)}`, {
        headers: { Accept: 'application/json' },
    })
        .then(async (response) => {
            if (!response.ok) return;
            searchResults.value = await response.json();
        })
        .catch(() => {
            searchResults.value = [];
        })
        .finally(() => {
            searching.value = false;
        });
};

watch(userSearch, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(runUserSearch, 300);
});

const toggleUser = (user: AudienceUser, checked: boolean) => {
    if (checked) {
        const userIds = [...selectedUserIds.value, user.id];
        if (!pickedUsers.value.some((picked) => picked.id === user.id)) {
            pickedUsers.value = [...pickedUsers.value, user];
        }

        return segmentOf('individual')
            ? replaceSegment({ type: 'individual', user_ids: userIds })
            : addSegment({ type: 'individual', user_ids: userIds });
    }

    const userIds = selectedUserIds.value.filter((id) => id !== user.id);
    pickedUsers.value = pickedUsers.value.filter(
        (picked) => picked.id !== user.id,
    );

    if (!userIds.length) return dropSegment('individual');

    replaceSegment({ type: 'individual', user_ids: userIds });
};

const removeUser = (userId: number) => {
    const user = pickedUsers.value.find((picked) => picked.id === userId);
    if (user) return toggleUser(user, false);

    const userIds = selectedUserIds.value.filter((id) => id !== userId);
    if (!userIds.length) return dropSegment('individual');

    replaceSegment({ type: 'individual', user_ids: userIds });
};

const userName = (user: AudienceUser) =>
    `${user.first_name} ${user.last_name}`.trim() || user.email;

watch(selectedUserIds, (ids) => {
    // Descarta objetos de personas que ya no estén seleccionadas.
    pickedUsers.value = pickedUsers.value.filter((user) =>
        ids.includes(user.id),
    );
});
</script>

<template>
    <div class="space-y-5">
        <!-- Todos los usuarios -->
        <label
            v-if="canManageAll"
            class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-zinc-700 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/30"
        >
            <Checkbox
                :model-value="allUsersSelected"
                class="mt-0.5"
                @update:model-value="toggleAllUsers(!!$event)"
            />
            <span>
                <span
                    class="block text-sm font-medium text-gray-900 dark:text-white"
                >
                    Todos los usuarios
                </span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                    Todos los usuarios activos del sistema
                </span>
            </span>
        </label>

        <!-- Roles -->
        <div v-if="canManageAll && roles.length">
            <p
                class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Roles
            </p>
            <div class="grid gap-1.5 sm:grid-cols-2">
                <label
                    v-for="role in roles"
                    :key="role.id"
                    class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-800"
                >
                    <Checkbox
                        :model-value="selectedRoleIds.includes(role.id)"
                        @update:model-value="toggleRole(role.id, !!$event)"
                    />
                    {{ role.name }}
                </label>
            </div>
        </div>

        <!-- Tipos de conferencia -->
        <div v-if="canManageAll">
            <p
                class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Speakers por tipo de conferencia
            </p>
            <div class="grid gap-1.5 sm:grid-cols-2">
                <label
                    v-for="(label, kind) in conferenceKinds"
                    :key="kind"
                    class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-800"
                >
                    <Checkbox
                        :model-value="selectedKinds.includes(kind)"
                        @update:model-value="toggleKind(kind, !!$event)"
                    />
                    {{ label || kind }}
                </label>
            </div>
        </div>

        <!-- Cursos / talleres -->
        <div
            v-for="row in [
                { type: 'workshop_enrollment', label: 'Inscritos de' },
                { type: 'workshop_instructors', label: 'Instructores de' },
            ] as const"
            :key="row.type"
        >
            <p
                class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                {{ row.label }} un curso
            </p>

            <label
                v-if="canManageAll"
                class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-800"
            >
                <Checkbox
                    :model-value="workshopSelection(row.type).all"
                    @update:model-value="toggleAllCourses(row.type, !!$event)"
                />
                Todos los cursos
            </label>

            <div
                v-if="!workshopSelection(row.type).all"
                class="mt-2 space-y-3"
            >
                <div v-if="!courses.length">
                    <p class="text-xs text-gray-400">
                        No hay cursos disponibles.
                    </p>
                </div>

                <div v-for="[day, items] in coursesByDay" :key="day">
                    <p
                        class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400"
                    >
                        {{ formatDay(day) }}
                    </p>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        <label
                            v-for="course in items"
                            :key="course.id"
                            class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-800"
                        >
                            <Checkbox
                                class="mt-0.5"
                                :model-value="
                                    workshopSelection(row.type).ids.includes(
                                        course.id,
                                    )
                                "
                                @update:model-value="
                                    toggleCourse(row.type, course.id, !!$event)
                                "
                            />
                            <span class="min-w-0">
                                <span class="block" :title="course.name">
                                    {{ course.name }}
                                </span>
                                <span
                                    v-if="courseHint(course)"
                                    class="block text-xs text-gray-400"
                                >
                                    {{ courseHint(course) }}
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usuarios individuales -->
        <div v-if="canManageAll">
            <p
                class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                Usuarios individuales
            </p>

            <div class="relative">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                />
                <input
                    v-model="userSearch"
                    type="text"
                    placeholder="Buscar por nombre, correo o DNI…"
                    class="w-full rounded-md border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                />
            </div>

            <div
                v-if="searchResults.length"
                class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-zinc-700"
            >
                <label
                    v-for="user in searchResults"
                    :key="user.id"
                    class="flex cursor-pointer items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 text-sm last:border-0 hover:bg-gray-50 dark:border-zinc-800 dark:hover:bg-zinc-800"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <Checkbox
                            :model-value="isPicked(user.id)"
                            @update:model-value="toggleUser(user, !!$event)"
                        />
                        <span class="min-w-0">
                            <span
                                class="block truncate font-medium text-gray-900 dark:text-white"
                            >
                                {{ userName(user) }}
                            </span>
                            <span
                                class="block truncate text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ user.email }}
                            </span>
                        </span>
                    </span>
                    <span
                        v-if="!isPicked(user.id)"
                        class="shrink-0 text-xs text-indigo-500"
                    >
                        Agregar
                    </span>
                </label>
            </div>

            <p v-if="searching" class="mt-2 text-xs text-gray-400">
                Buscando…
            </p>

            <div
                v-if="pickedUsers.length"
                class="mt-3 flex flex-wrap items-center gap-2"
            >
                <span
                    v-for="user of pickedUsers"
                    :key="user.id"
                    class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    <Users class="h-3 w-3 shrink-0 text-gray-400" />
                    <span class="truncate">{{ userName(user) }}</span>
                    <button
                        type="button"
                        @click="removeUser(user.id)"
                        class="shrink-0 text-gray-400 hover:text-red-500"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </span>
            </div>
        </div>
    </div>
</template>
