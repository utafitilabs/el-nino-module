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

namespace Uhifadhi\ElNino\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Uhifadhi\ElNino\Model\EnsoWords;
use Uhifadhi\ElNino\Model\GridCells;
use Uhifadhi\ElNino\Model\Outlook;
use Uhifadhi\ElNino\Model\RainBand;

/**
 * The words and the bands are the settled page's, ported; these hold the
 * thresholds so a template can never drift from them.
 */
final class WordsAndBandsTest extends TestCase
{
    public function testTheRainBandsReadAPercentOfNormalTheWayThePageDoes(): void
    {
        self::assertSame('much wetter than normal', RainBand::of(150.0, 150)?->words());
        self::assertSame('wetter than normal', RainBand::of(121.0, 150)?->words());
        self::assertSame('near normal', RainBand::of(120.0, 150)?->words());
        self::assertSame('near normal', RainBand::of(80.0, 150)?->words());
        self::assertSame('drier than normal', RainBand::of(79.9, 150)?->words());
        self::assertSame('dry', RainBand::of(49.9, 150)?->words());
        self::assertNull(RainBand::of(null, 150));
        self::assertSame('much wetter than normal', RainBand::of(130.0, 125)?->words(), 'the threshold is the area\'s own');
    }

    public function testTheRainChipIsWarnForMuchWetterIdleForDryAndOkBetween(): void
    {
        self::assertSame('warn', RainBand::MuchWetter->chip());
        self::assertSame('idle', RainBand::Dry->chip());
        self::assertSame('ok', RainBand::Near->chip());
        self::assertSame('ok', RainBand::Drier->chip());
    }

    public function testThePacificsWordsFollowThePhaseAndTheRun(): void
    {
        self::assertSame('very strong El Niño', EnsoWords::plain('El Niño', 'very strong'));
        self::assertSame('ENSO neutral', EnsoWords::plain('neutral', 'neutral'));
        self::assertSame('3 weeks above the +0.5 °C threshold', EnsoWords::run('El Niño', 3));
    }

    public function testARunOfNineWeeksOrMoreIsSaidInMonths(): void
    {
        self::assertSame('8 weeks above the +0.5 °C threshold', EnsoWords::run('El Niño', 8));
        self::assertSame('about 2 months above the +0.5 °C threshold', EnsoWords::run('El Niño', 9));
        self::assertSame('about 6 months below the −0.5 °C threshold', EnsoWords::run('La Niña', 26));
        self::assertSame('inside the neutral band', EnsoWords::run('neutral', 0));
    }

    public function testTheChanceWordsAndChips(): void
    {
        self::assertSame('likely', EnsoWords::chance(70));
        self::assertSame('possible', EnsoWords::chance(40));
        self::assertSame('unlikely', EnsoWords::chance(39));
        self::assertSame('fail', EnsoWords::chanceChip(94));
        self::assertSame('warn', EnsoWords::chanceChip(50));
        self::assertSame('ok', EnsoWords::chanceChip(10));
        self::assertSame('fail', EnsoWords::chip(0.5));
        self::assertSame('acc', EnsoWords::chip(-0.5));
        self::assertSame('idle', EnsoWords::chip(0.2));
        self::assertSame('+3.3 °C', EnsoWords::anomaly(3.3));
        self::assertSame('−0.7 °C', EnsoWords::anomaly(-0.7));
        self::assertSame('Oct–Dec', EnsoWords::season('OND'));
        self::assertSame('XYZ', EnsoWords::season('XYZ'));
    }

    public function testTheOutlookReadsTheShortRainsTheLongRainsAndTheFade(): void
    {
        $outlook = Outlook::fromRaw([
            'issued' => 'Mid-Sep 2026',
            'seasons' => [
                ['season' => 'SON', 'el_nino' => 100, 'neutral' => 0, 'la_nina' => 0],
                ['season' => 'OND', 'el_nino' => 94.4, 'neutral' => 5.6, 'la_nina' => 0],
                ['season' => 'MAM', 'el_nino' => 29, 'neutral' => 58, 'la_nina' => 13],
            ],
        ]);

        self::assertNotNull($outlook);
        self::assertSame(94, $outlook->shortRains()['el_nino'] ?? null);
        self::assertSame(29, $outlook->longRains()['el_nino'] ?? null);
        self::assertSame('MAM', $outlook->fade()['season'] ?? null);
        self::assertStringContainsString('Mar–May (29 %)', $outlook->fadeSentence());
        self::assertNull(Outlook::fromRaw(null));
        self::assertNull(Outlook::fromRaw(['issued' => 'x', 'seasons' => []]));
    }

    public function testTheGridBecomesOneSquarePerCellAndNullsDrawNothing(): void
    {
        $grid = ['cell_deg' => 0.1, 'x_centres' => [10.05, 10.15], 'y_centres' => [-1.05, -1.15]];
        $mm = [[3.0, null], [12.0, 1.5]];
        $pct = [[80.0, null], [160.0, 20.0]];

        $cells = GridCells::featureCollection($grid, $mm, $pct, 150);

        self::assertCount(3, $cells['features']);
        $first = $cells['features'][0];
        self::assertSame([
            'mm' => 3.0, 'pct' => 80.0, 'band' => 'near', 'wet' => false, 'label' => '3.0 mm · 80 % of normal',
        ], $first['properties']);
        self::assertSame(['type' => 'Polygon', 'coordinates' => [[[10.0, -1.1], [10.1, -1.1], [10.1, -1.0], [10.0, -1.0], [10.0, -1.1]]]], $first['geometry']);
        self::assertSame(['mm' => 12.0, 'pct' => 160.0, 'band' => 'much-wetter', 'wet' => true, 'label' => '12.0 mm · 160 % of normal'], $cells['features'][1]['properties']);
        self::assertCount(1, GridCells::wetCells($grid, $pct, 150)['features']);
        self::assertSame(1, GridCells::countWet($pct, 150));
        self::assertSame(['type' => 'Polygon', 'coordinates' => [[[10.0, -1.2], [10.2, -1.2], [10.2, -1.0], [10.0, -1.0], [10.0, -1.2]]]], GridCells::bounds($grid));
    }
}
