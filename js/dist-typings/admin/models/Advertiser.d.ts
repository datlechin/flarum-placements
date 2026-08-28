import Model from 'flarum/common/Model';
import type Campaign from './Campaign';
export default class Advertiser extends Model {
    name: () => string;
    contactEmail: () => string | null;
    notes: () => string | null;
    /**
     * Whether a report link exists, never the token itself. The token is the one
     * credential here that reaches somebody outside the forum, so it leaves the
     * server exactly once: in the response to the request that created it.
     */
    hasReportToken: () => boolean;
    reportTokenExpiresAt: () => Date | null;
    campaigns: () => false | (Campaign | undefined)[];
}
