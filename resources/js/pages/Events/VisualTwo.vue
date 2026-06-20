<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';

interface EventImage { id: number; url: string; sort_order: number }

interface EventPayload {
    name: string;
    description: string;
    venue: { name: string };
    schedule: { starts_at: string | number; ends_at: string | number };
    pricing: { currency: string; min_price: string | number };
}

interface EventCard {
    id: string;
    type: string;
    status: string;
    created_time: number;
    location_name: string | null;
    payload: EventPayload;
    images: EventImage[];
}

const props = defineProps<{
    filters: { date_from?: string; date_to?: string; location?: string; type?: string; search?: string };
    locations: string[];
    types: string[];
}>();

const form = reactive({
    search:    props.filters.search    ?? '',
    date_from: props.filters.date_from ?? '',
    date_to:   props.filters.date_to   ?? '',
    location:  props.filters.location  ?? '',
    type:      props.filters.type      ?? '',
});

const events        = ref<EventCard[]>([]);
const page          = ref(0);
const lastPage      = ref<number | null>(null);
const total         = ref<number | null>(null);
const loading       = ref(false);
const hasLoadedOnce = ref(false);
const fetchError    = ref(false);
const sentinel      = ref<HTMLElement | null>(null);
const activeImages  = reactive<Record<string, number>>({});
let observer: IntersectionObserver | null = null;

function getActiveImg(id: string) { return activeImages[id] ?? 0; }
function prevImg(id: string, count: number, e: MouseEvent) {
    e.stopPropagation();
    activeImages[id] = ((activeImages[id] ?? 0) - 1 + count) % count;
}
function nextImg(id: string, count: number, e: MouseEvent) {
    e.stopPropagation();
    activeImages[id] = ((activeImages[id] ?? 0) + 1) % count;
}

const hasMore = () => lastPage.value === null || page.value < lastPage.value;

function ts(val: string | number | undefined): number {
    if (!val) return 0;
    return typeof val === 'string' ? parseInt(val, 10) : val;
}

