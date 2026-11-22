<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OtpChallenge from '@/components/auth/OtpChallenge.vue';
import { logout } from '@/routes';
import { send, otp } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify your email',
        description:
            'Enter the 6-digit code we emailed you to unlock all portal features and consultation tracking',
    },
});

defineProps<{
    status?: string;
    email?: string | null;
    resendIn?: number;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div class="flex flex-col gap-5">
        <OtpChallenge
            :email="email ?? null"
            :resend-in="resendIn ?? 0"
            :verify-url="otp.url()"
            :resend-url="send.url()"
            submit-label="Verify email"
            icon="mark_email_read"
        />

        <div class="text-center text-sm text-site-muted">
            <Link
                :href="logout()"
                as="button"
                method="post"
                class="font-semibold text-site-red hover:underline"
            >
                Log out
            </Link>
        </div>
    </div>
</template>
