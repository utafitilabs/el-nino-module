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

namespace Uhifadhi\ElNino\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Chartjs\ChartjsBundle;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\Map\UXMapBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\AreaBundle\AreaBundle;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\TeamBundle;
use Uhifadhi\Contracts\Queue\AsyncMessageInterface;
use Uhifadhi\ElNino\Tests\Integration\Fixtures\CollectedContentProviders;
use Uhifadhi\ElNino\Tests\Integration\Fixtures\FixedWatchVoter;
use Uhifadhi\ElNino\UhifadhiElNinoBundle;
use UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * THE SMALLEST REAL INSTALLATION THAT RUNS THIS MODULE: the actual core
 * bundles on a real PostGIS database, with a security block so permission
 * checks go through the real authorization checker.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new StimulusBundle();
        yield new UXIconsBundle();
        yield new UXMapBundle();
        yield new ChartjsBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new UtafitiLabsPostGISBundle();
        yield new SecurityBundle();
        yield new RegistryBundle();
        yield new ShellBundle();
        yield new AtlasBundle();
        yield new TeamBundle();
        yield new AreaBundle();
        yield new UhifadhiElNinoBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => ['enabled' => true],
            // The ingest limiter counts in cache.app; in memory, a suite run starts the minute afresh.
            'cache' => ['app' => 'cache.adapter.array'],
            'property_access' => true,
            'property_info' => ['enabled' => true],
            'serializer' => ['enabled' => true],
            'validation' => ['enabled' => true],
            'assets' => true,
            'asset_mapper' => [
                'paths' => [__DIR__.'/Fixtures/app/assets' => ''],
            ],
            'messenger' => [
                'failure_transport' => 'failed',
                'transports' => [
                    'async' => ['dsn' => 'doctrine://default?auto_setup=0', 'options' => ['queue_name' => 'async']],
                    'failed' => ['dsn' => 'doctrine://default?auto_setup=0', 'options' => ['queue_name' => 'failed']],
                ],
                'routing' => [AsyncMessageInterface::class => 'async'],
            ],
        ]);

        $container->extension('security', [
            'password_hashers' => [
                'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
            'providers' => [
                'team_user_provider' => ['entity' => ['class' => User::class, 'property' => 'email']],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'team_user_provider',
                    'user_checker' => 'team.user_checker',
                ],
            ],
            'access_control' => [
                ['path' => '^/el-nino/ingest$', 'roles' => 'PUBLIC_ACCESS'],
            ],
        ]);

        $container->services()->set(FixedWatchVoter::class)->tag('security.voter');
        $container->services()->set(CollectedContentProviders::class)
            ->args([tagged_iterator('uhifadhi.devkit.content_provider')])->public();
        $container->services()->alias('test_public.devkit.content_providers', CollectedContentProviders::class)->public();

        $container->extension('doctrine', [
            'dbal' => [
                'url' => '%env(EL_NINO_TEST_DATABASE_URL)%',
            ],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
            ],
        ]);

        $container->extension('ux_icons', [
            'iconify' => ['on_demand' => false],
        ]);
        $container->extension('ux_map', ['renderer' => 'leaflet://default']);

        foreach ([
            \Uhifadhi\ElNino\Service\WatchService::class => 'el_nino.watches',
            \Uhifadhi\ElNino\Service\BulletinIngestService::class => 'el_nino.ingest',
            \Uhifadhi\ElNino\Service\ProducerPayloadReader::class => 'el_nino.payload_reader',
            \Uhifadhi\ElNino\Service\WatchReader::class => 'el_nino.watch_reader',
            \Uhifadhi\ElNino\Service\ExposureMapService::class => 'el_nino.exposure_map',
            \Uhifadhi\ElNino\Overview\ElNinoNowTiles::class => 'el_nino.overview.now_tiles',
            \Uhifadhi\ElNino\Overview\ElNinoAttention::class => 'el_nino.overview.attention',
            \Uhifadhi\ElNino\Overview\ElNinoMapLayers::class => 'el_nino.overview.map_layers',
            \Uhifadhi\ElNino\Devkit\ElNinoCommandProvider::class => 'el_nino.devkit.commands',
            \Uhifadhi\ElNino\Devkit\ElNinoContentProvider::class => 'el_nino.devkit.content',
            \Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService::class => 'registry.area_modules',
            \Uhifadhi\Bundle\RegistryBundle\Service\RegistrySyncService::class => 'registry.sync',
            \Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue::class => 'registry.catalogue',
            \Uhifadhi\Bundle\TeamBundle\Access\ConcernCatalogue::class => 'team.access.catalogue',
        ] as $class => $serviceId) {
            $container->services()->alias('test_public.'.$class, $serviceId)->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2).'/src/Controller/', 'attribute');
        $routes->import('@ShellBundle/Controller/', 'attribute');
        $routes->import('@AreaBundle/Controller/', 'attribute');
        $routes->import(ShellBundle::CONFIGURE_ROUTES);
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/Fixtures/app';
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/el-nino-module-tests/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/el-nino-module-tests/log/'.$this->environment;
    }
}
