<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout title="Vérifiez votre adresse email" description="Cliquez sur le lien envoyé dans votre boîte de réception pour activer votre accès.">
        <Head title="Vérification de l’email" />

        <div v-if="status === 'verification-link-sent'" class="mb-4 text-center text-sm font-medium text-green-600">
            Un nouveau lien de vérification vient de vous être envoyé.
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing" variant="secondary">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Renvoyer le lien de vérification
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="mx-auto block text-sm">Se déconnecter</TextLink>
        </form>
    </AuthLayout>
</template>
