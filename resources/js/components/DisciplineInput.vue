<script setup lang="ts">
import { ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const predefined = [
    'Nivel Universitario por área (Cálculo, Álgebra, Geometría Analítica, Álgebra Lineal, etc.)',
    'Nivel Preuniversitario (Bachillerato)',
    'Nivel Básico (Primaria o Secundaria)',
    'Ciencia, Tecnología, Ingeniería y Matemáticas (STEM)',
];

const tags = ref<string[]>([]);
const selectedOption = ref('');

watch(
    () => props.modelValue,
    (val) => {
        const parsed = val
            ? val.split(/\s*\|\|\s*/).map((s) => s.trim()).filter(Boolean)
            : [];
        if (JSON.stringify(parsed) !== JSON.stringify(tags.value)) {
            tags.value = parsed;
        }
    },
    { immediate: true },
);

watch(tags, () => {
    emit('update:modelValue', tags.value.join(' || '));
}, { deep: true });

function addTag(val: string) {
    const trimmed = val.trim();
    if (trimmed && !tags.value.includes(trimmed)) {
        tags.value.push(trimmed);
    }
}

function removeTag(index: number) {
    tags.value.splice(index, 1);
}

function onSelectChange() {
    if (selectedOption.value) {
        addTag(selectedOption.value);
        selectedOption.value = '';
    }
}
</script>

<template>
    <div
        class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800"
    >
        <div v-if="tags.length" class="mb-2 flex flex-wrap gap-1.5">
            <span
                v-for="(tag, idx) in tags"
                :key="idx"
                class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
            >
                {{ tag }}
                <button
                    type="button"
                    @click.stop="removeTag(idx)"
                    class="inline-flex items-center text-indigo-400 hover:text-indigo-600 dark:text-indigo-300 dark:hover:text-indigo-100"
                >
                    <X class="h-3 w-3" />
                </button>
            </span>
        </div>

        <select
            v-model="selectedOption"
            @change="onSelectChange"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-600 dark:bg-zinc-700 dark:text-gray-200"
        >
            <option value="" disabled>-- Agregar disciplina --</option>
            <option
                v-for="opt in predefined"
                :key="opt"
                :value="opt"
                :disabled="tags.includes(opt)"
            >
                {{ opt }}
            </option>
        </select>
    </div>
</template>
