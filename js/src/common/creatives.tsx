import app from 'flarum/common/app';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { mayLoadWithConsent } from './consent';
import { registerRenderer } from './renderers';
import type { Candidate } from './types';

/**
 * Where a creative may send the reader.
 *
 * Checked again here even though the server checks it on save, because this is
 * the last point before the URL reaches an `href`: a `javascript:` destination
 * is script execution, not navigation, and one stale row from before the
 * server-side check existed would be enough.
 */
function safeUrl(url: string | null): string | null {
  if (!url) return null;

  return /^https?:\/\//i.test(url) ? url : null;
}

/**
 * Wraps a creative in its link, when it has one.
 *
 * `rel` is not optional. `sponsored` is what Google requires of a paid link,
 * and a pattern of paid links passing PageRank earns an unnatural-outbound-links
 * action against the whole forum, which costs more than the advertising is
 * worth. `noopener` keeps the destination from reaching back through
 * `window.opener`.
 */
function linked(candidate: Candidate, content: Mithril.Children): Mithril.Children {
  const url = safeUrl(candidate.url);

  if (!url) return content;

  return (
    <a className="Placement-link" href={url} rel="sponsored nofollow noopener" target="_blank">
      {content}
    </a>
  );
}

function text(payload: Record<string, unknown>, key: string): string | null {
  const value = payload[key];

  return typeof value === 'string' && value !== '' ? value : null;
}

function size(payload: Record<string, unknown>, key: string): number | undefined {
  const value = payload[key];

  return typeof value === 'number' && value > 0 ? value : undefined;
}

export default function registerCreatives(): void {
  registerRenderer('image', (candidate) => {
    const src = text(candidate.payload, 'asset');

    if (!src) return null;

    return linked(
      candidate,
      <img
        className="Placement-image"
        src={src}
        // Empty rather than absent when there is no alt text: an advert with a
        // description of itself read out is worse for a screen reader than one
        // announced only by the slot's own label, and the surrounding <aside>
        // already says what this is.
        alt={text(candidate.payload, 'alt') ?? ''}
        width={size(candidate.payload, 'width')}
        height={size(candidate.payload, 'height')}
        loading="lazy"
        decoding="async"
      />
    );
  });

  // Markup somebody pasted in, kept inside a sandbox.
  //
  // `allow-scripts` without `allow-same-origin` is the whole point: with both,
  // the frame is same-origin and can simply remove its own sandbox attribute,
  // and from there it can read the CSRF token out of Flarum's boot payload and
  // call the API as whoever is reading the page.
  //
  // `srcdoc` rather than a URL, so nothing is served from this forum's origin.
  // The height is declared on the creative because a sandboxed frame cannot
  // measure itself without being allowed to talk to the page.
  registerRenderer('raw_html', (candidate) => {
    const html = candidate.payload.html;
    const height = candidate.payload.height;

    if (typeof html !== 'string' || html === '') return null;

    return (
      <iframe
        className="Placement-frame"
        sandbox="allow-scripts"
        srcdoc={html}
        height={typeof height === 'number' && height > 0 ? height : 250}
        loading="lazy"
        referrerpolicy="no-referrer"
        title={extractText(app.translator.trans('datlechin-placement.forum.label'))}
      />
    );
  });

  // A container an external network fills.
  //
  // The attributes are rendered as real attributes on a real element rather
  // than through `innerHTML`, and the server has already restricted which
  // names may reach here — so there is nothing to smuggle an `onerror` in
  // through.
  //
  // Deliberately *not* re-initialised when the reader navigates. Flarum is a
  // single-page application, so a route change rebuilds this element, and
  // re-requesting an advert at that moment is exactly the behaviour whose
  // permissibility nobody has established with any network. A forum owner who
  // has read their network's policy can turn `refreshOnNavigate` on.
  registerRenderer('network', (candidate) => {
    if (!mayLoadWithConsent(candidate.payload.requiresConsent !== false)) return null;

    const element = candidate.payload.element === 'ins' ? 'ins' : 'div';
    const attributes = candidate.payload.attributes;
    const height = candidate.payload.height;

    if (typeof attributes !== 'object' || attributes === null) return null;

    return m(element, {
      className: 'Placement-network',
      style: typeof height === 'number' && height > 0 ? { minHeight: `${height}px` } : undefined,
      ...(attributes as Record<string, string>),
    });
  });

  registerRenderer('text', (candidate) => {
    const headline = text(candidate.payload, 'headline');

    if (!headline) return null;

    return linked(
      candidate,
      <div className="Placement-text">
        <strong className="Placement-headline">{headline}</strong>
        {text(candidate.payload, 'body') && <span className="Placement-body">{text(candidate.payload, 'body')}</span>}
        {text(candidate.payload, 'cta') && <span className="Placement-cta">{text(candidate.payload, 'cta')}</span>}
      </div>
    );
  });
}

export { safeUrl };
