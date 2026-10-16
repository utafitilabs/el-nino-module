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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Uhifadhi\Bundle\AreaBundle\Overview\AttentionProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayerProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTileProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AtlasBundle\Map\MapBuilderInterface;
use Uhifadhi\Bundle\ShellBundle\Contract\StylesheetSourceInterface;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;
use Uhifadhi\Contracts\Shell\ModuleTabsInterface;
use Uhifadhi\ElNino\Access\ElNinoConcerns;
use Uhifadhi\ElNino\Devkit\ElNinoCommandProvider;
use Uhifadhi\ElNino\Devkit\ElNinoContentProvider;
use Uhifadhi\ElNino\Overview\ElNinoAttention;
use Uhifadhi\ElNino\Overview\ElNinoMapLayers;
use Uhifadhi\ElNino\Overview\ElNinoNowTiles;
use Uhifadhi\ElNino\Repository\AreaWatchRepository;
use Uhifadhi\ElNino\Repository\BulletinRepository;
use Uhifadhi\ElNino\Repository\ExposureRunRepository;
use Uhifadhi\ElNino\Service\BulletinIngestService;
use Uhifadhi\ElNino\Service\ChartService;
use Uhifadhi\ElNino\Service\CirculationService;
use Uhifadhi\ElNino\Service\ExposureMapService;
use Uhifadhi\ElNino\Service\ProducerPayloadReader;
use Uhifadhi\ElNino\Service\WatchReader;
use Uhifadhi\ElNino\Service\WatchService;
use Uhifadhi\ElNino\Service\WeeksService;
use Uhifadhi\ElNino\Shell\ElNinoConfigurationSections;
use Uhifadhi\ElNino\Shell\ElNinoModuleTabs;
use Uhifadhi\ElNino\Shell\ElNinoStylesheets;
use Uhifadhi\ElNino\Twig\ElNinoExtension;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * installations, and FQCN references stay refactor-safe and phpstan-checked.
 * Imported by UhifadhiElNinoBundle::loadExtension(), which keeps only the
 * config-driven definitions and the controllers behind the SecurityBundle guard.
 *
 * Everything below is defined EXPLICITLY — no autowire(), no autoconfigure(),
 * ids prefixed with the bundle alias — because this bundle is installed by
 * other projects via Composer, which is what Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 *
 * Repositories keep their class name as id: ContainerRepositoryFactory looks a
 * repository up by class, and the prefix would hide it from Doctrine.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // ---- repositories (FQCN ids, by Doctrine's rule)
    foreach ([AreaWatchRepository::class, BulletinRepository::class, ExposureRunRepository::class] as $repository) {
        $services->set($repository)
            ->args([service('doctrine')])
            ->tag('doctrine.repository_service');
    }

    // ---- the producer's files, read and stored
    $services->set('el_nino.payload_reader', ProducerPayloadReader::class);

    $services->set('el_nino.ingest', BulletinIngestService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(BulletinRepository::class),
            service(ExposureRunRepository::class),
        ]);

    $services->set('el_nino.watches', WatchService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(AreaWatchRepository::class),
        ]);

    // ---- the reading side
    $services->set('el_nino.watch_reader', WatchReader::class)
        ->args([
            service(AreaWatchRepository::class),
            service(BulletinRepository::class),
            service(ExposureRunRepository::class),
        ]);

    $services->set('el_nino.charts', ChartService::class);

    $services->set('el_nino.exposure_map', ExposureMapService::class)
        ->args([
            service(MapBuilderInterface::class),
            service('area.map_payload'),
        ]);

    $services->set('el_nino.circulation', CirculationService::class)
        ->args([service(MapBuilderInterface::class)]);

    $services->set('el_nino.weeks', WeeksService::class)
        ->args([param('el_nino.weeks_shown')]);

    // ---- the page's words, from Twig
    $services->set('el_nino.twig', ElNinoExtension::class)
        ->tag('twig.extension');

    // ---- what this module declares to the platform, each tag applied by hand
    $services->set('el_nino.access.concerns', ElNinoConcerns::class)
        ->tag(ConcernSourceInterface::TAG);

    $services->set('el_nino.shell.tabs', ElNinoModuleTabs::class)
        ->tag(ModuleTabsInterface::TAG);

    $services->set('el_nino.shell.stylesheets', ElNinoStylesheets::class)
        ->tag(StylesheetSourceInterface::TAG);

    $services->set('el_nino.shell.configuration', ElNinoConfigurationSections::class)
        ->args([
            service('request_stack'),
            service(AreaOfInterestRepository::class),
            service('el_nino.watches'),
            service('security.csrf.token_manager')->nullOnInvalid(),
        ])
        ->tag(ConfigurationSectionsInterface::TAG);

    // ---- what it puts on the area's overview
    $services->set('el_nino.overview.now_tiles', ElNinoNowTiles::class)
        ->args([service('el_nino.watch_reader'), service('router')])
        ->tag(NowTileProviderInterface::TAG);

    $services->set('el_nino.overview.attention', ElNinoAttention::class)
        ->args([service('el_nino.watch_reader'), service('router')])
        ->tag(AttentionProviderInterface::TAG);

    $services->set('el_nino.overview.map_layers', ElNinoMapLayers::class)
        ->args([service('el_nino.watch_reader')])
        ->tag(MapLayerProviderInterface::TAG);

    // ---- dev-only machinery: inert providers the devkit collects. The tags are
    //      literal strings because the devkit is absent in production and a
    //      module cannot reference its constants.
    $services->set('el_nino.devkit.content', ElNinoContentProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(AreaOfInterestRepository::class),
            service('el_nino.watches'),
            service('el_nino.ingest'),
            service('clock')->nullOnInvalid(),
        ])
        ->tag('uhifadhi.devkit.content_provider');

    $services->set('el_nino.devkit.commands', ElNinoCommandProvider::class)
        ->args([
            service(AreaOfInterestRepository::class),
            service('el_nino.watches'),
            service('el_nino.payload_reader'),
            service('el_nino.ingest'),
        ])
        ->tag('uhifadhi.devkit.command_provider');
};
