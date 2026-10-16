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

namespace Uhifadhi\ElNino\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\ElNino\Tests\Integration\Fixtures\FixedWatchVoter;

final class WatchPagesTest extends WebTestCase
{
    use EveryAreaRunsTheWatch;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private AreaOfInterest $area;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;
        self::freshSchema($this->em);
        $this->area = $this->seedWatchedArea($this->em);
        $this->everyAreaRunsTheWatch($this->em);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testTheOverviewAnswersTheFourQuestionsFromTheBulletin(): void
    {
        $this->signIn($this->client, $this->em);
        $crawler = $this->client->request('GET', '/areas/'.$this->area->getUuidString().'/modules/elnino');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Seed reserve — El Niño watch', $html);
        self::assertStringContainsString('very strong El Niño', $html);
        self::assertStringContainsString('How warm is the Pacific?', $html);
        self::assertStringContainsString('Will it last into the short rains?', $html);
        self::assertStringContainsString('How wet were the last weeks here?', $html);
        self::assertStringContainsString('Who gets cut off if it floods?', $html);
        self::assertSame(4, $crawler->filter('.kpi')->count(), 'four tiles');
        self::assertStringContainsString('94<em>%</em>', $html, 'the short rains chance');
        self::assertStringContainsString('139<em>% of normal</em>', $html);
        self::assertStringContainsString('built 2026-10-12 05:07 UTC', $html, 'the producer\'s own stamp, in its own clock');
        self::assertStringContainsString('Salt Pans Village', $html, 'the cut-off register');
        self::assertStringContainsString('Two of the four El Niño seasons on record', $html, 'the area\'s own record');
        self::assertStringContainsString('https://example.test/analysis', $html);
        self::assertSame(3, $crawler->filter('[data-atlas-plate]')->count(), 'the two rain plates and the exposure plate, all the atlas\'s');
        self::assertStringContainsString('What this page is not.', $html);
        self::assertStringContainsString('Roads and places come from the reserve&#039;s own road and station registers.', $html, 'the caveat names the sources the provenance names');
        self::assertStringNotContainsString('OpenStreetMap', $html, 'and no source the producer did not name');
        self::assertStringNotContainsString('PL·0', $html, 'no workshop labels');
    }

    public function testTheWeekByWeekTabOpensOnTheNewestWeekAndWalksByAddress(): void
    {
        $this->signIn($this->client, $this->em);
        $crawler = $this->client->request('GET', '/areas/'.$this->area->getUuidString().'/modules/elnino/weeks');

        self::assertResponseIsSuccessful();
        self::assertSame('5', $crawler->filter('select[name="week"] option[selected]')->attr('value'), 'the newest of six weeks');
        self::assertSame(1, $crawler->filter('table.en-weeks tr.on')->count());
        self::assertStringContainsString('Cut-off destinations in a wet cell', (string) $this->client->getResponse()->getContent());

        $crawler = $this->client->request('GET', '/areas/'.$this->area->getUuidString().'/modules/elnino/weeks?week=2&field=mm');
        self::assertResponseIsSuccessful();
        self::assertSame('2', $crawler->filter('select[name="week"] option[selected]')->attr('value'));
        self::assertSame(1, $crawler->filter('input[name="field"][checked]')->count());

        $crawler = $this->client->request('GET', '/areas/'.$this->area->getUuidString().'/modules/elnino/weeks?week=99');
        self::assertSame('5', $crawler->filter('select[name="week"] option[selected]')->attr('value'), 'clamped to the newest');
    }

    public function testAnAreaWithoutABulletinDrawsTheEmptyState(): void
    {
        $bare = new AreaOfInterest()->setSource('test fixture')->setName('Bare reserve')->setGeom(
            '{"type":"MultiPolygon","coordinates":[[[[13.2,-5.8],[13.5,-5.8],[13.5,-5.5],[13.2,-5.5],[13.2,-5.8]]]]}',
        );
        $this->em->persist($bare);
        $this->everyAreaRunsTheWatch($this->em);
        $this->signIn($this->client, $this->em);

        $this->client->request('GET', '/areas/'.$bare->getUuidString().'/modules/elnino');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No bulletin yet', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('names no producer yet', (string) $this->client->getResponse()->getContent());
    }

    public function testSomebodyWithoutTheReadPairIsRefused(): void
    {
        $this->signIn($this->client, $this->em, FixedWatchVoter::STRANGER_EMAIL);
        $this->client->request('GET', '/areas/'.$this->area->getUuidString().'/modules/elnino');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnAreaThatParkedTheModuleAnswersNotFound(): void
    {
        $areaModules = static::getContainer()->get('test_public.'.\Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService::class);
        \assert($areaModules instanceof \Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService);
        $areaModules->uninstall($this->area, \Uhifadhi\ElNino\Module\ElNinoModuleProvider::SLUG);
        $this->signIn($this->client, $this->em);

        $this->client->request('GET', '/areas/'.$this->area->getUuidString().'/modules/elnino');

        self::assertResponseStatusCodeSame(404);
    }
}
