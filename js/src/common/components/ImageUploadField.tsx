import app from 'flarum/common/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';

export interface ImageUploadFieldAttrs extends ComponentAttrs {
  value: string;
  onchange: (url: string) => void;
  placeholder?: string;
  required?: boolean;
}

/**
 * An address, and a way to get one without leaving the page.
 *
 * The address stays editable, because plenty of creatives point at an
 * advertiser's own CDN and always will; the upload is for the case where
 * somebody has a file and nowhere to put it. Before this existed, that case
 * had no answer at all, and it is the case a member submitting an advert is
 * always in.
 */
export default class ImageUploadField extends Component<ImageUploadFieldAttrs> {
  protected uploading = false;
  protected error: string | null = null;

  view(): Mithril.Children {
    return (
      <div className="ImageUploadField">
        <div className="ImageUploadField-row">
          <input
            className="FormControl"
            type="url"
            value={this.attrs.value}
            placeholder={this.attrs.placeholder ?? 'https://'}
            required={this.attrs.required}
            oninput={(e: InputEvent) => this.attrs.onchange((e.target as HTMLInputElement).value)}
          />

          <label className="Button ImageUploadField-choose">
            {this.uploading ? <LoadingIndicator display="inline" size="small" /> : app.translator.trans('datlechin-placements.lib.upload')}
            <input
              type="file"
              accept="image/jpeg,image/png,image/gif,image/webp"
              disabled={this.uploading}
              onchange={(e: InputEvent) => this.choose(e)}
            />
          </label>

          {this.attrs.value && (
            <Button
              className="Button Button--icon"
              icon="fas fa-times"
              type="button"
              title={app.translator.trans('datlechin-placements.lib.clear') as string}
              onclick={() => this.attrs.onchange('')}
            />
          )}
        </div>

        {this.error && <div className="ImageUploadField-error">{this.error}</div>}

        {/* Shown from the address rather than from the upload, so it also
            previews a URL somebody pasted -- which is the more common case and
            the one where a typo is otherwise invisible until the advert runs. */}
        {this.attrs.value && <img className="ImageUploadField-preview" src={this.attrs.value} alt="" onerror={this.hide} onload={this.show} />}
      </div>
    );
  }

  protected hide(e: Event): void {
    (e.target as HTMLElement).style.display = 'none';
  }

  protected show(e: Event): void {
    (e.target as HTMLElement).style.display = '';
  }

  protected choose(e: InputEvent): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    const body = new FormData();
    body.append('image', file);

    this.uploading = true;
    this.error = null;

    app
      .request<{ url: string }>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/placements/uploads`,
        body,
        // Without this, Mithril JSON-encodes the body and the multipart
        // boundary never happens, so the server sees no file at all.
        serialize: (raw: unknown) => raw,
      })
      .then((response) => {
        this.attrs.onchange(response.url);
      })
      .catch((error: { response?: { errors?: Array<{ detail?: string }> } }) => {
        // The message the server gave, which says which rule was broken --
        // "must be an image", "may not be greater than 2048 kilobytes" -- and
        // is the only useful thing to show here.
        this.error = error?.response?.errors?.[0]?.detail ?? (app.translator.trans('datlechin-placements.lib.upload_failed') as unknown as string);
      })
      .finally(() => {
        this.uploading = false;
        // The same file can then be chosen again, which otherwise fires no
        // change event and looks like the button stopped working.
        input.value = '';
        m.redraw();
      });
  }
}
