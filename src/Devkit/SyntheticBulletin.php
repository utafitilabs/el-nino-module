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

use Uhifadhi\ElNino\Model\ProducerBulletin;

/**
 * A MADE-UP BULLETIN OVER MADE-UP GROUND, for seed content and tests: the
 * shape of the contract exactly, the numbers invented, the places named by
 * nobody. Deterministic for a given bounding box and build time, so a seeded
 * installation and a test see the same week.
 *
 * @phpstan-type Box array{west: float, south: float, east: float, north: float}
 */
final class SyntheticBulletin
{
    public const string SLUG = 'kilimani-watch';

    /** @var list<array{name: string, kind: string, status: string, people: int, u: float, v: float}> */
    private const array DESTINATIONS = [
        ['name' => 'Eastgate Post', 'kind' => 'ranger post', 'status' => 'unchanged', 'people' => 120, 'u' => 0.88, 'v' => 0.52],
        ['name' => 'Fig Tree Ranger Post', 'kind' => 'ranger post', 'status' => 'cut off', 'people' => 340, 'u' => 0.42, 'v' => 0.68],
        ['name' => 'Ridge Outpost', 'kind' => 'outpost', 'status' => 'cut off', 'people' => 90, 'u' => 0.63, 'v' => 0.31],
        ['name' => 'Western Plains Camp', 'kind' => 'camp', 'status' => 'unchanged', 'people' => 60, 'u' => 0.18, 'v' => 0.47],
        ['name' => 'Salt Pans Village', 'kind' => 'village', 'status' => 'cut off', 'people' => 1450, 'u' => 0.27, 'v' => 0.22],
        ['name' => 'Lone Hills Clinic', 'kind' => 'clinic', 'status' => 'delayed', 'people' => 800, 'u' => 0.55, 'v' => 0.80],
    ];

    private function __construct()
    {
    }

