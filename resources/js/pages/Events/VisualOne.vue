<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';

interface EventImage { id: number; url: string; sort_order: number }

interface EventPayload {
    name: string;
    description: string;
    venue: { name: string; capacity: string | number };
    schedule: { starts_at: string | number; ends_at: string | number };
    pricing: { currency: string; min_price: string | number };
    tags: string[];
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
    featured: EventCard[];
}>();

/* ── Carousel ────────────────────────────────────────────── */
const slide = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

function goTo(i: number) { slide.value = i; resetTimer(); }
function next() { goTo((slide.value + 1) % props.featured.length); }
function prev() { goTo((slide.value - 1 + props.featured.length) % props.featured.length); }
function startTimer() {
    if (props.featured.length > 1)
        timer = setInterval(() => { slide.value = (slide.value + 1) % props.featured.length; }, 4500);
}
function resetTimer() { if (timer) clearInterval(timer); startTimer(); }

/* ── Infinite-scroll listing ─────────────────────────────── */
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
const showFilters   = ref(false);
const sentinel      = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasMore = () => lastPage.value === null || page.value < lastPage.value;

async function loadMore() {
    if (loading.value || !hasMore()) return;
    loading.value = true;
    fetchError.value = false;
    const params = new URLSearchParams({ page: String(page.value + 1), per_page: '12', status: 'published' });
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
        page.value       = data.current_page;
        lastPage.value   = data.last_page;
        total.value      = data.total;
        hasLoadedOnce.value = true;
    } catch {
        fetchError.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    events.value = []; page.value = 0; lastPage.value = null; total.value = null; hasLoadedOnce.value = false; fetchError.value = false;
    loadMore();
}
function selectType(t: string) { form.type = form.type === t ? '' : t; applyFilters(); }
function clearFilters() {
    Object.assign(form, { search: '', date_from: '', date_to: '', location: '', type: '' });
    showFilters.value = false;
    applyFilters();
}

/* ── Helpers ─────────────────────────────────────────────── */
function ts(val: string | number | undefined): number {
    if (!val) return 0;
    return typeof val === 'string' ? parseInt(val, 10) : val;
}
function formatDate(val: string | number | undefined): string {
    const t = ts(val);
    if (!t) return '—';
    return new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(t * 1000));
}
function formatPrice(val: string | number | undefined): string {
    const n = parseFloat(String(val ?? 0));
    return n === 0 ? 'Free' : `$${n.toFixed(2)}`;
}
function isPaid(val: string | number | undefined): boolean {
    return parseFloat(String(val ?? 0)) > 0;
}

/* Type → hero gradient fallback */
const typeBg: Record<string, string> = {
    concert: 'from-purple-400 to-violet-600', festival: 'from-amber-400 to-orange-500',
    conference: 'from-blue-400 to-sky-600',   workshop: 'from-emerald-400 to-green-600',
    meetup: 'from-cyan-400 to-teal-600',      sports: 'from-red-400 to-rose-500',
    networking: 'from-indigo-400 to-blue-600', exhibition: 'from-rose-400 to-pink-500',
};

onMounted(() => {
    startTimer();
    observer = new IntersectionObserver((e) => { if (e[0]?.isIntersecting) loadMore(); }, { rootMargin: '400px' });
    if (sentinel.value) observer.observe(sentinel.value);
    loadMore();
});
onBeforeUnmount(() => { if (timer) clearInterval(timer); observer?.disconnect(); });
</script>

