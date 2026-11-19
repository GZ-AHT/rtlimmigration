<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormField from '@/components/website/FormField.vue';
import PostCard from '@/components/website/PostCard.vue';
import SeoHead from '@/components/website/SeoHead.vue';
import SiteButton from '@/components/website/SiteButton.vue';
import SiteImg from '@/components/website/SiteImg.vue';
import { fieldClass } from '@/components/website/form';
import { store as storeComment } from '@/routes/blog/comments';
import type { PostCard as Post, Seo, SiteImage } from '@/types/website';

/**
 * Blog article (REQ §4, §21.8). Not in Figma: built from the About page
 * header and the article cards. Comments wait for moderation.
 */
type Comment = {
    id: number;
    name: string;
    body: string;
    date: string | null;
    replies: Comment[];
};
const props = defineProps<{
    post: Post & {
        content: string;
        tags: string[];
        author: {
            name: string;
            job_title: string | null;
            bio: string | null;
            avatar: SiteImage | null;
        } | null;
        allow_comments: boolean;
        preview: boolean;
    };
    comments: Comment[];
    related: Post[];
    seo: Seo;
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const sent = ref(false);
const replyTo = ref<Comment | null>(null);
const form = useForm({
    name: '',
    email: '',
    body: '',
    parent_id: null as number | null,
    website: '',
});

function submit(): void {
    form.parent_id = replyTo.value?.id ?? null;
    form.submit(storeComment(props.post.url.split('/').pop() ?? ''), {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            replyTo.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <SeoHead :seo="seo" />

    <article>
        <header class="bg-site-blush-bg">
            <div class="site-container pt-8 pb-10 lg:pt-[43px] lg:pb-14">
                <div
                    v-if="post.preview"
                    class="mb-6 rounded-[10px] bg-site-ink px-4 py-2 text-[13px] text-white"
                >
                    Preview: this article isn’t published yet. Visitors can’t
                    see it.
                </div>
                <nav aria-label="Breadcrumb">
                    <ol
                        class="flex flex-wrap gap-3 text-[11px] leading-[17px] font-semibold text-site-muted uppercase"
                    >
                        <li>
                            <Link href="/" class="hover:text-site-red"
                                >Home</Link
                            >
                        </li>
                        <li aria-hidden="true">/</li>
                        <li>
                            <Link href="/blog" class="hover:text-site-red"
                                >Blog</Link
                            >
                        </li>
                        <template v-if="post.category">
                            <li aria-hidden="true">/</li>
                            <li>
                                <Link
                                    :href="`/blog?category=${post.category.slug}`"
                                    class="hover:text-site-red"
                                    >{{ post.category.name }}</Link
                                >
                            </li>
                        </template>
                    </ol>
                </nav>
                <h1
                    class="mt-8 max-w-[900px] text-[28px] leading-[1.15] font-bold tracking-[-0.03em] break-words text-site-ink sm:text-[42px] sm:leading-[48px] md:text-[52px] md:leading-[58px]"
                >
                    {{ post.title }}
                </h1>
                <p
                    v-if="post.excerpt"
                    class="mt-6 max-w-[760px] text-[18px] leading-[1.6] text-site-muted sm:text-[20px] sm:leading-[30px]"
                >
                    {{ post.excerpt }}
                </p>
                <p
                    class="mt-6 text-[12px] font-semibold whitespace-pre text-site-red uppercase"
                >
                    <time
                        v-if="post.date"
                        :datetime="post.iso_date ?? undefined"
                        >{{ post.date }}</time
                    >
                    · {{ post.reading_minutes }} min read<template
                        v-if="post.author"
                    >
                        · {{ post.author.name }}</template
                    >
                </p>
            </div>
        </header>

        <div class="site-container">
            <SiteImg
                v-if="post.image"
                :image="post.image"
                eager
                class="-mt-1 aspect-[16/7] w-full rounded-[24px] lg:mt-10"
            />
            <div
                class="mx-auto grid max-w-[1000px] gap-12 py-12 lg:grid-cols-[1fr_220px] lg:py-16"
            >
                <div>
                    <!-- eslint-disable-next-line vue/no-v-html -- sanitised server-side by App\Support\Content\RichText -->
                    <div class="site-prose" v-html="post.content" />
                    <ul
                        v-if="post.tags.length"
                        class="mt-10 flex flex-wrap gap-2"
                        aria-label="Tags"
                    >
                        <li
                            v-for="tag in post.tags"
                            :key="tag"
                            class="rounded-full bg-site-sand px-3 py-1 text-[13px] font-medium text-site-ink"
                        >
                            {{ tag }}
                        </li>
                    </ul>
                </div>
                <aside
                    class="flex flex-col gap-6 lg:sticky lg:top-36 lg:self-start"
                >
                    <div
                        v-if="post.author"
                        class="rounded-[20px] bg-site-sand p-5"
                    >
                        <SiteImg
                            v-if="post.author.avatar"
                            :image="post.author.avatar"
                            :alt="''"
                            class="size-14 rounded-full"
                        />
                        <p
                            class="mt-3 text-[11px] font-semibold text-site-red uppercase"
                        >
                            Written by
                        </p>
                        <p class="mt-1 text-[16px] font-semibold text-site-ink">
                            {{ post.author.name }}
                        </p>
                        <p
                            v-if="post.author.job_title"
                            class="text-[13px] text-site-muted"
                        >
                            {{ post.author.job_title }}
                        </p>
                        <p
                            v-if="post.author.bio"
                            class="mt-3 text-[13px] leading-5 text-site-muted"
                        >
                            {{ post.author.bio }}
                        </p>
                    </div>
                    <div class="rounded-[20px] bg-site-ink p-5 text-white">
                        <p class="text-[17px] leading-6 font-semibold">
                            Have a question about your plans?
                        </p>
                        <p class="mt-2 text-[13px] text-site-on-dark">
                            Talk to an adviser for advice on your situation.
                        </p>
                        <SiteButton href="/contact" class="mt-4 w-full"
                            >Book a consultation</SiteButton
                        >
                    </div>
                </aside>
            </div>
        </div>
    </article>

    <section
        v-if="post.allow_comments || comments.length"
        class="bg-site-sand py-14 lg:py-[60px]"
        aria-labelledby="comments-heading"
    >
        <div class="site-container max-w-[1000px]!">
            <h2 id="comments-heading" class="text-[28px] font-bold">
                Comments
                <span v-if="comments.length" class="text-site-muted"
                    >({{ comments.length }})</span
                >
            </h2>
            <ul v-if="comments.length" class="mt-6 flex flex-col gap-4">
                <li
                    v-for="c in comments"
                    :key="c.id"
                    class="rounded-[16px] bg-white p-5"
                >
                    <p class="text-[14px] font-semibold text-site-ink">
                        {{ c.name }}
                        <span class="font-normal text-site-muted"
                            >· {{ c.date }}</span
                        >
                    </p>
                    <p
                        class="mt-2 text-[15px] leading-6 whitespace-pre-line text-site-muted"
                    >
                        {{ c.body }}
                    </p>
                    <button
                        v-if="post.allow_comments"
                        type="button"
                        class="mt-2 text-[13px] font-semibold text-site-red hover:underline"
                        @click="replyTo = c"
                    >
                        Reply
                    </button>
                    <ul
                        v-if="c.replies.length"
                        class="mt-4 flex flex-col gap-3 border-l-2 border-site-line pl-4"
                    >
                        <li v-for="r in c.replies" :key="r.id">
                            <p class="text-[14px] font-semibold text-site-ink">
                                {{ r.name }}
                                <span class="font-normal text-site-muted"
                                    >· {{ r.date }}</span
                                >
                            </p>
                            <p
                                class="mt-1 text-[15px] leading-6 whitespace-pre-line text-site-muted"
                            >
                                {{ r.body }}
                            </p>
                        </li>
                    </ul>
                </li>
            </ul>

            <div
                v-if="post.allow_comments && !post.preview"
                class="mt-8 rounded-[20px] bg-white p-6"
            >
                <p v-if="sent" role="status" class="font-medium text-site-ink">
                    Thank you. Your comment will appear once it has been
                    approved.
                </p>
                <form
                    v-else
                    class="grid gap-5 sm:grid-cols-2"
                    novalidate
                    @submit.prevent="submit"
                >
                    <p
                        v-if="replyTo"
                        class="text-[14px] text-site-muted sm:col-span-2"
                    >
                        Replying to {{ replyTo.name }}
                        <button
                            type="button"
                            class="ml-2 font-semibold text-site-red hover:underline"
                            @click="replyTo = null"
                        >
                            Cancel
                        </button>
                    </p>
                    <template v-if="!user">
                        <FormField
                            id="c-name"
                            label="Name *"
                            :error="form.errors.name"
                        >
                            <input
                                id="c-name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                :class="fieldClass"
                            />
                        </FormField>
                        <FormField
                            id="c-email"
                            label="Email (not published) *"
                            :error="form.errors.email"
                        >
                            <input
                                id="c-email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                :class="fieldClass"
                            />
                        </FormField>
                    </template>
                    <FormField
                        id="c-body"
                        label="Your comment *"
                        :error="form.errors.body"
                        class="sm:col-span-2"
                    >
                        <textarea
                            id="c-body"
                            v-model="form.body"
                            rows="4"
                            maxlength="2000"
                            :class="[
                                fieldClass,
                                'h-[120px] resize-y py-[13px]',
                            ]"
                        />
                    </FormField>
                    <div class="absolute -left-[9999px]" aria-hidden="true">
                        <input
                            v-model="form.website"
                            type="text"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-[50px] rounded-[12px] bg-site-red px-5 text-sm font-semibold text-white hover:bg-site-red-dark disabled:opacity-60"
                        >
                            {{ form.processing ? 'Sending…' : 'Post comment' }}
                        </button>
                        <p class="mt-2 text-xs text-site-muted">
                            Comments are reviewed before they appear.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section
        v-if="related.length"
        class="py-14 lg:py-[60px]"
        aria-labelledby="related-heading"
    >
        <div class="site-container">
            <p class="text-[11px] font-semibold text-site-red uppercase">
                Keep reading
            </p>
            <h2
                id="related-heading"
                class="mt-3 text-[30px] leading-[1.1] font-bold tracking-[-0.03em] sm:text-[42px]"
            >
                More insights for your next step.
            </h2>
            <ul
                class="-mx-4 mt-9 no-scrollbar flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pt-1 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pt-0 md:pb-0 lg:grid-cols-3 lg:gap-6"
            >
                <li
                    v-for="p in related"
                    :key="p.id"
                    class="w-[82vw] max-w-[340px] shrink-0 snap-start sm:w-[60vw] md:w-auto md:max-w-none"
                >
                    <PostCard :post="p" />
                </li>
            </ul>
        </div>
    </section>
</template>
