<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Gdpr;

use Datlechin\Placements\Listener\DetachDeletedUser;
use Datlechin\Placements\Model\Advertiser;
use Flarum\Gdpr\Data\Type;
use Illuminate\Support\Arr;

/**
 * What this extension holds about one member, for flarum/gdpr.
 *
 * A member who submits an advert gets an advertiser record and a campaign,
 * both named after their account and carrying their email address. Until now
 * none of that appeared in a data export, survived an anonymisation or went
 * away on an erasure, which is the one thing an extension holding an email
 * address must not do.
 *
 * The advertiser record linked to an account *is* that account's advertising
 * record. Flarum says as much where the link is set: it is written for a
 * member's own submissions, and an administrator links one by hand so that a
 * member's submissions are filed against it. So the account going away is that
 * record going away, and an administrator who linked something else should
 * unlink it first.
 *
 * Delivery figures are not touched by any of the three. They are hourly
 * counters keyed by campaign, creative, slot and device, they name nobody, and
 * the forum's totals have to keep adding up.
 *
 * @see \Datlechin\Placements\Submission\MemberInventory
 */
class AdvertisingData extends Type
{
    public static function dataType(): string
    {
        return 'Placements';
    }

    /**
     * The three descriptions below are resolved by the parent from
     * `flarum-gdpr.lib.data.<type>.*`, which is a namespace this extension
     * cannot write into. Overridden to name our own keys instead.
     */
    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('datlechin-placements.lib.gdpr.export_description');
    }

    public static function anonymizeDescription(): string
    {
        return self::staticTranslator()->trans('datlechin-placements.lib.gdpr.anonymize_description');
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('datlechin-placements.lib.gdpr.delete_description');
    }

    /**
     * Only the address. `name` would be right here too and is not listed on
     * purpose: this is a forum-wide list of key names to redact from
     * serialised payloads, and `name` is a key on nearly everything.
     *
     * @return string[]
     */
    public static function piiFields(): array
    {
        return ['contact_email'];
    }

    public function export(): ?array
    {
        $advertiser = $this->advertiser();

        if ($advertiser === null) {
            return null;
        }

        $data = [[
            'placements/advertiser.json' => $this->encodeForExport(Arr::only($advertiser->toArray(), [
                'name', 'contact_email', 'notes', 'created_at',
            ])),
        ]];

        // The relations rather than queries of their own: this is one member's
        // own inventory, which is a campaign or two, and the models carry the
        // types the query builder does not.
        foreach ($advertiser->campaigns->sortBy('id') as $campaign) {
            $data[] = ["placements/campaign-{$campaign->id}.json" => $this->encodeForExport(Arr::only($campaign->toArray(), [
                'name', 'status', 'tier', 'starts_at', 'ends_at', 'impressions', 'clicks', 'created_at',
            ]))];

            foreach ($campaign->creatives->sortBy('id') as $creative) {
                $data[] = ["placements/creative-{$creative->id}.json" => $this->encodeForExport(Arr::only($creative->toArray(), [
                    'name', 'type', 'status', 'weight', 'destination_url', 'payload',
                    'review_reason', 'reviewed_at', 'impressions', 'viewable_impressions', 'clicks', 'created_at',
                ]))];
            }
        }

        return $data;
    }

    public function anonymize(): void
    {
        $advertiser = $this->advertiser();

        if ($advertiser === null) {
            return;
        }

        $anonymous = $this->anonymousName();

        // Campaigns first, while the record still says what it was called.
        // `MemberInventory` names the advertiser and the campaign from the
        // same account, so renaming only the advertiser would leave the name
        // sitting at the top of the campaign list.
        $advertiser->campaigns()
            ->where('name', $advertiser->name)
            ->update(['name' => $anonymous]);

        $advertiser->name = $anonymous;
        $advertiser->contact_email = null;
        $advertiser->notes = null;

        // An anonymised account keeps its adverts, and the report link is a
        // URL that anyone holding it can read them at. Whoever was sent it is
        // not who is left.
        $advertiser->revokeReportToken();

        $advertiser->save();
    }

    public function delete(): void
    {
        $advertiser = $this->advertiser();

        if ($advertiser === null) {
            return;
        }

        // Through the model and one at a time, because deleting a campaign is
        // what clears its creatives, their slot assignments, its targeting
        // rules, and any slot pointing at one of those creatives as its
        // passback.
        //
        // Before the advertiser rather than with it: `Advertiser::deleting`
        // detaches campaigns instead of deleting them, which is right when an
        // administrator removes an advertiser and wrong when the adverts are
        // the thing being erased.
        $advertiser->campaigns->each->delete();

        $advertiser->delete();

        // `created_by` on campaigns this member never owned, and `reviewed_by`
        // on creatives they moderated, both outlive the advertiser record.
        //
        // Called here rather than left to the listener: flarum/gdpr erases by
        // calling `delete()` on the user model, and `Deleted` is only
        // dispatched by the API resource layer, so the listener never runs on
        // this path.
        DetachDeletedUser::detach($this->user->id);
    }

    protected function advertiser(): ?Advertiser
    {
        $advertiser = Advertiser::query()->where('user_id', $this->user->id)->first();

        return $advertiser instanceof Advertiser ? $advertiser : null;
    }

    /**
     * The same name flarum/gdpr is about to give the account itself, so the
     * two still line up afterwards.
     */
    protected function anonymousName(): string
    {
        $name = $this->settings->get('flarum-gdpr.default-anonymous-username');

        return (is_string($name) ? $name : 'Anonymous').($this->erasureRequest->id ?? '');
    }
}
