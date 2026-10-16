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

namespace Uhifadhi\ElNino;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Uhifadhi\ElNino\Controller\IngestController;
use Uhifadhi\ElNino\Controller\ProducerController;
use Uhifadhi\ElNino\Controller\WatchController;
use Uhifadhi\ElNino\DependencyInjection\ElNinoConfiguration;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * THE ONE SYMFONY PLUG OF THE EL NIÑO WATCH MODULE.
 *
 * A reusable bundle, in Symfony's sense: installed by other projects through
 * Composer, so it is wired EXPLICITLY (no autowire, no autoconfigure, ids
 * prefixed with the alias) and it prepends every block it contributes, so the
 * installation keeps the last word on all of it.
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 *
 * Prepended settings "are overridden by any other settings done explicitly
 * inside the config/* files" — the vendor form is prependExtensionConfig() in
 * symfony/ux-map's UXMapBundle and symfony/ux-chartjs's ChartjsExtension.
 */
final class UhifadhiElNinoBundle extends AbstractBundle
{
    /** The module's own sheet, under the path AssetMapper gives a bundle's public/ directory. */
    public const string STYLESHEET = 'bundles/uhifadhielnino/el-nino.css';

    /** The npm-style form of the package name, which is what Flex keys controllers.json by. */
    public const string ASSET_NAMESPACE = '@uhifadhi/el-nino-module';

    /** StimulusBundle's own normalisation of the namespace above. */
    public const string CONTROLLER_PREFIX = 'uhifadhi--el-nino-module--';

    /** Config lives under "el_nino:", not the class-derived "uhifadhi_el_nino:". */
    protected string $extensionAlias = 'el_nino';

    public function configure(DefinitionConfigurator $definition): void
    {
        ElNinoConfiguration::define($definition->rootNode());
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The Stimulus controller under an AssetMapper namespace — what
        // symfony/ux-turbo does in TurboExtension::prepend. Guarded on both
        // conditions: a kernel may have no framework extension, and AssetMapper
        // is optional.
        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $builder->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__.'/../assets' => self::ASSET_NAMESPACE,
                    ],
                ],
            ]);
        }

        // The three glyphs this module draws, under its own prefix: `el-nino:map`
        // and the two chevrons. A module names its icons with its own prefix and
        // ships them, never reaching for a set nobody installed.
        if ($builder->hasExtension('ux_icons')) {
            $builder->prependExtensionConfig('ux_icons', [
                'icon_sets' => [
                    'el-nino' => ['path' => __DIR__.'/../assets/icons/el-nino'],
                ],
            ]);
        }

        // The module ships the migrations for the tables it owns (ruled
        // 2026-09-10): an installation runs doctrine:migrations:migrate and
        // writes no SQL for them.
        if ($builder->hasExtension('doctrine_migrations')) {
            $builder->prependExtensionConfig('doctrine_migrations', [
                'migrations_paths' => [
                    'Uhifadhi\\ElNino\\Migrations' => __DIR__.'/../migrations',
                ],
            ]);
        }

        // Zero-config persistence: installations never write a doctrine mappings block.
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'UhifadhiElNino' => [
                            'type' => 'attribute',
                            'dir' => __DIR__.'/Entity',
                            'prefix' => 'Uhifadhi\\ElNino\\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        /*
         * THE INGEST DOOR'S OWN LIMITER. A producer posts once a week; a
         * stranger who found the address posts whatever it likes. The bearer
         * token is what admits a bulletin, and this limiter is what keeps the
         * token check from being the only thing a flood meets. Keyed by client
         * address; the installation may override it under `framework.rate_limiter`.
         */
        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', [
                'rate_limiter' => [
                    'el_nino_ingest' => [
                        'policy' => 'fixed_window',
                        'limit' => 30,
                        'interval' => '1 minute',
                    ],
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $services = $container->services();

        $category = \is_string($config['module_category'] ?? null) ? $config['module_category'] : ElNinoConfiguration::DEFAULT_CATEGORY;
        $services->set('el_nino.module_provider', ElNinoModuleProvider::class)
            ->args([$category])
            ->tag('uhifadhi.module');

        $cap = $config['ingest_body_cap'] ?? ElNinoConfiguration::DEFAULT_INGEST_BODY_CAP;
        $builder->setParameter('el_nino.ingest_body_cap', \is_int($cap) ? $cap : ElNinoConfiguration::DEFAULT_INGEST_BODY_CAP);
        $weeks = $config['weeks_shown'] ?? ElNinoConfiguration::DEFAULT_WEEKS_SHOWN;
        $builder->setParameter('el_nino.weeks_shown', \is_int($weeks) ? $weeks : ElNinoConfiguration::DEFAULT_WEEKS_SHOWN);

        /*
         * THE SECURITYBUNDLE GUARD. The screens enforce a declared pair through
         * #[IsGranted], and the configure writes carry a CSRF token; both need
         * SecurityBundle in the kernel. Read `kernel.bundles`, as
         * FrameworkExtension does — `hasExtension('security')` cannot be asked
         * while an extension is loading, and `class_exists()` proves only that a
         * dev dependency is autoloadable.
         */
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        $hasSecurity = \is_array($bundles) && isset($bundles['SecurityBundle']);

        if ($hasSecurity) {
            $services->set('el_nino.controller.watch', WatchController::class)
                ->args([
                    service('twig'),
                    service('el_nino.watch_reader'),
                    service('el_nino.exposure_map'),
                    service('el_nino.charts'),
                    service('el_nino.weeks'),
                    service('router'),
                ])
                ->public();
            $services->alias(WatchController::class, 'el_nino.controller.watch')->public();

            $services->set('el_nino.controller.producer', ProducerController::class)
                ->args([
                    service('router'),
                    service('el_nino.watches'),
                    service('security.csrf.token_manager'),
                ])
                ->public();
            $services->alias(ProducerController::class, 'el_nino.controller.producer')->public();

            /*
             * THE INGEST DOOR. Firewall-free by the installation's own rule (a
             * PUBLIC_ACCESS line, as the README says), so the bearer token the
             * controller checks is the whole of its authentication — plus the
             * limiter above and the body cap.
             */
            $services->set('el_nino.controller.ingest', IngestController::class)
                ->args([
                    service('el_nino.watches'),
                    service('el_nino.payload_reader'),
                    service('el_nino.ingest'),
                    service('limiter.el_nino_ingest'),
                    service('logger')->nullOnInvalid(),
                    param('el_nino.ingest_body_cap'),
                ])
                ->public();
            $services->alias(IngestController::class, 'el_nino.controller.ingest')->public();
        }
    }
}