    /**
     * @param Box $box the area's bounding box in degrees
     */
    public static function over(array $box, \DateTimeImmutable $builtAt, int $weeks = 26, bool $withExposure = true, string $slug = self::SLUG): ProducerBulletin
    {
        $builtAt = $builtAt->setTimezone(new \DateTimeZone('UTC'))->setTime((int) $builtAt->format('H'), (int) $builtAt->format('i'));
        $cell = 0.05;
        $cols = max(2, (int) ceil(($box['east'] - $box['west']) / $cell));
        $rows = max(2, (int) ceil(($box['north'] - $box['south']) / $cell));
        $xs = [];
        for ($i = 0; $i < $cols; ++$i) {
            $xs[] = round($box['west'] + $cell * ($i + 0.5), 4);
        }
        $ys = [];
        for ($j = 0; $j < $rows; ++$j) {
            $ys[] = round($box['north'] - $cell * ($j + 0.5), 4);
        }
        $grid = [
            'crs' => 'EPSG:4326',
            'cell_deg' => $cell,
            'rows' => $rows,
            'cols' => $cols,
            'x_centres' => $xs,
            'y_centres' => $ys,
            'origin_nw_corner' => [$box['north'], $box['west']],
        ];

        $monday = $builtAt->setTime(0, 0)->modify('monday this week');
        $weekRows = [];
        $hotNames = [];
        for ($w = $weeks - 1; $w >= 0; --$w) {
            $start = $monday->modify(\sprintf('-%d weeks', $w));
            $end = $start->modify('+6 days');
            $phase = ($weeks - 1 - $w) / max(1, $weeks - 1);
            $normal = 12.0 + 20.0 * $phase;
            $mm = $normal * (0.4 + 1.6 * $phase * $phase);
            [$gridMm, $gridPct] = self::grids($rows, $cols, $mm, $normal, $phase, $w);
            $wet = 0;
            foreach ($gridPct as $row) {
                foreach ($row as $value) {
                    if (null !== $value && $value >= 150) {
                        ++$wet;
                    }
                }
            }
            $hot = $wet > 0 && $phase > 0.85 ? ['Fig Tree Ranger Post'] : [];
            if (0 === $w) {
                $hotNames = $hot;
            }
            $weekRows[] = [
                'label' => $start->format('Y-m-d').' to '.$end->format('Y-m-d'),
                'start' => $start,
                'end' => $end,
                'days' => 0 === $w ? 4 : 7,
                'mm' => round($mm, 1),
                'normal_mm' => round($normal, 1),
                'pct' => round(100 * $mm / $normal),
                'wet_cells' => $wet,
                'hot' => $hot,
                'nino_date' => $start->modify('+2 days'),
                'nino_anomaly' => round(-0.4 + 3.4 * $phase, 1),
                'grid_mm' => $gridMm,
                'grid_pct' => $gridPct,
            ];
        }
        $last = $weekRows[\count($weekRows) - 1];
        [$recentMm, $recentPct] = self::grids($rows, $cols, 86.0, 62.0, 1.0, 0);

        $destinations = $withExposure ? self::destinations($box) : null;
        $provenance = $withExposure ? [
            'contract' => ProducerBulletin::CONTRACT,
            'notebook' => 'notebooks/flood_exposure.ipynb',
            'notebook_commit' => 'seed0000',
            'run_date' => $builtAt->format('Y-m-d'),
            'weights' => ['hand' => 0.3, 'slope' => 0.15, 'dist' => 0.15, 'facc' => 0.1, 'rain' => 0.2, 'wet' => 0.1],
            'road_rules' => ['at_risk_m' => 100, 'likely_cut_m' => 300, 'fords' => 'always likely cut'],
        ] : null;

        return new ProducerBulletin(
            contract: ProducerBulletin::CONTRACT,
            producerSlug: $slug,
            producer: ['slug' => $slug, 'name' => 'Seed watch', 'country' => 'ZZ'],
            builtAt: $builtAt,
            enso: [
                'week' => $last['nino_date']->format('Y-m-d'),
                'sst' => 29.6,
                'anomaly' => $last['nino_anomaly'],
                'state' => 'El Niño range for three months',
                'phase' => 'El Niño',
                'strength' => 'very strong',
                'weeks_in_range' => 22,
            ],
            rain: [
                'start' => $last['end']->modify('-29 days')->format('Y-m-d'),
                'end' => $last['end']->format('Y-m-d'),
                'days' => 30,
                'missing' => [],
                'total_mm' => 86.0,
                'normal_mm' => 62.0,
                'pct' => 139.0,
            ],
            wetThresholdPct: 150,
            cutOffInWetCell: $hotNames,
            outlook: [
                'issued' => 'Mid-'.$builtAt->format('M Y'),
                'seasons' => [
                    ['season' => 'SON', 'el_nino' => 98, 'neutral' => 2, 'la_nina' => 0],
                    ['season' => 'OND', 'el_nino' => 94, 'neutral' => 6, 'la_nina' => 0],
                    ['season' => 'NDJ', 'el_nino' => 88, 'neutral' => 12, 'la_nina' => 0],
                    ['season' => 'DJF', 'el_nino' => 76, 'neutral' => 23, 'la_nina' => 1],
                    ['season' => 'JFM', 'el_nino' => 61, 'neutral' => 36, 'la_nina' => 3],
                    ['season' => 'FMA', 'el_nino' => 44, 'neutral' => 49, 'la_nina' => 7],
                    ['season' => 'MAM', 'el_nino' => 29, 'neutral' => 58, 'la_nina' => 13],
                    ['season' => 'AMJ', 'el_nino' => 18, 'neutral' => 62, 'la_nina' => 20],
                    ['season' => 'MJJ', 'el_nino' => 12, 'neutral' => 60, 'la_nina' => 28],
                ],
            ],
            exposure: ['cut' => 14, 'risk' => 31, 'cut_off' => 3, 'other' => 3, 'wet' => $last['wet_cells']],
            notebookRun: $withExposure ? ['run_date' => $builtAt->format('Y-m-d'), 'notebook_commit' => 'seed0000'] : null,
            zones: null,
            grid: $grid,
            recentGridMm: $recentMm,
            recentGridPct: $recentPct,
            weeks: $weekRows,
            roads: $withExposure ? self::roads($box) : null,
            destinations: $destinations,
            susceptibility: $withExposure ? self::patches($box) : null,
            bomas: null,
            provenance: $provenance,
        );
    }

