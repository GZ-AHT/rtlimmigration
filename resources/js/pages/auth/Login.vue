<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { fieldClass } from '@/components/website/form';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Sign in to your account',
        description: 'Enter your credentials to access your dashboard',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-6 rounded-[12px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800"
    >
        {{ status }}
    </div>

    <PasskeyVerify class="mb-6" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
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
                name="email"
                required
                v-focus
                :tabindex="1"
                autocomplete="email"
                placeholder="name@example.com"
                :class="fieldClass"
                :aria-invalid="!!errors.email || undefined"
            />
            <InputError :message="errors.email" class="mt-1.5 text-site-red" />
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <label
                    for="password"
                    class="block text-sm font-medium text-site-ink"
                >
                    Password
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-xs font-semibold text-site-red hover:underline"
                    :tabindex="5"
                >
                    Forgot password?
                </Link>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Enter your password"
                :class="fieldClass"
                :aria-invalid="!!errors.password || undefined"
            />
            <InputError
                :message="errors.password"
                class="mt-1.5 text-site-red"
            />
        </div>

        <div class="flex items-center justify-between pt-1">
            <label
                for="remember"
                class="flex cursor-pointer items-center gap-2.5 text-sm text-site-muted select-none"
            >
                <input
                    id="remember"
                    type="checkbox"
                    name="remember"
                    :tabindex="3"
                    class="size-4 rounded border-site-field-border text-site-red accent-site-red focus:ring-2 focus:ring-site-red/20"
                />
                <span>Remember me</span>
            </label>
        </div>

        <button
            type="submit"
            class="mt-2 flex h-[52px] w-full items-center justify-center gap-2.5 rounded-[12px] bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <span v-if="processing">Signing in…</span>
            <template v-else>
                <span>Sign in</span>
                <SiteArrow glyph="→" />
            </template>
        </button>

        <div class="pt-2 text-center text-sm text-site-muted">
            Don't have an account?
            <Link
                :href="register()"
                :tabindex="6"
                class="font-semibold text-site-red hover:underline"
            >
                Create an account
            </Link>
        </div>
    </Form>
</template>
