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
use Uhifadhi\Bundle\AreaBundle\Overview\NowTile;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTileProviderInterface;
use Uhifadhi\ElNino\Model\EnsoWords;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;
use Uhifadhi\ElNino\Service\WatchReader;

/**
 * THE FOUR QUESTIONS AS FOUR TILES on the area overview: how warm the Pacific
 * is, whether it lasts into the short rains, how wet the last weeks were, and
 * who gets cut off if it floods. Nothing where the area has no bulletin.
 */
final readonly class ElNinoNowTiles implements NowTileProviderInterface
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

    public function nowTilesFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $reading = $this->reader->read($area);
        if (null === $reading) {
            return [];
        }
        $b = $reading->bulletin;
        $url = $this->router->generate('el_nino_watch', ['uuid' => $area->getUuidString()]);
        $tiles = [];

        $anomaly = $b->getEnsoAnomaly();
        $tiles[] = new NowTile(
            'EN·N1',
            self::moduleSlugStatic(),
            'Pacific this week',
            null === $anomaly ? '–' : \sprintf('%s%.1f', $anomaly >= 0 ? '+' : '−', abs($anomaly)),
            \sprintf('%s · %s', $reading->ensoPlain(), $reading->ensoRun()),
            unit: '°C',
            tone: $reading->isElNino() ? NowTile::TONE_HOT : NowTile::TONE_PLAIN,
            url: $url,
            priority: 300,
        );

        $short = $reading->shortRains();
        if (null !== $short) {
            $tiles[] = new NowTile(
                'EN·N2',
                self::moduleSlugStatic(),
                'Lasts into the short rains',
                (string) $short['el_nino'],
                \sprintf('%s · %s in %s · %s', EnsoWords::chance($short['el_nino']), EnsoWords::EL_NINO, EnsoWords::season($short['season']), $reading->outlook->issued ?? 'IRI'),
                unit: '%',
                tone: $short['el_nino'] >= 50 ? NowTile::TONE_HOT : NowTile::TONE_PLAIN,
                url: $url,
                priority: 310,
            );
        }

        $pct = $b->getRainPct();
        $tiles[] = new NowTile(
            'EN·N3',
            self::moduleSlugStatic(),
            \sprintf('Rain, last %d days', $b->getRainDays()),
            null === $pct ? '–' : (string) (int) round($pct),
            \sprintf('%s · %s mm of %s mm', $reading->rainWords(), self::mm($b->getRainMm()), self::mm($b->getRainNormalMm())),
            unit: '% of normal',
            tone: null !== $pct && $pct >= $b->getWetThresholdPct() ? NowTile::TONE_HOT : NowTile::TONE_PLAIN,
            url: $url,
            priority: 320,
        );

        $hot = $reading->hotCount();
        $cutOff = \count($reading->cutOff);
        if ($cutOff > 0 || $hot > 0) {
            $tiles[] = new NowTile(
                'EN·N4',
                self::moduleSlugStatic(),
                'Cut off if it floods',
                (string) $cutOff,
                $reading->cutOffPeople > 0 ? \sprintf('about %s people', number_format($reading->cutOffPeople)) : 'destinations in the analysis',
                unit: 'destinations',
                alarm: $hot > 0 ? \sprintf('%d in a wet cell now', $hot) : null,
                tone: $hot > 0 ? NowTile::TONE_BAD : NowTile::TONE_PLAIN,
                url: $url,
                priority: 330,
            );
        }

        return $tiles;
    }

    private static function moduleSlugStatic(): string
    {
        return ElNinoModuleProvider::SLUG;
    }

    private static function mm(?float $value): string
    {
        return null === $value ? '–' : (string) (int) round($value);
    }
}