async function loadMore() {
    if (loading.value || !hasMore()) return;
    loading.value = true;
    fetchError.value = false;
    const params = new URLSearchParams({ page: String(page.value + 1), per_page: '15', status: 'published', sort: 'asc' });
    if (form.search)    params.set('search',    form.search);
    if (form.date_from) params.set('date_from', form.date_from);
    if (form.date_to)   params.set('date_to',   form.date_to);
    if (form.location)  params.set('location',  form.location);
    if (form.type)      params.set('type',       form.type);
    try {
        const res = await fetch(`/events/data?${params}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) { fetchError.value = true; return; }
        const data = await res.json();
        events.value.push(...data.data);
        page.value      = data.current_page;
        lastPage.value  = data.last_page;
        total.value     = data.total;
        hasLoadedOnce.value = true;
    } catch {
        fetchError.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    events.value = [];
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoadedOnce.value = false;
    fetchError.value = false;
    loadMore();
}

function clearFilters() {
    form.search    = '';
    form.date_from = '';
    form.date_to   = '';
    form.location  = '';
    form.type      = '';
    applyFilters();
}

function formatDay(val: string | number | undefined): string {
    const t = ts(val);
    if (!t) return '—';
    return new Intl.DateTimeFormat(undefined, { day: '2-digit' }).format(new Date(t * 1000));
}

function formatMonthShort(val: string | number | undefined): string {
    const t = ts(val);
    if (!t) return '';
    return new Intl.DateTimeFormat(undefined, { month: 'short' }).format(new Date(t * 1000));
}

function formatTime(val: string | number | undefined): string {
    const t = ts(val);
    if (!t) return '';
    return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZoneName: 'short' }).format(new Date(t * 1000));
}

function formatPrice(val: string | number | undefined): string {
    const n = parseFloat(String(val ?? 0));
    return n === 0 ? 'Free' : `$${n.toFixed(2)}`;
}

const isFree = (val: string | number | undefined) => parseFloat(String(val ?? 0)) === 0;

const typeGradients: Record<string, string> = {
    concert:    'from-purple-500 to-pink-500',
    conference: 'from-blue-500 to-cyan-400',
    meetup:     'from-teal-500 to-green-400',
    workshop:   'from-orange-500 to-amber-400',
    festival:   'from-pink-500 to-rose-400',
    sports:     'from-green-600 to-emerald-400',
    networking: 'from-indigo-500 to-purple-400',
    exhibition: 'from-yellow-500 to-orange-400',
};

onMounted(() => {
    observer = new IntersectionObserver((e) => { if (e[0]?.isIntersecting) loadMore(); }, { rootMargin: '400px' });
    if (sentinel.value) observer.observe(sentinel.value);
    loadMore();
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="EventHub" />

    <div class="min-h-screen bg-gray-50">

        <!-- Sticky navbar -->
        <nav class="sticky top-0 z-20 bg-white shadow-sm">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-6">
                <div class="flex items-center gap-2">
                    <span class="text-xl font-black tracking-tight text-gray-900">Event<span class="text-orange-500">Hub</span></span>
                    <span class="hidden rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 sm:inline">
                        {{ total !== null ? `${total.toLocaleString()} events` : '…' }}
                    </span>
                </div>
                <a href="/events-visual-1" class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500 hover:bg-gray-200 transition-colors">
                    Switch to Grid →
                </a>
            </div>
        </nav>

        <div class="mx-auto flex max-w-7xl gap-6 px-6 py-6">

            <!-- ── Left sidebar ── -->
            <aside class="hidden w-60 shrink-0 lg:block">
                <div class="sticky top-[4.5rem] space-y-5 rounded-2xl bg-white p-5 shadow-sm">

                    <div>
                        <p class="mb-1.5 text-xs font-bold uppercase tracking-wider text-gray-500">Search</p>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                            </svg>
                            <input
                                v-model="form.search"
                                type="text"
                                placeholder="Search events…"
                                class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-8 pr-3 text-sm text-gray-700 outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                                @keydown.enter="applyFilters"
                            />
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400">Press Enter to search</p>
                    </div>

                    <div>
                        <p class="mb-1.5 text-xs font-bold uppercase tracking-wider text-gray-500">Location</p>
                        <div class="relative">
                            <select
                                v-model="form.location"
                                class="w-full appearance-none rounded-xl border border-gray-200 bg-white py-2.5 pl-3.5 pr-9 text-sm text-gray-700 shadow-sm outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                                @change="applyFilters"
                            >
                                <option value="">All Locations</option>
                                <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-1.5 text-xs font-bold uppercase tracking-wider text-gray-500">Event Type</p>
                        <div class="relative">
                            <select
                                v-model="form.type"
                                class="w-full appearance-none rounded-xl border border-gray-200 bg-white py-2.5 pl-3.5 pr-9 text-sm text-gray-700 shadow-sm outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                                @change="applyFilters"
                            >
                                <option value="">All Types</option>
                                <option v-for="t in types" :key="t" :value="t" class="capitalize">{{ t }}</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Date Range</p>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">From</label>
                            <input v-model="form.date_from" type="date"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                                @change="applyFilters" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">To</label>
                            <input v-model="form.date_to" type="date"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                                @change="applyFilters" />
                        </div>
                    </div>

                    <button class="w-full text-center text-xs text-gray-400 hover:text-gray-600 transition-colors" @click="clearFilters">
                        Clear filters
                    </button>
                </div>
            </aside>

            <!-- ── Timeline list ── -->
            <div class="min-w-0 flex-1">

                <!-- Mobile filter strip -->
                <div class="mb-4 flex flex-wrap gap-2 lg:hidden">
                    <div class="relative flex-1">
                        <svg class="absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input v-model="form.search" type="text" placeholder="Search…"
                            class="w-full rounded-lg border border-gray-200 bg-white py-1.5 pl-7 pr-3 text-xs text-gray-700 outline-none focus:border-orange-400"
                            @keydown.enter="applyFilters" />
                    </div>
                    <div class="relative">
                        <select v-model="form.location" class="appearance-none rounded-xl border border-gray-200 bg-white py-1.5 pl-3 pr-8 text-xs text-gray-700 shadow-sm outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" @change="applyFilters">
                            <option value="">All Locations</option>
                            <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                    <div class="relative">
                        <select v-model="form.type" class="appearance-none rounded-xl border border-gray-200 bg-white py-1.5 pl-3 pr-8 text-xs text-gray-700 shadow-sm outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" @change="applyFilters">
                            <option value="">All Types</option>
                            <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="relative space-y-4 pl-14">

                    <div class="absolute bottom-0 left-9 top-0 w-px bg-gray-200" />

                    <article
                        v-for="(event, i) in events"
                        :key="event.id"
                        class="event-card group relative flex cursor-pointer overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 transition-all duration-200 hover:shadow-md hover:ring-orange-200"
                        :style="{ animationDelay: `${i * 40}ms` }"
                        @click="router.visit(`/events/${event.id}?from=visual2`)"
                    >
                        <!-- Date bubble centered on the timeline line -->
                        <div class="absolute -left-[38px] top-6 flex h-9 w-9 flex-col items-center justify-center rounded-full bg-white shadow-sm ring-2 ring-gray-100 z-10">
                            <span class="text-[11px] font-black leading-none text-gray-800">
                                {{ formatDay(event.payload.schedule.starts_at || event.created_time) }}
                            </span>
                            <span class="text-[8px] font-semibold uppercase leading-none text-orange-500">
                                {{ formatMonthShort(event.payload.schedule.starts_at || event.created_time) }}
                            </span>
                        </div>

                        <!-- Image with prev/next arrows -->
                        <div class="relative h-48 w-64 shrink-0 overflow-hidden">
                            <img
                                v-if="event.images[getActiveImg(event.id)]"
                                :key="getActiveImg(event.id)"
                                :src="event.images[getActiveImg(event.id)].url"
                                :alt="event.payload.name"
                                class="img-slide h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                :class="`h-full w-full bg-gradient-to-br ${typeGradients[event.type] ?? 'from-slate-500 to-slate-400'} flex items-end p-3`"
                            >
                                <span class="text-sm font-bold uppercase text-white/60">{{ event.type }}</span>
                            </div>

                            <button
                                v-if="event.images.length > 1"
                                class="absolute left-2 top-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition-opacity duration-150 hover:bg-black/60 group-hover:opacity-100"
                                @click="prevImg(event.id, event.images.length, $event)"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>

                            <button
                                v-if="event.images.length > 1"
                                class="absolute right-2 top-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition-opacity duration-150 hover:bg-black/60 group-hover:opacity-100"
                                @click="nextImg(event.id, event.images.length, $event)"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>

                            <div v-if="event.images.length > 1" class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                                <div
                                    v-for="(_, idx) in event.images" :key="idx"
                                    :class="['h-1.5 w-1.5 rounded-full transition-all duration-200', idx === getActiveImg(event.id) ? 'bg-white scale-125' : 'bg-white/50']"
                                />
                            </div>

                            <div v-if="event.images.length > 1" class="absolute right-2 top-2 rounded-full bg-black/40 px-2 py-0.5 text-[10px] font-semibold text-white backdrop-blur-sm">
                                {{ getActiveImg(event.id) + 1 }}/{{ event.images.length }}
                            </div>
                        </div>

                        <!-- Card content -->
                        <div class="flex flex-1 flex-col justify-between p-5">
                            <div>
                                <div class="mb-2 flex items-start justify-between gap-2">
                                    <h3 class="line-clamp-2 text-base font-bold leading-snug text-gray-900">
                                        {{ event.payload.name }}
                                    </h3>
                                    <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold capitalize text-gray-600">
                                        {{ event.type }}
                                    </span>
                                </div>
                                <p class="line-clamp-3 text-sm leading-relaxed text-gray-500">
                                    {{ event.payload.description }}
                                </p>
                            </div>

                            <div class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                                <div class="flex items-center gap-1.5 text-sm text-gray-500">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    {{ formatTime(event.payload.schedule.starts_at) }}
                                </div>
                                <div v-if="event.location_name" class="flex items-center gap-1.5 text-sm text-gray-500">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    {{ event.location_name }}
                                </div>

                                <div class="flex items-center justify-between pt-1">
                                    <span :class="['text-base font-bold', isFree(event.payload.pricing?.min_price) ? 'text-emerald-600' : 'text-gray-900']">
                                        {{ formatPrice(event.payload.pricing?.min_price) }}
                                    </span>
                                    <span class="text-sm font-semibold text-orange-500 transition-colors group-hover:text-orange-600">
                                        View Details →
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Empty state -->
                <div v-if="hasLoadedOnce && events.length === 0 && !loading" class="py-24 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-sm">
                        <svg class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                        </svg>
                    </div>
                    <p class="font-semibold text-gray-600">No events found</p>
                    <p class="mt-1 text-sm text-gray-400">Try adjusting your filters or search term</p>
                    <button class="mt-4 text-sm font-medium text-orange-500 hover:text-orange-600" @click="clearFilters">
                        Clear all filters
                    </button>
                </div>

                <div v-if="fetchError" class="py-12 text-center">
                    <p class="text-sm text-red-500">Failed to load events. Check your connection and try again.</p>
                    <button class="mt-3 text-sm font-medium text-orange-500 hover:text-orange-600" @click="loadMore">Retry</button>
                </div>

                <div ref="sentinel" class="h-2" />
                <div v-if="loading" class="flex justify-center py-10">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-gray-200 border-t-orange-500" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
.event-card { animation: fadeSlideIn 0.3s ease both; }

@keyframes imgFade {
    from { opacity: 0; }
    to   { opacity: 1; }
}
.img-slide { animation: imgFade 0.2s ease; }
</style>
