import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

import { trans } from '../config';

export interface DaypartGridAttrs extends ComponentAttrs {
  /** 42 hex characters, or null for "every hour". */
  mask: string | null;
  onchange: (mask: string | null) => void;
}

const HOURS = 168;
const LENGTH = 42;
const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

/**
 * The seven-by-twenty-four grid a schedule is drawn on.
 *
 * The hours are the forum's, not the reader's — Flarum stores no timezone for
 * anybody, so the alternative would be a schedule that could not be enforced.
 * Which timezone the forum keeps is a setting, and the label says which one is
 * in force so nobody has to guess.
 */
export default class DaypartGrid extends Component<DaypartGridAttrs> {
  /** Set while a pointer is down, so a schedule can be painted in one gesture. */
  protected painting: boolean | null = null;

  view(): Mithril.Children {
    const on = this.hours();

    return (
      <div className="DaypartGrid" onmouseup={() => (this.painting = null)} onmouseleave={() => (this.painting = null)}>
        <div className="DaypartGrid-scroll">
          <table>
            <thead>
              <tr>
                <th />
                {Array.from({ length: 24 }, (_, hour) => (
                  <th key={hour} className="DaypartGrid-hour">
                    {/* Every third hour, so the header stays readable at the
                        width a table of 24 columns actually gets. */}
                    {hour % 3 === 0 ? hour : ''}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {DAYS.map((day, index) => (
                <tr key={day}>
                  <th className="DaypartGrid-day">{trans(`daypart.days.${day}`)}</th>
                  {Array.from({ length: 24 }, (_, hour) => this.cell(index * 24 + hour, on))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="DaypartGrid-actions">
          {/* Plain buttons, not `Button--link`. That variant is transparent
              with no border and no underline, so beside the help sentence
              these two presets read as part of it rather than as controls. */}
          <Button className="Button" onclick={() => this.attrs.onchange(null)}>
            {trans('daypart.always')}
          </Button>
          <Button className="Button" onclick={() => this.set(this.officeHours())}>
            {trans('daypart.office_hours')}
          </Button>
          <span className="helpText">{trans('daypart.timezone_note')}</span>
        </div>
      </div>
    );
  }

  protected cell(index: number, on: Set<number>): Mithril.Children {
    const active = on.has(index);

    return (
      <td
        key={index}
        className={`DaypartGrid-cell ${active ? 'DaypartGrid-cell--on' : ''}`}
        role="checkbox"
        aria-checked={active}
        tabindex={0}
        onmousedown={() => {
          this.painting = !active;
          this.toggle(index, !active);
        }}
        onmouseenter={() => {
          if (this.painting !== null) this.toggle(index, this.painting);
        }}
        onkeydown={(e: KeyboardEvent) => {
          // The grid is 168 cells; without this a keyboard user could reach
          // them but never change one.
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            this.toggle(index, !active);
          }
        }}
      />
    );
  }

  /**
   * A null mask means every hour, so the grid shows everything on rather than
   * everything off — which is what "no schedule" actually means.
   */
  protected hours(): Set<number> {
    const mask = this.attrs.mask;

    if (!mask || mask.length !== LENGTH) {
      return new Set(Array.from({ length: HOURS }, (_, i) => i));
    }

    const on = new Set<number>();

    for (let index = 0; index < HOURS; index++) {
      const nibble = parseInt(mask[Math.floor(index / 4)], 16);

      if (nibble & (1 << (3 - (index % 4)))) on.add(index);
    }

    return on;
  }

  protected toggle(index: number, on: boolean): void {
    const hours = this.hours();

    on ? hours.add(index) : hours.delete(index);

    this.set(hours);
  }

  protected set(hours: Set<number>): void {
    // Every hour on is the same as no schedule, and storing null keeps the
    // common case out of the database and out of the serving path.
    if (hours.size === HOURS) {
      this.attrs.onchange(null);

      return;
    }

    let mask = '';

    for (let nibble = 0; nibble < LENGTH; nibble++) {
      let value = 0;

      for (let bit = 0; bit < 4; bit++) {
        if (hours.has(nibble * 4 + bit)) value |= 1 << (3 - bit);
      }

      mask += value.toString(16);
    }

    this.attrs.onchange(mask);
  }

  protected officeHours(): Set<number> {
    const hours = new Set<number>();

    for (let day = 0; day < 5; day++) {
      for (let hour = 9; hour < 17; hour++) hours.add(day * 24 + hour);
    }

    return hours;
  }
}
