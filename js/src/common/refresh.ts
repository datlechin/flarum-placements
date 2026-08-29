import app from 'flarum/common/app';

import placements from './placements';
import type { Candidate } from './types';

/**
 * Fresh proof for adverts already served, once per navigation.
 *
 * The plan is minted once per page load, so in a single-page application one
 * nonce has to cover an entire reading session -- and a nonce is refused twice
 * by the browser and again by the server. A reader who moved through sixty
 * pages was therefore worth one impression per creative, and once the token
 * passed its lifetime every further event was dropped while the adverts went
 * on appearing.
 *
 * Nothing is re-decided by asking: the server reads each triple out of the
 * signature it produced, so this can only ever renew what was already chosen.
 */

/**
 * Long enough that a burst of navigation is one request, short enough that a
 * reader who lingers on a page and moves on is counted for both.
 */
const MIN_INTERVAL = 20_000;

let lastRefresh = 0;
let inFlight = false;

interface RefreshedToken {
  creative: number;
  campaign: number;
  placement: string;
  token: string;
  nonce: string;
  issued: number;
}

/**
 * For tests, and for a reader whose session is being replaced wholesale.
 */
export function resetRefresh(): void {
  lastRefresh = 0;
  inFlight = false;
}

/**
 * Every candidate currently carrying a token, with the slot it belongs to.
 */
function outstanding(): Array<{ placement: string; candidate: Candidate }> {
  const state = placements();

  if (!state.active || state.demo) return [];

  const found: Array<{ placement: string; candidate: Candidate }> = [];

  state.keys().forEach((placement) => {
    (state.slot(placement)?.candidates ?? []).forEach((candidate) => {
      if (candidate.token && candidate.nonce && candidate.issued != null) {
        found.push({ placement, candidate });
      }
    });
  });

  return found;
}

export function refreshTokens(now: number = Date.now()): Promise<void> {
  if (inFlight || now - lastRefresh < MIN_INTERVAL) return Promise.resolve();

  const held = outstanding();

  if (!held.length) return Promise.resolve();

  inFlight = true;
  lastRefresh = now;

  return app
    .request<{ tokens: RefreshedToken[] }>({
      method: 'POST',
      url: `${app.forum.attribute('apiUrl')}/placements/tokens`,
      body: {
        tokens: held.map(({ placement, candidate }) => ({
          creative: candidate.creative,
          campaign: candidate.campaign,
          placement,
          token: candidate.token,
          nonce: candidate.nonce,
          issued: candidate.issued,
        })),
      },
    })
    .then((response) => {
      const fresh = response?.tokens ?? [];

      held.forEach(({ placement, candidate }) => {
        const match = fresh.find(
          (entry) => entry.creative === candidate.creative && entry.campaign === candidate.campaign && entry.placement === placement
        );

        // Mutated in place rather than replacing the plan: the candidate
        // objects are what the slots are already holding, and a slot that is
        // mounted must go on drawing the same advert.
        if (match) {
          candidate.token = match.token;
          candidate.nonce = match.nonce;
          candidate.issued = match.issued;
        }
      });
    })
    .catch(() => {
      // A refusal leaves the old tokens in place. They may still be within
      // their reporting lifetime, and if they are not the worst case is the
      // undercount this exists to fix -- never a wrong count.
    })
    .then(() => {
      inFlight = false;
    });
}
