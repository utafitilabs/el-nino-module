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

namespace Uhifadhi\ElNino\Overview;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayer;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayerProviderInterface;
use Uhifadhi\Contracts\Atlas\PlatePalette;
use Uhifadhi\ElNino\Entity\SusceptibilityPatch;
use Uhifadhi\ElNino\Model\GridCells;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;
use Uhifadhi\ElNino\Service\WatchReader;

/**
 * WHAT THIS MODULE PUTS ON THE AREA'S OWN MAP: the very-high susceptibility
 * patches, the roads likely cut, the destinations that lose access, and the
 * cells that were much wetter than normal in the last window — each with a
 * legend row, so the area page draws them like anybody else's layers.
 */
final readonly class ElNinoMapLayers implements MapLayerProviderInterface
{
    public const string GROUP = 'El Niño watch';

    public function __construct(
        private WatchReader $reader,
    ) {
    }

    public function moduleSlug(): string
    {
        return ElNinoModuleProvider::SLUG;
    }

    public function mapLayersFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $reading = $this->reader->read($area);
        if (null === $reading) {
            return [];
        }
        $layers = [];
        $run = $reading->run;
        if (null !== $run) {
            $patches = [];
            foreach ($run->getPatches() as $patch) {
                if (SusceptibilityPatch::VERY_HIGH !== $patch->getClass()) {
                    continue;
                }
                $geometry = self::decode($patch->getGeom());
                if (null !== $geometry) {
                    $patches[] = self::feature($geometry, ['label' => 'Very high susceptibility']);
                }
            }
            $layers[] = new MapLayer('el-nino.very-high', ElNinoModuleProvider::SLUG, self::GROUP, 'Very high susceptibility', PlatePalette::FAIL, self::collection($patches), MapLayer::STYLE_FILL, null, on: false);

            $roads = [];
            foreach ($run->getRoads() as $road) {
                if (!$road->isCut()) {
                    continue;
                }
                $geometry = self::decode($road->getGeom());
                if (null !== $geometry) {
                    $roads[] = self::feature($geometry, ['label' => 'Road likely cut · '.($road->getHighway() ?? 'road')]);
                }
            }
            $layers[] = new MapLayer('el-nino.roads-cut', ElNinoModuleProvider::SLUG, self::GROUP, 'Roads likely cut', PlatePalette::FAIL, self::collection($roads), MapLayer::STYLE_LINE, \count($roads));

            $places = [];
            foreach ($reading->cutOff as $destination) {
                $geometry = self::decode($destination->getGeom());
                if (null !== $geometry) {
                    $places[] = self::feature($geometry, [
                        'label' => $destination->getName(),
                        'wet' => $reading->isHot($destination),
                    ]);
                }
            }
            $layers[] = new MapLayer('el-nino.cut-off', ElNinoModuleProvider::SLUG, self::GROUP, 'Cut off if it floods', PlatePalette::FAIL, self::collection($places), MapLayer::STYLE_FILL, \count($places));
        }

        $bulletin = $reading->bulletin;
        $wet = GridCells::wetCells($bulletin->getGrid(), $bulletin->getRecentGridPct(), $bulletin->getWetThresholdPct());
        $layers[] = new MapLayer(
            'el-nino.wet-cells',
            ElNinoModuleProvider::SLUG,
            self::GROUP,
            \sprintf('Rain ≥ %d %% of normal', $bulletin->getWetThresholdPct()),
            PlatePalette::ACCENT,
            $wet,
            MapLayer::STYLE_FILL,
            \count($wet['features']),
        );

        return $layers;
    }

    /**
     * @param array<string, mixed> $geometry
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private static function feature(array $geometry, array $properties): array
    {
        return ['type' => 'Feature', 'properties' => $properties, 'geometry' => $geometry];
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return array<string, mixed>
     */
    private static function collection(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
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
