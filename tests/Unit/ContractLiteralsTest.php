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

namespace Uhifadhi\ElNino\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\ElNino\Controller\IngestController;
use Uhifadhi\ElNino\UhifadhiElNinoBundle;

/**
 * THE SEAM TESTS: every name that crosses a boundary — a tag written as a
 * literal, a Stimulus controller name, a part name the producer's files are
 * read by — is asserted to be the same string on both sides.
 */
final class ContractLiteralsTest extends TestCase
{
    public function testTheModuleTagIsTheRegistrysOwn(): void
    {
        $bundle = (string) file_get_contents(self::root().'/src/UhifadhiElNinoBundle.php');
        self::assertStringContainsString("->tag('".RegistryBundle::MODULE_TAG."')", $bundle);
    }

    public function testTheDevkitTagsAreTheLiteralsTheDevkitCollects(): void
    {
        $services = (string) file_get_contents(self::root().'/config/services.php');
        self::assertStringContainsString("->tag('uhifadhi.devkit.content_provider')", $services);
        self::assertStringContainsString("->tag('uhifadhi.devkit.command_provider')", $services);
    }

    public function testEveryControllerStampsItsRoutesWithTheModule(): void
    {
        foreach (['WatchController', 'ProducerController'] as $controller) {
            $source = (string) file_get_contents(self::root().'/src/Controller/'.$controller.'.php');
            self::assertStringContainsString('#[Route(defaults: [RegistryBundle::MODULE_ROUTE_DEFAULT => ElNinoModuleProvider::SLUG])]', $source, $controller);
        }
    }

    public function testTheWeeksControllerIsNamedTheSameInTheManifestTheTemplateAndTheBundle(): void
    {
        /** @var array{symfony: array{controllers: array<string, array{main: string}>}} $manifest */
        $manifest = json_decode((string) file_get_contents(self::root().'/assets/package.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('weeks', $manifest['symfony']['controllers']);
        self::assertFileExists(self::root().'/assets/'.$manifest['symfony']['controllers']['weeks']['main']);

        $template = (string) file_get_contents(self::root().'/templates/watch/weeks.html.twig');
        self::assertStringContainsString("constant('Uhifadhi\\\\ElNino\\\\UhifadhiElNinoBundle::CONTROLLER_PREFIX') }}weeks", $template);
        self::assertStringContainsString('weeks#go', $template);
        self::assertStringContainsString('weeks#slide', $template);

        $js = (string) file_get_contents(self::root().'/assets/controllers/weeks_controller.js');
        self::assertStringContainsString('go()', $js);
        self::assertStringContainsString('slide(event)', $js);
        self::assertSame('uhifadhi--el-nino-module--', UhifadhiElNinoBundle::CONTROLLER_PREFIX);
    }

    public function testTheAssetNamespaceIsTheNpmFormOfThePackageName(): void
    {
        /** @var array{name: string} $composer */
        $composer = json_decode((string) file_get_contents(self::root().'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('@'.$composer['name'], UhifadhiElNinoBundle::ASSET_NAMESPACE);
        self::assertNotEmpty(glob(self::root().'/assets/*') ?: [], 'assets/ keeps a tracked file, or a zipball drops the directory and every page 500s');
    }

    public function testTheIngestPartsAreTheContractsOwnPaths(): void
    {
        self::assertSame([
            'status.json', 'series.json', 'map/roads.geojson', 'map/destinations.geojson',
            'map/susceptibility.geojson', 'map/bomas.geojson', 'map/provenance.json',
        ], array_keys(IngestController::PARTS));
        self::assertSame('/el-nino/ingest', IngestController::PATH);
    }

    public function testTheIngestDoorIsNamedInTheReadmeWithItsAccessRule(): void
    {
        $readme = (string) file_get_contents(self::root().'/README.md');
        self::assertStringContainsString(IngestController::PATH, $readme);
        self::assertStringContainsString('PUBLIC_ACCESS', $readme);
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
