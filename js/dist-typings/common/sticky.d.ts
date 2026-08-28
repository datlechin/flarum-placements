import type { Candidate } from './types';
/**
 * The creatives this slot settled on, of those still eligible.
 *
 * Anything that has since become ineligible — a campaign that ended, a
 * frequency cap now reached — is dropped rather than resurrected, so a sticky
 * slot never shows something a fresh draw would not have.
 */
export declare function stickyChoice(placement: string, eligible: Candidate[]): Candidate[];
export declare function remember(placement: string, chosen: Candidate[]): void;
/**
 * For tests.
 */
export declare function resetSticky(): void;
