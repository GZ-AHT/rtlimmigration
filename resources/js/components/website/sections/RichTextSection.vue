<script setup lang="ts">
/** Formatted text (REQ §5). Rich text is cleaned on the server by App\Support\Content\RichText. */
defineProps<{ data: { content: string } }>();
</script>

<template>
    <div class="site-container">
        <!-- eslint-disable-next-line vue/no-v-html -- sanitised server-side -->
        <div class="site-prose max-w-[800px]" v-html="data.content" />
    </div>
</template>
