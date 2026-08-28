/**
 * The shape the server writes into `app.data.placement`.
 *
 * The key's absence is meaningful: a viewer who is ad-free, or a crawler, has
 * nothing written for them at all, so there are no slots to reserve space for
 * and nothing describing inventory in view-source.
 */
export interface PlacementPayload {
    /** Whether this viewer asked to see every slot filled with a sample. */
    demo: boolean;
    slots: Record<string, SlotConfig>;
}
export type Breakpoint = 'phone' | 'tablet' | 'desktop';
/**
 * One creative this viewer is eligible for, as the server left it.
 *
 * The server filters; the browser draws. Everything left to decide — the
 * viewport, the viewer's own clock, how often they have already seen this —
 * only exists here.
 */
export interface Candidate {
    creative: number;
    campaign: number;
    /** Lower is more important. Only the best non-empty tier is drawn from. */
    tier: number;
    /** Relative share within the tier. Always at least 1. */
    weight: number;
    type: string;
    payload: Record<string, unknown>;
    /** Where a click goes, for the first-party creative types. */
    url: string | null;
    /** Overrides the default "Advertisement" wording. */
    label: string | null;
    /**
     * Proof this server served this creative, in this slot, a moment ago.
     *
     * Null on a demo sample and on anything served before measurement existed,
     * in which case nothing is reported: there is nothing to prove.
     */
    token?: string | null;
    nonce?: string | null;
    issued?: number | null;
    /**
     * How many times this campaign may be shown to one reader per window.
     *
     * Enforced in the browser, because how often somebody has already seen
     * something is only knowable there. Best effort: a private window or cleared
     * site data starts the count again.
     */
    cap?: number | null;
    window?: string;
}
export interface SlotConfig {
    /** Stable identifier, matching a placement declared on the server. */
    key: string;
    /** Which part of the forum this slot belongs to. */
    group: string;
    /** Translation key for the slot's name. */
    label: string;
    /** Translation key for the sentence saying where the slot appears. */
    description: string;
    /** Creative type keys this slot accepts. Empty means all of them. */
    allowedTypes: string[];
    /** How many creatives may be served here at once. */
    maxFill: number;
    /** Whether the slot may appear more than once on a page. */
    repeating: boolean;
    /** Width and height in CSS pixels, as guidance rather than a constraint. */
    recommendedSize: [number, number] | null;
    /**
     * Height in CSS pixels to hold open per breakpoint, so the slot does not
     * shift the page when it fills. A breakpoint that is absent reserves
     * nothing, which is not the same instruction as reserving zero.
     */
    reserve: Partial<Record<Breakpoint, number>>;
    fallback: 'next_tier' | 'house' | 'passback' | 'collapse';
    passbackCreativeId: number | null;
    labelMode: 'inherit' | 'always' | 'never';
    /**
     * Whether the slot draws again on every page, or keeps what it drew for the
     * rest of the visit.
     */
    rotation?: 'random' | 'sticky';
    /** Repeating slots only: show in every nth position. */
    everyN: number | null;
    /** Repeating slots only: at most this many times per page. */
    repeatLimit: number | null;
    sortOrder: number;
    /**
     * Everything eligible here, best tier first. Absent in demo mode, where the
     * slot renders a sample instead.
     */
    candidates?: Candidate[];
}
