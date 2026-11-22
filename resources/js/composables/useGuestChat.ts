import { ref } from 'vue';

/**
 * Lets other website components (the phone action bar) open the existing
 * live chat panel owned by ChatLauncher. Each call bumps a counter that the
 * launcher watches, so there is one chat system and one source of truth.
 */
export const guestChatRequests = ref(0);

export function openGuestChat(): void {
    guestChatRequests.value++;
}
