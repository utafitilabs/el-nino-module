<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi El Niño Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\ElNino\Service;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\ElNino\Entity\AreaWatch;
use Uhifadhi\ElNino\Model\WatchReading;
use Uhifadhi\ElNino\Repository\AreaWatchRepository;
use Uhifadhi\ElNino\Repository\BulletinRepository;
use Uhifadhi\ElNino\Repository\ExposureRunRepository;

/**
 * WHAT AN AREA'S WATCH SAYS RIGHT NOW: one read, shared by the page, the
 * tiles, the attention list and the map layers.
 */
final class WatchReader
{
    public function __construct(
        private readonly AreaWatchRepository $watches,
        private readonly BulletinRepository $bulletins,
        private readonly ExposureRunRepository $runs,
    ) {
    }

    /** Null where the area has no bulletin yet — the page's empty state, never a page of noughts. */
    public function read(AreaOfInterest $area): ?WatchReading
    {
        $bulletin = $this->bulletins->findLatestForArea($area);
        if (null === $bulletin) {
            return null;
        }
        $watch = $this->watches->findOneByArea($area) ?? new AreaWatch($area);

        return new WatchReading($watch, $bulletin, $this->runs->findCurrentForArea($area));
    }

    public function watchOf(AreaOfInterest $area): ?AreaWatch
    {
        return $this->watches->findOneByArea($area);
    }
}
