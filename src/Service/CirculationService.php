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

use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;
use Uhifadhi\Bundle\AtlasBundle\Model\BaseLayer;
use Uhifadhi\Bundle\AtlasBundle\Model\GeoJsonLayer;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerShape;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerStyle;
use Uhifadhi\Contracts\Atlas\PlatePalette;
use Uhifadhi\ElNino\Model\Circulation;

/**
 * THE BACKGROUND PLATE, STATED: the two circulations along the equator — the
 * Pacific Walker cell and the Indian Ocean cell — as schematic shapes on the
 * atlas's plate, in one of three states. Positions are schematic, said so in
 * the attribution; every colour is a plate token; nothing here is data.
 *
 * Longitudes run past 180° so the Pacific is drawn whole from Indonesia to
 * South America without a seam at the date line.
 */
final class CirculationService
{
    public const string GROUP = 'Circulation';

    public function __construct(
        private readonly MapBuilderInterface $maps,
    ) {
    }

    public function plate(Circulation $state): AtlasMap
    {
        $normal = Circulation::Normal === $state;
        $nino = Circulation::ElNino === $state;
        $dipole = Circulation::Dipole === $state;

        $map = $this->maps->createMap()
            ->baseLayers(BaseLayer::Satellite, BaseLayer::Street)
            ->fullscreen(false)
            ->fitTo(self::polygon(22.0, -26.0, 298.0, 24.0));

        // ---- the sea surface
        $warm = [];
        if ($normal || $dipole) {
            $warm[] = self::box(118, -10, 165, 10, 'warm pool, above 28 °C');
        }
        if ($nino) {
            $warm[] = self::box(150, -8, 278, 8, 'warm water spread east, 2 to 3 °C above normal off South America');
        }
        if ($nino || $dipole) {
            $warm[] = self::box(45, -10, 70, 10, 'western Indian Ocean warmer than normal');
        }
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.warm',
            label: 'Warm water',
            features: self::collection($warm),
            swatch: PlatePalette::FAIL,
            shape: LayerShape::Fill,
            group: self::GROUP,
            style: new LayerStyle(color: PlatePalette::FAIL, weight: 1.2, fill: true, fillColor: PlatePalette::FAIL, fillOpacity: $nino ? 0.26 : 0.22),
            tooltip: 'label',
            labels: false,
        ));
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.cool',
            label: 'Cool water',
            features: self::collection($dipole ? [self::box(90, -10, 110, 0, 'eastern Indian Ocean cooler than normal')] : []),
            swatch: PlatePalette::ACCENT,
            shape: LayerShape::Fill,
            group: self::GROUP,
            visible: $dipole,
            style: new LayerStyle(color: PlatePalette::ACCENT, weight: 1.2, fill: true, fillColor: PlatePalette::ACCENT, fillOpacity: 0.25),
            tooltip: 'label',
            labels: false,
        ));
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.nino',
            label: 'Niño 3.4 region',
            features: self::collection([self::box(190, -5, 240, 5, 'Niño 3.4 region, 5° N–5° S, 170°–120° W')]),
            swatch: PlatePalette::FAIL,
            shape: LayerShape::Line,
            group: self::GROUP,
            style: new LayerStyle(color: PlatePalette::FAIL, weight: 1.4, fill: false, dashArray: $nino ? null : '4 4'),
            tooltip: 'label',
            labels: false,
        ));

        // ---- the winds and the loops
        $trades = [];
        foreach ([270, 240, 210, 180, 150] as $x) {
            $trades[] = self::line([[$x, -13], [$x - 16, -13]], $nino ? 'trade winds, weakened' : 'trade winds, east to west at the surface');
        }
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.trades',
            label: 'Trade winds at the surface',
            features: self::collection($trades),
            swatch: PlatePalette::DIM,
            shape: LayerShape::Line,
            group: self::GROUP,
            style: new LayerStyle(color: PlatePalette::DIM, weight: $nino ? 1.0 : 1.8, dashArray: $nino ? '2 5' : '5 4'),
            tooltip: 'label',
            labels: false,
        ));
        $rise = $nino ? 202 : 120;
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.pacific',
            label: 'Pacific Walker cell, flow aloft',
            features: self::collection([self::line([[$rise, 8], [($rise + 272) / 2, 9], [272, 8]], $nino ? 'Pacific cell shifted east: rises over the central Pacific' : 'Pacific Walker cell: rises over Indonesia, sinks over the eastern Pacific')]),
            swatch: PlatePalette::FAIL,
            shape: LayerShape::Line,
            group: self::GROUP,
            style: new LayerStyle(color: PlatePalette::FAIL, weight: $nino ? 2.0 : 3.0),
            tooltip: 'label',
            labels: false,
        ));
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.indian',
            label: 'Indian Ocean cell, flow aloft',
            features: self::collection([self::line([[118, 8], [100, 9], [80, 9], [60, 9], [44, 8]], $normal ? 'Indian Ocean cell: rises over Indonesia, sinks over East Africa' : 'Indian Ocean cell, weakened')]),
            swatch: PlatePalette::ACCENT,
            shape: LayerShape::Line,
            group: self::GROUP,
            style: new LayerStyle(color: PlatePalette::ACCENT, weight: $normal ? 3.0 : 1.6, dashArray: $normal ? null : '4 5'),
            tooltip: 'label',
            labels: false,
        ));
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.moist',
            label: 'Moist air towards the coast',
            features: self::collection($normal ? [] : [self::line([[70, -4], [60, -5], [50, -5], [43, -4]], 'moist air from the warm water towards the coast')]),
            swatch: PlatePalette::ACCENT,
            shape: LayerShape::Line,
            group: self::GROUP,
            visible: !$normal,
            style: new LayerStyle(color: PlatePalette::ACCENT, weight: 2.6),
            tooltip: 'label',
            labels: false,
        ));

        // ---- where air rises with rain, and where it sinks dry
        $clouds = [];
        $dry = [];
        if ($normal) {
            $clouds[] = self::point(120, 2, 'rising air over the warm pool: heavy rain over Indonesia');
            $dry[] = self::point(40, 1, 'sinking, dry, over East Africa');
            $dry[] = self::point(272, 1, 'sinking over the cool eastern Pacific, dry coast');
        }
        if ($nino) {
            $clouds[] = self::point(202, 2, 'the rising branch moves east: rain over the central Pacific');
            $clouds[] = self::point(38, 0, 'short rains enhanced over East Africa');
            $dry[] = self::point(120, 1, 'Indonesia turns dry');
        }
        if ($dipole) {
            $clouds[] = self::point(120, 2, 'rain over Indonesia, weaker');
            $clouds[] = self::point(60, 0, 'convection over the warm western Indian Ocean');
            $clouds[] = self::point(38, 0, 'short rains enhanced over East Africa');
            $dry[] = self::point(100, -4, 'cool eastern Indian Ocean: sinking, dry off Sumatra');
            $dry[] = self::point(272, 1, 'sinking over the eastern Pacific');
        }
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.rise',
            label: 'Rising air, clouds and rain',
            features: self::collection($clouds),
            swatch: PlatePalette::ACCENT,
            shape: LayerShape::Point,
            group: self::GROUP,
            style: new LayerStyle(color: PlatePalette::ACCENT, fillColor: PlatePalette::ACCENT, radius: 9.0, weight: 2.0, fillOpacity: 0.55),
            tooltip: 'label',
            labels: false,
        ));
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.sink',
            label: 'Sinking air, dry',
            features: self::collection($dry),
            swatch: PlatePalette::WARN,
            shape: LayerShape::Point,
            group: self::GROUP,
            style: new LayerStyle(color: PlatePalette::WARN, fillColor: PlatePalette::WARN, radius: 7.0, weight: 2.0, fillOpacity: 0.7),
            tooltip: 'label',
            labels: false,
        ));

        // ---- the names on the map
        $labels = [
            self::point(112, 16, 'Indonesia'),
            self::point(30, -14, 'East Africa'),
            self::point(290, -14, 'South America'),
            self::point(75, -21, 'INDIAN OCEAN'),
            self::point(205, 20, 'PACIFIC OCEAN'),
            self::point(215, -8, 'Niño 3.4'),
        ];
        if ($nino) {
            $labels[] = self::point(202, 15, 'rising moves east');
        }
        $map->addLayer(new GeoJsonLayer(
            id: 'circ.labels',
            label: 'Labels',
            features: self::collection($labels),
            swatch: PlatePalette::DIM,
            shape: LayerShape::Point,
            group: self::GROUP,
            style: new LayerStyle(radius: 0.1, weight: 0.0, fillOpacity: 0.0, opacity: 0.0),
            labels: true,
        ));

        return $map;
    }

    /** @return array<string, mixed> */
    private static function polygon(float $west, float $south, float $east, float $north): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]]];
    }

    /** @return array<string, mixed> */
    private static function box(float $west, float $south, float $east, float $north, string $label): array
    {
        return ['type' => 'Feature', 'properties' => ['label' => $label], 'geometry' => self::polygon($west, $south, $east, $north)];
    }

    /**
     * @param list<array{0: float|int, 1: float|int}> $points lon, lat
     *
     * @return array<string, mixed>
     */
    private static function line(array $points, string $label): array
    {
        return ['type' => 'Feature', 'properties' => ['label' => $label], 'geometry' => ['type' => 'LineString', 'coordinates' => array_map(static fn (array $p): array => [(float) $p[0], (float) $p[1]], $points)]];
    }

    /** @return array<string, mixed> */
    private static function point(float $lon, float $lat, string $label): array
    {
        return ['type' => 'Feature', 'properties' => ['label' => $label], 'geometry' => ['type' => 'Point', 'coordinates' => [$lon, $lat]]];
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private static function collection(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
