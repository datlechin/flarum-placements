import type Mithril from 'mithril';
import type { Candidate } from './types';
export type CreativeRenderer = (candidate: Candidate) => Mithril.Children;
/**
 * Teach a slot how to draw a creative type.
 *
 * The companion to the PHP `CreativeTypeInterface`: the server validates and
 * stores the payload, this turns it into markup. Register under the same key
 * the type reports.
 */
export declare function registerRenderer(type: string, renderer: CreativeRenderer): void;
export declare function rendererFor(type: string): CreativeRenderer | null;
/**
 * For tests, and for the admin preview, which swaps in its own.
 */
export declare function resetRenderers(): void;
