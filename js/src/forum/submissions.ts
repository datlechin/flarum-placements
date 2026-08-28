import app from 'flarum/forum/app';
import type Mithril from 'mithril';

export const SUBMISSION_RESOURCE = 'placement-submissions';

const EXTENSION = 'datlechin-placements';

export function trans(key: string, params: Record<string, unknown> = {}): Mithril.Children {
  return app.translator.trans(`${EXTENSION}.forum.submissions.${key}`, params);
}

/**
 * Whether this viewer may submit anything at all.
 *
 * Read from the forum payload rather than from a permission check on the
 * client, because the client has no permission list: the server decides, and
 * says so in one boolean.
 */
export function canSubmit(): boolean {
  return app.forum.attribute<boolean>('canSubmitPlacements') === true;
}

/**
 * The types a member may submit.
 *
 * The server sends only these, so the form cannot offer a choice that would be
 * refused on save -- and it is a shorter list than the admin panel's even for
 * somebody holding both permissions, because a type that runs code is authored
 * there and never submitted.
 */
export function submittableTypes(): Array<{ key: string; label: string }> {
  return app.forum.attribute<Array<{ key: string; label: string }>>('placementSubmittableTypes') ?? [];
}
