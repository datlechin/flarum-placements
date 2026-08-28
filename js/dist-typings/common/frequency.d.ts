import type { Candidate } from './types';
/**
 * When the window a moment belongs to began.
 *
 * Fixed windows rather than rolling ones: "three times a day" is what an
 * administrator means, and a rolling window would need every timestamp kept
 * rather than one count.
 */
export declare function windowStart(window_: string, now?: number): number;
export declare function timesSeen(candidate: Candidate, now?: number): number;
/**
 * Whether this creative may still be shown to this reader.
 *
 * Counted per campaign rather than per creative: an advertiser who supplied
 * four variations of one advert has still shown the reader their advert four
 * times.
 */
export declare function withinCap(candidate: Candidate, now?: number): boolean;
export declare function recordSeen(candidate: Candidate, now?: number): void;
/**
 * For tests.
 */
export declare function resetFrequency(): void;
