<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { verify } from '@/routes/ponente';

const page = usePage();
const successMessage = page.props.flash?.success as string | undefined;

const form = useForm({
    submission_id: '',
    email: '',
});

function submit() {
    form.post(verify());
}
</script>

<template>
    <AuthLayout
        title="Activar cuenta de Ponente"
        description="Ingresa el ID de tu ponencia aceptada y tu correo electrónico"
    >
        <Head title="Activar cuenta" />

        <div v-if="successMessage" class="flex flex-col gap-6">
            <div
                class="flex flex-col items-center gap-4 rounded-xl border border-green-200 bg-green-50 p-8 text-center dark:border-green-800 dark:bg-green-950"
            >
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900"
                >
                    <CheckCircle2
                        class="h-8 w-8 text-green-600 dark:text-green-400"
                    />
                </div>
                <p
                    class="text-sm font-medium text-green-800 dark:text-green-200"
                >
                    {{ successMessage }}
                </p>
            </div>
            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink :href="login()">Iniciar sesión</TextLink>
            </div>
        </div>

        <form v-else @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="submission_id">ID de Ponencia</Label>
                    <Input
                        id="submission_id"
                        v-model="form.submission_id"
                        type="text"
                        required
                        autofocus
                        placeholder="Ej: 123"
                    />
                    <InputError :message="form.errors.submission_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo Electrónico</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="correo@ejemplo.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Verificar
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink :href="login()">Iniciar sesión</TextLink>
            </div>
        </form>
    </AuthLayout>
</template>
