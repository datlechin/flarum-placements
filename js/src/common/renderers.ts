import type Mithril from 'mithril';

import type { Candidate } from './types';

export type CreativeRenderer = (candidate: Candidate) => Mithril.Children;

const renderers: Record<string, CreativeRenderer> = {};

/**
 * Teach a slot how to draw a creative type.
 *
 * The companion to the PHP `CreativeTypeInterface`: the server validates and
 * stores the payload, this turns it into markup. Register under the same key
 * the type reports.
 */
export function registerRenderer(type: string, renderer: CreativeRenderer): void {
  renderers[type] = renderer;
}

export function rendererFor(type: string): CreativeRenderer | null {
  return renderers[type] ?? null;
}

/**
 * For tests, and for the admin preview, which swaps in its own.
 */
export function resetRenderers(): void {
  Object.keys(renderers).forEach((key) => delete renderers[key]);
}
