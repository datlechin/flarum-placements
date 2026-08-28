import fs from 'fs';
import path from 'path';

/**
 * Two files whose paths differ only by extension are a trap, not a choice.
 *
 * `import './extend'` is resolved by the bundler against an ordered extension
 * list, and webpack's default puts `.ts` ahead of `.tsx`. So a skeleton stub
 * left at `extend.ts` silently wins over the real `extend.tsx` beside it, and
 * everything the real file registered is simply absent from the bundle.
 *
 * That is exactly what happened here: the admin extender registering the
 * settings page and all four permissions was written, tested and never
 * shipped. No module-level test could catch it, because a test importing
 * `../../src/admin/extend.tsx` by its full name gets the right file every
 * time -- the bug lives in the resolution, which only the bundler performs.
 */

// Jest runs from the package's own `js` directory. The second test below fails
// loudly if that ever stops being true, rather than passing on an empty tree.
const SRC = path.join(process.cwd(), 'src');
const RESOLVABLE = ['.ts', '.tsx', '.js', '.jsx'];

function sourceFiles(dir: string): string[] {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) return sourceFiles(full);

    return RESOLVABLE.includes(path.extname(full)) ? [full] : [];
  });
}

describe('module resolution', () => {
  it('has no two source files that differ only by extension', () => {
    const byStem = new Map<string, string[]>();

    for (const file of sourceFiles(SRC)) {
      const stem = file.slice(0, -path.extname(file).length);

      byStem.set(stem, [...(byStem.get(stem) ?? []), path.relative(SRC, file)]);
    }

    const shadowed = [...byStem.values()].filter((files) => files.length > 1);

    expect(shadowed).toEqual([]);
  });

  /**
   * A path typo would otherwise make the check above pass by finding nothing.
   */
  it('reads the source tree it claims to read', () => {
    const files = sourceFiles(SRC);

    expect(files.length).toBeGreaterThan(25);
    expect(files.some((f) => f.endsWith(path.join('admin', 'extend.tsx')))).toBe(true);
    expect(files.some((f) => f.endsWith(path.join('forum', 'extend.ts')))).toBe(true);
  });
});
