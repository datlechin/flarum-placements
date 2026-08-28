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
        title={extractText(app.translator.trans('datlechin-placements.forum.label'))}
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

  // Copy the server has already turned into markup.
  //
  // `m.trust` is safe on exactly this payload and would not be on an arbitrary
  // one: the HTML was generated by Flarum's formatter from a parse tree, the
  // same pipeline that produces every post on the forum, rather than filtered
  // out of something somebody pasted.
  //
  // The wrap is the server's decision, not a guess made here. Copy that already
  // contains a link must not be put inside another one -- nested anchors are
  // invalid, and browsers close the outer one wherever they like.
  registerRenderer('rich_text', (candidate) => {
    const html = text(candidate.payload, 'html');

    if (!html) return null;

    const content = <div className="Placement-rich">{m.trust(html)}</div>;

    return candidate.payload.wrappable === true ? linked(candidate, content) : content;
  });

  // Everyone who paid this quarter, in one block.
  //
  // Each logo carries its own destination, so the campaign's own is unused.
  // Clicks are still counted: the slot listens on the wrapper around this, not
  // on each link.
  registerRenderer('logo_wall', (candidate) => {
    const stored = candidate.payload.logos;
    const columns = candidate.payload.columns;

    if (!Array.isArray(stored)) return null;

    // Filtered before it is mapped, not inside the map. Returning null for a
    // bad row from inside a keyed list is a hole, and Mithril refuses a
    // fragment where some children have keys and others do not -- so one row
    // with no image would take down every page the slot appears on.
    const rows = stored
      .filter((logo): logo is Record<string, unknown> => typeof logo === 'object' && logo !== null)
      .map((logo) => ({ src: text(logo, 'asset'), alt: text(logo, 'alt') ?? '', url: safeUrl(text(logo, 'url')) }))
      .filter((row): row is { src: string; alt: string; url: string | null } => row.src !== null);

    if (!rows.length) return null;

    return (
      <ul
        className="Placement-logos"
        // The property itself rather than a custom property it reads: one less
        // indirection, and the stylesheet keeps a sensible default for the
        // creatives stored before a column count was ever set.
        style={`grid-template-columns: repeat(${typeof columns === 'number' && columns > 0 ? columns : 4}, 1fr)`}
      >
        {rows.map((row, index) => {
          const image = <img className="Placement-logo" src={row.src} alt={row.alt} loading="lazy" decoding="async" />;

          return (
            <li className="Placement-logoItem" key={index}>
              {row.url ? (
                <a className="Placement-link" href={row.url} rel="sponsored nofollow noopener" target="_blank">
                  {image}
                </a>
              ) : (
                image
              )}
            </li>
          );
        })}
      </ul>
    );
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
