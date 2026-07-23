<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { Search, Plus, Edit, Trash2, Users } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role_id === 1);

const props = defineProps<{
    workshops: {
        data: any[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: any;
    enrollments?: number[];
}>();

const formFilters = useForm({
    search: props.filters?.search || '',
});

let searchTimeout: ReturnType<typeof setTimeout>;
watch(
    () => formFilters.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            formFilters.get('/workshops', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
);

const showModal = ref(false);
const isEditing = ref(false);

interface InstructorForm {
    name: string;
    institution: string;
    email: string;
}

const form = useForm({
    id: null as number | null,
    name: '',
    description: '',
    capacity: 30,
    location: '',
    day: '',
    start_time: '',
    end_time: '',
    qr_time_restricted: true,
    instructors: [{ name: '', institution: '', email: '' }] as InstructorForm[],
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.instructors = [{ name: '', institution: '', email: '' }];
    showModal.value = true;
};

const openEditModal = (workshop: any) => {
    isEditing.value = true;
    form.reset();
    form.id = workshop.id;
    form.name = workshop.name || '';
    form.description = workshop.description || '';
    form.capacity = workshop.capacity || 30;
    form.location = workshop.location || '';
    form.day = workshop.day || '';
    form.start_time = workshop.start_time || '';
    form.end_time = workshop.end_time || '';
    form.qr_time_restricted = workshop.qr_time_restricted ?? true;
    form.instructors = workshop.instructors?.length
        ? workshop.instructors.map((i: any) => ({
              name: i.name || '',
              institution: i.institution || '',
              email: i.email || '',
          }))
        : [{ name: '', institution: '', email: '' }];
    showModal.value = true;
};

const addInstructor = () => {
    if (form.instructors.length < 5) {
        form.instructors.push({ name: '', institution: '', email: '' });
    }
};

const removeInstructor = (index: number) => {
    if (form.instructors.length > 1) {
        form.instructors.splice(index, 1);
    }
};

const saveWorkshop = () => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    };

    if (isEditing.value && form.id) {
        form.put('/workshops/' + form.id, options);
    } else {
        form.post('/workshops', options);
    }
};

const deleteWorkshop = (id: number) => {
    if (confirm('¿Estás seguro de eliminar este taller?')) {
        router.delete('/workshops/' + id, {
            preserveScroll: true,
        });
    }
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Talleres', href: '/workshops' }]">
        <Head title="Talleres" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1 class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white">
                Talleres
            </h1>

            <div class="mt-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
                <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                    <div class="flex flex-col">
                        <label class="text-[11px] font-medium tracking-wide text-gray-600 uppercase dark:text-gray-400">
                            Buscar
                        </label>
                        <div class="relative w-full sm:w-64">
                            <Search class="absolute top-[11px] left-3 h-4 w-4 text-gray-500" />
                            <input
                                v-model="formFilters.search"
                                type="text"
                                class="w-full rounded-md border border-gray-300 py-2 pr-4 pl-9 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-white dark:focus:ring-white"
                                placeholder="Nombre del taller"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="isAdmin" class="flex items-center gap-3 self-start xl:self-auto">
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                    >
                        <Plus class="h-4 w-4" /> Nuevo Taller
                    </button>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                        <thead class="border-b bg-white dark:border-zinc-800 dark:bg-zinc-900">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200">
                                    Taller
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200">
                                    Instructor
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200">
                                    Horario
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200">
                                    Lugar
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200">
                                    Cupos
                                </th>
                                <th scope="col" class="relative px-6 py-4">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                            <tr
                                v-for="workshop in workshops.data"
                                :key="workshop.id"
                                class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ workshop.name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatDate(workshop.day) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    <div v-for="(instructor, idx) in workshop.instructors" :key="instructor.id">
                                        <div>{{ instructor.name }}</div>
                                        <div v-if="instructor.institution" class="text-xs text-gray-400">{{ instructor.institution }}</div>
                                        <div v-if="idx < (workshop.instructors?.length ?? 0) - 1" class="my-1 border-b border-gray-100 dark:border-zinc-800"></div>
                                    </div>
                                    <div v-if="!workshop.instructors?.length" class="text-gray-400">—</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ workshop.start_time }} - {{ workshop.end_time }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ workshop.location }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ workshop.enrolled_count || 0 }} / {{ workshop.capacity }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="'/workshops/' + workshop.id"
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Users class="h-4 w-4" />
                                        </Link>
                                        <template v-if="isAdmin">
                                            <button
                                                @click="openEditModal(workshop)"
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </button>
                                            <button
                                                @click="deleteWorkshop(workshop.id)"
                                                class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!workshops?.data || workshops.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No se encontraron talleres.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800" v-if="workshops.total > 0">
                    <Link
                        v-if="workshops.prev_page_url"
                        :href="workshops.prev_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                    >Anterior</Link>
                    <span v-else class="text-gray-400 dark:text-zinc-600">Anterior</span>
                    <Link
                        v-if="workshops.next_page_url"
                        :href="workshops.next_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                    >Siguiente</Link>
                    <span v-else class="text-gray-400 dark:text-zinc-600">Siguiente</span>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-black/50 transition-opacity" @click="showModal = false"></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle">&#8203;</span>
                <div class="relative inline-block transform overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-8 sm:align-middle dark:border-zinc-800 dark:bg-zinc-900">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ isEditing ? 'Editar Taller' : 'Nuevo Taller' }}
                    </h3>

                    <form @submit.prevent="saveWorkshop">
                        <div v-if="Object.keys(form.errors).length > 0" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                            <p class="font-medium">Corrige los siguientes errores:</p>
                            <ul class="mt-1 list-inside list-disc">
                                <li v-for="(message, key) in form.errors" :key="key">{{ message }}</li>
                            </ul>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre *</label>
                                    <input v-model="form.name" type="text" required class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100" />
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                                    <textarea v-model="form.description" rows="2" class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"></textarea>
                                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-500">{{ form.errors.description }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Capacidad *</label>
                                    <input v-model.number="form.capacity" type="number" min="1" required class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100" />
                                    <p v-if="form.errors.capacity" class="mt-1 text-xs text-red-500">{{ form.errors.capacity }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Lugar *</label>
                                    <input v-model="form.location" type="text" required class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100" />
                                    <p v-if="form.errors.location" class="mt-1 text-xs text-red-500">{{ form.errors.location }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Día *</label>
                                    <input v-model="form.day" type="date" required class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100" />
                                    <p v-if="form.errors.day" class="mt-1 text-xs text-red-500">{{ form.errors.day }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Hora Inicio *</label>
                                    <input v-model="form.start_time" type="time" required class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100" />
                                    <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-500">{{ form.errors.start_time }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Hora Fin *</label>
                                    <input v-model="form.end_time" type="time" required class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100" />
                                    <p v-if="form.errors.end_time" class="mt-1 text-xs text-red-500">{{ form.errors.end_time }}</p>
                                </div>

                                <!-- Instructors -->
                                <div class="sm:col-span-2 border-t border-gray-100 pt-4 dark:border-zinc-800">
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Instructores *</label>
                                        <button
                                            v-if="form.instructors.length < 5"
                                            type="button"
                                            @click="addInstructor"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                        >
                                            <Plus class="h-3 w-3" /> Agregar instructor
                                        </button>
                                    </div>

                                    <div v-for="(instructor, index) in form.instructors" :key="index" class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Instructor {{ index + 1 }}</span>
                                            <button
                                                v-if="form.instructors.length > 1"
                                                type="button"
                                                @click="removeInstructor(index)"
                                                class="text-xs text-red-500 hover:text-red-700 dark:text-red-400"
                                            >
                                                × Quitar
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                            <div>
                                                <input
                                                    v-model="instructor.name"
                                                    type="text"
                                                    placeholder="Nombre *"
                                                    required
                                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                                />
                                                <p v-if="form.errors['instructors.' + index + '.name']" class="mt-1 text-xs text-red-500">{{ form.errors['instructors.' + index + '.name'] }}</p>
                                            </div>
                                            <div>
                                                <input
                                                    v-model="instructor.institution"
                                                    type="text"
                                                    placeholder="Institución"
                                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                                />
                                                <p v-if="form.errors['instructors.' + index + '.institution']" class="mt-1 text-xs text-red-500">{{ form.errors['instructors.' + index + '.institution'] }}</p>
                                            </div>
                                            <div>
                                                <input
                                                    v-model="instructor.email"
                                                    type="email"
                                                    placeholder="Correo *"
                                                    required
                                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-100"
                                                />
                                                <p v-if="form.errors['instructors.' + index + '.email']" class="mt-1 text-xs text-red-500">{{ form.errors['instructors.' + index + '.email'] }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.instructors" class="mt-1 text-xs text-red-500">{{ form.errors.instructors }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800">
                            <button type="button" @click="showModal = false" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50">
                                {{ isEditing ? 'Guardar' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
