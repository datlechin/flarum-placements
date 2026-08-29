import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import ItemList from 'flarum/common/utils/ItemList';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Campaign from '../models/Campaign';
import type { TargetingRule } from '../models/Campaign';
import TabbedFormModal from './TabbedFormModal';
import type { ModalTab } from './TabbedFormModal';
export interface CampaignModalAttrs extends IFormModalAttrs {
    campaign?: Campaign;
    onsaved?: () => void;
}
export default class CampaignModal extends TabbedFormModal<CampaignModalAttrs> {
    protected name: Stream<string>;
    protected status: Stream<string>;
    protected tier: Stream<string>;
    protected isHouse: Stream<boolean>;
    protected startsAt: Stream<string>;
    protected endsAt: Stream<string>;
    protected maxImpressions: Stream<string>;
    protected maxClicks: Stream<string>;
    protected pacing: Stream<string>;
    protected rules: Stream<TargetingRule[]>;
    protected daypartMask: Stream<string | null>;
    protected frequencyCap: Stream<string>;
    protected frequencyWindow: Stream<string>;
    protected advertiserId: Stream<string>;
    protected rateType: Stream<string>;
    protected rateAmount: Stream<string>;
    protected rateCurrency: Stream<string>;
    protected contractNotes: Stream<string>;
    oninit(vnode: Mithril.Vnode<CampaignModalAttrs, this>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    tabs(): ItemList<ModalTab>;
    protected identityTab(): Mithril.Children;
    protected scheduleTab(): Mithril.Children;
    protected deliveryTab(): Mithril.Children;
    protected targetingTab(): Mithril.Children;
    /**
     * What was agreed.
     *
     * These four fields were writable over the API from the start and had no
     * control anywhere, so recording a contracted rate meant hand-crafting a
     * request. Nothing here charges, invoices or converts a currency -- it is
     * somewhere to write down what was agreed so a report can state it.
     */
    protected commercialTab(): Mithril.Children;
    protected field(key: string, control: Mithril.Children, help?: Mithril.Children): Mithril.Children;
    /**
     * `datetime-local` wants `YYYY-MM-DDTHH:mm` in the browser's own zone, while
     * the API speaks ISO 8601. The conversion is here rather than in the model
     * because it is a property of the input element, not of a campaign.
     */
    protected date(value: Date | null | undefined): string;
    protected iso(value: string): string | null;
    onsubmit(e: SubmitEvent): void;
}
