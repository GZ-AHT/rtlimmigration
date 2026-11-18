<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { fieldClass } from '@/components/website/form';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'Use an authentication code instead',
        };
    }

    return {
        title: 'Two-Factor Authentication',
        description:
            'Enter the 6-digit authentication code from your authenticator application.',
        buttonText: 'Use an emergency recovery code instead',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="flex flex-col gap-5"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center gap-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup class="gap-2">
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                    class="size-12 rounded-[10px] border border-site-field-border bg-site-field text-lg font-semibold text-site-ink focus:border-site-red focus:ring-2 focus:ring-site-red/20"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError
                        :message="errors.code"
                        class="mt-1.5 text-site-red"
                    />
                </div>

                <button
                    type="submit"
                    class="mt-2 flex h-[52px] w-full items-center justify-center gap-2.5 rounded-[12px] bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
                    :disabled="processing || code.length < 6"
                >
                    <span v-if="processing">Verifying…</span>
                    <template v-else>
                        <span>Verify & Sign in</span>
                        <SiteArrow glyph="→" />
                    </template>
                </button>

                <div class="pt-2 text-center text-sm text-site-muted">
                    <button
                        type="button"
                        class="font-semibold text-site-red hover:underline"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="flex flex-col gap-5"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <div>
                    <label
                        for="recovery_code"
                        class="mb-2 block text-sm font-medium text-site-ink"
                    >
                        Emergency recovery code
                    </label>
                    <input
                        id="recovery_code"
                        name="recovery_code"
                        type="text"
                        placeholder="e.g. abcde-12345"
                        v-focus
                        required
                        :class="fieldClass"
                        :aria-invalid="!!errors.recovery_code || undefined"
                    />
                    <InputError
                        :message="errors.recovery_code"
                        class="mt-1.5 text-site-red"
                    />
                </div>

                <button
                    type="submit"
                    class="mt-2 flex h-[52px] w-full items-center justify-center gap-2.5 rounded-[12px] bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
                    :disabled="processing"
                >
                    <span v-if="processing">Verifying…</span>
                    <template v-else>
                        <span>Verify & Sign in</span>
                        <SiteArrow glyph="→" />
                    </template>
                </button>

                <div class="pt-2 text-center text-sm text-site-muted">
                    <button
                        type="button"
                        class="font-semibold text-site-red hover:underline"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
