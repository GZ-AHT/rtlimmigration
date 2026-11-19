<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/components/panel/AppIcon.vue';
import SiteArrow from '@/components/website/SiteArrow.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verify your email',
        description:
            'Please confirm your email address to unlock all portal features and consultation tracking',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800"
    >
        A new verification link has been sent to the email address you provided.
    </div>

    <Form
        v-bind="send.form()"
        class="flex flex-col gap-5 text-center"
        v-slot="{ processing }"
    >
        <p class="text-sm leading-relaxed text-site-muted">
            Before proceeding, please check your inbox for a verification email.
            If you didn't receive it, we can easily send you another.
        </p>

        <button
            type="submit"
            :disabled="processing"
            class="flex h-13 w-full items-center justify-center gap-2.5 rounded-xl bg-site-red text-[15px] font-semibold text-white transition-colors hover:bg-site-red-dark focus-visible:ring-4 focus-visible:ring-site-red/30 focus-visible:outline-none disabled:opacity-60"
        >
            <AppIcon
                v-if="processing"
                name="progress_activity"
                :size="18"
                class="animate-spin"
            />
            <AppIcon v-else name="mark_email_read" :size="18" />
            <span v-if="processing">Sending verification link…</span>
            <template v-else>
                <span>Resend verification email</span>
                <SiteArrow glyph="→" />
            </template>
        </button>

        <div class="pt-2 text-center text-sm text-site-muted">
            <Link
                :href="logout()"
                as="button"
                method="post"
                class="font-semibold text-site-red hover:underline"
            >
                Log out
            </Link>
        </div>
    </Form>
</template>
