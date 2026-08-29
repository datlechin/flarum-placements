import fs from 'fs';
import path from 'path';

/**
 * Class names that look like core's and are not.
 *
 * A CSS class that core does not define fails in silence: the markup renders,
 * the build passes, every test passes, and the element is simply unstyled. It
 * happened twice here with the same component. `Badge--important` does not
 * appear anywhere in core, and `Badge--warning` is only ever defined nested
 * inside `.AdminNav` and `.ExtensionWidget`, so outside those two places it
 * styles nothing -- yet both read as though they were part of the palette.
 *
 * Worse than the colour, `Badge` was the wrong element. It is a fixed 22-pixel
 * circle whose `.Badge-label` is `display: none`, because any wording belongs
 * in a tooltip; status text in one spilled out of a circle. `Pill` is the
 * primitive for a short piece of text, and `StatusPill` wraps it.
 *
 * So this asserts the two modifiers are absent, and that `Badge` is not used at
 * all -- there is no legitimate use of it in this extension, and the moment
 * there is, this test is the place to record why.
 */

const root = path.resolve(process.cwd(), '..');

const FORBIDDEN = [
  {
    pattern: /Badge--important/,
    why: '`Badge--important` is not defined anywhere in flarum/core.',
  },
  {
    // `.Table()` and `.loading-container()` are parametric mixins: the
    // parentheses mean Less emits no class of that name. Markup saying
    // `class="Table"` therefore gets a bare browser table on a themed page,
    // and nothing about it looks wrong until you open the panel. Every admin
    // table here shipped that way once. `Table-container` is worse -- core
    // does not define it in any form.
    //
    // Matched as a class token inside any string literal rather than after
    // `className=`, because these names reach the DOM through `classList()`
    // and through column descriptors too -- an earlier version of this test
    // required the attribute and so caught none of them.
    pattern: /["'`][^"'`]*\b(?:Table|Table-container|Table-controls|Table-controls-item|loading-container)\b[^"'`]*["'`]/,
    why: '`Table`, `Table-container`, `Table-controls` and `loading-container` emit no CSS: call the `.Table()` / `.loading-container()` mixins from `less/admin.less` instead.',
  },
  {
    pattern: /Badge--warning/,
    why: '`Badge--warning` is only defined nested inside .AdminNav and .ExtensionWidget, so it styles nothing elsewhere.',
  },
  {
    pattern: /className=(?:"|\{`)Badge\b/,
    why: '`Badge` is a 22px circle for an icon, not a text label. Use StatusPill, which wraps core\'s `Pill`.',
  },
];

/**
 * The file with whole-line comments dropped.
 *
 * `StatusPill`'s own docblock names both dead modifiers, because explaining why
 * not to reach for them is the point of it -- so scanning raw text reports the
 * documentation as the offence. Only whole-line comments are removed rather
 * than every `//`: a `https://` inside a string would otherwise truncate the
 * line and could hide a real use sitting after it.
 */
function code(file: string): string {
  return fs
    .readFileSync(file, 'utf8')
    .split('\n')
    .filter((line) => !/^\s*(\/\/|\*|\/\*)/.test(line))
    .join('\n');
}

function sources(dir: string, found: string[] = []): string[] {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) sources(full, found);
    else if (/\.tsx?$/.test(entry.name)) found.push(full);
  }

  return found;
}

describe('core class names', () => {
  const files = sources(path.join(root, 'js/src'));

  it('reads the source it is checking', () => {
    // A scan that found no files would pass every assertion below.
    expect(files.length).toBeGreaterThan(40);
  });

  it.each(FORBIDDEN)('does not use $why', ({ pattern }) => {
    const offenders = files.filter((file) => pattern.test(code(file))).map((file) => path.relative(root, file));

    expect(offenders).toEqual([]);
  });

  /**
   * The other half of the trap: having stopped naming the mixins in markup,
   * the stylesheet has to actually call them, or the tables are unstyled in a
   * different way.
   */
  it('calls the table mixins from the stylesheet', () => {
    const less = fs.readFileSync(path.join(root, 'less/admin.less'), 'utf8');

    expect(less).toMatch(/^\s*\.Table\(\);/m);
    expect(less).toMatch(/^\s*\.loading-container\(\);/m);
  });
});
