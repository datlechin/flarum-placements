import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
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
export default class ReportSection extends Component<ComponentAttrs> {
    protected report: Report | null;
    protected loading: boolean;
    protected days: number;
    oninit(vnode: Mithril.Vnode<ComponentAttrs, this>): void;
    protected load(): void;
    view(): Mithril.Children;
    protected body(): Mithril.Children;
    protected figures(totals: Report['totals']): Mithril.Children;
    protected figure(key: string, value: Mithril.Children, help?: Mithril.Children): Mithril.Children;
    /**
     * A percentage, or a dash when there is not enough to divide by.
     */
    protected rate(part: number, whole: number): string;
    protected table(key: 'placements' | 'creatives' | 'campaigns', rows: Row[]): Mithril.Children;
}
export {};
