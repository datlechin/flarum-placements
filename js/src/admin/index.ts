import app from 'flarum/admin/app';

import registerCreatives from '../common/creatives';
import { EXTENSION, RESOURCE } from './config';
import Advertiser from './models/Advertiser';
import Campaign from './models/Campaign';
import Creative from './models/Creative';
import PlacementSetting from './models/PlacementSetting';

export { default as extend } from './extend';

export { default as PlacementsPage } from './components/PlacementsPage';
export { default as CampaignModal } from './components/CampaignModal';
export { default as CreativeModal } from './components/CreativeModal';
export { default as AdvertiserModal } from './components/AdvertiserModal';
export { default as CreativePreviewModal } from './components/CreativePreviewModal';
export { default as TabbedFormModal } from './components/TabbedFormModal';
export { default as RulesEditor } from './components/RulesEditor';
export { default as DaypartGrid } from './components/DaypartGrid';
export { default as Campaign } from './models/Campaign';
export { default as Creative } from './models/Creative';
export { default as Advertiser } from './models/Advertiser';
export { default as PlacementSetting } from './models/PlacementSetting';

// The tabs, exported so another extension can subclass one or reach its
// columns rather than having to replace the whole page to add a field.
export { default as CampaignsTab } from './components/tabs/CampaignsTab';
export { default as CampaignDetail } from './components/tabs/CampaignDetail';
export { default as AdvertisersTab } from './components/tabs/AdvertisersTab';
export { default as ReviewTab } from './components/tabs/ReviewTab';
export { default as SlotsTab } from './components/tabs/SlotsTab';
export { default as ReportsTab } from './components/tabs/ReportsTab';
export { default as SettingsTab } from './components/tabs/SettingsTab';

export { default as RecordsTable } from './components/RecordsTable';
export { default as ListToolbar } from './components/ListToolbar';
export { default as StatusPill } from '../common/components/StatusPill';
export { default as Figures } from './components/Figures';
export { default as DeliveryChart } from './components/DeliveryChart';
export { default as CreativePreview } from './components/CreativePreview';

export { default as PlacementsState } from './states/PlacementsState';
export { default as RecordListState } from './states/RecordListState';

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
