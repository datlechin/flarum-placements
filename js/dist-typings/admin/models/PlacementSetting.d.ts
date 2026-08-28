import Model from 'flarum/common/Model';
/**
 * An administrator's overrides for one slot.
 *
 * The id is the placement key, and there is no create endpoint: a slot exists
 * because a component renders it, and the row is written the first time
 * somebody changes something about it.
 */
export default class PlacementSetting extends Model {
    enabled: () => boolean;
    maxFill: () => number;
    fallback: () => string;
    passbackCreativeId: () => number | null;
    labelMode: () => string;
    /** random | sticky. Sticky holds the slot's choice for the rest of the visit. */
    rotation: () => string;
    reservePhone: () => number | null;
    reserveTablet: () => number | null;
    reserveDesktop: () => number | null;
    everyN: () => number | null;
    repeatLimit: () => number | null;
    sortOrder: () => number;
}
