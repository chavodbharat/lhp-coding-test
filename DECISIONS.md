# What We Built & Why

## Two Different Layouts

We were asked for two distinct layouts — so we made sure they actually feel like different products, not just the same page with a different colour.

**Visual 1** is a dark hero with an auto-scrolling featured carousel at the top, and a 3-column card grid below. Filters live as pill chips right under the hero so they're always visible. Cards animate in with a staggered fade-up — feels like the page is filling in, not just loading.

**Visual 2** takes a completely different approach — a sticky sidebar on the left holds all the filters, and the right side shows events as a vertical timeline with date bubbles. Cards are wide and horizontal (image on the left, details on the right) which gives each event a bit more room to breathe. Events are sorted chronologically so the timeline actually makes sense to scroll through.

---

## Images

The events didn't have images in the original data, so we added full support end-to-end. There's a new `event_images` table linked to events, images are stored locally in Laravel's storage (no external URLs), and each event gets at least two images from a seeded placeholder pool.

---

## Turning Lat/Lng into Real City Names

The dataset only has coordinates — no city names. We didn't want to call an external geocoding API (rate limits, latency, costs, needing a key), so we built a simple offline lookup instead.

The seeder already uses ~80 fixed city anchor points, so we just mapped those same coordinates to human-readable city names using nearest-neighbour distance. It's instant and works perfectly for this dataset because every seeded event is within half a degree of its anchor city.

The resolved city name (e.g. "Miami, US") gets saved to a dedicated `location_name` column on the events table, so filtering by city is just a fast indexed lookup — no geocoding at query time.

---

## Date & Time

Event timestamps are stored as Unix integers. On the frontend, we use JavaScript's built-in `Intl.DateTimeFormat` with `undefined` as the locale — which means it automatically uses whatever timezone the user's device is set to. So if you're in London the event shows in BST, if you're in New York it shows in EST. No guessing, no hardcoded zones.

---

## Filtering & Search Performance

The dataset has 1.25 million events. That's the first thing we had to deal with — searching or filtering across a table that size naively would just time out.

We handled this in two ways:

**Default city filter** — both pages load with "Miami, US" pre-selected. That alone cuts the working set from 1.25M rows down to ~17K before any other filter or search runs.

**Search** — you can't put a FULLTEXT index on a raw JSON column in MySQL, so we added a stored generated column (`event_name`) that automatically extracts the event name from the JSON payload. Then we put the FULLTEXT index on that column. When you search, we first resolve up to 1000 matching event IDs using `MATCH...AGAINST`, then the main query filters just those IDs. This keeps the expensive full-text scan bounded and fast.

---

## Database Indexes

With 1.25 million rows, the wrong query without an index just hangs. We added indexes deliberately for the exact filter combinations both pages use:

- A composite index on `(status, created_time)` — covers the most common query: published events ordered by date
- A composite index on `(status, type, created_time)` — covers filtered queries when event type is also selected
- A B-tree index on `location_name` — makes city filtering an instant lookup instead of a full scan
- A FULLTEXT index on the `event_name` generated column — powers the keyword search

We also added a unique constraint on `(event_id, email)` in the attendees table so the database itself guarantees no duplicate registrations, even if something bypasses the application layer.

---

## Animations

We kept animations purposeful — they only appear on content that's actually loading or appearing, not on static parts of the page like the navbar or sidebar.

- Card grid (V1) fades cards in with a slight upward drift, staggered so the grid fills in sequentially
- Timeline (V2) slides cards in from below — a softer feel to match the calmer layout
- Carousel images cross-fade (no sliding, which can cause layout issues with different aspect ratios)
- Thumbnail images zoom slightly on hover to signal they're clickable

---

## Event Detail Page

The event detail page pulls everything together — full image gallery with thumbnail switcher, a registration card, venue and schedule info, and an interactive map.

For the map we used Leaflet.js with CartoDB Voyager tiles (free, no API key, looks close to Google Maps). The marker is placed on the exact event coordinates and opens a popup with the venue name. The map is loaded lazily on mount so it doesn't block the rest of the page from rendering.

---

## Registering for an Event

We validate in two places deliberately — client-side first, server-side as a safety net.

**In Vue** — before any request is made, we check that the name is filled in and at least 2 characters, that it doesn't contain numbers or symbols, and that the email looks like a real email address. If anything fails, the error shows up inline under the field immediately — no loading, no network round-trip.

**In Laravel** — if the request reaches the server, it validates again with stricter rules: the email must pass RFC format checking plus a live DNS lookup to catch fake domains. Any errors that come back are shown inline under the relevant field, not just as a generic toast.

To handle double-submissions cleanly, we use `firstOrCreate` on the combination of event + email. So if someone submits twice, the second attempt is silently ignored — they just get told they're already registered rather than getting an error or a duplicate record.

A confirmation email is queued immediately after successful registration (not sent synchronously, so the response stays fast).

---

## Reminder Emails

We built a dedicated Artisan command (`reminders:send`) that handles both reminder windows:

- **3 days before** — runs daily, targets events starting on that specific day
- **24 hours before** — runs hourly, targets events within a ±30 minute window around the 24-hour mark (so no event gets missed regardless of its exact start time)

The command has a `--dry-run` flag you can use to preview who would receive emails without actually sending anything — useful for testing the scheduler without spamming real inboxes.

Since event start times live inside a JSON column, we use MySQL's `JSON_EXTRACT` to pull them out and compare as Unix timestamps. Attendees are processed in batches of 200 to keep memory usage flat, and all emails are queued rather than sent synchronously.
