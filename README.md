# Placements

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md) [![Latest Stable Version](https://img.shields.io/packagist/v/datlechin/flarum-placements.svg)](https://packagist.org/packages/datlechin/flarum-placements) [![Total Downloads](https://img.shields.io/packagist/dt/datlechin/flarum-placements.svg)](https://packagist.org/packages/datlechin/flarum-placements) [![Sponsor](https://img.shields.io/github/sponsors/datlechin?logo=githubsponsors&label=Sponsor)](https://github.com/sponsors/datlechin)

An ad server for [Flarum](https://flarum.org) 2.x. Campaigns, targeting, scheduling and reporting, in sixteen slots the theme already provides. No templates to edit.

<!--
  Absolute URLs rather than relative paths: `screenshots/` is `export-ignore`d,
  so it is absent from the Composer package, and Flarum's admin README modal
  renders the README from that package. Each image is captured at 2x and shown
  at half its pixel width, so it stays sharp on a retina display.
-->
<img src="https://raw.githubusercontent.com/datlechin/flarum-placements/main/screenshots/admin-campaigns.png" alt="The campaigns list: search and filters, then a table of campaigns with their advertiser, status, priority, flight and delivery" width="1190">

> [!WARNING]
> You are the one serving the advertising. Policy compliance, consent and disclosure to your members are yours to handle.

## What it does

- Sixteen slots, from the notice bar to the post footer.
- Campaigns with a flight, caps, a 7×24 schedule, even pacing and five priority tiers.
- Six creative types: image, text, formatted text, logo wall, network container, raw HTML.
- Targeting on eight axes with include/exclude rules.
- An ad-free permission for supporters.
- Impressions, viewable impressions and clicks, by device, in hourly buckets.
- Member submissions with a review queue.
- Advertiser report links: one signed URL showing delivery, nothing else.
- `/ads.txt` from a setting.
- Import from `davwheat/flarum-ext-ads`, and export/import between forums.

## Installation

```sh
composer require datlechin/flarum-placements:"*"
```

Enable it, then grant **Manage advertising** to whoever handles sponsors. Admins have it already.

## Updating

```sh
composer update datlechin/flarum-placements:"*"
php flarum migrate
php flarum cache:clear
```

## Getting started

1. Press **Open the forum in demo mode** on the extension page. Every slot fills with a labelled sample, visible only to you.
2. Create a campaign on the **Campaigns** tab and add a creative to it.
3. Assign the campaign to slots on the **Slots** tab.

A creative must be approved and a slot must be assigned before anything shows.

<img src="https://raw.githubusercontent.com/datlechin/flarum-placements/main/screenshots/forum-demo.png" alt="The forum in demo mode: every slot outlined and labelled with its name, key and recommended size" width="720">

## Slots

<img src="https://raw.githubusercontent.com/datlechin/flarum-placements/main/screenshots/admin-slots.png" alt="The slots tab: each slot with a switch, its key, its recommended size and a summary of any settings that differ from the default" width="1190">

| Group | Slot | Key | Size |
| --- | --- | --- | --- |
| Everywhere | Notice bar | `notice` | 728×90 |
| | Header | `header` | 160×30 |
| | Footer | `footer` | 728×90 |
| Every page | Top | `page_top` | 728×90 |
| | Bottom | `page_bottom` | 728×90 |
| | Sidebar, top | `page_sidebar_top` | 160×600 |
| | Sidebar, bottom | `page_sidebar_bottom` | 160×600 |
| Discussion list | Above the list | `index_above_list` | 728×90 |
| | Below the list | `index_below_list` | 728×90 |
| | Sidebar | `index_sidebar` | 160×600 |
| Discussions | After the first post | `discussion_after_op` | 728×90 |
| | End of the discussion | `discussion_stream_end` | 728×90 |
| | Sidebar | `discussion_sidebar` | 160×600 |
| | Post footer | `post_footer` | 468×60 |
| Profiles | Sidebar | `profile_sidebar` | 160×600 |
| Tags | Tag directory | `tags_page` | 728×90 |

"An advert every nth post" is the post footer. A 300×250 fits no Flarum sidebar; use 160×600 there.

## Campaigns

<img src="https://raw.githubusercontent.com/datlechin/flarum-placements/main/screenshots/admin-campaign.png" alt="One campaign: delivery figures, its flight, advertiser, cap and rate, and the creatives under it" width="1190">

| Field | Values |
| --- | --- |
| Status | `draft`, `scheduled`, `active`, `paused`, `archived` |
| Tier | Sponsorship, Guaranteed, Standard, Remnant, House |
| Caps | Total impressions, total clicks. Reaching either stops the campaign and notifies its author. |
| Pacing | `asap`, or `even` to spread the cap across the flight |
| Schedule | A 7×24 grid of forum hours (Flarum stores no timezone per user) |
| Frequency cap | Per campaign, per `session`, `hour` or `day`. Counted in the browser, so best effort. |

Only the highest tier with something eligible is drawn from, so a sponsorship is never diluted by a filler.

### Targeting

| Axis | Matches on |
| --- | --- |
| Visitor | Signed in or not |
| Group | The viewer's groups |
| Page | The route being viewed |
| Discussion | A specific discussion |
| Tag | Tags on the page (needs `flarum/tags`) |
| Language | The viewer's locale |
| Posts written | The viewer's post count |
| Account age | Days since joining |

No rules means everybody. Otherwise: different axes all have to match, same axis needs only one, exclusions win. If the viewer's value is unknown, an inclusion fails and an exclusion does not fire.

## Creatives

<img src="https://raw.githubusercontent.com/datlechin/flarum-placements/main/screenshots/forum-advert.png" alt="A forum index page with a leaderboard above the discussion list, labelled Advertisement, and a wall of sponsor logos in the footer" width="1190">

| Type | What it is |
| --- | --- |
| Image | A picture with a link. Paste a URL or upload a file. SVG is refused. |
| Text | Headline, body, call to action. |
| Formatted text | Written and rendered like a post. Links get `rel="sponsored"`. |
| Logo wall | A row of sponsor logos, each with its own link. |
| Network container | An element a third-party script fills. |
| Raw HTML | Pasted markup, rendered in a sandboxed iframe. |

Weight is 1 to 100. Status is `draft`, `pending`, `approved` or `rejected`; only approved creatives serve, and editing an approved one sends it back to the queue.

Raw HTML needs two keys turned at once, because pasted markup would otherwise run as same-origin JavaScript on every page:

```php
// config.php
'datlechin-placements' => ['raw_html' => true],
```

plus the **Author raw HTML creatives** permission.

## Slot settings

Each slot picks what happens when nothing in its tier matched, and how it rotates.

| Fallback | Behaviour |
| --- | --- |
| `next_tier` | Fall to the next tier down. Default. |
| `collapse` | Show nothing. |
| `house` | Skip the paid tiers, go straight to your own adverts. |
| `passback` | Show one nominated creative. |

Rotation is `random` (redraw every page) or `sticky` (hold for the visit).

## Permissions

| Permission | What it allows |
| --- | --- |
| Manage advertising | Campaigns, creatives, slots, reports. |
| Browse without advertising | No payload at all: no reserved space, no measurement, nothing in the page source. |
| Submit a creative for review | Adds a **My adverts** tab to the member's profile. |
| Author raw HTML creatives | Separate from managing, and useless without the `config.php` flag. |

Admins are not ad-free by default: whoever checks that the ads work needs to see them.

## Settings

| Setting | Default | What it does |
| --- | --- | --- |
| Timezone | `UTC` | The clock schedule hours are read in. |
| Retention | `90` days | How long hourly statistics are kept. 1 to 730. |
| `ads.txt` | *(empty)* | Served at `/ads.txt`. Empty answers 404, as the spec asks. |
| Network scripts | *(empty)* | Loader URLs, one per line, loaded `async` in the head. |

## Measurement

<img src="https://raw.githubusercontent.com/datlechin/flarum-placements/main/screenshots/admin-reports.png" alt="The reports tab: headline figures, a daily delivery chart, and breakdowns by campaign, creative, slot and device" width="1190">

Counted in the browser, because the server sees one request for a session that reads thirty discussions. Every event carries a token this server signed for that creative, in that slot, at that moment, so the endpoint cannot be used to exhaust a rival's cap.

An **impression** is counted when the slot mounts. A **viewable** impression is counted at the MRC standard: half the pixels for one continuous second, 30% for units 970×250 and larger, timer paused while the tab is hidden.

Stats are hourly buckets, which identify nobody:

```
2026-08-28 14:00 · campaign 3 · creative 7 · index_above_list · desktop · 412 impressions
```

## Console commands

| Command | What it does |
| --- | --- |
| `placements:flush` | Write buffered counts to the database. Scheduled every minute, and fires on roughly 1 beacon in 50 for forums without `schedule:run`. |
| `placements:prune` | Drop old buckets and unreferenced images. Scheduled daily. `--days=N`, `--keep-images`, `--dry-run`. |
| `placements:export` | Write the configuration out as JSON. `-o file`. |
| `placements:import` | Read it back on another forum. `--dry-run`, `--settings`. |
| `placements:import-davwheat` | Bring a `davwheat/flarum-ext-ads` setup across. `--dry-run`. |

Nothing serves straight after an import: `placements:import` arrives paused, `placements:import-davwheat` as drafts. The JSON matches advertisers and campaigns by name, so running it twice updates rather than duplicates, and the file never carries counts, report tokens or member submissions.

## Third-party networks

Put the network's loader URL in the settings and use a **network container** creative. The loader is only sent to readers actually being served adverts.

The container is **not** re-initialised when the reader navigates, because no network has confirmed that re-requesting an advert on a client-side route change is compliant. A per-creative `refreshOnNavigate` flag turns it on for anyone who has checked their own policy. No vendor lifecycles are shipped.

## Consent

This is not a CMP. Whatever CMP you already run tells it once:

```js
window.flarumPlacement.setConsent(true);
```

Consent is unknown until something says otherwise, and unknown counts as no, so a container creative that needs consent renders nothing. First-party images are unaffected.

## Member submissions

<img src="https://raw.githubusercontent.com/datlechin/flarum-placements/main/screenshots/admin-review.png" alt="The review queue: each submitted creative with a preview of how it will render, and approve and reject buttons" width="1190">

Members with **Submit a creative for review** get a **My adverts** tab on their profile. They can reach nothing else: no campaign, rate, weight, slot or other advertiser. Every write leaves the row pending, and there is a limit of ten undecided submissions each.

Staff work the queue from the **Review** tab. Rejected adverts stay listed with their reason.

## Advertiser reports

Press **regenerate** on an advertiser to get a URL, shown once and never again. It is unguessable, revocable, expirable and `noindex`. It shows delivery, never targeting, rates, or another advertiser.

## For extension developers

Declare a slot, a creative type or a targeting axis:

```php
// extend.php
return [
    (new Datlechin\Placements\Extend\Placements())
        ->placement(new Datlechin\Placements\Placement(
            key: 'acme.profile_rail',
            group: 'user',
            label: 'acme-widgets.lib.placements.profile_rail.label',
            recommendedSize: [160, 600],
            reserveDesktop: 600,
        ))
        ->creativeType(Acme\Creative\VideoType::class)
        ->dimension(Acme\Targeting\CountryDimension::class),
];
```

Namespace the key with a prefix of your own, then render it where you like:

```tsx
import PlacementSlot from 'ext:datlechin/flarum-placements/common/components/PlacementSlot';

extend(UserPage.prototype, 'sidebarItems', (items) => {
  items.add('acmeRail', <PlacementSlot name="acme.profile_rail" />, -50);
});
```

A creative type is a `CreativeTypeInterface` on the server plus a renderer under the same key on the client:

```ts
import { registerRenderer } from 'ext:datlechin/flarum-placements/common/renderers';

registerRenderer('video', (candidate) => <video src={candidate.payload.src} muted autoplay loop />);
```

A targeting axis is a `DimensionInterface`. Its `resolve()` runs on every page view and must not query. Return `false` from `isServerSide()` if the axis can only be decided in the browser.

## Sponsors

If this extension is useful to you, you can sponsor the work via [GitHub Sponsors](https://github.com/sponsors/datlechin) or [Buy Me a Coffee](https://buymeacoffee.com/ngoquocdat).

## Links

- [Packagist](https://packagist.org/packages/datlechin/flarum-placements)
- [GitHub](https://github.com/datlechin/flarum-placements)
