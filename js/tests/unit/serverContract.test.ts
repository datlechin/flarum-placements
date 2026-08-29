import fs from 'fs';
import path from 'path';

import { DemoMode } from '../../src/common/placements';

/**
 * Values the browser has to spell exactly as the server does.
 *
 * A query parameter, a status word or a bucket name that drifts is invisible:
 * nothing fails to compile, no test that mocks the server notices, and the
 * only symptom is a control that appears to do nothing. Each of these is read
 * back out of the PHP that defines it.
 */

const root = path.resolve(process.cwd(), '..');

function phpConst(file: string, name: string): string {
  const source = fs.readFileSync(path.join(root, file), 'utf8');
  const match = source.match(new RegExp(`const\\s+${name}\\s*=\\s*'([^']*)'`));

  if (!match) throw new Error(`No constant ${name} in ${file}`);

  return match[1];
}

describe('constants shared with the server', () => {
  /**
   * Demo mode lives in the session, so the only way out is the parameter that
   * turns it off. A link built from a different spelling leaves the reader
   * stuck in it with no way back.
   */
  it('names the demo-mode parameter the way the server does', () => {
    expect(DemoMode.PARAM).toBe(phpConst('src/Support/DemoMode.php', 'PARAM'));
  });
});
