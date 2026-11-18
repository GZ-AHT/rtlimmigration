<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { fieldClass } from '@/components/website/form';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Choose a new password',
        description:
            'Please enter your new password to regain access to your account',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Reset password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
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
                autocomplete="email"
                v-model="inputEmail"
                readonly
                :class="[fieldClass, 'cursor-not-allowed opacity-75']"
            />
            <InputError :message="errors.email" class="mt-1.5 text-site-red" />
        </div>

        <div>
            <label
                for="password"
                class="mb-2 block text-sm font-medium text-site-ink"
            >
                New password
            </label>
            <PasswordInput
                id="password"
                name="password"
                autocomplete="new-password"
                v-focus
                placeholder="Enter new password"
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
                Confirm new password
            </label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                placeholder="Confirm new password"
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
            :disabled="processing"
            data-test="reset-password-button"
        >
            <span v-if="processing">Resetting password…</span>
            <template v-else>
                <span>Reset password</span>
                <SiteArrow glyph="→" />
            </template>
        </button>
    </Form>
</template>
