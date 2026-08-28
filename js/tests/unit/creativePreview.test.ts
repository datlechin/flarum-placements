import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import mq from 'mithril-query';
import m from 'mithril';

import CreativePreview from '../../src/admin/components/CreativePreview';
import registerCreatives from '../../src/common/creatives';
import { resetRenderers } from '../../src/common/renderers';
import { pending, resetBeacon } from '../../src/common/beacon';
import { setConsent } from '../../src/common/consent';

beforeAll(() => bootstrapAdmin());

beforeEach(() => {
  resetBeacon();
  resetRenderers();
  registerCreatives();
  setConsent(true);
});

const render = (attrs: Record<string, unknown>) => mq(m(CreativePreview, attrs as any));

describe('the creative preview', () => {
  it('draws an image creative the way a reader would see it', () => {
    const rendered = render({
      type: 'image',
      payload: { asset: 'https://cdn.example/a.png', alt: 'Acme' },
      destinationUrl: 'https://acme.example',
    });

    expect(rendered).toHaveElement('.Placement .Placement-creative .Placement-image');
    expect(rendered.rootEl.querySelector('img')!.getAttribute('src')).toBe('https://cdn.example/a.png');
  });

  it('draws the disclosure label, because a reader would see that too', () => {
    expect(render({ type: 'text', payload: { headline: 'Buy things' } })).toHaveElement('.Placement-label');
  });

  /**
   * A preview is not an impression, and the beacon would refuse it anyway --
   * there is no signed token, since the server only issues one when it decides
   * to serve something to somebody.
   */
  it('reports nothing at all', () => {
    render({ type: 'image', payload: { asset: 'https://cdn.example/a.png' } });
    render({ type: 'text', payload: { headline: 'Buy things' } });

    expect(pending()).toEqual([]);
  });

  it('says so when the type has no renderer', () => {
    const rendered = render({ type: 'invented_by_an_extension', payload: {} });

    expect(rendered).toHaveElement('.Placeholder');
    expect(rendered).not.toHaveElement('.Placement');
  });

  /**
   * The two reasons a creative draws nothing look identical until somebody
   * says which is which, so the preview says.
   */
  it('says so when the renderer draws nothing', () => {
    const rendered = render({ type: 'image', payload: { alt: 'no asset at all' } });

    expect(rendered).toHaveElement('.Placeholder');
    expect(rendered).not.toHaveElement('.Placement-creative');
  });

  it('shows a network container as empty while consent is withheld', () => {
    setConsent(false);

    const rendered = render({
      type: 'network',
      payload: { element: 'ins', attributes: { 'data-ad-client': 'ca-pub-1' }, requiresConsent: true },
    });

    expect(rendered).toHaveElement('.Placeholder');
  });

  it('draws that same container once consent is given', () => {
    setConsent(true);

    const rendered = render({
      type: 'network',
      payload: { element: 'ins', attributes: { 'data-ad-client': 'ca-pub-1' }, requiresConsent: true },
    });

    expect(rendered).toHaveElement('.Placement-network');
  });
});
