<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';

const form = useForm({
    submission_id: '',
    email: '',
});

function submit() {
    form.post(route('ponente.verify'));
}
</script>

<template>
    <AuthLayout
        title="Activar cuenta de Ponente"
        description="Ingresa el ID de tu ponencia aceptada y tu correo electrónico"
    >
        <Head title="Activar cuenta" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
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
                <TextLink :href="route('login')">Iniciar sesión</TextLink>
            </div>
        </form>
    </AuthLayout>
</template>
