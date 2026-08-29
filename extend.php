<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements;

// Imported explicitly: `use Flarum\Extend` below aliases the whole `Extend`
// segment, so an unqualified `Extend\Placements` would resolve to Flarum's
// namespace rather than this extension's.
use Datlechin\Placements\Extend\Placements;
use Flarum\Api\Resource\ForumResource;
use Flarum\Extend;
// Imported rather than written inline for the same reason: inside this
// namespace a bare `Flarum\Search\...` resolves to
// `Datlechin\Placements\Flarum\Search\...`, and the failure is a 500 from
// the search manager rather than anything that names the mistake.
use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\Search\Database\DatabaseSearchDriver;
use Illuminate\Console\Scheduling\Event;

return [
    // Registered unconditionally, and cheap to leave registered: every binding
    // in it is lazy, so a request that serves nothing pays for nothing. It is
    // also what guarantees the placement registry exists whenever this
    // extension is enabled, which other extensions rely on when they declare
    // a placement of their own.
    (new Extend\ServiceProvider())
        ->register(PlacementServiceProvider::class),

    // Assets are compiled into Flarum's own bundles. Note js(), not
    // jsDirectory(): jsDirectory() publishes chunks at /assets/js/{extension-id}/,
    // which puts the extension id into a public URL path, and that is exactly
    // the shape EasyList already uses to block self-hosted ad plugins on other
    // platforms. The main bundle is concatenated with core's, so it cannot be
    // blocked by name. Keep it that way.
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        // Default priority, so this runs after core has populated the payload
        // and after the route's own content handler: the actor and their
        // groups are already resolved by the time it is called.
        ->content(Frontend\AddPlacementPayload::class)
        // After the plan, so it can tell whether this viewer is being served
        // anything at all: an ad-free member should not be handed a network's
        // script for adverts they will never see.
        ->content(Frontend\AddNetworkScripts::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    // Only the two things that really are settings. Campaigns are records and
    // live in their own tables: writing one through the settings API would
    // dispatch Settings\Event\Saved and restart every queue worker on the forum.
    (new Extend\Settings())
        ->default(Support\Settings::TIMEZONE, Support\Settings::DEFAULT_TIMEZONE)
        ->default(Support\Settings::RETENTION_DAYS, Support\Settings::DEFAULT_RETENTION_DAYS)
        ->default(Support\Settings::ADS_TXT, '')
        ->default(Support\Settings::NETWORK_SCRIPTS, ''),

    // The one path in this extension that is not ours to name: it is fixed by
    // the IAB specification, and a network that cannot find it there treats the
    // forum's inventory as unauthorised.
    (new Extend\Routes('forum'))
        ->get('/ads.txt', 'datlechin-placements.ads_txt', Http\Controller\AdsTxtController::class)
        // What an advertiser gets instead of a login. The URL is the
        // credential: unguessable, revocable, and noindex.
        ->get('/r/{token}', 'datlechin-placements.report_link', Http\Controller\AdvertiserReportController::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    // Flarum routes every list filter, search term and sort through a searcher
    // — `AbstractDatabaseResource::filters()` is final and throws — so each of
    // the three administered lists needs one before it can be asked anything
    // more specific than "all of them".
    //
    // Note that registering a searcher changes how the Index endpoint answers:
    // it goes through the searcher *instead of* the resource, so the permission
    // has to be re-checked there. See Search\AbstractManagedSearcher.
    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(Model\Creative::class, Search\CreativeSearcher::class)
        ->setFulltext(Search\CreativeSearcher::class, Search\Fulltext\CreativeTextFilter::class)
        ->addFilter(Search\CreativeSearcher::class, Search\Filter\StatusFilter::class)
        ->addFilter(Search\CreativeSearcher::class, Search\Filter\CampaignFilter::class)
        ->addFilter(Search\CreativeSearcher::class, Search\Filter\TypeFilter::class)

        ->addSearcher(Model\Campaign::class, Search\CampaignSearcher::class)
        ->setFulltext(Search\CampaignSearcher::class, Search\Fulltext\CampaignTextFilter::class)
        ->addFilter(Search\CampaignSearcher::class, Search\Filter\StatusFilter::class)
        ->addFilter(Search\CampaignSearcher::class, Search\Filter\TierFilter::class)
        ->addFilter(Search\CampaignSearcher::class, Search\Filter\AdvertiserFilter::class)
        ->addFilter(Search\CampaignSearcher::class, Search\Filter\SourceFilter::class)

        ->addSearcher(Model\Advertiser::class, Search\AdvertiserSearcher::class)
        ->setFulltext(Search\AdvertiserSearcher::class, Search\Fulltext\AdvertiserTextFilter::class),

    (new Extend\ApiResource(Api\Resource\CampaignResource::class)),
    (new Extend\ApiResource(Api\Resource\CreativeResource::class)),
    (new Extend\ApiResource(Api\Resource\AdvertiserResource::class)),
    (new Extend\ApiResource(Api\Resource\PlacementSettingResource::class)),

    // The same rows as CreativeResource, behind a much narrower door: a
    // member's own submissions and nothing else. Widening the administrative
    // resource instead would have put one permission check between a member and
    // every rate on the forum.
    //
    // Its own model class, not Creative — see Model\Submission for why that is
    // load-bearing rather than tidiness.
    (new Extend\ApiResource(Api\Resource\SubmissionResource::class)),

    // Open to guests by necessity — most readers of a public forum are guests,
    // and refusing to count them would make every number meaningless. What
    // stops it being a stats-poisoning endpoint is that each event must carry
    // a token this server signed.
    (new Extend\Routes('api'))
        ->post('/placements/events', 'datlechin-placements.events', Api\Controller\RecordEventsController::class)
        ->get('/placements/report', 'datlechin-placements.report', Api\Controller\ReportController::class)
        // What the retention setting is holding, so that the field saying "90
        // days" can say what 90 days currently amounts to.
        ->get('/placements/storage', 'datlechin-placements.storage', Api\Controller\StorageController::class)
        // The first gate that refused one creative in one slot, which is most
        // of the support an ad server ever generates.
        ->get('/placements/diagnose', 'datlechin-placements.diagnose', Api\Controller\DiagnoseController::class)
        // Somewhere to put the banner a sponsor emailed you. Without it every
        // image creative needs a URL hosted elsewhere, and a member submitting
        // an advert has to solve image hosting first.
        ->post('/placements/uploads', 'datlechin-placements.uploads', Api\Controller\UploadCreativeImageController::class)
        // Fresh proof for adverts already served. A plan is minted once per
        // page load, so without this one nonce has to cover a whole reading
        // session -- and a nonce is refused twice by both ends.
        ->post('/placements/tokens', 'datlechin-placements.tokens', Api\Controller\RefreshTokensController::class),

    // The path deliberately says `placements` and not anything containing
    // `ad`, `ads`, `banner` or `sponsor`: those are the tokens EasyList matches
    // on, and a blocked upload path would take the images down on the readers
    // who see adverts at all. Same reasoning as D1's choice of name.
    (new Extend\Filesystem())
        ->disk(Upload\CreativeImageUploader::DISK, function (Paths $paths, UrlGenerator $url): array {
            return [
                'root' => "$paths->public/assets/placements",
                'url' => $url->to('forum')->path('assets/placements'),
            ];
        }),

    // Generous enough to survive a household, an office or a university behind
    // one address, and low enough that nobody floods the buffer from a laptop.
    (new Extend\ThrottleApi())
        ->set('datlechin-placements.events', Api\Throttler\EventThrottler::class)
        ->set('datlechin-placements.tokens', Api\Throttler\EventThrottler::class),

    // The beacon is fired with `navigator.sendBeacon`, which cannot set a
    // header, so a CSRF token could never reach this route.
    //
    // Exempting it is correct rather than merely convenient. CSRF protects
    // against an attacker making somebody's browser perform an authenticated
    // action; there is no authenticated action here to perform. Nothing is read,
    // nothing belonging to the viewer is written, and the only thing that can be
    // affected — a count — is already gated by a token this server signed for
    // one creative in one slot for one moment.
    (new Extend\Csrf())
        ->exemptRoute('datlechin-placements.events')
        ->exemptRoute('datlechin-placements.tokens'),

    // Alerts only, and not email by default: a campaign reaching its cap is
    // useful to know and not worth waking somebody up for.
    (new Extend\Notification())
        ->type(Notification\CampaignStoppedBlueprint::class, ['alert']),

    (new Extend\Console())
        ->command(Console\FlushStatsCommand::class)
        ->command(Console\PruneStatsCommand::class)
        ->command(Console\ImportDavwheatCommand::class)
        ->command(Console\ExportCommand::class)
        ->command(Console\ImportCommand::class)
        // Every minute, so a cap is enforced within a minute of being reached
        // and overdelivery is bounded by that and nothing else. Daily pruning
        // is enough: the table grows by hours, not by seconds.
        ->schedule(Console\FlushStatsCommand::class, function (Event $event): void {
            $event->everyMinute();
        })
        ->schedule(Console\PruneStatsCommand::class, function (Event $event): void {
            $event->daily();
        }),

    // The tag directory only exists when flarum/tags does. A tag-filtered
    // listing at /t/{slug} is still a plain IndexPage, so it is already
    // covered by index_above_list and needs nothing here.
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-tags', fn () => [
            (new Placements())
                ->placement(...BuiltInPlacements::tags())
                // Registered here rather than as a default so that a rule
                // editor on a forum without tags does not offer an axis that
                // can never resolve to anything.
                ->dimension(Targeting\Dimension\TagDimension::class),
        ]),
];
