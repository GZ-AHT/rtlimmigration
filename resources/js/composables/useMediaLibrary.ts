import { headers } from '@/composables/useMessageThread';
import { index, store } from '@/routes/admin/media';
import type { MediaItem } from '@/types';

/**
 * JSON access to the media library for pickers in every editor. Uploads
 * go through the same server-side checks as the library page.
 */
export function useMediaLibrary() {
    async function list(
        page = 1,
        search = '',
        kind: 'image' | 'video' = 'image',
    ): Promise<{ data: MediaItem[]; last_page: number }> {
        const url = index.url({
            query: { page: String(page), kind, ...(search ? { search } : {}) },
        });
        const response = await fetch(url, {
            headers: headers(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Could not load the media library.');
        }

        return response.json();
    }

    async function upload(file: File, alt = ''): Promise<MediaItem> {
        const body = new FormData();
        body.append('file', file);

        if (alt) {
            body.append('alt', alt);
        }

        const response = await fetch(store.url(), {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body,
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const first = data?.errors
                ? (Object.values(data.errors)[0] as string[])[0]
                : null;
            throw new Error(first ?? data?.message ?? 'Upload failed.');
        }

        return data as MediaItem;
    }

    return { list, upload };
}
