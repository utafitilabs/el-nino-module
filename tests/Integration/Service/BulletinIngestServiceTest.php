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

namespace Uhifadhi\ElNino\Tests\Integration\Service;

use Uhifadhi\ElNino\Devkit\SyntheticBulletin;
use Uhifadhi\ElNino\Entity\Bulletin;
use Uhifadhi\ElNino\Entity\ExposureRun;
use Uhifadhi\ElNino\Model\IngestReport;
use Uhifadhi\ElNino\Model\PayloadException;
use Uhifadhi\ElNino\Service\BulletinIngestService;
use Uhifadhi\ElNino\Service\WatchReader;
use Uhifadhi\ElNino\Service\WatchService;
use Uhifadhi\ElNino\Tests\Integration\IntegrationTestCase;

final class BulletinIngestServiceTest extends IntegrationTestCase
{
    public function testABulletinIsStoredWithItsWeeksAndItsExposureLayers(): void
    {
        $area = $this->seedArea();
        $watches = $this->service(WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->saveWords($area, SyntheticBulletin::SLUG, null, null);
        $ingest = $this->service(BulletinIngestService::class);
        \assert($ingest instanceof BulletinIngestService);

        $report = $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(self::seedBox(), new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC')), 8));

        self::assertFalse($report->duplicate);
        self::assertSame(8, $report->weeks);
        self::assertSame(IngestReport::EXPOSURE_IMPORTED, $report->exposure);

        $this->em->clear();
        $reader = $this->service(WatchReader::class);
        \assert($reader instanceof WatchReader);
        $reading = $reader->read($area);
        self::assertNotNull($reading);
        self::assertSame('El Niño', $reading->bulletin->getEnsoPhase());
        self::assertSame('very strong', $reading->bulletin->getEnsoStrength());
        self::assertSame(139.0, $reading->bulletin->getRainPct());
        self::assertCount(8, $reading->bulletin->getWeeks());
        self::assertSame('Seed watch', $reading->bulletin->getProducer()['name'] ?? null);
        self::assertNotNull($reading->run);
        self::assertCount(4, $reading->run->getRoads());
        self::assertCount(6, $reading->run->getDestinations());
        self::assertCount(6, $reading->run->getPatches());
        self::assertCount(3, $reading->cutOff, 'three destinations are cut off in the seed');
        self::assertSame('Salt Pans Village', $reading->cutOff[0]->getName(), 'most people first');
        self::assertSame(1880, $reading->cutOffPeople);
        self::assertStringStartsWith('{"type":"Point"', $reading->cutOff[0]->getGeom());
        self::assertNotNull($reading->watch->getLastIngestAt());
    }

    public function testTheSameBuildSentTwiceIsAcceptedOnceAndAnswersDuplicate(): void
    {
        $area = $this->seedArea();
        $watches = $this->service(WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->saveWords($area, SyntheticBulletin::SLUG, null, null);
        $ingest = $this->service(BulletinIngestService::class);
        \assert($ingest instanceof BulletinIngestService);
        $built = new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC'));

        $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(self::seedBox(), $built, 3));
        $second = $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(self::seedBox(), $built, 3));

        self::assertTrue($second->duplicate);
        self::assertSame(IngestReport::EXPOSURE_KEPT, $second->exposure);
        self::assertSame(1, $this->em->getRepository(Bulletin::class)->count([]));
        self::assertSame(1, $this->em->getRepository(ExposureRun::class)->count([]));
    }

    public function testANewerBuildBecomesTheReadingAndAnUnchangedAnalysisKeepsItsRun(): void
    {
        $area = $this->seedArea();
        $watches = $this->service(WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->saveWords($area, SyntheticBulletin::SLUG, null, null);
        $ingest = $this->service(BulletinIngestService::class);
        \assert($ingest instanceof BulletinIngestService);

        $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(self::seedBox(), new \DateTimeImmutable('2026-10-05 05:07', new \DateTimeZone('UTC')), 3));
        $later = SyntheticBulletin::over(self::seedBox(), new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC')), 3);
        // The seed's provenance names the build date as the run date, so the
        // second build is a re-run of the analysis and replaces the layers.
        $report = $ingest->ingest($watches->forArea($area), $later);

        self::assertFalse($report->duplicate);
        self::assertSame(IngestReport::EXPOSURE_IMPORTED, $report->exposure);
        self::assertSame(2, $this->em->getRepository(Bulletin::class)->count([]));
        $runs = $this->em->getRepository(ExposureRun::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $runs);
        self::assertFalse($runs[0]->isCurrent());
        self::assertTrue($runs[1]->isCurrent());
        self::assertSame('2026-10-05', $runs[0]->getRunDate()?->format('Y-m-d'), 'the superseded run keeps its provenance');
        self::assertCount(0, $runs[0]->getRoads(), 'and drops its layers');

        $this->em->clear();
        $reader = $this->service(WatchReader::class);
        \assert($reader instanceof WatchReader);
        self::assertSame('2026-10-12', $reader->read($area)?->bulletin->getBuiltAt()->format('Y-m-d'));
    }

    public function testABulletinAboutAnotherProducerIsRefused(): void
    {
        $area = $this->seedArea();
        $watches = $this->service(WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->saveWords($area, 'somebody-else', null, null);
        $ingest = $this->service(BulletinIngestService::class);
        \assert($ingest instanceof BulletinIngestService);

        $this->expectException(PayloadException::class);
        $this->expectExceptionMessage('this area is fed by "somebody-else"');

        $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(self::seedBox(), new \DateTimeImmutable('2026-10-12 05:07'), 2));
    }

    public function testAnAreaWithNoProducerNamedRefusesEveryBulletin(): void
    {
        $area = $this->seedArea();
        $watches = $this->service(WatchService::class);
        \assert($watches instanceof WatchService);
        $ingest = $this->service(BulletinIngestService::class);
        \assert($ingest instanceof BulletinIngestService);

        $this->expectException(PayloadException::class);
        $this->expectExceptionMessage('names no producer');

        $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(self::seedBox(), new \DateTimeImmutable('2026-10-12 05:07'), 2));
    }

    public function testATokenIsIssuedOnceReadableAndFoundByItsHash(): void
    {
        $area = $this->seedArea();
        $watches = $this->service(WatchService::class);
        \assert($watches instanceof WatchService);

        $token = $watches->issueToken($area);

        self::assertSame(64, \strlen($token));
        self::assertSame($area->getId(), $watches->findByToken($token)?->getArea()->getId());
        self::assertNull($watches->findByToken('not-a-token'));
        self::assertNotSame($token, $watches->forArea($area)->getTokenHash(), 'only the hash is kept');

        $watches->withdrawToken($area);
        self::assertNull($watches->findByToken($token));
    }
}
