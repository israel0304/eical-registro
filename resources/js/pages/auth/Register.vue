<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import RegisterUserForm from '@/components/RegisterUserForm.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

withDefaults(
    defineProps<{
        closed?: boolean;
    }>(),
    {
        closed: false,
    },
);
</script>

<template>
  <AuthBase
    v-if="!closed"
    title="Crear cuenta de Asistente"
    description="Regístrate para participar en el evento"
  >
    <Head title="Registro" />

        <RegisterUserForm :form="store.form()" />

        <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Iniciar sesión</TextLink
                >
            </div>
    </AuthBase>

    <AuthBase
        v-else
        title="Registro cerrado"
        description="Las inscripciones al evento han concluido"
    >
        <Head title="Registro cerrado" />

        <div class="flex flex-col items-center gap-4 py-6 text-center">
            <div
                class="flex h-12 w-12 items-center justify-center rounded-full bg-muted"
            >
                <Lock class="h-6 w-6 text-muted-foreground" />
            </div>
            <p class="text-sm leading-relaxed text-muted-foreground">
                El registro de cuentas para este evento está cerrado. Si ya
                tienes una cuenta, inicia sesión para continuar.
            </p>
            <Button as-child class="w-full" tabindex="1">
                <TextLink :href="login()" class="underline underline-offset-4">
                    Iniciar sesión
                </TextLink>
            </Button>
        </div>
    </AuthBase>
</template>
