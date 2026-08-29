import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import FieldSet from 'flarum/common/components/FieldSet';
import InfoTile from 'flarum/common/components/InfoTile';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { SETTING, trans } from '../../config';
import type PlacementsPage from '../PlacementsPage';
import type { StorageReport } from '../../states/PlacementsState';

export interface SettingsTabAttrs extends ComponentAttrs {
  /** The page, for its setting streams and its save button. */
  page: PlacementsPage;
}

/**
 * The four things that really are settings. Everything else here is a record.
 *
 * Grouped rather than listed: a timezone, a retention window, an `ads.txt` file
 * and a list of loader scripts have nothing to do with one another, and running
 * them together as four unlabelled fields makes each one look like a
 * continuation of the last.
 */
export default class SettingsTab extends Component<SettingsTabAttrs> {
  oninit(vnode: Mithril.Vnode<SettingsTabAttrs, this>) {
    super.oninit(vnode);

    if (app.placements.storage() === null) {
      this.loadStorage();
    }
  }

  protected loadStorage(): void {
    app
      .request<StorageReport>({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/placements/storage` })
      .then((storage) => {
        app.placements.storage(storage);
        m.redraw();
      })
      .catch(() => {
        // Leaving it null keeps the fields usable and simply says nothing
        // about what is stored.
        m.redraw();
      });
  }

  view(): Mithril.Children {
    const page = this.attrs.page;

    return (
      <div className="PlacementsTab PlacementSettings">
        <FieldSet label={extractText(trans('settings.group_serving'))} description={extractText(trans('settings.group_serving_help'))}>
          <div className="Form-group">
            <label>{trans('settings.timezone')}</label>
            <input className="FormControl" bidi={page.setting(SETTING.timezone, 'UTC')} placeholder="UTC" />
            <div className="helpText">{trans('settings.timezone_help')}</div>
          </div>
        </FieldSet>

        <FieldSet label={extractText(trans('settings.group_retention'))}>
          <div className="Form-group">
            <label>{trans('settings.retention_days')}</label>
            <input className="FormControl" type="number" min="1" max="730" bidi={page.setting(SETTING.retentionDays, '90')} />
            <div className="helpText">{trans('settings.retention_days_help')}</div>
          </div>

          {this.storage()}
        </FieldSet>

        <FieldSet label={extractText(trans('settings.group_publisher'))} description={extractText(trans('settings.group_publisher_help'))}>
          <div className="Form-group">
            <label>{trans('settings.ads_txt')}</label>
            <textarea
              className="FormControl"
              rows="5"
              bidi={page.setting(SETTING.adsTxt, '')}
              placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"
            />
            <div className="helpText">{trans('settings.ads_txt_help')}</div>
          </div>

          <div className="Form-group">
            <label>{trans('settings.network_scripts')}</label>
            <textarea className="FormControl" rows="3" bidi={page.setting(SETTING.networkScripts, '')} placeholder="https://…/loader.js" />
            <div className="helpText">{trans('settings.network_scripts_help')}</div>
          </div>
        </FieldSet>

        <div className="Form-group Form-controls">{page.submitButton()}</div>
      </div>
    );
  }

  /**
   * What the retention setting is currently holding.
   *
   * The field alone is a number nobody can judge: it does not say how much is
   * stored, how far back the history goes, or what lowering it would throw
   * away. It also does nothing by itself -- the scheduled prune acts on it --
   * so on a forum whose scheduler was never set up the field reads "90" while
   * every bucket ever recorded is still on disk. That is the case worth
   * naming, and it is the one this makes visible.
   */
  protected storage(): Mithril.Children {
    const storage = app.placements.storage();

    if (storage === null) return <LoadingIndicator size="small" display="block" />;

    if (!storage.buckets) {
      return <InfoTile icon="fas fa-database">{trans('settings.storage_empty')}</InfoTile>;
    }

    return (
      <InfoTile icon="fas fa-database" className="PlacementSettings-storage">
        <p>
          {trans('settings.storage_summary', {
            buckets: storage.buckets.toLocaleString(),
            since: storage.oldest ? humanTime(new Date(storage.oldest)) : '—',
          })}
        </p>

        {storage.expiring > 0 && (
          <p>{trans('settings.storage_expiring', { count: storage.expiring.toLocaleString(), days: storage.retentionDays })}</p>
        )}

        {storage.orphanedImages > 0 && <p>{trans('settings.storage_orphans', { count: storage.orphanedImages.toLocaleString() })}</p>}

        <p className="helpText">{trans('settings.storage_prune_help')}</p>
      </InfoTile>
    );
  }
}
