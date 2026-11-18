<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { fieldClass } from '@/components/website/form';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description:
            'Join RTL Immigration to track your application, files, and consultation progress',
    },
});
</script>

<template>
    <Head title="Create an account" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div>
            <label
                for="name"
                class="mb-2 block text-sm font-medium text-site-ink"
            >
                Full name
            </label>
            <input
                id="name"
                type="text"
                required
                v-focus
                :tabindex="1"
                autocomplete="name"
                name="name"
                placeholder="Your full name"
                :class="fieldClass"
                :aria-invalid="!!errors.name || undefined"
            />
            <InputError :message="errors.name" class="mt-1.5 text-site-red" />
        </div>

        <div>
            <label
                for="email"
                class="mb-2 block text-sm font-medium text-site-ink"
            >
                Email address
            </label>
            <input
                id="email"
                type="email"
                required
                :tabindex="2"
                autocomplete="email"
                name="email"
                placeholder="name@example.com"
                :class="fieldClass"
                :aria-invalid="!!errors.email || undefined"
            />
            <InputError :message="errors.email" class="mt-1.5 text-site-red" />
        </div>

        <div>
            <label
                for="password"
                class="mb-2 block text-sm font-medium text-site-ink"
            >
                Password
            </label>
            <PasswordInput
                id="password"
                required
                :tabindex="3"
                autocomplete="new-password"
                name="password"
                placeholder="Create a strong password"
                :passwordrules="passwordRules"
                :class="fieldClass"
                :aria-invalid="!!errors.password || undefined"
            />
            <InputError
                :message="errors.password"
                class="mt-1.5 text-site-red"
            />
        </div>

        <div>
            <label
                for="password_confirmation"
                class="mb-2 block text-sm font-medium text-site-ink"
            >
                Confirm password
            </label>
            <PasswordInput
                id="password_confirmation"
                required
                :tabindex="4"
                autocomplete="new-password"
                name="password_confirmation"
                placeholder="Re-enter your password"
                :passwordrules="passwordRules"
                :class="fieldClass"
                :aria-invalid="!!errors.password_confirmation || undefined"
            />
            <InputError
                :message="errors.password_confirmation"
                class="mt-1.5 text-site-red"
            />
        </div>

        <button
            type="submit"
            class="mt-2 flex h-[52px] w-full items-center justify-center gap-2.5 rounded-[12px] bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
            :tabindex="5"
            :disabled="processing"
            data-test="register-user-button"
        >
            <span v-if="processing">Creating account…</span>
            <template v-else>
                <span>Create account</span>
                <SiteArrow glyph="→" />
            </template>
        </button>

        <div class="pt-2 text-center text-sm text-site-muted">
            Already have an account?
            <Link
                :href="login()"
                :tabindex="6"
                class="font-semibold text-site-red hover:underline"
            >
                Sign in
            </Link>
        </div>
    </Form>
</template>
