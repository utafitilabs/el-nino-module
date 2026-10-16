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

namespace Uhifadhi\ElNino\Model;

/**
 * THE RAIN GRID AS SHAPES: one square feature per cell with a value, so a
 * week's rain is drawn on the atlas's plate like any other layer rather than
 * as a picture of its own.
 *
 * The producer states the grid once — row-major, north first, west first,
 * cell centres, a cell size in degrees — and every week's `grid_mm` and
 * `grid_pct` are read against it. A null cell is outside the area and draws
 * nothing.
 *
 * @phpstan-type Grid list<list<float|null>>
 */
final class GridCells
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $grid the bulletin's grid block
     * @param Grid                 $mm   the week's millimetres
     * @param Grid                 $pct  the week's percent of normal
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public static function featureCollection(array $grid, array $mm, array $pct, int $wetThresholdPct): array
    {
        $xs = self::centres($grid['x_centres'] ?? null);
        $ys = self::centres($grid['y_centres'] ?? null);
        $half = self::cellDeg($grid) / 2;
        $wettest = self::wettest($mm);
        $features = [];
        foreach ($ys as $j => $y) {
            foreach ($xs as $i => $x) {
                $p = $pct[$j][$i] ?? null;
                $m = $mm[$j][$i] ?? null;
                if (null === $p && null === $m) {
                    continue;
                }
                $band = RainBand::of($p, $wetThresholdPct);
                $features[] = [
                    'type' => 'Feature',
                    'properties' => [
                        'mm' => null === $m ? null : round($m, 1),
                        'pct' => null === $p ? null : round($p),
                        'band' => $band?->value,
                        'step' => null === $m ? null : self::step($m, $wettest),
                        'wet' => null !== $p && $p >= $wetThresholdPct,
                        'label' => null === $m
                            ? 'no reading'
                            : \sprintf('%.1f mm · %s', $m, null === $p ? 'no normal' : \sprintf('%d %% of normal', (int) round($p))),
                    ],
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [round($x - $half, 5), round($y - $half, 5)],
                            [round($x + $half, 5), round($y - $half, 5)],
                            [round($x + $half, 5), round($y + $half, 5)],
                            [round($x - $half, 5), round($y + $half, 5)],
                            [round($x - $half, 5), round($y - $half, 5)],
                        ]],
                    ],
                ];
            }
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * THE MILLIMETRE STEPS: a cell's share of the wettest cell in the same
     * window, in fifths — 0 for no rain, 1 to 5 upward — so the mm plate reads
     * as the design's ramp: deeper mint where more fell.
     */
    public const int STEPS = 5;

    /** @param Grid $mm */
    public static function wettest(array $mm): float
    {
        $wettest = 0.0;
        foreach ($mm as $row) {
            foreach ($row as $value) {
                if (null !== $value && $value > $wettest) {
                    $wettest = $value;
                }
            }
        }

        return $wettest;
    }

    public static function step(float $mm, float $wettest): int
    {
        if ($mm <= 0.0 || $wettest <= 0.0) {
            return 0;
        }

        return max(1, min(self::STEPS, (int) ceil($mm / $wettest * self::STEPS)));
    }

    /**
     * Only the cells at or above the wet threshold — the outline the page draws
     * on the exposure map, and the cells the attention list reads.
     *
     * @param array<string, mixed> $grid
     * @param Grid                 $pct
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public static function wetCells(array $grid, array $pct, int $wetThresholdPct): array
    {
        $all = self::featureCollection($grid, [], $pct, $wetThresholdPct);
        $wet = array_values(array_filter(
            $all['features'],
            static function (array $feature): bool {
                $properties = $feature['properties'] ?? null;

                return \is_array($properties) && true === ($properties['wet'] ?? false);
            },
        ));

        return ['type' => 'FeatureCollection', 'features' => $wet];
    }

    /** @param Grid $pct */
    public static function countWet(array $pct, int $wetThresholdPct): int
    {
        $count = 0;
        foreach ($pct as $row) {
            foreach ($row as $value) {
                if (null !== $value && $value >= $wetThresholdPct) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * The bounds of the grid as a GeoJSON polygon, for a plate to fit to.
     *
     * @param array<string, mixed> $grid
     *
     * @return array<string, mixed>|null
     */
    public static function bounds(array $grid): ?array
    {
        $xs = self::centres($grid['x_centres'] ?? null);
        $ys = self::centres($grid['y_centres'] ?? null);
        if ([] === $xs || [] === $ys) {
            return null;
        }
        $half = self::cellDeg($grid) / 2;
        $west = round(min($xs) - $half, 5);
        $east = round(max($xs) + $half, 5);
        $south = round(min($ys) - $half, 5);
        $north = round(max($ys) + $half, 5);

        return ['type' => 'Polygon', 'coordinates' => [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]]];
    }

    /** @return list<float> */
    private static function centres(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            if (\is_int($value) || \is_float($value)) {
                $out[] = (float) $value;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $grid */
    private static function cellDeg(array $grid): float
    {
        $cell = $grid['cell_deg'] ?? null;

        return \is_int($cell) || \is_float($cell) ? (float) $cell : 0.05;
    }
}
