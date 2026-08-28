import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Campaign from './Campaign';
export default class Creative extends Model {
    name: () => string;
    type: () => string;
    status: () => string;
    weight: () => number;
    destinationUrl: () => string | null;
    labelOverride: () => string | null;
    variantGroup: () => string | null;
    payload: () => Record<string, unknown>;
    /**
     * Placement key to weight. A null weight means "use the creative's own".
     */
    placements: () => Record<string, number | null>;
    impressions: () => number;
    viewableImpressions: () => number;
    clicks: () => number;
    /**
     * Why a creative was rejected. Written by whoever rejected it and shown to
     * whoever submitted it: a rejection with no reason is one somebody resubmits
     * unchanged.
     */
    reviewReason: () => string | null;
    reviewedAt: () => Date | null;
    createdAt: () => Date | null;
    campaign: () => false | Campaign;
    reviewer: () => false | User;
}
