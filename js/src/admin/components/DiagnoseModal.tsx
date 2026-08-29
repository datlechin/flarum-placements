import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { slots, trans } from '../config';
import type Creative from '../models/Creative';
import StatusPill from '../../common/components/StatusPill';

interface Verdict {
  placement: string;
  reason: string;
  dimension?: string;
}

/**
 * Why one creative is not showing, slot by slot.
 *
 * The question this extension will otherwise be asked for ever. Every gate
 * that could answer it existed and refused in silence, and
 * `RuleEvaluator::firstFailure()` was computing the answer and throwing it
 * away.
 *
 * It reports for the administrator looking at it, which is the only honest
 * answer available: targeting depends on who is asking and dayparting on when.
 * The help text says so rather than letting somebody read "eligible" as a
 * promise about everybody.
 */
export default class DiagnoseModal extends Modal<DiagnoseModalAttrs> {
  protected verdicts: Verdict[] | null = null;

  oninit(vnode: Mithril.Vnode<DiagnoseModalAttrs, this>) {
    super.oninit(vnode);

    this.check();
  }

  className(): string {
    return 'DiagnoseModal Modal--medium';
  }

  title(): Mithril.Children {
    return trans('diagnose.title', { name: this.attrs.creative.name() });
  }

  protected assigned(): string[] {
    return Object.keys(this.attrs.creative.placements() ?? {});
  }

  protected check(): void {
    const keys = this.assigned();

    if (!keys.length) {
      this.verdicts = [];

      return;
    }

    Promise.all(
      keys.map((placement) =>
        app
          .request<Verdict>({
            method: 'GET',
            url: `${app.forum.attribute('apiUrl')}/placements/diagnose`,
            params: { creative: this.attrs.creative.id(), placement },
          })
          // A slot whose answer could not be fetched is reported as such
          // rather than dropped, so the list always has one row per slot.
          .catch(() => ({ placement, reason: 'unavailable' } as Verdict))
      )
    ).then((verdicts) => {
      this.verdicts = verdicts;
      m.redraw();
    });
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <p className="helpText">{trans('diagnose.help')}</p>

        {this.verdicts === null ? <LoadingIndicator /> : this.list()}
      </div>
    );
  }

  protected list(): Mithril.Children {
    if (!this.verdicts?.length) {
      // Not assigned anywhere at all, which is the commonest cause of the
      // question and the one the per-slot list cannot show.
      return <Placeholder text={trans('creatives.unassigned')} />;
    }

    const names = Object.fromEntries(slots().map((slot) => [slot.key, extractText(app.translator.trans(slot.label))]));

    return (
      <ul className="PlacementDiagnosis">
        {this.verdicts.map((verdict) => (
          <li className="PlacementDiagnosis-item" key={verdict.placement}>
            <span className="PlacementDiagnosis-slot">{names[verdict.placement] ?? verdict.placement}</span>

            <StatusPill tone={verdict.reason === 'eligible' ? 'success' : 'warning'}>{trans(`diagnose.reasons.${verdict.reason}`)}</StatusPill>

            {verdict.dimension && (
              <span className="PlacementDiagnosis-detail">{trans('diagnose.on_dimension', { dimension: verdict.dimension })}</span>
            )}
          </li>
        ))}
      </ul>
    );
  }
}

export interface DiagnoseModalAttrs extends IInternalModalAttrs {
  creative: Creative;
}
