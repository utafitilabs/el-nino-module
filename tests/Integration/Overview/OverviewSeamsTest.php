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

namespace Uhifadhi\ElNino\Tests\Integration\Overview;

use Uhifadhi\Bundle\AreaBundle\Overview\AttentionSeverity;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTile;
use Uhifadhi\ElNino\Devkit\ElNinoContentProvider;
use Uhifadhi\ElNino\Devkit\SyntheticBulletin;
use Uhifadhi\ElNino\Overview\ElNinoAttention;
use Uhifadhi\ElNino\Overview\ElNinoMapLayers;
use Uhifadhi\ElNino\Overview\ElNinoNowTiles;
use Uhifadhi\ElNino\Service\BulletinIngestService;
use Uhifadhi\ElNino\Service\WatchService;
use Uhifadhi\ElNino\Tests\Integration\IntegrationTestCase;

/**
 * What the area page is handed: the four tiles, an attention item per hot
 * destination, the four layers — and nothing at all for an area without a
 * bulletin.
 */
final class OverviewSeamsTest extends IntegrationTestCase
{
    public function testTheTilesTheAttentionAndTheLayersReadTheLatestBulletin(): void
    {
        $area = $this->seedArea();
        $watches = $this->service(WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->saveWords($area, SyntheticBulletin::SLUG, null, null);
        $ingest = $this->service(BulletinIngestService::class);
        \assert($ingest instanceof BulletinIngestService);
        $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(self::seedBox(), new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC')), 26));
        $now = new \DateTimeImmutable('2026-10-13 09:00', new \DateTimeZone('UTC'));

        $tiles = $this->service(ElNinoNowTiles::class);
        \assert($tiles instanceof ElNinoNowTiles);
        $drawn = $tiles->nowTilesFor($area, $now);
        self::assertCount(4, $drawn);
        self::assertSame('EN·N1', $drawn[0]->index);
        self::assertSame(NowTile::TONE_HOT, $drawn[0]->tone, 'El Niño is hot');
        self::assertSame('94', $drawn[1]->value);
        self::assertSame('139', $drawn[2]->value);
        self::assertSame('3', $drawn[3]->value);
        self::assertSame('1 in a wet cell now', $drawn[3]->alarm);
        self::assertSame(NowTile::TONE_BAD, $drawn[3]->tone);

        $attention = $this->service(ElNinoAttention::class);
        \assert($attention instanceof ElNinoAttention);
        $items = $attention->attentionFor($area, $now);
        self::assertCount(1, $items);
        self::assertSame(AttentionSeverity::Soon, $items[0]->severity);
        self::assertStringContainsString('Fig Tree Ranger Post', $items[0]->headline);
        self::assertContains('about 340 people', $items[0]->meta);
        self::assertSame('1 d', $items[0]->ageLabel);

        $layers = $this->service(ElNinoMapLayers::class);
        \assert($layers instanceof ElNinoMapLayers);
        $onMap = $layers->mapLayersFor($area, $now);
        self::assertSame(['el-nino.very-high', 'el-nino.roads-cut', 'el-nino.cut-off', 'el-nino.wet-cells'], array_map(static fn ($l) => $l->id, $onMap));
        self::assertSame(2, $onMap[1]->count);
        self::assertSame(3, $onMap[2]->count);
        self::assertGreaterThan(0, $onMap[3]->count, 'the recent window has wet cells');
    }

    public function testAnAreaWithoutABulletinContributesNothing(): void
    {
        $area = $this->seedArea();
        $now = new \DateTimeImmutable();

        $tiles = $this->service(ElNinoNowTiles::class);
        \assert($tiles instanceof ElNinoNowTiles);
        $attention = $this->service(ElNinoAttention::class);
        \assert($attention instanceof ElNinoAttention);
        $layers = $this->service(ElNinoMapLayers::class);
        \assert($layers instanceof ElNinoMapLayers);

        self::assertSame([], $tiles->nowTilesFor($area, $now));
        self::assertSame([], $attention->attentionFor($area, $now));
        self::assertSame([], $layers->mapLayersFor($area, $now));
    }

    public function testTheSeedContentFeedsEveryAreaWithABoundary(): void
    {
        $this->seedArea('Kilimani Game Reserve');
        $this->seedArea('Tambarare Game Reserve');
        $bare = new \Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest()->setSource('test fixture')->setName('Unmeasured');
        $this->em->persist($bare);
        $this->em->flush();

        $content = $this->service(ElNinoContentProvider::class);
        \assert($content instanceof ElNinoContentProvider);
        self::assertSame(['area'], $content->dependsOn());
        $content->load();

        $tiles = $this->service(ElNinoNowTiles::class);
        \assert($tiles instanceof ElNinoNowTiles);
        $areas = $this->em->getRepository(\Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest::class)->findAll();
        $fed = 0;
        foreach ($areas as $area) {
            $fed += [] === $tiles->nowTilesFor($area, new \DateTimeImmutable()) ? 0 : 1;
        }
        self::assertSame(2, $fed, 'the two reserves with a boundary; the unmeasured one is left alone');
    }
}
