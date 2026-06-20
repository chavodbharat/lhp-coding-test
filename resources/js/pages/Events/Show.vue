<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { toast, Toaster } from 'vue-sonner';

interface EventImage { id: number; url: string; sort_order: number }

interface EventDetail {
    id: string;
    type: string;
    status: string;
    created_time: number;
    location_name: string | null;
    latitude: number | null;
    longitude: number | null;
    payload: {
        name: string;
        description: string;
        organizer: { name: string; verified: boolean };
        venue: { name: string; capacity: string | number };
        schedule: { starts_at: string | number; ends_at: string | number };
        pricing: { currency: string; min_price: string | number };
        tags: string[];
    };
    images: EventImage[];
}

const props = defineProps<{ event: EventDetail; attendeeCount: number; backTo: string }>();

const activeImg          = ref(0);
const regForm            = ref({ name: '', email: '' });
const regErrors          = ref<{ name?: string; email?: string }>({});
const regLoading         = ref(false);
const regDone            = ref(false);
const localAttendeeCount = ref(props.attendeeCount);

function ts(val: string | number | undefined): number {
    if (!val) return 0;
    return typeof val === 'string' ? parseInt(val, 10) : val;
}

function formatDateRange(): string {
    const s = ts(props.event.payload.schedule?.starts_at);
    const e = ts(props.event.payload.schedule?.ends_at);
    if (!s) return '—';
    const date = new Intl.DateTimeFormat(undefined, {
        weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
    }).format(new Date(s * 1000));
    const startTime = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' })
        .format(new Date(s * 1000));
    const endTime = e
        ? new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZoneName: 'short' })
            .format(new Date(e * 1000))
        : '';
    return `${date} · ${startTime}${endTime ? ` – ${endTime}` : ''}`;
}

function formatDateShort(val: string | number | undefined): string {
    const t = ts(val);
    if (!t) return '—';
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
        .format(new Date(t * 1000));
}

function formatPrice(val: string | number | undefined): string {
    const n = parseFloat(String(val ?? 0));
    return n === 0 ? 'Free' : `$${n.toFixed(2)}`;
}

const isFree = parseFloat(String(props.event.payload.pricing?.min_price ?? 0)) === 0;

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function validateForm(): boolean {
    const errors: { name?: string; email?: string } = {};
    const name = regForm.value.name.trim();
    const email = regForm.value.email.trim();

    if (!name) {
        errors.name = 'Name is required.';
    } else if (name.length < 2) {
        errors.name = 'Name must be at least 2 characters.';
    } else if (!/^[\p{L}\s\-'\.]+$/u.test(name)) {
        errors.name = 'Name can only contain letters, spaces, hyphens, apostrophes and dots.';
    }

    if (!email) {
        errors.email = 'Email is required.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.email = 'Please enter a valid email address.';
    }

    regErrors.value = errors;
    return Object.keys(errors).length === 0;
}

async function register() {
    if (!validateForm()) return;
    regErrors.value = {};
    regLoading.value = true;
    try {
        const res = await fetch(`/events/${props.event.id}/attendees`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            body: JSON.stringify(regForm.value),
        });
        const data = await res.json();
        if (res.status === 422 && data.errors) {
            regErrors.value = {
                name:  data.errors.name?.[0],
                email: data.errors.email?.[0],
            };
            return;
        }
        if (!res.ok) { toast.error(data.message ?? 'Something went wrong.'); return; }
        toast.success(data.message);
        if (!data.already_registered) localAttendeeCount.value++;
        regDone.value = true;
    } finally {
        regLoading.value = false;
    }
}

const statusStyle: Record<string, string> = {
    published: 'bg-emerald-100 text-emerald-700',
    cancelled:  'bg-red-100 text-red-600',
    sold_out:   'bg-amber-100 text-amber-700',
    draft:      'bg-gray-100 text-gray-500',
};

const typeGradients: Record<string, string> = {
    concert:    'from-purple-500 to-pink-500',
    conference: 'from-blue-500 to-cyan-500',
    meetup:     'from-teal-500 to-green-500',
    workshop:   'from-orange-500 to-amber-500',
    festival:   'from-pink-500 to-rose-500',
    sports:     'from-green-500 to-emerald-500',
    networking: 'from-indigo-500 to-purple-500',
    exhibition: 'from-yellow-500 to-orange-500',
};
const heroGradient = typeGradients[props.event.type] ?? 'from-slate-500 to-slate-700';

function orgInitial(): string {
    return (props.event.payload.organizer?.name ?? 'O')[0].toUpperCase();
}

