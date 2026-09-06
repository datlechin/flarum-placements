# Changelog

[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format, [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-09-06

First release. Requires Flarum 2.0 and PHP 8.3.

### Slots

- 16 built-in slots across the index, discussions, user profiles, the tag directory and the page chrome.
- Slots are declared in code by the components that render them, so an extension can add its own with the `Placements` extender and inherit targeting, reporting and fallback for free.
- Per-slot settings: enable, rotation, repeat interval and limit, maximum fill, passback creative, label mode, and reserved height per device class.
- Reserved height keeps the layout from jumping while a creative loads, and collapses cleanly when nothing is served.

### Campaigns

- Five tiers: sponsorship, guaranteed, standard, remnant and house. A slot falls through them in order.
- Flights with start and end dates, dayparting on a 7×24 grid, and a forum-wide timezone for both.
- Impression and click caps, with even or as-soon-as-possible pacing.
- Frequency capping per session, hour or day.
- Commercial fields: advertiser, rate type, rate amount, currency and contract notes. Recorded, never shown to readers.
- An alert when a campaign stops because it reached its cap.

### Targeting

- Eight axes: visitor type, group, account age, post count, locale, page, discussion and tag.
- Rules combine with `is` and `is not`, plus `at least` and `at most` on the numeric axes.
- Axes that cannot be decided on the server resolve in the browser instead, so page caching stays intact.

### Creatives

- Six types: image, text, rich text, logo wall, network container and raw HTML.
- Raw HTML stays off until a `config.php` key and a separate permission are both turned. Pasted markup renders in a sandboxed iframe.
- Variant groups with weights, for splitting delivery between creatives that mean the same thing.
- Image upload with dimension, size and type validation, plus a preview against the slot it is assigned to.
- Rich text is rendered by the forum's own formatter.

### Measurement

- Impressions, viewable impressions and clicks, counted in the browser and buffered.
- Viewability follows the MRC standard: half the pixels for one continuous second, 30% for units 970×250 and larger, with the timer paused while the tab is hidden.
- Every event carries a token signed for one creative, in one slot, at one moment, so the endpoint cannot be used to exhaust a rival's cap.
- Hourly buckets that identify nobody. Reports break delivery down by campaign, creative, slot and device, with a daily chart and CSV export.
- A retention setting, and a storage summary saying what it currently amounts to.

### Publisher tools

- `ads.txt` served at the path the IAB specification fixes.
- Third-party network loaders, sent only to readers who are actually being served adverts.
- A consent gate for creatives that need one, with nothing loaded until consent is given.
- Demo mode, for seeing the slots on the forum without waiting for a campaign to go live.
- A diagnostic that explains, for one creative in one slot, exactly which gate refused it.

### Member submissions

- Members with the permission can submit a creative and track it from their profile.
- A review queue with approve, reject and a reason the submitter can read.

### Advertiser reports

- A plain page at an unguessable, revocable URL showing an advertiser their own delivery. No login, no targeting, no rates, and no other advertiser.

### Permissions

- `datlechin-placements.manage`, `datlechin-placements.authorHtml`, `datlechin-placements.submit` and `datlechin-placements.viewWithoutAds`.

### Console

- `placements:flush` writes buffered counts, scheduled every minute.
- `placements:prune` drops old buckets and unreferenced images, scheduled daily.
- `placements:export` and `placements:import` move a configuration between forums.
- `placements:import-davwheat` brings a `davwheat/flarum-ext-ads` setup across.

[1.0.0]: https://github.com/datlechin/flarum-placements/releases/tag/v1.0.0
