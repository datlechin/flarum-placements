import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
interface Row {
    key: string;
    /** The campaign or creative's own name; the key itself for a slot. */
    name: string;
    impressions: number;
    viewable: number;
    clicks: number;
}
interface Report {
    since: string;
    days: number;
    totals: {
        impressions: number;
        viewable: number;
        clicks: number;
        filtered: number;
    };
    daily: Array<{
        day: string;
        impressions: number;
        viewable: number;
        clicks: number;
    }>;
    campaigns: Row[];
    creatives: Row[];
    placements: Row[];
}
type Breakdown = 'campaigns' | 'creatives' | 'placements';
type SortKey = 'name' | 'impressions' | 'viewable' | 'clicks' | 'ctr';
/**
 * What was delivered.
 *
 * The range is in the address, so "the last quarter" is a link somebody can
 * send. It used to reset to thirty days on every visit and lived a scroll
 * below the campaign list, which made it the hardest screen here to refer to.
 */
export default class ReportsTab extends Component {
    protected report: Report | null;
    protected loading: boolean;
    /** Which column each breakdown is ordered by, and which way. */
    protected sorts: Record<Breakdown, {
        key: SortKey;
        descending: boolean;
    }>;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    protected days(): number;
    protected load(): void;
    view(): Mithril.Children;
    protected body(): Mithril.Children;
    /**
     * A percentage, or a dash when there is not enough to divide by.
     *
     * Shared with the campaign pages rather than defined again here, so the same
     * two numbers cannot produce a rate on one screen and a dash on another.
     */
    protected rate(part: number, whole: number): Mithril.Children;
    /**
     * The breakdowns are sorted in the browser rather than by the server.
     *
     * The whole breakdown arrives in one payload -- the endpoint caps each at a
     * hundred rows -- so there is nothing to fetch to reorder it, and asking the
     * server would trade an instant reorder for a round trip and a spinner.
     */
    protected sorted(breakdown: Breakdown, rows: Row[]): Row[];
    protected table(breakdown: Breakdown, rows: Row[]): Mithril.Children;
    protected sortBy(breakdown: Breakdown, key: SortKey): void;
    protected sortIcon(breakdown: Breakdown, key: SortKey): string;
    protected ariaSort(breakdown: Breakdown, key: SortKey): string;
}
export {};
