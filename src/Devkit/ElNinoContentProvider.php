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

namespace Uhifadhi\ElNino\Devkit;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;
use Uhifadhi\ElNino\Service\BulletinIngestService;
use Uhifadhi\ElNino\Service\WatchService;

/**
 * SEED CONTENT: a made-up bulletin for every seed area that has a boundary,
 * through the same ingest a producer's push goes through. Inert without the
 * devkit, which is the only thing that calls load().
 */
final readonly class ElNinoContentProvider implements ContentProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AreaOfInterestRepository $areas,
        private WatchService $watches,
        private BulletinIngestService $ingest,
        private ?ClockInterface $clock,
    ) {
    }

    public function key(): string
    {
        return 'el-nino';
    }

    public function label(): string
    {
        return 'El Niño watch';
    }

    public function description(): string
    {
        return 'A made-up weekly bulletin for every seed area: a very strong El Niño, a wet fortnight, and three places that lose road access.';
    }

    public function dependsOn(): array
    {
        return ['area'];
    }

    public function load(): void
    {
        $now = $this->clock?->now() ?? new \DateTimeImmutable();
        foreach ($this->areas->findAll() as $area) {
            $box = self::boxOf($area);
            if (null === $box) {
                continue;
            }
            $slug = SyntheticBulletin::SLUG.'-'.substr((string) $area->getUuidString(), 0, 8);
            $this->watches->saveWords($area, $slug, null, null);
            $watch = $this->watches->forArea($area);
            $this->ingest->ingest($watch, SyntheticBulletin::over($box, $now->modify('-1 day'), 26, true, $slug), $now);
        }
        $this->entityManager->flush();
    }

    /**
     * @return array{west: float, south: float, east: float, north: float}|null
     */
    private static function boxOf(AreaOfInterest $area): ?array
    {
        $geom = $area->getGeom();
        if (null === $geom) {
            return null;
        }
        try {
            $decoded = json_decode($geom, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!\is_array($decoded)) {
            return null;
        }
        $west = $south = \INF;
        $east = $north = -\INF;
        $walk = static function (mixed $node) use (&$walk, &$west, &$south, &$east, &$north): void {
            if (!\is_array($node)) {
                return;
            }
            if (2 === \count($node) && is_numeric($node[0] ?? null) && is_numeric($node[1] ?? null)) {
                $x = (float) $node[0];
                $y = (float) $node[1];
                $west = min($west, $x);
                $east = max($east, $x);
                $south = min($south, $y);
                $north = max($north, $y);

                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($decoded['coordinates'] ?? null);
        if (!is_finite($west) || $east <= $west || $north <= $south) {
            return null;
        }

        return ['west' => $west, 'south' => $south, 'east' => $east, 'north' => $north];
    }
}
