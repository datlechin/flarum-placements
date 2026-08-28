import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';

import CreativeModal from '../../src/admin/components/CreativeModal';
import Creative from '../../src/admin/models/Creative';
import Campaign from '../../src/admin/models/Campaign';
import { RESOURCE } from '../../src/admin/config';

beforeAll(() => {
  bootstrapAdmin();

  app.store.models[RESOURCE.creatives] = Creative;
  app.store.models[RESOURCE.campaigns] = Campaign;
});

beforeEach(() => {
  app.store.data = {};
});

/**
 * The modal's own state, without mounting it. What is worth testing here is
 * the translation between the map the server stores and the ordered pairs the
 * form has to edit -- rendering a modal proves nothing about that.
 */
function modalFor(payload: Record<string, unknown> | null, type = 'network') {
  app.store.pushPayload({
    data: [
      { id: '1', type: RESOURCE.campaigns, attributes: { name: 'Acme' } },
      ...(payload
        ? [
            {
              id: '1',
              type: RESOURCE.creatives,
              attributes: { name: 'A container', type, payload },
              relationships: { campaign: { data: { id: '1', type: RESOURCE.campaigns } } },
            },
          ]
        : []),
    ],
  } as any);

  const campaign = app.store.getById(RESOURCE.campaigns, '1')!;
  const creative = payload ? app.store.getById(RESOURCE.creatives, '1') : undefined;

  const modal = new (CreativeModal as any)();

  modal.attrs = { campaign, creative };
  modal.oninit({ attrs: modal.attrs, state: modal } as any);

  return modal;
}

describe('the network container form', () => {
  it('loads the stored attributes as ordered pairs', () => {
    const modal = modalFor({ element: 'ins', attributes: { 'data-ad-client': 'ca-pub-1', class: 'adsbygoogle' } });

    expect(modal.attributePairs()).toEqual([
      ['data-ad-client', 'ca-pub-1'],
      ['class', 'adsbygoogle'],
    ]);
  });

  it('starts empty for a creative that has none', () => {
    expect(modalFor(null).attributePairs()).toEqual([]);
  });

  it('survives a payload whose attributes are not a map', () => {
    expect(modalFor({ attributes: 'ca-pub-1' }).attributePairs()).toEqual([]);
    expect(modalFor({ attributes: ['a', 'b'] }).attributePairs()).toEqual([]);
  });

  /**
   * The reason the pairs are a list. Renaming a key in a map means deleting
   * one and adding another, so a row would jump or vanish mid-word, and two
   * rows briefly sharing a blank name would collapse into one.
   */
  it('keeps a row in place while its name is being retyped', () => {
    const modal = modalFor({ attributes: { 'data-a': '1', 'data-b': '2' } });

    modal.setAttributeAt(0, '', '1');

    expect(modal.attributePairs()).toEqual([
      ['', '1'],
      ['data-b', '2'],
    ]);

    modal.setAttributeAt(0, 'data-c', '1');

    expect(modal.attributePairs()).toEqual([
      ['data-c', '1'],
      ['data-b', '2'],
    ]);
  });

  it('keeps two blank rows apart', () => {
    const modal = modalFor(null);

    modal.setAttributes([
      ['', ''],
      ['', ''],
    ]);

    expect(modal.attributePairs()).toHaveLength(2);
  });

  it('sends the pairs as a map, dropping the ones with no name', () => {
    const modal = modalFor({ element: 'ins' });

    modal.setAttributes([
      ['data-ad-client', 'ca-pub-1'],
      ['  ', 'abandoned'],
      ['class', 'adsbygoogle'],
    ]);

    expect(modal.cleanPayload().attributes).toEqual({ 'data-ad-client': 'ca-pub-1', class: 'adsbygoogle' });
  });

  /**
   * Both defaults are the cautious ones, and the form has to agree with the
   * server about which way round that is.
   */
  it('defaults to requiring consent and not refreshing', () => {
    const modal = modalFor(null);

    expect(modal.payload().requiresConsent !== false).toBe(true);
    expect(modal.payload().refreshOnNavigate === true).toBe(false);
  });

  it('reads a stored consent flag back', () => {
    expect(modalFor({ requiresConsent: false }).payload().requiresConsent).toBe(false);
  });

  /**
   * Only the network form converts them, so a logo wall's rows are not
   * quietly turned into attributes.
   */
  it('leaves other types alone', () => {
    const modal = modalFor({ logos: [{ asset: 'https://cdn.example/a.png' }] }, 'logo_wall');

    modal.setAttributes([['data-a', '1']]);

    expect(modal.cleanPayload().attributes).toBeUndefined();
    expect(modal.cleanPayload().logos).toEqual([{ asset: 'https://cdn.example/a.png' }]);
  });
});
