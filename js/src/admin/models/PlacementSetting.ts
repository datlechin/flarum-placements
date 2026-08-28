import Model from 'flarum/common/Model';

/**
 * An administrator's overrides for one slot.
 *
 * The id is the placement key, and there is no create endpoint: a slot exists
 * because a component renders it, and the row is written the first time
 * somebody changes something about it.
 */
export default class PlacementSetting extends Model {
  enabled = Model.attribute<boolean>('enabled');
  maxFill = Model.attribute<number>('maxFill');
  fallback = Model.attribute<string>('fallback');
  passbackCreativeId = Model.attribute<number | null>('passbackCreativeId');
  labelMode = Model.attribute<string>('labelMode');

  /** random | sticky. Sticky holds the slot's choice for the rest of the visit. */
  rotation = Model.attribute<string>('rotation');

  reservePhone = Model.attribute<number | null>('reservePhone');
  reserveTablet = Model.attribute<number | null>('reserveTablet');
  reserveDesktop = Model.attribute<number | null>('reserveDesktop');

  everyN = Model.attribute<number | null>('everyN');
  repeatLimit = Model.attribute<number | null>('repeatLimit');

  sortOrder = Model.attribute<number>('sortOrder');
}
