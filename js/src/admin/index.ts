import app from 'flarum/admin/app';

import registerCreatives from '../common/creatives';
import { EXTENSION, RESOURCE } from './config';
import Advertiser from './models/Advertiser';
import Campaign from './models/Campaign';
import Creative from './models/Creative';
import PlacementSetting from './models/PlacementSetting';

export { default as extend } from './extend';

export { default as PlacementPage } from './components/PlacementPage';
export { default as CampaignModal } from './components/CampaignModal';
export { default as CreativeModal } from './components/CreativeModal';
export { default as AdvertiserModal } from './components/AdvertiserModal';
export { default as RulesEditor } from './components/RulesEditor';
export { default as DaypartGrid } from './components/DaypartGrid';
export { default as Campaign } from './models/Campaign';
export { default as Creative } from './models/Creative';
export { default as Advertiser } from './models/Advertiser';
export { default as PlacementSetting } from './models/PlacementSetting';
export { default as SlotSettings } from './components/SlotSettings';
export { default as ReportSection } from './components/ReportSection';
export { default as DeliveryChart } from './components/DeliveryChart';
export { default as PlacementSlot } from '../common/components/PlacementSlot';
export { registerRenderer } from '../common/renderers';
export { report } from '../common/beacon';
export * from './config';

app.initializers.add(EXTENSION, () => {
  app.store.models[RESOURCE.campaigns] = Campaign;
  app.store.models[RESOURCE.creatives] = Creative;
  app.store.models[RESOURCE.advertisers] = Advertiser;
  app.store.models[RESOURCE.settings] = PlacementSetting;

  // The same renderers the forum uses, so a preview in the admin panel shows
  // what a reader would actually see rather than an approximation of it.
  registerCreatives();
});
