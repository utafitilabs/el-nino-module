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
use Uhifadhi\ElNino\Model\GridCells;

/**
 * THE RAIN GRID AS CELLS: the percent view bands each cell by its word, the
 * millimetre view steps it by its share of the wettest cell in the window.
 */
final class GridCellsTest extends TestCase
{
    public function testMillimetresStepInFifthsOfTheWettestCellAndNoRainIsNought(): void
    {
        self::assertSame(15.7, GridCells::wettest([[null, 3.0], [15.7, 0.0]]));
        self::assertSame(0, GridCells::step(0.0, 15.7));
        self::assertSame(1, GridCells::step(0.1, 15.7));
        self::assertSame(1, GridCells::step(3.14, 15.7));
        self::assertSame(2, GridCells::step(3.2, 15.7));
        self::assertSame(5, GridCells::step(15.7, 15.7));
        self::assertSame(0, GridCells::step(2.0, 0.0), 'a window with no rain anywhere has no steps');
    }

    public function testEveryCellCarriesItsBandAndItsStep(): void
    {
        $grid = ['x_centres' => [34.0, 34.05], 'y_centres' => [-3.0], 'cell_deg' => 0.05];
        $cells = GridCells::featureCollection($grid, [[0.0, 10.0]], [[0.0, 200.0]], 150);

        self::assertCount(2, $cells['features']);
        $dry = $cells['features'][0]['properties'] ?? null;
        $wet = $cells['features'][1]['properties'] ?? null;
        self::assertIsArray($dry);
        self::assertIsArray($wet);
        self::assertSame(['band' => 'dry', 'step' => 0], array_intersect_key($dry, ['band' => 1, 'step' => 1]));
        self::assertSame(['band' => 'much-wetter', 'step' => 5], array_intersect_key($wet, ['band' => 1, 'step' => 1]));
    }
}
