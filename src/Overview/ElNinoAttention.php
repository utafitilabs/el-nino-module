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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionItem;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionSeverity;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;
use Uhifadhi\ElNino\Service\WatchReader;

/**
 * ONE ATTENTION ITEM PER CUT-OFF DESTINATION SITTING IN A WET CELL THIS
 * WINDOW — the one combination on the page that is about now rather than
 * about a season. Where the producer routed households, the item counts the
 * village's cut-off bomas; where it did not, the people within reach.
 */
final readonly class ElNinoAttention implements AttentionProviderInterface
{
    public function __construct(
        private WatchReader $reader,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function moduleSlug(): string
    {
        return ElNinoModuleProvider::SLUG;
    }

    public function attentionFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $reading = $this->reader->read($area);
        if (null === $reading || [] === $reading->hot) {
            return [];
        }
        $bulletin = $reading->bulletin;
        $age = max(0, $now->getTimestamp() - $bulletin->getBuiltAt()->getTimestamp());
        $url = $this->router->generate('el_nino_watch', ['uuid' => $area->getUuidString()]);
        $run = $reading->run;
        $bomasByVillage = [];
        $zoneByVillage = [];
        if (null !== $run) {
            foreach ($run->getBomas() as $boma) {
                $village = $boma->getVillage();
                if (null === $village || !$boma->isCutOff()) {
                    continue;
                }
                $bomasByVillage[$village] = ($bomasByVillage[$village] ?? 0) + 1;
                $zoneByVillage[$village] ??= $boma->getZone();
            }
        }
        $items = [];
        foreach ($reading->cutOff as $destination) {
            if (!$reading->isHot($destination)) {
                continue;
            }
            $name = $destination->getName();
            $bomas = $bomasByVillage[$name] ?? null;
            $meta = [];
            if (null !== $destination->getKind()) {
                $meta[] = $destination->getKind();
            }
            if (null !== $bomas) {
                $meta[] = \sprintf('%d bomas cut off', $bomas);
            } elseif (null !== $destination->getPopulation()) {
                $meta[] = \sprintf('about %s people', number_format($destination->getPopulation()));
            }
            $zone = $zoneByVillage[$name] ?? null;
            if (null !== $zone) {
                $meta[] = $zone.' zone';
            }
            $items[] = new AttentionItem(
                AttentionSeverity::Soon,
                ElNinoModuleProvider::SLUG,
                'El Niño watch',
                \sprintf('%s is in a much-wetter-than-normal cell this week.', $name),
                'cut-off destination',
                self::ageLabel($age),
                $age,
                $url,
                \sprintf(
                    'It loses road access once the flagged roads flood, and the rain over it was at or above %d %% of normal between %s and %s.',
                    $bulletin->getWetThresholdPct(),
                    $bulletin->getRainStart()?->format('j M') ?? '?',
                    $bulletin->getRainEnd()?->format('j M') ?? '?',
                ),
                $meta,
            );
        }

        return $items;
    }

    private static function ageLabel(int $seconds): string
    {
        if ($seconds < 3600) {
            return \sprintf('%d min', intdiv($seconds, 60));
        }
        if ($seconds < 86400) {
            return \sprintf('%d h', intdiv($seconds, 3600));
        }

        return \sprintf('%d d', intdiv($seconds, 86400));
    }
}