    /**
     * @return array{0: list<list<float|null>>, 1: list<list<float|null>>}
     */
    private static function grids(int $rows, int $cols, float $meanMm, float $normal, float $phase, int $seed): array
    {
        $mm = [];
        $pct = [];
        for ($j = 0; $j < $rows; ++$j) {
            $rowMm = [];
            $rowPct = [];
            for ($i = 0; $i < $cols; ++$i) {
                $edge = 0 === $j || 0 === $i || $rows - 1 === $j || $cols - 1 === $i;
                if ($edge && 0 === ($i + $j + $seed) % 3) {
                    $rowMm[] = null;
                    $rowPct[] = null;
                    continue;
                }
                $wave = 0.55 + 0.9 * (0.5 + 0.5 * sin(0.9 * $i + 0.7 * $j + $seed)) * (0.3 + $phase);
                $value = $meanMm * $wave;
                $rowMm[] = round($value, 1);
                $rowPct[] = round(100 * $value / $normal);
            }
            $mm[] = $rowMm;
            $pct[] = $rowPct;
        }

        return [$mm, $pct];
    }

    /**
     * @param Box $box
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private static function destinations(array $box): array
    {
        $features = [];
        foreach (self::DESTINATIONS as $row) {
            $features[] = [
                'type' => 'Feature',
                'properties' => ['name' => $row['name'], 'kind' => $row['kind'], 'status' => $row['status'], 'population_2020' => $row['people']],
                'geometry' => ['type' => 'Point', 'coordinates' => [self::lon($box, $row['u']), self::lat($box, $row['v'])]],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @param Box $box
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private static function roads(array $box): array
    {
        $features = [];
        $lines = [
            ['cut', 'track', 1020, [[0.30, 0.20], [0.36, 0.34], [0.42, 0.48]]],
            ['cut', 'unclassified', 640, [[0.60, 0.28], [0.64, 0.40], [0.70, 0.50]]],
            ['risk', 'track', 180, [[0.15, 0.60], [0.25, 0.63], [0.35, 0.70]]],
            ['risk', 'unclassified', 140, [[0.75, 0.70], [0.82, 0.62], [0.88, 0.55]]],
        ];
        foreach ($lines as [$cls, $highway, $metres, $points]) {
            $features[] = [
                'type' => 'Feature',
                'properties' => ['cls' => $cls, 'highway' => $highway, 'run_very_high_m' => $metres],
                'geometry' => ['type' => 'LineString', 'coordinates' => array_map(
                    static fn (array $p): array => [self::lon($box, $p[0]), self::lat($box, $p[1])],
                    $points,
                )],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @param Box $box
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private static function patches(array $box): array
    {
        $features = [];
        $cells = [
            [4, 'very high', 0.30, 0.20, 0.14, 0.12], [4, 'very high', 0.58, 0.28, 0.12, 0.10],
            [3, 'high', 0.22, 0.36, 0.16, 0.14], [3, 'high', 0.66, 0.44, 0.14, 0.12],
            [2, 'medium', 0.40, 0.55, 0.20, 0.16], [1, 'low', 0.10, 0.75, 0.30, 0.18],
        ];
        foreach ($cells as [$class, $name, $u, $v, $w, $h]) {
            $features[] = [
                'type' => 'Feature',
                'properties' => ['class' => $class, 'name' => $name],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                    [self::lon($box, $u), self::lat($box, $v)],
                    [self::lon($box, $u + $w), self::lat($box, $v)],
                    [self::lon($box, $u + $w), self::lat($box, $v + $h)],
                    [self::lon($box, $u), self::lat($box, $v + $h)],
                    [self::lon($box, $u), self::lat($box, $v)],
                ]]],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /** @param Box $box */
    private static function lon(array $box, float $u): float
    {
        return round($box['west'] + ($box['east'] - $box['west']) * $u, 5);
    }

    /** @param Box $box */
    private static function lat(array $box, float $v): float
    {
        return round($box['south'] + ($box['north'] - $box['south']) * $v, 5);
    }
}
