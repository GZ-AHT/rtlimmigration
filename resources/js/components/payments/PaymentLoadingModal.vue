<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.show) {
        emit('close');
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-250 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div
            v-if="show"
            class="fixed inset-0 z-[99999] flex items-center justify-center overflow-hidden bg-slate-950/60 p-4 backdrop-blur-sm"
            aria-live="polite"
            aria-busy="true"
            @click.self="emit('close')"
        >
            <div
                class="relative flex h-[510px] max-h-[90vh] w-[380px] max-w-[94vw] flex-col items-center justify-between overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl"
            >
                <!-- Close Button -->
                <button
                    type="button"
                    class="absolute top-4 right-4 z-20 flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Cancel payment"
                    @click="emit('close')"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>

                <!-- Top Badge -->
                <div
                    class="inline-flex items-center gap-1.5 rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700"
                >
                    <svg
                        class="h-3.5 w-3.5 animate-pulse text-sky-600"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <rect
                            x="3"
                            y="11"
                            width="18"
                            height="11"
                            rx="2"
                            ry="2"
                        />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <span>Secure Gateway Connection</span>
                </div>

                <!-- Airplane Flight Scene -->
                <div
                    class="relative flex h-[220px] w-full items-center justify-center overflow-hidden rounded-xl bg-gradient-to-b from-sky-50 via-sky-100/40 to-white"
                >
                    <!-- Background Clouds -->
                    <div class="cloud cloud-1" />
                    <div class="cloud cloud-2" />
                    <div class="cloud cloud-3" />

                    <!-- Curved Flight Path Trajectory -->
                    <svg
                        class="absolute inset-0 h-full w-full"
                        viewBox="0 0 320 220"
                        fill="none"
                    >
                        <path
                            d="M 20 170 Q 120 150 175 105 T 300 45"
                            stroke="#0284c7"
                            stroke-width="2"
                            stroke-dasharray="6 6"
                            stroke-linecap="round"
                            class="flight-path"
                        />
                    </svg>

                    <!-- Commercial Passenger Jet Aircraft (Clean SVG Illustration) -->
                    <div class="airplane-container">
                        <svg
                            class="h-24 w-24 drop-shadow-xl"
                            viewBox="0 0 100 100"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <!-- Jet Engine Smoke Contrails -->
                            <path
                                d="M38 29.5 L10 29.5"
                                stroke="#bae6fd"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-dasharray="3 3"
                                opacity="0.8"
                            />
                            <path
                                d="M38 70.5 L10 70.5"
                                stroke="#bae6fd"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-dasharray="3 3"
                                opacity="0.8"
                            />

                            <!-- Left Wing (Far side) -->
                            <path
                                d="M56 44 L36 12 C34 9 38 8 42 10 L68 44 Z"
                                fill="#0284c7"
                            />
                            <!-- Left Winglet -->
                            <path d="M36 12 L38 9 L42 10 Z" fill="#38bdf8" />

                            <!-- Right Wing (Near side) -->
                            <path
                                d="M56 56 L36 88 C34 91 38 92 42 90 L68 56 Z"
                                fill="#0284c7"
                            />
                            <!-- Right Winglet -->
                            <path d="M36 88 L38 91 L42 90 Z" fill="#38bdf8" />

                            <!-- Rear Tail Stabilizers -->
                            <path
                                d="M28 47 L16 28 C15 26 18 25 21 27 L32 47 Z"
                                fill="#0369a1"
                            />
                            <path
                                d="M28 53 L16 72 C15 74 18 75 21 73 L32 53 Z"
                                fill="#0369a1"
                            />

                            <!-- Left Jet Engine -->
                            <rect
                                x="42"
                                y="27"
                                width="16"
                                height="5.5"
                                rx="2.5"
                                fill="#0369a1"
                            />
                            <ellipse
                                cx="58"
                                cy="29.75"
                                rx="1.5"
                                ry="2.75"
                                fill="#38bdf8"
                            />

                            <!-- Right Jet Engine -->
                            <rect
                                x="42"
                                y="67.5"
                                width="16"
                                height="5.5"
                                rx="2.5"
                                fill="#0369a1"
                            />
                            <ellipse
                                cx="58"
                                cy="70.25"
                                rx="1.5"
                                ry="2.75"
                                fill="#38bdf8"
                            />

                            <!-- Main Fuselage (Aircraft Body) -->
                            <path
                                d="M18 50 C18 45 24 43 35 43 L72 43 C84 43 92 47 95 50 C92 53 84 57 72 57 L35 57 C24 57 18 55 18 50 Z"
                                fill="#f8fafc"
                                stroke="#cbd5e1"
                                stroke-width="1.2"
                            />

                            <!-- RTL Brand Livery Stripe -->
                            <path
                                d="M22 50 L92 50"
                                stroke="#0284c7"
                                stroke-width="2.2"
                                stroke-linecap="round"
                            />

                            <!-- Cockpit Windshield -->
                            <path
                                d="M78 46 C84 47 88 49 89 50 C88 51 84 53 78 54 Z"
                                fill="#0f172a"
                            />

                            <!-- Passenger Windows (Upper & Lower rows) -->
                            <circle cx="72" cy="46.5" r="1.1" fill="#475569" />
                            <circle cx="67" cy="46.5" r="1.1" fill="#475569" />
                            <circle cx="62" cy="46.5" r="1.1" fill="#475569" />
                            <circle cx="57" cy="46.5" r="1.1" fill="#475569" />
                            <circle cx="52" cy="46.5" r="1.1" fill="#475569" />
                            <circle cx="47" cy="46.5" r="1.1" fill="#475569" />
                            <circle cx="42" cy="46.5" r="1.1" fill="#475569" />
                            <circle cx="37" cy="46.5" r="1.1" fill="#475569" />

                            <circle cx="72" cy="53.5" r="1.1" fill="#475569" />
                            <circle cx="67" cy="53.5" r="1.1" fill="#475569" />
                            <circle cx="62" cy="53.5" r="1.1" fill="#475569" />
                            <circle cx="57" cy="53.5" r="1.1" fill="#475569" />
                            <circle cx="52" cy="53.5" r="1.1" fill="#475569" />
                            <circle cx="47" cy="53.5" r="1.1" fill="#475569" />
                            <circle cx="42" cy="53.5" r="1.1" fill="#475569" />
                            <circle cx="37" cy="53.5" r="1.1" fill="#475569" />

                            <!-- Vertical Tail Fin (Rudder) -->
                            <path
                                d="M19 49 L33 49 L27 45 L17 46 Z"
                                fill="#0284c7"
                            />
                        </svg>
                    </div>
                </div>

                <!-- Text & Information -->
                <div class="flex flex-col items-center text-center">
                    <h3 class="font-display text-lg font-bold text-slate-800">
                        Preparing Your Payment
                    </h3>
                    <p
                        class="mt-1.5 max-w-[270px] text-xs leading-relaxed text-slate-500"
                    >
                        Connecting to SSLCommerz. Your payment channels will
                        open automatically in a moment.
                    </p>
                </div>

                <!-- Bottom Progress Bar & Cancel Action -->
                <div class="w-full space-y-2.5">
                    <div
                        class="relative h-1.5 w-full overflow-hidden rounded-full bg-slate-100"
                    >
                        <div
                            class="progress-bar-glow h-full rounded-full bg-gradient-to-r from-sky-500 via-indigo-500 to-sky-600"
                        />
                    </div>
                    <div
                        class="flex items-center justify-between text-[11px] font-medium text-slate-400"
                    >
                        <span class="flex items-center gap-1.5">
                            <span
                                class="h-2 w-2 animate-ping rounded-full bg-emerald-500"
                            />
                            RTL Immigration
                        </span>
                        <span>SSLCommerz Gateway</span>
                    </div>
                    <div class="pt-1 text-center">
                        <button
                            type="button"
                            class="rounded px-2.5 py-1 text-[11px] font-medium text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600"
                            @click="emit('close')"
                        >
                            Cancel and return
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes airplane-flight {
    0% {
        transform: translate(-10px, 8px) rotate(-38deg);
    }
    50% {
        transform: translate(12px, -8px) rotate(-32deg);
    }
    100% {
        transform: translate(-10px, 8px) rotate(-38deg);
    }
}

.airplane-container {
    position: relative;
    z-index: 10;
    animation: airplane-flight 3.5s ease-in-out infinite;
}

@keyframes cloud-move {
    0% {
        transform: translateX(180px);
        opacity: 0;
    }
    10% {
        opacity: 0.85;
    }
    90% {
        opacity: 0.85;
    }
    100% {
        transform: translateX(-180px);
        opacity: 0;
    }
}

.cloud {
    position: absolute;
    background: #ffffff;
    border-radius: 9999px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04);
}

