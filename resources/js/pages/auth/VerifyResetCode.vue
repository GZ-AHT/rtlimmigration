<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OtpChallenge from '@/components/auth/OtpChallenge.vue';
import { request } from '@/routes/password';
import { resend } from '@/routes/password/otp';
import { verify } from '@/routes/password/otp';

defineOptions({
    layout: {
        title: 'Enter your code',
        description: 'Confirm it is you to choose a new password',
    },
});

defineProps<{
    email: string;
    resendIn: number;
    status?: string;
}>();
</script>

<template>
    <Head title="Enter reset code" />

    <div class="flex flex-col gap-5">
        <OtpChallenge
            :email="email"
            :resend-in="resendIn"
            :verify-url="verify.url()"
            :resend-url="resend.url()"
            submit-label="Verify code"
            icon="lock_reset"
        />

        <div class="text-center text-sm text-site-muted">
            Wrong email?
            <Link
                :href="request()"
                class="font-semibold text-site-red hover:underline"
            >
                Start again
            </Link>
        </div>
    </div>
</template>
