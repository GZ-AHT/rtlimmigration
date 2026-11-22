<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { useCountdown } from '@/composables/useCountdown';

/**
 * Six-digit code entry: auto-focus, paste, one box per digit, a 5-minute
 * code and a resend that unlocks after the 60-second wait. Shared by email
 * verification and forgot password.
 */
const props = defineProps<{
    email: string | null;
    /** Seconds before another code may be requested, from the server. */
    resendIn: number;
    verifyUrl: string;
    resendUrl: string;
    submitLabel: string;
    icon: string;
}>();

const form = useForm({ code: '' });
const resendForm = useForm({ code: '' });
const { remaining, start } = useCountdown();
const resent = ref(false);

watch(
    () => props.resendIn,
    (seconds) => start(seconds),
    { immediate: true },
);

const canSubmit = computed(() => form.code.length === 6 && !form.processing);

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.post(props.verifyUrl, {
        preserveScroll: true,
        onError: () => {
            form.code = '';
        },
    });
}

function resend(): void {
    resent.value = false;
    form.clearErrors();
    resendForm
        .transform(() => ({}))
        .post(props.resendUrl, {
            preserveScroll: true,
            onSuccess: () => {
                resent.value = true;
                form.code = '';
            },
        });
}
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="submit">
        <p class="text-center text-sm leading-relaxed text-site-muted">
            We sent a 6-digit code to
            <strong
                v-if="email"
                class="font-semibold break-all text-site-ink"
                >{{ email }}</strong
            >
            <span v-else>your email address</span>. It expires in 5 minutes.
        </p>

        <div class="flex flex-col items-center gap-3">
            <InputOTP
                id="otp-code"
                v-model="form.code"
                :maxlength="6"
                :disabled="form.processing"
                inputmode="numeric"
                pattern="^\d*$"
                autocomplete="one-time-code"
                autofocus
                class="w-full justify-center"
                aria-label="6-digit code"
                @complete="submit"
            >
                <InputOTPGroup
                    class="w-full max-w-85 justify-center gap-1.5 sm:gap-2"
                >
                    <InputOTPSlot
                        v-for="index in 6"
                        :key="index"
                        :index="index - 1"
                        :aria-invalid="!!form.errors.code || undefined"
                        class="h-12 max-w-12 min-w-0 flex-1 rounded-[10px] border border-site-field-border bg-site-field text-lg font-semibold text-site-ink first:rounded-l-[10px] last:rounded-r-[10px] focus:border-site-red aria-invalid:border-site-red data-[active=true]:border-site-red data-[active=true]:ring-2 data-[active=true]:ring-site-red/20 sm:h-13"
                    />
                </InputOTPGroup>
            </InputOTP>
            <InputError
                :message="form.errors.code || resendForm.errors.code"
                class="text-center text-site-red"
            />
            <p
                v-if="resent && !resendForm.errors.code"
                class="text-center text-sm font-medium text-emerald-700"
                role="status"
            >
                A new code is on its way.
            </p>
        </div>

        <button
            type="submit"
            class="flex h-13 w-full items-center justify-center gap-2.5 rounded-xl bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
            :disabled="!canSubmit"
            data-test="otp-submit-button"
        >
            <AppIcon
                v-if="form.processing"
                name="progress_activity"
                :size="18"
                class="animate-spin"
            />
            <AppIcon v-else :name="icon" :size="18" />
            <span v-if="form.processing">Checking…</span>
            <template v-else>
                <span>{{ submitLabel }}</span>
                <SiteArrow glyph="→" />
            </template>
        </button>

        <div class="text-center text-sm text-site-muted" aria-live="polite">
            <span v-if="remaining > 0">
                Resend code in
                <span class="font-semibold text-site-ink tabular-nums"
                    >{{ remaining }}s</span
                >
            </span>
            <template v-else>
                Didn't get it?
                <button
                    type="button"
                    class="font-semibold text-site-red hover:underline disabled:opacity-60"
                    :disabled="resendForm.processing"
                    data-test="otp-resend-button"
                    @click="resend"
                >
                    Resend code
                </button>
            </template>
        </div>
    </form>
</template>
