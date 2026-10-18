<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, CircleHelp, LoaderCircle, LockKeyhole, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Heureux de vous revoir" description="Connectez-vous à votre espace sécurisé pour reprendre le suivi des courriers.">
        <Head title="Connexion" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="email" class="text-xs font-semibold text-foreground/80">Adresse email</Label>
                    <div class="relative">
                        <Mail class="pointer-events-none absolute left-3.5 top-3.5 size-4 text-muted-foreground" /><Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            tabindex="1"
                            autocomplete="email"
                            class="pl-10"
                            placeholder="nom@disc.sn"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-xs font-semibold text-foreground/80">Mot de passe</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" :tabindex="5">
                            Mot de passe oublié ?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <LockKeyhole class="pointer-events-none absolute left-3.5 top-3.5 size-4 text-muted-foreground" /><Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            tabindex="2"
                            autocomplete="current-password"
                            class="pl-10"
                            placeholder="Votre mot de passe"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between rounded-xl bg-muted/60 px-3 py-2.5" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3 text-sm text-muted-foreground">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>Rester connecté sur cet appareil</span>
                    </Label>
                </div>

                <Button type="submit" size="lg" class="mt-2 w-full" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    <template v-else><span>Ouvrir mon espace</span><ArrowRight class="ml-auto size-4" /></template>
                </Button>
            </div>
        </form>

        <div class="mt-6 border-t border-border/60 pt-5 text-center">
            <Link
                href="/aide#connexion"
                class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-amber-700"
            >
                <CircleHelp class="size-4" /> Besoin d’aide pour vous connecter ?
            </Link>
        </div>
    </AuthBase>
</template>
