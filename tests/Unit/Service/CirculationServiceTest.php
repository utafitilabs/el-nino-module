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

namespace Uhifadhi\ElNino\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Map\Map as UxMap;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;
use Uhifadhi\ElNino\Model\Circulation;
use Uhifadhi\ElNino\Service\CirculationService;

/**
 * The three states draw the shapes the settled page draws, and nothing is data.
 *
 * @phpstan-type Summary array{count: int, dash: string|null, firstX: float|null, swatch: string}
 */
final class CirculationServiceTest extends TestCase
{
    public function testTheThreeStatesDifferWhereThePageSaysTheyDo(): void
    {
        $service = new CirculationService(new class implements MapBuilderInterface {
            public function createMap(): AtlasMap
            {
                return new AtlasMap(new UxMap());
            }
        });

        $normal = self::summarize($service->plate(Circulation::Normal));
        $nino = self::summarize($service->plate(Circulation::ElNino));
        $dipole = self::summarize($service->plate(Circulation::Dipole));

        self::assertSame(['circ.warm', 'circ.cool', 'circ.nino', 'circ.trades', 'circ.pacific', 'circ.indian', 'circ.moist', 'circ.rise', 'circ.sink', 'circ.labels'], array_keys($normal));
        self::assertSame(0, $normal['circ.moist']['count'], 'no moist air towards the coast in a normal year');
        self::assertSame(1, $nino['circ.moist']['count']);
        self::assertSame(0, $normal['circ.cool']['count']);
        self::assertSame(1, $dipole['circ.cool']['count'], 'the dipole cools the eastern Indian Ocean');
        self::assertSame('4 4', $normal['circ.nino']['dash'], 'the Niño 3.4 box is dashed outside El Niño');
        self::assertNull($nino['circ.nino']['dash']);
        self::assertSame(202.0, $nino['circ.pacific']['firstX'], 'the rising branch moves east');
        self::assertSame(120.0, $normal['circ.pacific']['firstX']);
        self::assertSame(7, $nino['circ.labels']['count'], 'El Niño adds one label');
        self::assertSame(6, $normal['circ.labels']['count']);
        foreach ([$normal, $nino, $dipole] as $state) {
            foreach ($state as $id => $layer) {
                self::assertMatchesRegularExpression('/^var\(--plate-[a-z]+\)$/', $layer['swatch'], $id.' names a plate token');
            }
        }
        self::assertSame('Normal year', Circulation::fromQuery(null)->label());
        self::assertSame(Circulation::ElNino, Circulation::fromQuery('nino'));
        self::assertSame(Circulation::Normal, Circulation::fromQuery('anything else'));
    }

    /**
     * @return array<string, Summary>
     */
    private static function summarize(AtlasMap $map): array
    {
        $out = [];
        foreach ($map->toArray()['layers'] as $layer) {
            $id = $layer['id'];
            \assert(\is_string($id));
            $features = $layer['features'];
            \assert(\is_array($features) && \is_array($features['features']));
            $style = $layer['style'];
            \assert(\is_array($style));
            $dash = $style['dashArray'] ?? null;
            $firstX = null;
            $first = $features['features'][0] ?? null;
            if (\is_array($first) && \is_array($first['geometry'] ?? null) && \is_array($first['geometry']['coordinates'] ?? null)) {
                $head = $first['geometry']['coordinates'][0] ?? null;
                if (\is_array($head) && \is_float($head[0] ?? null)) {
                    $firstX = $head[0];
                }
            }
            $swatch = $layer['swatch'];
            \assert(\is_string($swatch));
            $out[$id] = [
                'count' => \count($features['features']),
                'dash' => \is_string($dash) ? $dash : null,
                'firstX' => $firstX,
                'swatch' => $swatch,
            ];
        }

        return $out;
    }
}
