<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_email
 * @property int|null $user_id
 * @property string|null $report_token
 * @property Carbon|null $report_token_expires_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Campaign> $campaigns
 * @property-read User|null $user
 */
class Advertiser extends AbstractModel
{
    /**
     * Bytes of entropy behind a report token. 32 bytes is 43 base64url
     * characters, which is what the column is sized for.
     */
    public const TOKEN_BYTES = 32;

    protected $table = 'placement_advertisers';

    public $timestamps = true;

    protected $casts = [
        'report_token_expires_at' => 'datetime',
    ];

    /**
     * Hidden from every serialiser by default. A leaked token is a leaked
     * report, and reports are the one thing here an outsider can read.
     *
     * @var list<string>
     */
    protected $hidden = ['report_token'];

    /**
     * Whether a token was issued during this request.
     *
     * The link is shown exactly once, in the response to the request that
     * asked for it. Listing advertisers later must not be a way to recover it,
     * because at that point the URL is the only thing standing between an
     * outsider and the report.
     */
    public bool $wasRecentlyIssuedToken = false;

    protected static function booted(): void
    {
        static::deleting(function (self $advertiser) {
            // Campaigns outlive their advertiser: the delivery already
            // happened and the reports still have to add up.
            $advertiser->campaigns()->update(['advertiser_id' => null]);
        });
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a fresh report token, invalidating any previous one.
     */
    public function regenerateReportToken(?\DateTimeInterface $expiresAt = null): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');

        $this->report_token = $token;
        $this->report_token_expires_at = $expiresAt === null ? null : Carbon::instance($expiresAt);
        $this->wasRecentlyIssuedToken = true;

        return $token;
    }

    public function revokeReportToken(): void
    {
        $this->report_token = null;
        $this->report_token_expires_at = null;
    }

    public function hasUsableReportToken(): bool
    {
        if ($this->report_token === null) {
            return false;
        }

        return $this->report_token_expires_at === null
            || $this->report_token_expires_at->isFuture();
    }
}
