<script setup lang="ts">
import Image from '@tiptap/extension-image';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/panel/AppIcon.vue';
import MediaPicker from '@/components/cms/MediaPicker.vue';
import type { MediaItem } from '@/types';

/**
 * Rich text for posts, pages and universities (REQ §5 "Rich Text Editor").
 * Only formatting the server keeps is offered here; the server cleans the
 * HTML again on save (App\Support\Content\RichText).
 */
const props = withDefaults(
    defineProps<{ id?: string; invalid?: boolean; minHeight?: string }>(),
    { id: undefined, invalid: false, minHeight: '220px' },
);
const model = defineModel<string>({ default: '' });
const picking = ref(false);

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: {
                openOnClick: false,
                autolink: true,
                protocols: ['mailto', 'tel'],
                HTMLAttributes: { rel: 'noopener noreferrer' },
            },
        }),
        Image,
    ],
    editorProps: {
        attributes: {
            class: 'rte-content focus:outline-none',
            ...(props.id ? { id: props.id } : {}),
            role: 'textbox',
            'aria-multiline': 'true',
        },
    },
    onUpdate: ({ editor: e }) => {
        model.value = e.isEmpty ? '' : e.getHTML();
    },
});

watch(model, (value) => {
    const e = editor.value;

    if (e && value !== e.getHTML() && !(value === '' && e.isEmpty)) {
        e.commands.setContent(value || '', { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function setLink(): void {
    const e = editor.value;

    if (!e) {
        return;
    }

    const previous = (e.getAttributes('link').href as string | undefined) ?? '';
    const url = window.prompt(
        'Link address (https://…, /page, mailto:…)',
        previous,
    );

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        e.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    if (!/^(https?:\/\/|\/(?!\/)|#|mailto:|tel:)/i.test(url.trim())) {
        window.alert(
            'Use a full https:// address, a site path like /contact, or mailto:/tel:.',
        );

        return;
    }

    e.chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url.trim() })
        .run();
}

function insertImage(items: MediaItem[]): void {
    const item = items[0];

    if (item) {
        editor.value
            ?.chain()
            .focus()
            .setImage({ src: item.url, alt: item.alt ?? '' })
            .run();
    }
}

type Tool = {
    icon: string;
    label: string;
    run: () => void;
    active?: () => boolean;
};

const tools: (Tool | 'sep')[] = [
    {
        icon: 'format_bold',
        label: 'Bold',
        run: () => editor.value?.chain().focus().toggleBold().run(),
        active: () => !!editor.value?.isActive('bold'),
    },
    {
        icon: 'format_italic',
        label: 'Italic',
        run: () => editor.value?.chain().focus().toggleItalic().run(),
        active: () => !!editor.value?.isActive('italic'),
    },
    {
        icon: 'format_underlined',
        label: 'Underline',
        run: () => editor.value?.chain().focus().toggleUnderline().run(),
        active: () => !!editor.value?.isActive('underline'),
    },
    'sep',
    {
        icon: 'format_h2',
        label: 'Heading',
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
        active: () => !!editor.value?.isActive('heading', { level: 2 }),
    },
    {
        icon: 'format_h3',
        label: 'Subheading',
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
        active: () => !!editor.value?.isActive('heading', { level: 3 }),
    },
    {
        icon: 'format_list_bulleted',
        label: 'Bulleted list',
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
        active: () => !!editor.value?.isActive('bulletList'),
    },
    {
        icon: 'format_list_numbered',
        label: 'Numbered list',
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
        active: () => !!editor.value?.isActive('orderedList'),
    },
    {
        icon: 'format_quote',
        label: 'Quote',
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
        active: () => !!editor.value?.isActive('blockquote'),
    },
    'sep',
    {
        icon: 'link',
        label: 'Link',
        run: setLink,
        active: () => !!editor.value?.isActive('link'),
    },
    { icon: 'image', label: 'Image', run: () => (picking.value = true) },
    {
        icon: 'horizontal_rule',
        label: 'Divider',
        run: () => editor.value?.chain().focus().setHorizontalRule().run(),
    },
    'sep',
    {
        icon: 'undo',
        label: 'Undo',
        run: () => editor.value?.chain().focus().undo().run(),
    },
    {
        icon: 'redo',
        label: 'Redo',
        run: () => editor.value?.chain().focus().redo().run(),
    },
];
</script>

<template>
    <div
        class="overflow-hidden rounded-md border bg-background"
        :class="invalid ? 'border-danger' : 'border-input'"
    >
        <div
            class="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/40 px-1.5 py-1"
            role="toolbar"
            aria-label="Text formatting"
        >
            <template v-for="(tool, i) in tools" :key="i">
                <span
                    v-if="tool === 'sep'"
                    class="mx-1 h-5 w-px bg-border"
                    aria-hidden="true"
                />
                <button
                    v-else
                    type="button"
                    class="grid size-8 place-items-center rounded hover:bg-muted"
                    :class="
                        tool.active?.()
                            ? 'bg-muted text-primary'
                            : 'text-foreground'
                    "
                    :aria-label="tool.label"
                    :aria-pressed="tool.active ? tool.active() : undefined"
                    :title="tool.label"
                    @click="tool.run"
                >
                    <AppIcon :name="tool.icon" :size="18" />
                </button>
            </template>
        </div>
        <EditorContent
            :editor="editor"
            class="max-h-[70vh] overflow-y-auto px-3.5 py-3 text-[14px]"
            :style="{ minHeight }"
        />
        <MediaPicker v-model:open="picking" @select="insertImage" />
    </div>
</template>

<style>
.rte-content {
    min-height: inherit;
    line-height: 1.65;
}
.rte-content > * + * {
    margin-top: 0.7em;
}
.rte-content h2 {
    font-size: 1.35em;
    font-weight: 700;
}
.rte-content h3 {
    font-size: 1.15em;
    font-weight: 700;
}
.rte-content h4 {
    font-weight: 700;
}
.rte-content ul {
    list-style: disc;
    padding-left: 1.4em;
}
.rte-content ol {
    list-style: decimal;
    padding-left: 1.4em;
}
.rte-content blockquote {
    border-left: 3px solid var(--color-border);
    padding-left: 0.9em;
    color: var(--color-muted-foreground);
}
.rte-content a {
    color: var(--color-primary);
    text-decoration: underline;
}
.rte-content img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
}
.rte-content hr {
    border-color: var(--color-border);
}
.rte-content p.is-editor-empty:first-child::before {
    content: 'Start writing…';
    color: var(--color-muted-foreground);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
