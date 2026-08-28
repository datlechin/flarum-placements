# Placements

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/datlechin/flarum-placements.svg)](https://packagist.org/packages/datlechin/flarum-placements) [![Total Downloads](https://img.shields.io/packagist/dt/datlechin/flarum-placements.svg)](https://packagist.org/packages/datlechin/flarum-placements)

An ad server for Flarum 2.x. Campaigns with flights, caps and targeting; typed creatives rather than a box you paste HTML into; slots declared by the components that render them; and a viewer who is entitled to see nothing gets nothing at all.

> [!WARNING]
> Serving third-party advertising is something you take on, not something this extension takes on for you. Advertising policy compliance, consent for whatever your network sets, and what you disclose to your members are the forum owner's responsibility. See [Third-party networks](#third-party-networks).

## Installation

```sh
composer require datlechin/flarum-placements:"*"
```

Enable it, then grant **Manage advertising** to whoever handles sponsors — administrators have it already.

## The first thing to do

Open the admin page and press **Open the forum in demo mode**.

Every slot on the forum fills with a labelled sample, visible only to you. Flarum has no template files to open, so this is the only way to see where a placement actually is — and it answers "why is my advert not showing?" in about five seconds.

## How it decides what to show

The server filters, the browser draws.

Everything that depends on who is asking is decided in PHP — permissions, group membership, the campaign's flight and caps, the route, the tags on the page. What survives is written into the page's boot payload. The browser then makes the final draw, because the last few inputs only exist there: the viewport, the reader's own clock, and how often they have already seen a given creative.

Two consequences worth knowing:

- **Serving costs no queries.** The inventory is one cached array, dropped whenever a campaign changes.
- **Eligible inventory is visible in the page source.** That is fine for house ads and direct-sold campaigns. Anything commercially sensitive should be decided on the server, which is where group and permission targeting already happens.

Only the most important tier with something eligible is ever drawn from, so a sponsorship is never diluted by a filler.

## Slots

| Group | Slot |
| --- | --- |
| Everywhere | Notice bar, header, footer |
| Every page | Top, bottom, sidebar top, sidebar bottom |
| Discussion list | Above the list, below the list, sidebar |
| Discussions | After the first post, end of the discussion, sidebar, post footer |
| Profiles | Sidebar |
| Tags | Tag directory (needs flarum/tags) |

Every one of them is a real, priority-ordered extension point in core. There is not one `view()` override, with a single exception: core's `Footer` returns `null` and offers nothing to extend, so that slot is reached by overriding it and keeping the original's result.

**"An advert every nth post" is the post footer**, not a standalone row. Core exposes no seam between posts, and splicing into the post stream is not merely inelegant: the stream keys its scroll targets, scrubber maths and load-more off `.PostStream-item[data-index]` and watches new items with a `ResizeObserver`, so an advert that resizes after loading drags the reader's viewport. The footer slot is keyed on the post's own number, so it also stays put when `flarum/realtime` pushes a new post into an open discussion.

### Sizes

`--sidebar-width` is 190px, widening to 260px and 280px at the larger breakpoints, and the discussion page narrows it to 180px. **A 300×250 fits no sidebar on a Flarum forum.** The sidebar slots recommend a 160×600 instead.

## Targeting

A campaign with no rules reaches everybody. Otherwise: rules on different axes all have to match, rules on the same axis need only one to match, and an exclusion always wins.

Shipped axes: visitor (signed in or not), group, page, discussion, tag, language, posts written, days since joining.

One rule is worth stating because it is the difference between sensible and maddening behaviour. When the viewer's value on an axis is unknown, an *inclusion* cannot be confirmed and fails, while an *exclusion* cannot be confirmed either and therefore does not fire — so a campaign excluded from the `nsfw` tag still runs on a profile page, which carries no tags at all.

## Ad-free browsing

Grant **Browse without advertising** to a group. Somebody who has it gets no payload written for them at all: no reserved space, no measurement, and nothing in the page source describing inventory they cannot see.

Note that this is an explicit grant and nothing else. Administrators are *not* ad-free by default, deliberately: the person who has to check that the advertising works needs to be able to see it.

## Campaign discipline

**Even delivery** is a probabilistic throttle, not a forecast. It compares how much of the flight has elapsed with how much of the cap is spent and thins the campaign out when it is ahead. It will not hit the total exactly; it stops a month of inventory being spent in three days, which is the complaint people actually have. Real forecasting — predicting matching impressions per targeting combination and reforecasting as campaigns change — is a subsystem of months and is deliberately absent.

**Scheduling** is a seven-by-twenty-four grid, stored as 168 bits. The hours are the *forum's*, not the reader's: Flarum stores no timezone for anybody, so viewer-local hours could only be decided in the browser and could not be enforced. Which clock the forum keeps is a setting.

**Frequency capping** is counted in the reader's browser, per campaign rather than per creative — an advertiser who supplied four variations has still shown you their advert four times. It is best effort and the admin panel says so: a private window, cleared site data or a second device all start the count again. It stops somebody seeing the same advert forty times in an afternoon; it is not a guarantee to quote to an advertiser.

**When nothing matched** is per slot, and the four answers are genuinely different. `next_tier` falls from one tier to the next and is the default, because a lower-tier campaign is still somebody paying. `collapse` shows nothing rather than falling: a sponsorship position that quietly fills with remnant is worth less than an empty one, and the sponsor notices. `house` falls straight past the paid tiers to your own adverts, which is `collapse` with something in the hole. `passback` shows one creative you nominate — it still has to be approved, so nominating one is not a way around review.

**Rotation** is per slot. `random` draws again on every page; `sticky` holds the choice for the visit, because a sponsor whose advert flickers between three others as a reader moves through the forum looks like a forum with a fault — and because holding it makes one creative's click-through rate mean something rather than being an average of whatever was drawn.

## Measurement

Counted in the browser, because the server sees one request for a session that reads thirty discussions. Counting there would undercount by roughly thirty times, and it would do so in a way that is biased toward your most engaged readers.

Every decision the server makes carries a token signed for that creative, in that slot, at that moment. The beacon has to present it, and the body's own claims about what was served are never trusted. Without that, the endpoint would not merely be inaccurate: anybody could post to it until a rival advertiser's cap was exhausted and their campaign came off the forum.

An impression is counted when the slot mounts. A **viewable** impression is counted separately, at the MRC standard — at least half the pixels for at least one continuous second, thirty per cent for units of 970×250 and larger, and the timer stops when the tab is hidden. That second number is the one worth quoting to a sponsor, and it is what exposes a slot nobody ever scrolls to.

Statistics are stored as hourly buckets:

```
2026-08-28 14:00 · campaign 3 · creative 7 · index_above_list · desktop · 412 impressions
```

That identifies nobody. It is also the only affordable shape — a row per event is roughly three gigabytes a year on a busy forum, against about thirty megabytes for buckets.

```sh
php flarum placements:flush            # write buffered counts (scheduled every minute)
php flarum placements:prune            # drop old buckets and uploaded images nothing refers to
php flarum placements:prune --dry-run  # say what would go, delete nothing
```

The flush also runs opportunistically on about one beacon request in fifty, because a large share of self-hosted Flarum installs never added `schedule:run` and statistics that simply never appear are a worse failure than an occasional small write.

## Creatives

Six types ship, and a seventh can be registered by any extension.

**Image** is a picture with a link. Paste an address, or upload the file a sponsor emailed you: uploads land in `assets/placements`, are renamed by the forum rather than keeping the name they arrived with, and are checked by decoding them rather than by trusting the extension. SVG is refused — it is a document that can carry script, and it would be served from your own origin. Members with **Submit adverts for review** can upload too, which is the difference between a submission portal somebody can use and one where they have to solve image hosting first.

**Text** is a headline, some words and a call to action, stored and rendered as text so there is nothing to escape.

**Formatted text** sits between them and HTML: copy with a bold word and a link in it, written the same way a post on this forum is written and rendered through the same formatter. Whatever markdown or BBCode the forum has enabled works here, the output is safe because it comes from a parse tree rather than from filtering, and links are marked `rel="sponsored"` — a pattern of paid links passing PageRank earns an unnatural-outbound-links action against the whole forum.

**Logo wall** is a row of sponsor logos, each linking somewhere of its own. It is the shape a community forum actually sells: one creative naming everybody who paid this quarter, rather than one slot per sponsor and a stack of assignments to keep in step.

**Network container** is described under *Third-party networks* below.

**HTML** is markup somebody pasted in, and it is treated as what it is: privilege escalation with a delivery mechanism. Unsandboxed, it would run as same-origin JavaScript on every page of the forum including the one an administrator is looking at — and Flarum renders the CSRF token into the boot payload in plain text, so one `getElementById` is the whole chain from "pasted an advert" to "posts to the API as whoever is reading".

So it renders inside `<iframe sandbox="allow-scripts" srcdoc>`, never with `allow-same-origin` alongside (a same-origin sandboxed frame can remove its own `sandbox` attribute), and it needs two keys turned at once:

```php
// config.php — a file on disk, which no web request can write
'datlechin-placements' => ['raw_html' => true],
```

plus the **Author raw HTML creatives** permission, which is deliberately separate from being able to manage advertising at all. A compromised or careless administrator account can turn one of them.

Filtering `<script>` out of the pasted text is not offered and is not a substitute: `<img onerror>` and `<svg onload>` do the same job, and a filter that catches some of them teaches everybody the field is safe.

Every payload is validated against its own type's rules and stored normalised, so what reaches the renderer is what the type says it should be rather than whatever arrived.

## ads.txt

Served from a setting at `/ads.txt`, so authorising a seller does not need shell access. It answers 404 while it is empty, which is what the specification asks for — an empty file means "nobody is authorised" and would stop every network buying the forum's inventory.

## Third-party networks

Two things, described by what they mechanically are rather than named after a vendor.

**A loader script**, one URL per line in the settings, loaded `async` in the head — and only for readers who are actually being served adverts, so an ad-free member is not handed a network's script for adverts they will never see. Never in the JavaScript bundle: `Extend\Frontend->js()` concatenates every extension into one file, and a third-party script there would block the whole forum from rendering until the network answered.

**A container creative**, whose attributes are stored as data and rendered as real attributes on a real element rather than through `innerHTML`.

What this extension does **not** do is re-initialise that container when the reader navigates. Flarum is a single-page application, so a route change destroys and rebuilds it — and re-requesting an advert at that moment is exactly the behaviour whose permissibility nobody has established with any network. No Flarum contributor has ever confirmed that serving AdSense from a single-page app is compliant, and the failure mode is not a broken slot, it is the forum owner's account. A `refreshOnNavigate` flag exists per creative so somebody who has read their network's policy can turn it on. It is off until they do.

Nothing here is a vendor integration, and none of the vendor-specific lifecycles (`googletag.destroySlots`, `adsbygoogle.push`) are shipped, because they cannot be tested here and shipping an untested one puts somebody's account behind it.

## Consent

This extension is not a consent management platform and does not pretend to be one — since January 2024 Google has required a *certified* IAB TCF v2.2 CMP for EEA and UK traffic, and shipping our own banner would imply a certification it does not have.

It is the seam. Whatever CMP the forum already runs tells it once:

```js
window.flarumPlacement.setConsent(true);
```

Until something says otherwise consent is unknown, and unknown is treated as *not yet* — so a container creative marked as needing consent renders nothing. A first-party image sets nothing, so there is nothing to consent to and it is unaffected.

## For extension developers

Declare a slot your own frontend renders, add a creative type, or add a targeting axis:

```php
// extend.php
return [
    (new Datlechin\Placements\Extend\Placements())
        ->placement(new Datlechin\Placements\Placement(
            key: 'acme.profile_rail',
            group: 'user',
            label: 'acme-widgets.admin.placements.profile_rail.label',
            description: 'acme-widgets.admin.placements.profile_rail.description',
            recommendedSize: [160, 600],
            reserveDesktop: 600,
        ))
        ->creativeType(Acme\Creative\VideoType::class)
        ->dimension(Acme\Targeting\CountryDimension::class),
];
```

Namespace your key with a prefix of your own. Then render it wherever you like:

```tsx
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import PlacementSlot from 'ext:datlechin/flarum-placements/common/components/PlacementSlot';

extend(UserPage.prototype, 'sidebarItems', (items) => {
  items.add('acmeRail', <PlacementSlot name="acme.profile_rail" />, -50);
});
```

A creative type is a `CreativeTypeInterface` on the server (validation, payload shape, asset references) plus a renderer registered under the same key on the client:

```ts
import { registerRenderer } from 'ext:datlechin/flarum-placements/common/renderers';

registerRenderer('video', (candidate) => <video src={candidate.payload.src} muted autoplay loop />);
```

A targeting axis is a `DimensionInterface`. `resolve()` runs on the serving path of every page view and must not query — everything it needs is already on the actor, on the request, or in the API document the page is about to send anyway. Say `isServerSide(): false` if your axis can only be decided in the browser.

## Letting members submit adverts

Grant **Submit adverts for review** to a group and its members get a **My adverts** tab on their own profile. They write the advert, it goes into the queue, and staff decide.

A member can reach nothing else. Not a campaign, not a rate, not another advertiser, not a weight, and not a slot: where an advert runs and how much of the rotation it takes are the forum's decisions, made on something somebody already read. Every write leaves the row pending, editing an approved one included, so there is no path from the portal to an advert on the forum that nobody looked at. The types that run code are refused outright rather than checked against a permission.

Submitting makes the member an advertiser: an advertiser row and one campaign, created the first time and reused after. Approval and a slot assignment are the two things that put an advert on the page, and a member can do neither, so nothing appears until staff have done both.

Ten undecided submissions per member. Not a rate limit — somebody has to read each of these.

Staff work the queue from **Waiting for review** at the top of the admin page. Rejected adverts stay in the list with their reason, because a rejection is the start of a conversation and an administrator who cannot see what they turned down cannot answer "why?" a week later.

## Moving a configuration between forums

```sh
php flarum placements:export -o placement.json
php flarum placements:import placement.json --dry-run
php flarum placements:import placement.json
```

For staging to production, for a copy before a large change, and for keeping the set-up in version control.

Three things the file never carries. **Counts**, because they describe one forum's traffic. **Report tokens**, because an advertiser's report link is the whole of their authentication and a file holding one is a working login. **Member submissions**, because a `user_id` points at an account that does not exist on the machine the file is going to.

Everything arrives **paused**. A creative's approval travels with it, so the usual review gate is already spent by the time the file lands, and the last thing an import should do is put adverts on the page before anybody has looked at the result. Advertisers and campaigns are matched by name and never by id, so importing the same file twice updates rather than duplicates. Assignments naming a slot this forum does not have are dropped and reported; so is a creative whose payload no longer validates. `--settings` also brings the forum's own settings across, and only the four this extension owns — a file is a text file somebody may have edited.

## Coming from davwheat/flarum-ext-ads

```sh
php flarum placements:import-davwheat --dry-run   # see what would come across
php flarum placements:import-davwheat
```

It brings the six ad slots, the "between N posts" interval, and — importantly — the ad-free permission, so a supporter who paid to browse without advertising does not start seeing it because the extension was replaced.

Everything arrives as a **draft** and nothing serves until you have looked at it. The imported creatives are raw HTML, so they also need the permission and the `config.php` flag described above; the command says so rather than quietly opening that door for you.

## Advertisers

An advertiser gets a link, not a login.

Flarum's admin area is gated by a single all-or-nothing `administrate` permission, so "give them a login" means either handing a stranger the whole forum or building a second complete frontend — routes, policies, scoped endpoints, uploads, an approval queue — which is a three-month product bolted onto a three-week one, and the exact thing that killed the last extension to try.

What an advertiser actually wants is to know they are getting what they paid for without emailing you every week. So: press **regenerate** on an advertiser and you get a URL once, in that response and never again. It is unguessable, revocable, expirable, `noindex`, and serves plain HTML with no session and nothing of the forum's own on it.

It shows delivery. Never targeting, never rates, and never another advertiser.

## Design notes

A few decisions that look arbitrary and are not:

- **Campaigns have their own tables, never Flarum settings.** Writing through the settings API dispatches `Settings\Event\Saved`, which restarts every queue worker on the forum, and any setting registered as a LESS variable rebuilds one asset bundle per locale. Saving a banner should not do either.
- **Reserved space is an inline custom property**, not generated CSS, for the same reason.
- **Statistics are not stored per viewer.** When measurement lands it will be hourly aggregates by default, which identify nobody, so the default configuration does not quietly make a forum owner a data controller for advertising data they never asked to collect.
- **Class names and routes avoid the obvious words.** `.ad-slot`, `.ad-unit` and friends are element-hidden by EasyList on every website already. Nothing here detects or works around an ad blocker; the names are simply stable and unremarkable, and the required "Advertisement" label is translated text rather than a class.

## Links

- [GitHub](https://github.com/datlechin/flarum-placements)
- [Packagist](https://packagist.org/packages/datlechin/flarum-placements)
