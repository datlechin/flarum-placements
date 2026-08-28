/**
 * Where a creative may send the reader.
 *
 * Checked again here even though the server checks it on save, because this is
 * the last point before the URL reaches an `href`: a `javascript:` destination
 * is script execution, not navigation, and one stale row from before the
 * server-side check existed would be enough.
 */
declare function safeUrl(url: string | null): string | null;
export default function registerCreatives(): void;
export { safeUrl };
