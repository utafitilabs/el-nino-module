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

namespace Uhifadhi\ElNino\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\ElNino\Model\Circulation;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;
use Uhifadhi\ElNino\Service\ChartService;
use Uhifadhi\ElNino\Service\CirculationService;
use Uhifadhi\ElNino\Service\ExposureMapService;
use Uhifadhi\ElNino\Service\WatchReader;
use Uhifadhi\ElNino\Service\WeeksService;

/**
 * THE TWO DATA PLACES OF THE WATCH: the overview, and the week-by-week walk.
 *
 * Extends nothing and takes its collaborators explicitly, patterned on
 * FrameworkBundle's TemplateController. Every route names the pair it
 * enforces and stamps itself with the module, so a parked area answers 404.
 */
#[Route(defaults: [RegistryBundle::MODULE_ROUTE_DEFAULT => ElNinoModuleProvider::SLUG])]
final readonly class WatchController
{
    /** The query parameter that picks a week on the week-by-week tab. */
    public const string WEEK_PARAMETER = 'week';

    /** The query parameter that switches the week's map from percent of normal to millimetres. */
    public const string FIELD_PARAMETER = 'field';

    /** The query parameter that picks the state the background plate draws. */
    public const string CIRCULATION_PARAMETER = 'circulation';

    public function __construct(
        private Environment $twig,
        private WatchReader $reader,
        private ExposureMapService $maps,
        private ChartService $charts,
        private WeeksService $weeks,
        private CirculationService $circulation,
        private UrlGeneratorInterface $router,
    ) {
    }

    #[Route('/areas/{uuid}/modules/elnino', name: 'el_nino_watch', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('el-nino.read', subject: 'area')]
    public function watch(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $reading = $this->reader->read($area);
        if (null === $reading) {
            return new Response($this->twig->render('@UhifadhiElNino/watch/empty.html.twig', [
                'area' => $area,
                'watch' => $this->reader->watchOf($area),
            ]));
        }

        $weeks = $this->weeks->weeks($reading->bulletin);
        $state = Circulation::fromQuery($request->query->getString(self::CIRCULATION_PARAMETER));

        return new Response($this->twig->render('@UhifadhiElNino/watch/show.html.twig', [
            'area' => $area,
            'circulation' => $state,
            'circulationStates' => Circulation::cases(),
            'circulationMap' => $this->circulation->plate($state),
            'circulationParameter' => self::CIRCULATION_PARAMETER,
            'reading' => $reading,
            'bulletin' => $reading->bulletin,
            'watch' => $reading->watch,
            'map' => $this->maps->exposure($area, $reading),
            'rainMmMap' => $this->maps->recent($area, $reading, true),
            'rainPctMap' => $this->maps->recent($area, $reading, false),
            'ninoChart' => [] === $weeks ? null : $this->charts->nino($reading->bulletin),
            'outlookChart' => null === $reading->outlook ? null : $this->charts->outlook($reading->outlook),
            'weeksUrl' => $this->router->generate('el_nino_weeks', ['uuid' => $area->getUuidString()]),
        ]));
    }

    #[Route('/areas/{uuid}/modules/elnino/weeks', name: 'el_nino_weeks', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('el-nino.read', subject: 'area')]
    public function weeks(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $reading = $this->reader->read($area);
        if (null === $reading) {
            return new Response($this->twig->render('@UhifadhiElNino/watch/empty.html.twig', [
                'area' => $area,
                'watch' => $this->reader->watchOf($area),
            ]));
        }

        $weeks = $this->weeks->weeks($reading->bulletin);
        $asked = $request->query->has(self::WEEK_PARAMETER) ? $request->query->getInt(self::WEEK_PARAMETER) : null;
        $picked = $this->weeks->pick($weeks, $asked);
        $millimetres = 'mm' === $request->query->getString(self::FIELD_PARAMETER);
        $week = $weeks[$picked] ?? null;

        return new Response($this->twig->render('@UhifadhiElNino/watch/weeks.html.twig', [
            'area' => $area,
            'reading' => $reading,
            'bulletin' => $reading->bulletin,
            'weeks' => $weeks,
            'picked' => $picked,
            'week' => $week,
            'millimetres' => $millimetres,
            'caption' => null === $week ? null : $this->weeks->caption($week, $reading->bulletin->getWetThresholdPct()),
            'weekMap' => null === $week ? null : $this->maps->week($area, $reading, $week, $millimetres),
            'rainChart' => [] === $weeks ? null : $this->charts->weeklyRain($weeks),
            'ninoChart' => [] === $weeks ? null : $this->charts->weeklyNino($weeks),
            'weekParameter' => self::WEEK_PARAMETER,
            'fieldParameter' => self::FIELD_PARAMETER,
        ]));
    }
}