<template>
    <Head title="EventHub" />

    <!-- ── Navbar ───────────────────────────────────────── -->
    <nav class="sticky top-0 z-30 bg-white shadow-sm">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
            <span class="text-xl font-black tracking-tight text-gray-900">Event<span class="text-orange-500">Hub</span></span>
            <div class="flex items-center gap-6 text-sm font-medium">
                <a href="/events-visual-1" class="text-orange-500">Card View</a>
                <a href="/events-visual-2" class="text-gray-400 transition-colors hover:text-gray-900">Timeline View</a>
            </div>
        </div>
    </nav>

    <!-- ── Featured Carousel ── dark hero ─────────────────── -->
    <section v-if="featured.length" class="bg-slate-900">

        <div class="relative mx-auto max-w-7xl px-8" style="height: 420px;">
            <div
                v-for="(event, i) in featured"
                :key="event.id"
                class="carousel-slide absolute inset-0 flex items-center"
                :class="i === slide ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'"
            >
                <div class="grid w-full items-center gap-10 lg:grid-cols-2">
                    <!-- Image -->
                    <div class="flex justify-center">
                        <div class="w-full max-w-md overflow-hidden rounded-2xl shadow-2xl ring-1 ring-white/10">
                            <img
                                v-if="event.images[0]"
                                :src="event.images[0].url"
                                :alt="event.payload.name"
                                class="aspect-[4/3] w-full object-cover"
                            />
                            <div
                                v-else
                                :class="['aspect-[4/3] w-full bg-gradient-to-br', typeBg[event.type] ?? 'from-gray-600 to-gray-800']"
                            />
                        </div>
                    </div>

                    <!-- Content -->
                    <div>
                        <span class="inline-block rounded-full bg-orange-500 px-4 py-1 text-xs font-bold uppercase tracking-widest text-white">
                            {{ event.type }}
                        </span>
                        <h2 class="mt-3 line-clamp-3 text-3xl font-extrabold leading-tight text-white lg:text-4xl">
                            {{ event.payload.name }}
                        </h2>
                        <p class="mt-2 line-clamp-2 text-sm text-gray-400">
                            {{ event.payload.description }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-300">
                            <span class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                {{ formatDate(event.payload.schedule.starts_at) }}
                            </span>
                            <span v-if="event.location_name" class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                </svg>
                                {{ event.location_name }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                {{ isPaid(event.payload.pricing?.min_price) ? 'Paid Event' : 'Free Event' }}
                            </span>
                        </div>

                        <button
                            class="mt-6 inline-flex items-center gap-2 rounded-full bg-orange-500 px-7 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-orange-600 active:scale-95"
                            @click="router.visit(`/events/${event.id}?from=visual1`)"
                        >
                            Register Now
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Prev / Next arrows -->
            <button v-if="featured.length > 1" class="absolute left-0 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm transition hover:bg-white/20" @click="prev">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button v-if="featured.length > 1" class="absolute right-0 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm transition hover:bg-white/20" @click="next">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <!-- Dot indicators -->
        <div v-if="featured.length > 1" class="flex justify-center gap-2 py-4">
            <button
                v-for="(_, i) in featured" :key="i"
                class="h-2 rounded-full transition-all duration-300"
                :class="i === slide ? 'w-6 bg-orange-500' : 'w-2 bg-white/30 hover:bg-white/50'"
                @click="goTo(i)"
            />
        </div>

        <!-- Search bar — white pill on dark: high contrast, clear CTA -->
        <div class="px-4 pb-8">
            <div class="mx-auto flex max-w-2xl overflow-hidden rounded-full bg-white shadow-2xl">
                <div class="flex items-center pl-4 text-orange-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>
                <input
                    v-model="form.search"
                    type="text"
                    placeholder="Search events or categories..."
                    class="flex-1 px-3 py-3.5 text-sm text-gray-800 outline-none placeholder:text-gray-400"
                    @keydown.enter="applyFilters"
                />
                <div class="flex items-center gap-1.5 border-l border-gray-100 px-4">
                    <svg class="h-4 w-4 shrink-0 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                    <div class="relative flex items-center">
                        <select v-model="form.location" class="cursor-pointer appearance-none bg-transparent py-1 pl-0 pr-5 text-sm text-gray-700 outline-none" @change="applyFilters">
                            <option value="">Select City</option>
                            <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
                        </select>
                        <div class="pointer-events-none absolute right-0 flex items-center">
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Fallback if no featured events -->
    <section v-else class="bg-slate-900 py-12 text-center">
        <h1 class="mb-2 text-4xl font-black tracking-tight text-white">Event<span class="text-orange-500">Hub</span></h1>
        <p class="mb-6 text-sm text-gray-400">Discover events near you</p>
        <div class="mx-auto max-w-2xl px-4">
            <div class="flex overflow-hidden rounded-full bg-white shadow-2xl">
                <div class="flex items-center pl-4 text-orange-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <input v-model="form.search" type="text" placeholder="Search events..." class="flex-1 px-3 py-3.5 text-sm text-gray-800 outline-none placeholder:text-gray-400" @keydown.enter="applyFilters" />
            </div>
        </div>
    </section>

    <!-- ── Type pills + Filters bar ─────────────────────── -->
    <div class="sticky top-14 z-20 bg-white shadow-sm">
        <div class="mx-auto max-w-7xl px-4">
            <div class="flex items-center gap-3 py-3">
                <!-- Pill strip -->
                <div class="flex flex-1 gap-2 overflow-x-auto pb-0.5" style="scrollbar-width:none;">
                    <button
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-all"
                        :class="!form.type ? 'bg-orange-500 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                        @click="selectType('')"
                    >All Events</button>
                    <button
                        v-for="t in types" :key="t"
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium capitalize transition-all"
                        :class="form.type === t ? 'bg-orange-500 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                        @click="selectType(t)"
                    >{{ t }}</button>
                </div>
                <!-- Filters toggle -->
                <button
                    class="flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-all"
                    :class="showFilters ? 'border-orange-500 bg-orange-500 text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    @click="showFilters = !showFilters"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
                    Filters
                    <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="showFilters ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
            <!-- Date range panel -->
            <div v-if="showFilters" class="flex flex-wrap items-end gap-4 border-t border-gray-100 py-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium uppercase tracking-wide text-gray-500">From</label>
                    <input v-model="form.date_from" type="date" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-200" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium uppercase tracking-wide text-gray-500">To</label>
                    <input v-model="form.date_to" type="date" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-200" />
                </div>
                <button class="rounded-lg bg-orange-500 px-5 py-1.5 text-sm font-medium text-white hover:bg-orange-600 transition-colors" @click="applyFilters(); showFilters = false">Apply</button>
                <button class="rounded-lg border border-gray-200 px-5 py-1.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" @click="clearFilters">Clear all</button>
            </div>
        </div>
    </div>

    <!-- ── Card grid ─────────────────────────────────────── -->
    <div class="min-h-screen bg-gray-50">
        <div class="mx-auto max-w-7xl px-4 py-6">
            <p v-if="total !== null" class="mb-5 text-sm text-gray-500">
                Showing <span class="font-semibold text-gray-800">{{ total.toLocaleString() }}</span> events
            </p>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="(event, i) in events"
                    :key="event.id"
                    class="event-card group cursor-pointer overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 transition-shadow hover:shadow-md"
                    :style="{ animationDelay: `${Math.min(i, 11) * 50}ms` }"
                    @click="router.visit(`/events/${event.id}?from=visual1`)"
                >
                    <!-- Image — padding-bottom ratio trick: immune to repaint glitches that aspect-ratio CSS gets during hover transitions -->
                    <div class="relative overflow-hidden bg-gray-100" style="padding-bottom: 62.5%">
                        <img v-if="event.images[0]" :src="event.images[0].url" :alt="event.payload.name" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                        <div v-else :class="['absolute inset-0 bg-gradient-to-br', typeBg[event.type] ?? 'from-gray-300 to-gray-400']" />
                        <span class="absolute right-2.5 top-2.5 rounded-full bg-black/50 px-2.5 py-0.5 text-[11px] font-medium capitalize text-white backdrop-blur-sm">{{ event.type }}</span>
                    </div>

                    <!-- Body -->
                    <div class="p-4">
                        <h3 class="mb-3 line-clamp-1 text-base font-bold text-gray-900">{{ event.payload.name }}</h3>
                        <div class="space-y-1.5 text-sm text-gray-500">
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <span>{{ formatDate(event.payload.schedule.starts_at) }}</span>
                            </div>
                            <div v-if="event.location_name" class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 shrink-0 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span class="line-clamp-1">{{ event.location_name }}</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
                            <span
                                class="text-xs font-semibold"
                                :class="isPaid(event.payload.pricing?.min_price) ? 'text-gray-700' : 'text-emerald-600'"
                            >
                                {{ formatPrice(event.payload.pricing?.min_price) }}
                            </span>
                            <span class="text-sm font-medium text-orange-500 transition-colors group-hover:text-orange-600">View Details →</span>
                        </div>
                    </div>
                </article>
            </div>

            <div v-if="hasLoadedOnce && events.length === 0 && !loading" class="py-24 text-center">
                <svg class="mx-auto mb-4 h-14 w-14 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <p class="text-gray-400">No events found for the selected filters.</p>
                <button class="mt-4 text-sm text-orange-500 hover:underline" @click="clearFilters">Clear filters</button>
            </div>

            <div v-if="fetchError" class="py-12 text-center">
                <p class="text-sm text-red-500">Failed to load events. Check your connection and try again.</p>
                <button class="mt-3 text-sm font-medium text-orange-500 hover:text-orange-600" @click="loadMore">Retry</button>
            </div>

            <div ref="sentinel" class="h-2" />
            <div v-if="loading" class="flex justify-center py-10">
                <div class="h-7 w-7 animate-spin rounded-full border-2 border-gray-200 border-t-orange-500" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.carousel-slide { transition: opacity 0.6s ease; }

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
.event-card { animation: fadeInUp 0.3s ease both; }
</style>
