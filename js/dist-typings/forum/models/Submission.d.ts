import Model from 'flarum/common/Model';
/**
 * A member's own advert.
 *
 * Deliberately smaller than the admin-side Creative: there is no weight, no
 * campaign, no slot, and no advertiser on it, because none of those are the
 * member's to see or set.
 */
export default class Submission extends Model {
    name: () => string;
    type: () => string;
    payload: () => Record<string, unknown>;
    destinationUrl: () => string | null;
    /** Read-only: a member says what their advert is, the forum says whether it runs. */
    status: () => string;
    reviewReason: () => string | null;
    reviewedAt: () => Date | null;
    createdAt: () => Date | null;
    updatedAt: () => Date | null;
}