.cloud::before,
.cloud::after {
    content: '';
    position: absolute;
    background: #ffffff;
    border-radius: 50%;
}

.cloud-1 {
    width: 60px;
    height: 18px;
    top: 30px;
    animation: cloud-move 6s linear infinite;
}
.cloud-1::before {
    width: 24px;
    height: 24px;
    top: -10px;
    left: 10px;
}
.cloud-1::after {
    width: 18px;
    height: 18px;
    top: -6px;
    right: 12px;
}

.cloud-2 {
    width: 76px;
    height: 22px;
    top: 130px;
    animation: cloud-move 8s linear infinite 2s;
}
.cloud-2::before {
    width: 28px;
    height: 28px;
    top: -12px;
    left: 14px;
}
.cloud-2::after {
    width: 22px;
    height: 22px;
    top: -8px;
    right: 16px;
}

.cloud-3 {
    width: 48px;
    height: 16px;
    top: 70px;
    animation: cloud-move 5s linear infinite 3.5s;
}
.cloud-3::before {
    width: 20px;
    height: 20px;
    top: -8px;
    left: 8px;
}
.cloud-3::after {
    width: 16px;
    height: 16px;
    top: -6px;
    right: 10px;
}

@keyframes path-dash {
    0% {
        stroke-dashoffset: 24;
    }
    100% {
        stroke-dashoffset: 0;
    }
}

.flight-path {
    animation: path-dash 1.2s linear infinite;
}

@keyframes progress-slide {
    0% {
        transform: translateX(-100%);
    }
    50% {
        transform: translateX(0%);
    }
    100% {
        transform: translateX(100%);
    }
}

.progress-bar-glow {
    width: 100%;
    animation: progress-slide 2s ease-in-out infinite;
}
</style>
