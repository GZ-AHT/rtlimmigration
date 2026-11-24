import { ref } from 'vue';

/**
 * Lets any website component open the video consultation booking popup that
 * ConsultationModal (in the website layout) owns. Each call bumps a counter
 * the modal watches, the same way the live chat panel is opened.
 */
export const consultationRequests = ref(0);

export function openConsultationBooking(): void {
    consultationRequests.value++;
}
