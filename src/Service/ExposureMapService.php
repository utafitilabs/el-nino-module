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
use Uhifadhi\Bundle\AreaBundle\Service\AreaMapPayload;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\AtlasBundle\Model\AtlasMap;
use Uhifadhi\Bundle\AtlasBundle\Model\FeaturePopup;
use Uhifadhi\Bundle\AtlasBundle\Model\GeoJsonLayer;
use Uhifadhi\Bundle\AtlasBundle\Model\Ground;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerShape;
use Uhifadhi\Bundle\AtlasBundle\Model\LayerStyle;
use Uhifadhi\Bundle\AtlasBundle\Model\LegendItem;
use Uhifadhi\Bundle\AtlasBundle\Model\StyleRule;
use Uhifadhi\Contracts\Atlas\PlatePalette;
use Uhifadhi\ElNino\Entity\ExposureRun;
use Uhifadhi\ElNino\Entity\Road;
use Uhifadhi\ElNino\Entity\SusceptibilityPatch;
use Uhifadhi\ElNino\Entity\Week;
use Uhifadhi\ElNino\Model\GridCells;
use Uhifadhi\ElNino\Model\RainBand;
use Uhifadhi\ElNino\Model\WatchReading;

/**
 * THE MAPS, STATED: the exposure plate of the overview and the rain plate of
 * a week, each as an atlas map the plate draws. The ground is the area's own;
 * every colour is a plate token, so the plates read right in light, in dark
 * and on imagery like every other map in the product.
 */
final class ExposureMapService
{
    public const string GROUP_EXPOSURE = 'If it floods';
    public const string GROUP_RAIN = 'Rain';

    /** The legend's name for each susceptibility class, by class number. */
    private const array CLASS_LABELS = [4 => 'Very high', 3 => 'High', 2 => 'Medium', 1 => 'Low'];

    public function __construct(
        private readonly MapBuilderInterface $maps,
        private readonly AreaMapPayload $ground,
    ) {
    }

    /** The overview plate: susceptibility, flagged roads, destinations, and this window's wet cells. */
    public function exposure(AreaOfInterest $area, WatchReading $reading): AtlasMap
    {
        $map = $this->maps->createMap();
        $ground = $this->ground->forArea($area);
        $map->ground(Ground::fromGeoJson($ground['boundary'], $ground['zones'], scrim: true));

        $run = $reading->run;
        if (null !== $run) {
            $map->addLayer($this->susceptibilityLayer($run));
            foreach (array_reverse(self::CLASS_LABELS, true) as $class => $label) {
                if (4 === $class) {
                    continue;
                }
                $map->addLegendItem(new LegendItem(
                    label: $label,
                    swatch: self::classSwatch($class),
                    group: self::GROUP_EXPOSURE,
                    note: 'susceptibility',
                ));
            }
            $map->addLayer($this->roadsLayer($run, Road::CUT, 'Road likely cut', PlatePalette::FAIL, 2.6));
            $map->addLayer($this->roadsLayer($run, Road::RISK, 'Road at risk', PlatePalette::WARN, 1.8));
            $map->addLayer($this->destinationsLayer($run, $reading));
        }
        $map->addLayer($this->wetCellsLayer($reading));

        return $map;
    }

    /** A week's rain as cells, with the cut-off destinations over it. */
    public function week(AreaOfInterest $area, WatchReading $reading, Week $week, bool $millimetres): AtlasMap
    {
        $map = $this->maps->createMap();
        $ground = $this->ground->forArea($area);
        $map->ground(Ground::fromGeoJson($ground['boundary'], $ground['zones'], scrim: false));
        $bulletin = $reading->bulletin;
        $threshold = $bulletin->getWetThresholdPct();
        $cells = GridCells::featureCollection($bulletin->getGrid(), $week->getGridMm(), $week->getGridPct(), $threshold);
        $rules = [];
        foreach (RainBand::ordered() as $band) {
            $rules[] = StyleRule::when('band', $band->value)
                ->fillColor($band->swatch())
                ->fillOpacity($millimetres ? 0.3 + 0.4 * $band->fillOpacity() : $band->fillOpacity());
        }
        $map->addLayer(new GeoJsonLayer(
            id: 'el-nino.rain',
            label: $millimetres ? 'Rain, mm' : 'Rain, % of normal',
            features: $cells,
            swatch: PlatePalette::ACCENT,
            shape: LayerShape::Fill,
            count: null,
            group: self::GROUP_RAIN,
            style: new LayerStyle(color: PlatePalette::DIM, weight: 0.4, opacity: 0.5, fill: true, fillOpacity: 0.2),
            rules: $rules,
            tooltip: 'label',
            labels: false,
        ));
        foreach (RainBand::ordered() as $band) {
            $map->addLegendItem(new LegendItem(label: $band->legendLabel($threshold), swatch: $band->swatch(), group: self::GROUP_RAIN));
        }
        $run = $reading->run;
        if (null !== $run) {
            $map->addLayer($this->destinationsLayer($run, $reading, $week->getHot()));
        }
        $bounds = GridCells::bounds($bulletin->getGrid());
        if (null !== $bounds) {
            $map->fitTo($bounds);
        }

        return $map;
    }

