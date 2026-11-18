<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { fieldClass } from '@/components/website/form';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description:
            'Enter the email associated with your account and we’ll send you a secure password reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        class="mb-6 rounded-[12px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800"
    >
        {{ status }}
    </div>

    <Form
        v-bind="email.form()"
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
                autocomplete="off"
                v-focus
                placeholder="name@example.com"
                :class="fieldClass"
                :aria-invalid="!!errors.email || undefined"
            />
            <InputError :message="errors.email" class="mt-1.5 text-site-red" />
        </div>

        <button
            type="submit"
            class="mt-2 flex h-[52px] w-full items-center justify-center gap-2.5 rounded-[12px] bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <span v-if="processing">Sending reset link…</span>
            <template v-else>
                <span>Send password reset link</span>
                <SiteArrow glyph="→" />
            </template>
        </button>

        <div class="pt-2 text-center text-sm text-site-muted">
            Remembered your password?
            <Link
                :href="login()"
                class="font-semibold text-site-red hover:underline"
            >
                Return to sign in
            </Link>
        </div>
    </Form>
</template>
