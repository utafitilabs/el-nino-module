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

use Uhifadhi\ElNino\Entity\Bulletin;
use Uhifadhi\ElNino\Entity\Week;
use Uhifadhi\ElNino\Model\EnsoWords;

/**
 * THE WEEK-BY-WEEK WALK: which weeks the tab shows, which one is picked, and
 * the one sentence a ranger reads first about it.
 */
final class WeeksService
{
    public function __construct(
        private readonly int $weeksShown,
    ) {
    }

    /**
     * The newest weeks the bulletin carries, oldest first, as many as the installation shows.
     *
     * @return list<Week>
     */
    public function weeks(Bulletin $bulletin): array
    {
        $weeks = $bulletin->getWeeks();

        return \array_slice($weeks, -$this->weeksShown);
    }

    /**
     * The index the page opens on: the one asked for, clamped, else the newest.
     *
     * @param list<Week> $weeks
     */
    public function pick(array $weeks, ?int $asked): int
    {
        $last = \count($weeks) - 1;
        if ($last < 0) {
            return 0;
        }
        if (null === $asked) {
            return $last;
        }

        return max(0, min($last, $asked));
    }

    /** The caption under the week's map, the way the settled page says it. */
    public function caption(Week $week, int $wetThresholdPct): string
    {
        $nino = null === $week->getNinoAnomaly()
            ? 'no CPC value for this week'
            : \sprintf('Niño 3.4 %s (CPC week of %s)', EnsoWords::anomaly($week->getNinoAnomaly()), $week->getNinoDate()?->format('Y-m-d') ?? 'unknown');
        $hot = $week->getHot();

        return \sprintf(
            'Mean rain over the area %.1f mm against a normal of %.1f mm (%s); %d %s at or above %d %% of normal. %s. Cut-off destinations in a wet cell: %s.',
            $week->getMm(),
            $week->getNormalMm(),
            null === $week->getPct() ? '–' : \sprintf('%d %%', (int) round($week->getPct())),
            $week->getWetCells(),
            1 === $week->getWetCells() ? 'cell' : 'cells',
            $wetThresholdPct,
            $nino,
            [] === $hot ? 'none' : implode(', ', $hot),
        );
    }
}
