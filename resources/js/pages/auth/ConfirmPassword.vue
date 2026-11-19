<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { fieldClass } from '@/components/website/form';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';

defineOptions({
    layout: {
        title: 'Confirm password',
        description:
            'This is a secure area of the application. Please confirm your password before continuing.',
    },
});
</script>

<template>
    <Head title="Confirm password" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirm with passkey"
        loading-label="Confirming..."
        separator="Or confirm with password"
        class="mb-6"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div>
            <label
                for="password"
                class="mb-2 block text-sm font-medium text-site-ink"
            >
                Password
            </label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                v-focus
                placeholder="Enter your password"
                :class="fieldClass"
                :aria-invalid="!!errors.password || undefined"
            />
            <InputError
                :message="errors.password"
                class="mt-1.5 text-site-red"
            />
        </div>

        <button
            type="submit"
            class="mt-2 flex h-13 w-full items-center justify-center gap-2.5 rounded-xl bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
            :disabled="processing"
            data-test="confirm-password-button"
        >
            <AppIcon
                v-if="processing"
                name="progress_activity"
                :size="18"
                class="animate-spin"
            />
            <AppIcon v-else name="lock" :size="18" />
            <span v-if="processing">Confirming…</span>
            <template v-else>
                <span>Confirm password</span>
                <SiteArrow glyph="→" />
            </template>
        </button>
    </Form>
</template>
