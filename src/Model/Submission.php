<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Model;

/**
 * A creative, seen from the member side.
 *
 * The same table and the same rows as Creative. It exists as its own class
 * because Flarum ties a searcher to a model class by exact name, and the
 * Index endpoint routes through the searcher *instead of* the resource's own
 * query -- so with both resources on one model, the searcher registered for
 * the review queue answered the member's list too, and answered it with
 * everybody's adverts.
 *
 * Nothing else about it differs, and nothing should: the difference between
 * the two is who may see which rows, and that belongs on the resource.
 *
 * @see \Datlechin\Placement\Api\Resource\SubmissionResource
 * @see \Datlechin\Placement\Search\CreativeSearcher
 */
class Submission extends Creative
{
}