    private function susceptibilityLayer(ExposureRun $run): GeoJsonLayer
    {
        $features = [];
        foreach ($run->getPatches() as $patch) {
            $geometry = self::decode($patch->getGeom());
            if (null === $geometry) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'properties' => ['class' => $patch->getClass(), 'label' => ucfirst($patch->getName()).' susceptibility'],
                'geometry' => $geometry,
            ];
        }
        $rules = [];
        foreach (self::CLASS_LABELS as $class => $label) {
            $rules[] = StyleRule::when('class', $class)
                ->fillColor(self::classSwatch($class))
                ->fillOpacity(SusceptibilityPatch::VERY_HIGH === $class ? 0.55 : (3 === $class ? 0.35 : (2 === $class ? 0.2 : 0.08)));
        }

        return new GeoJsonLayer(
            id: 'el-nino.susceptibility',
            label: self::CLASS_LABELS[4],
            features: ['type' => 'FeatureCollection', 'features' => $features],
            swatch: self::classSwatch(4),
            shape: LayerShape::Fill,
            count: null,
            group: self::GROUP_EXPOSURE,
            style: new LayerStyle(weight: 0.0, fill: true),
            rules: $rules,
            tooltip: 'label',
            labels: false,
        );
    }

    private function roadsLayer(ExposureRun $run, string $cls, string $label, string $swatch, float $weight): GeoJsonLayer
    {
        $features = [];
        foreach ($run->getRoads() as $road) {
            if ($road->getCls() !== $cls) {
                continue;
            }
            $geometry = self::decode($road->getGeom());
            if (null === $geometry) {
                continue;
            }
            $metres = $road->getRunVeryHighM();
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'label' => \sprintf('%s · %s%s', $label, $road->getHighway() ?? 'road', null === $metres ? '' : \sprintf(' · %d m through very high', $metres)),
                ],
                'geometry' => $geometry,
            ];
        }

        return new GeoJsonLayer(
            id: 'el-nino.roads.'.$cls,
            label: $label,
            features: ['type' => 'FeatureCollection', 'features' => $features],
            swatch: $swatch,
            shape: LayerShape::Line,
            count: \count($features),
            group: self::GROUP_EXPOSURE,
            style: new LayerStyle(color: $swatch, weight: $weight, opacity: 0.95),
            tooltip: 'label',
            labels: false,
        );
    }

    /**
     * @param list<string>|null $hot the names in a wet cell, where a week's own list replaces the window's
     */
    private function destinationsLayer(ExposureRun $run, WatchReading $reading, ?array $hot = null): GeoJsonLayer
    {
        $hot ??= $reading->hot;
        $features = [];
        $count = 0;
        foreach ($run->getDestinations() as $destination) {
            if (!$destination->isCutOff()) {
                continue;
            }
            $geometry = self::decode($destination->getGeom());
            if (null === $geometry) {
                continue;
            }
            ++$count;
            $wet = \in_array($destination->getName(), $hot, true);
            $people = $destination->getPopulation();
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'name' => $destination->getName(),
                    'label' => $destination->getName(),
                    'kind' => $destination->getKind() ?? 'place',
                    'wet' => $wet,
                    'detail' => \sprintf(
                        'Cut off once the flagged roads go%s%s',
                        null === $people ? '' : \sprintf(' · about %s people within 1.5 km', number_format($people)),
                        $wet ? ' · in a wet cell now' : '',
                    ),
                ],
                'geometry' => $geometry,
            ];
        }

        return new GeoJsonLayer(
            id: 'el-nino.destinations',
            label: 'Cut off once the flagged roads go',
            features: ['type' => 'FeatureCollection', 'features' => $features],
            swatch: PlatePalette::FAIL,
            shape: LayerShape::Point,
            count: $count,
            group: self::GROUP_EXPOSURE,
            style: new LayerStyle(color: PlatePalette::FAIL, fillColor: PlatePalette::FAIL, radius: 6.0, weight: 1.5, fillOpacity: 0.9),
            rules: [StyleRule::when('wet', true)->radius(8.0)->color(PlatePalette::ACCENT)->weight(2.5)],
            popup: new FeaturePopup('name', ['kind', 'detail']),
            labels: false,
        );
    }

    private function wetCellsLayer(WatchReading $reading): GeoJsonLayer
    {
        $bulletin = $reading->bulletin;
        $cells = GridCells::wetCells($bulletin->getGrid(), $bulletin->getRecentGridPct(), $bulletin->getWetThresholdPct());

        return new GeoJsonLayer(
            id: 'el-nino.wet',
            label: \sprintf('Rain ≥ %d %% of normal, last %d days', $bulletin->getWetThresholdPct(), $bulletin->getRainDays()),
            features: $cells,
            swatch: PlatePalette::ACCENT,
            shape: LayerShape::Fill,
            count: \count($cells['features']),
            group: self::GROUP_RAIN,
            style: new LayerStyle(color: PlatePalette::ACCENT, weight: 2.0, opacity: 0.95, fill: true, fillColor: PlatePalette::ACCENT, fillOpacity: 0.18),
            tooltip: 'label',
            labels: false,
        );
    }

    private static function classSwatch(int $class): string
    {
        return match ($class) {
            4 => PlatePalette::FAIL,
            3 => PlatePalette::WARN,
            2 => PlatePalette::ACCENT,
            default => PlatePalette::DIM,
        };
    }

    /** @return array<string, mixed>|null */
    private static function decode(string $geoJson): ?array
    {
        try {
            $decoded = json_decode($geoJson, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!\is_array($decoded) || !\is_string($decoded['type'] ?? null)) {
            return null;
        }
        $out = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
