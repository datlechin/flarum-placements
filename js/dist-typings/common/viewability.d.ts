import type { Candidate } from './types';
/**
 * Watches a slot and reports when it has actually been seen.
 *
 * This is what turns an impression from a vanity number into something an
 * administrator can defend in a sponsorship conversation, and it is what
 * exposes a slot nobody ever scrolls to.
 *
 * Returns a function that stops watching. Call it on unmount: a discussion
 * page can create and destroy dozens of these as the reader scrolls.
 */
export declare function watchViewability(element: Element, candidate: Candidate, onViewable: () => void): () => void;
/**
 * The share of pixels this creative has to show to count as seen.
 */
export declare function requiredRatio(candidate: Candidate): number;
