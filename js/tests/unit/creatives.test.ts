import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import mq from 'mithril-query';
import m from 'mithril';

import registerCreatives from '../../src/common/creatives';
import { rendererFor, resetRenderers } from '../../src/common/renderers';
import type { Candidate } from '../../src/common/types';

beforeAll(() => bootstrapForum());

function candidate(overrides: Partial<Candidate> = {}): Candidate {
  return {
    creative: 7,
    campaign: 3,
    tier: 50,
    weight: 10,
    type: 'image',
    payload: {},
    url: null,
    label: null,
    token: 'signed-token',
    nonce: 'abc123',
    issued: 1_800_000_000,
    ...overrides,
  };
}

function render(type: string, payload: Record<string, unknown>, overrides: Partial<Candidate> = {}) {
  const renderer = rendererFor(type)!;

  return mq(m('div', renderer(candidate({ type, payload, ...overrides }))));
}

beforeEach(() => {
  resetRenderers();
  registerCreatives();
});

describe('the rich text renderer', () => {
  const html = '<p>Buy <b>things</b>.</p>';

  it('renders the markup the server produced', () => {
    const rendered = render('rich_text', { html, wrappable: true });

    expect(rendered).toHaveElement('.Placement-rich b');
    expect(rendered).toContainRaw('things');
  });

  it('wraps the copy in the destination link when the server said it could', () => {
    const rendered = render('rich_text', { html, wrappable: true }, { url: 'https://acme.example' });

    expect(rendered).toHaveElement('a.Placement-link .Placement-rich');
  });

  /**
   * Nested anchors are invalid, and browsers recover from them by closing the
   * outer one wherever they like -- so copy that already contains a link is
   * never put inside another.
   */
  it('does not wrap copy that already contains a link', () => {
    const rendered = render(
      'rich_text',
      { html: '<p><a href="https://acme.example">Acme</a></p>', wrappable: false },
      { url: 'https://acme.example' }
    );

    expect(rendered).not.toHaveElement('a.Placement-link');
    expect(rendered).toHaveElement('.Placement-rich a');
  });

  /**
   * `wrappable` is the server's decision. Treating a missing value as "yes"
   * would make a creative stored before this field existed wrap itself.
   */
  it('does not wrap when the server said nothing', () => {
    expect(render('rich_text', { html }, { url: 'https://acme.example' })).not.toHaveElement('a.Placement-link');
  });

  it('renders nothing when there is no markup', () => {
    expect(rendererFor('rich_text')!(candidate({ type: 'rich_text', payload: {} }))).toBeNull();
    expect(rendererFor('rich_text')!(candidate({ type: 'rich_text', payload: { html: '' } }))).toBeNull();
  });
});

describe('the logo wall renderer', () => {
  const logos = [{ asset: 'https://cdn.example/a.png', alt: 'Acme', url: 'https://acme.example' }, { asset: 'https://cdn.example/b.png' }];

  it('renders one item per logo', () => {
    const rendered = render('logo_wall', { logos, columns: 3 });

    expect(rendered.find('.Placement-logoItem')).toHaveLength(2);
    expect(rendered.find('.Placement-logo')).toHaveLength(2);
  });

  it('links only the logos that carry a destination', () => {
    const rendered = render('logo_wall', { logos });

    expect(rendered.find('a.Placement-link')).toHaveLength(1);
    expect(rendered.rootEl.querySelector('a.Placement-link')!.getAttribute('href')).toBe('https://acme.example');
  });

  /**
   * A paid link that passes PageRank is what earns an unnatural-outbound-links
   * action against the whole forum.
   */
  it('marks every logo link as paid', () => {
    const anchor = render('logo_wall', { logos }).rootEl.querySelector('a.Placement-link')!;

    expect(anchor.getAttribute('rel')).toBe('sponsored nofollow noopener');
  });

  /**
   * Checked again here even though the server checks it on save: this is the
   * last point before the value reaches an `href`, where a `javascript:`
   * scheme is script execution rather than navigation.
   */
  it('refuses a destination that is not http', () => {
    const rendered = render('logo_wall', {
      logos: [{ asset: 'https://cdn.example/a.png', url: 'javascript:alert(1)' }],
    });

    expect(rendered).not.toHaveElement('a.Placement-link');
    expect(rendered).toHaveElement('.Placement-logo');
  });

  it('lays the wall out in the number of columns the creative stored', () => {
    const list = render('logo_wall', { logos, columns: 6 }).rootEl.querySelector('.Placement-logos') as HTMLElement;

    // The raw attribute, not the parsed CSSOM: domino's CSS parser drops the
    // `1fr` when it reads `repeat()` back, and asserting on that would be
    // asserting on a bug in the test environment.
    expect(list.getAttribute('style')).toBe('grid-template-columns: repeat(6, 1fr)');
  });

  it('falls back to four columns when none was stored', () => {
    const list = render('logo_wall', { logos }).rootEl.querySelector('.Placement-logos') as HTMLElement;

    // The raw attribute, not the parsed CSSOM: domino's CSS parser drops the
    // `1fr` when it reads `repeat()` back, and asserting on that would be
    // asserting on a bug in the test environment.
    expect(list.getAttribute('style')).toBe('grid-template-columns: repeat(4, 1fr)');
  });

  it('skips a row with no image rather than drawing a gap', () => {
    const rendered = render('logo_wall', { logos: [{ alt: 'no image' }, { asset: 'https://cdn.example/b.png' }] });

    expect(rendered.find('.Placement-logo')).toHaveLength(1);
  });

  it('renders nothing when there are no logos', () => {
    const renderer = rendererFor('logo_wall')!;

    expect(renderer(candidate({ type: 'logo_wall', payload: { logos: [] } }))).toBeNull();
    expect(renderer(candidate({ type: 'logo_wall', payload: {} }))).toBeNull();
    expect(renderer(candidate({ type: 'logo_wall', payload: { logos: 'https://cdn.example/a.png' } }))).toBeNull();
  });
});
