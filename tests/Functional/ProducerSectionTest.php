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
use Uhifadhi\ElNino\Service\WatchService;
use Uhifadhi\ElNino\Tests\Integration\Fixtures\FixedWatchVoter;

final class ProducerSectionTest extends WebTestCase
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
        $this->area = new AreaOfInterest()->setSource('test fixture')->setName('Seed reserve')->setGeom(
            '{"type":"MultiPolygon","coordinates":[[[[12.2,-5.8],[12.5,-5.8],[12.5,-5.5],[12.2,-5.5],[12.2,-5.8]]]]}',
        );
        $this->em->persist($this->area);
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

    public function testTheSectionDrawsTheFormsAndSavesTheWords(): void
    {
        $this->signIn($this->client, $this->em, FixedWatchVoter::CONFIGURER_EMAIL);
        $crawler = $this->client->request('GET', $this->section());
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('input[name="producer_slug"]')->count());

        $form = $crawler->selectButton('Save')->form([
            'producer_slug' => 'kilimani-watch',
            'analysis_url' => 'https://example.test/analysis',
            'area_record' => 'Two of the four El Niño seasons on record brought more than double the normal short rains here.',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects($this->section());
        $this->client->followRedirect();
        self::assertStringContainsString('Saved.', (string) $this->client->getResponse()->getContent());

        $watches = static::getContainer()->get('test_public.'.WatchService::class);
        \assert($watches instanceof WatchService);
        self::assertSame('kilimani-watch', $watches->forArea($this->area)->getProducerSlug());
        self::assertSame('https://example.test/analysis', $watches->forArea($this->area)->getAnalysisUrl());
    }

    public function testASlugThatIsNotASlugIsRefusedAndNothingSaved(): void
    {
        $this->signIn($this->client, $this->em, FixedWatchVoter::CONFIGURER_EMAIL);
        $crawler = $this->client->request('GET', $this->section());
        $this->client->submit($crawler->selectButton('Save')->form(['producer_slug' => 'Not A Slug']));
        $this->client->followRedirect();

        self::assertStringContainsString('Nothing was saved.', (string) $this->client->getResponse()->getContent());
        $watches = static::getContainer()->get('test_public.'.WatchService::class);
        \assert($watches instanceof WatchService);
        self::assertNull($watches->forArea($this->area)->getProducerSlug());
    }

    public function testATokenIsIssuedPrintedOnceAndThenWithdrawn(): void
    {
        $this->signIn($this->client, $this->em, FixedWatchVoter::CONFIGURER_EMAIL);
        $crawler = $this->client->request('GET', $this->section());
        $this->client->submit($crawler->selectButton('Issue a token')->form());
        $crawler = $this->client->followRedirect();

        self::assertSame(1, $crawler->filter('pre.en-token')->count(), 'the token is printed on the page the issue lands on');
        $printed = trim($crawler->filter('pre.en-token')->text());
        self::assertSame(64, \strlen($printed));

        $crawler = $this->client->request('GET', $this->section());
        self::assertSame(0, $crawler->filter('pre.en-token')->count(), 'and never again');

        $watches = static::getContainer()->get('test_public.'.WatchService::class);
        \assert($watches instanceof WatchService);
        self::assertNotNull($watches->findByToken($printed));

        $this->client->submit($crawler->selectButton('Withdraw the token')->form());
        $this->client->followRedirect();
        self::assertNull($watches->findByToken($printed));
    }

    public function testAReaderIsRefusedTheWrites(): void
    {
        $this->signIn($this->client, $this->em, FixedWatchVoter::READER_EMAIL);
        $this->client->request('POST', '/areas/'.$this->area->getUuidString().'/modules/elnino/configure/producer/token', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    private function section(): string
    {
        return '/areas/'.$this->area->getUuidString().'/modules/elnino/configure/producer';
    }
}
