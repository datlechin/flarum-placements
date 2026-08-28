import type { Candidate } from './types';
export type EventType = 'impression' | 'viewable' | 'click';
export interface ReportedEvent {
    type: EventType;
    token: string;
    nonce: string;
    issued: number;
    creative: number;
    campaign: number;
    placement: string;
    device: string;
}
/**
 * Which class of screen this is.
 *
 * Read from the CSS variable core sets, so it agrees with the breakpoints the
 * stylesheet uses rather than with a second set of numbers that could drift
 * from them.
 */
export declare function device(): string;
/**
 * Report that something happened to a creative.
 *
 * Silently does nothing for a candidate with no token — a demo sample, or one
 * served before measurement was switched on. There is nothing to prove, so
 * there is nothing to count.
 */
export declare function report(type: EventType, candidate: Candidate, placement: string): void;
/**
 * Send whatever is queued.
 *
 * `sendBeacon` is used where it exists because it survives the page being
 * closed, which is exactly when the last impression of a visit is reported.
 * It cannot set headers, which is why the endpoint is exempt from CSRF and
 * relies on the signed token instead.
 */
export declare function flush(): void;
/**
 * For tests.
 */
export declare function resetBeacon(): void;
export declare function pending(): ReportedEvent[];