/* ── Leaflet map ───────────────────────────────────────── */
const mapEl = ref<HTMLElement | null>(null);
let leafletMap: any = null;

onMounted(async () => {
    if (!props.event.latitude || !props.event.longitude || !mapEl.value) return;

    const L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');

    // Fix webpack-mangled default icon paths
    const iconDefault = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34],
    });

    leafletMap = L.map(mapEl.value, { zoomControl: true, scrollWheelZoom: false })
        .setView([props.event.latitude, props.event.longitude], 15);

    // CartoDB Voyager — closest free tile style to Google Maps
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors © <a href="https://carto.com">CARTO</a>',
        maxZoom: 19,
    }).addTo(leafletMap);

    L.marker([props.event.latitude, props.event.longitude], { icon: iconDefault })
        .addTo(leafletMap)
        .bindPopup(`<strong>${props.event.payload.venue?.name ?? ''}</strong><br>${props.event.location_name ?? ''}`)
        .openPopup();
});

onBeforeUnmount(() => {
    leafletMap?.remove();
});
</script>

<template>
    <Head :title="event.payload.name" />
    <Toaster rich-colors />

    <div class="min-h-screen bg-gray-50">

        <!-- Sticky navbar -->
        <nav class="sticky top-0 z-20 bg-white shadow-sm">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                <Link
                    :href="backTo"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition-colors hover:text-gray-900"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Back to events
                </Link>
                <p class="hidden max-w-sm truncate text-sm font-semibold text-gray-800 md:block">
                    {{ event.payload.name }}
                </p>
                <span class="capitalize rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-600">
                    {{ event.type }}
                </span>
            </div>
        </nav>

        <div class="mx-auto max-w-6xl px-4 py-8 space-y-6">

            <!-- ① Title block -->
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize', statusStyle[event.status] ?? 'bg-gray-100 text-gray-500']">
                        {{ event.status.replace('_', ' ') }}
                    </span>
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', isFree ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700']">
                        {{ isFree ? 'Free' : 'Paid' }}
                    </span>
                </div>

                <h1 class="text-3xl font-bold leading-tight text-gray-900">{{ event.payload.name }}</h1>

                <div class="mt-2 flex items-center gap-2">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#eef1f8] text-xs font-bold text-indigo-700">
                        {{ orgInitial() }}
                    </div>
                    <span class="text-sm text-gray-500">
                        Organised by
                        <span class="font-semibold text-gray-700">{{ event.payload.organizer?.name ?? 'Unknown' }}</span>
                    </span>
                    <span
                        v-if="event.payload.organizer?.verified"
                        class="inline-flex items-center gap-0.5 rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-semibold text-blue-600"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Verified
                    </span>
                </div>
            </div>

            <!-- ② Image (left) + Registration card (right) -->
            <div class="grid gap-6 lg:grid-cols-5">

                <!-- Image column -->
                <div class="lg:col-span-3">
                    <div class="overflow-hidden rounded-2xl bg-gray-200 shadow-sm" style="aspect-ratio:16/9">
                        <img
                            v-if="event.images[activeImg]"
                            :src="event.images[activeImg].url"
                            :alt="event.payload.name"
                            class="h-full w-full object-cover transition-opacity duration-300"
                        />
                        <div
                            v-else
                            :class="`bg-gradient-to-br ${heroGradient} flex h-full items-center justify-center`"
                        >
                            <span class="text-5xl font-black uppercase tracking-tight text-white/20">
                                {{ event.type }}
                            </span>
                        </div>
                    </div>

                    <!-- Thumbnails — in own row so border never clips -->
                    <div v-if="event.images.length > 1" class="mt-3 flex gap-2">
                        <button
                            v-for="(img, i) in event.images"
                            :key="img.id"
                            class="h-14 w-20 shrink-0 overflow-hidden rounded-lg border-2 transition-all"
                            :class="i === activeImg
                                ? 'border-orange-500 opacity-100'
                                : 'border-transparent opacity-55 hover:opacity-80'"
                            @click="activeImg = i"
                        >
                            <img :src="img.url" alt="" class="h-full w-full object-cover" />
                        </button>
                    </div>

                    <!-- Tags — directly below the image/thumbnails -->
                    <div v-if="event.payload.tags?.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="tag in event.payload.tags"
                            :key="tag"
                            class="rounded-full bg-white px-3 py-1 text-xs font-medium text-gray-500 shadow-sm ring-1 ring-gray-100"
                        >
                            #{{ tag }}
                        </span>
                    </div>
                </div>

                <!-- Registration card column -->
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 h-full">

                        <!-- Price header -->
                        <div class="bg-[#eef1f8] px-5 py-5">
                            <p class="text-4xl font-black text-gray-900">{{ formatPrice(event.payload.pricing?.min_price) }}</p>
                            <p class="mt-0.5 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                {{ isFree ? 'No charge' : event.payload.pricing?.currency }}
                            </p>
                        </div>

                        <!-- Quick summary -->
                        <div class="divide-y divide-gray-50 border-b border-gray-100 text-sm">
                            <div class="flex items-center gap-2 px-5 py-2.5 text-gray-600">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                <span>{{ formatDateShort(event.payload.schedule?.starts_at) }}</span>
                            </div>
                            <div v-if="event.location_name" class="flex items-center gap-2 px-5 py-2.5 text-gray-600">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>{{ event.location_name }}</span>
                            </div>
                        </div>

                        <!-- Form -->
                        <div class="px-5 py-5">
                            <template v-if="!regDone">
                                <h3 class="mb-3 text-sm font-bold text-gray-900">Register your interest</h3>
                                <form class="space-y-3" @submit.prevent="register">
                                    <div>
                                        <label for="show-name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Name</label>
                                        <input
                                            id="show-name"
                                            v-model="regForm.name"
                                            type="text"
                                            placeholder="Your name"
                                            :class="['w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 outline-none transition focus:ring-2',
                                                regErrors.name
                                                    ? 'border-red-400 focus:border-red-400 focus:ring-red-100'
                                                    : 'border-gray-200 focus:border-orange-400 focus:ring-orange-100']"
                                        />
                                        <p v-if="regErrors.name" class="mt-1 text-xs text-red-500">{{ regErrors.name }}</p>
                                    </div>
                                    <div>
                                        <label for="show-email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Email</label>
                                        <input
                                            id="show-email"
                                            v-model="regForm.email"
                                            type="text"
                                            placeholder="you@example.com"
                                            :class="['w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 outline-none transition focus:ring-2',
                                                regErrors.email
                                                    ? 'border-red-400 focus:border-red-400 focus:ring-red-100'
                                                    : 'border-gray-200 focus:border-orange-400 focus:ring-orange-100']"
                                        />
                                        <p v-if="regErrors.email" class="mt-1 text-xs text-red-500">{{ regErrors.email }}</p>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="regLoading"
                                        class="w-full rounded-lg bg-orange-500 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-orange-600 active:scale-[0.98] disabled:opacity-60"
                                    >
                                        {{ regLoading ? 'Registering…' : 'Register Now' }}
                                    </button>
                                </form>
                                <p class="mt-3 text-center text-xs text-gray-400">
                                    Confirmation + reminders sent automatically.
                                </p>
                            </template>

                            <div v-else class="rounded-xl bg-emerald-50 p-5 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                                    <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <p class="font-bold text-emerald-700">You're registered!</p>
                                <p class="mt-1 text-xs text-gray-500">Check your email for confirmation.</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ③ Info cards — full width below the image row -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                <div class="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-100">
                        <svg class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date & Time</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-800 leading-snug">{{ formatDateRange() }}</p>
                    </div>
                </div>

                <div v-if="event.location_name" class="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-purple-100">
                        <svg class="h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Location</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-800">{{ event.location_name }}</p>
                    </div>
                </div>

                <div v-if="event.payload.venue?.name" class="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-purple-100">
                        <svg class="h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Venue</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-800">{{ event.payload.venue.name }}</p>
                        <p v-if="event.payload.venue.capacity" class="text-xs text-gray-400">
                            Capacity: {{ Number(event.payload.venue.capacity).toLocaleString() }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Registered</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-800">
                            {{ localAttendeeCount }} {{ localAttendeeCount === 1 ? 'person' : 'people' }}
                        </p>
                    </div>
                </div>

            </div>

            <!-- ④ Description -->
            <div class="rounded-2xl bg-white px-6 py-5 shadow-sm">
                <h2 class="mb-3 text-base font-bold text-gray-900">About this event</h2>
                <p class="leading-relaxed text-gray-600">{{ event.payload.description }}</p>
            </div>

            <!-- ⑤ Map -->
            <div v-if="event.latitude && event.longitude" class="overflow-hidden rounded-2xl shadow-sm">
                <div ref="mapEl" style="height: 300px; width: 100%;" />
                <div class="flex items-center justify-between bg-white px-4 py-2.5">
                    <span class="text-xs text-gray-400">{{ event.location_name }}</span>
                    <a
                        :href="`https://www.google.com/maps?q=${event.latitude},${event.longitude}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-xs font-medium text-orange-500 hover:text-orange-600"
                    >
                        Open in Google Maps
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>


        </div>
    </div>
</template>
