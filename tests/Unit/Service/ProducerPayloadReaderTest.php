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

namespace Uhifadhi\ElNino\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Uhifadhi\ElNino\Devkit\SyntheticBulletin;
use Uhifadhi\ElNino\Model\PayloadException;
use Uhifadhi\ElNino\Model\ProducerBulletin;
use Uhifadhi\ElNino\Service\ProducerPayloadReader;
use Uhifadhi\ElNino\Tests\Fixtures\ProducerFiles;

final class ProducerPayloadReaderTest extends TestCase
{
    private const array BOX = ['west' => 12.2, 'south' => -5.8, 'east' => 12.5, 'north' => -5.5];

    public function testABuildReadsBackAsTheBulletinItCameFrom(): void
    {
        $built = new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC'));
        $sent = SyntheticBulletin::over(self::BOX, $built, 6);
        $read = new ProducerPayloadReader()->read(ProducerFiles::parts($sent));

        self::assertSame(ProducerBulletin::CONTRACT, $read->contract);
        self::assertSame(SyntheticBulletin::SLUG, $read->producerSlug);
        self::assertSame('2026-10-12 05:07', $read->builtAt->format('Y-m-d H:i'));
        self::assertSame('El Niño', $read->enso['phase']);
        self::assertSame(150, $read->wetThresholdPct);
        self::assertCount(6, $read->weeks);
        self::assertSame($sent->weeks[0]['start']->format('Y-m-d'), $read->weeks[0]['start']->format('Y-m-d'));
        self::assertSame($sent->weeks[5]['grid_pct'], $read->weeks[5]['grid_pct']);
        self::assertSame($sent->grid['x_centres'], $read->grid['x_centres']);
        self::assertNotNull($read->roads);
        self::assertCount(4, $read->roads['features']);
        self::assertNotNull($read->destinations);
        self::assertCount(6, $read->destinations['features']);
        self::assertNotNull($read->susceptibility);
        self::assertSame('seed0000', $read->provenanceCommit());
        self::assertSame('2026-10-12', $read->provenanceRunDate()?->format('Y-m-d'));
    }

    public function testTheWeeksAreSortedOldestFirstWhateverOrderTheyArrivedIn(): void
    {
        $sent = SyntheticBulletin::over(self::BOX, new \DateTimeImmutable('2026-10-12 05:07'), 4);
        $parts = ProducerFiles::parts($sent);
        /** @var array<string, mixed> $series */
        $series = json_decode($parts[ProducerPayloadReader::SERIES], true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($series['weeks']));
        $series['weeks'] = array_reverse($series['weeks']);
        $parts[ProducerPayloadReader::SERIES] = json_encode($series, \JSON_THROW_ON_ERROR);

        $read = new ProducerPayloadReader()->read($parts);

        self::assertTrue($read->weeks[0]['start'] < $read->weeks[3]['start']);
    }

    public function testTheExposurePartsAreOptional(): void
    {
        $sent = SyntheticBulletin::over(self::BOX, new \DateTimeImmutable('2026-10-12 05:07'), 3, withExposure: false);
        $read = new ProducerPayloadReader()->read(ProducerFiles::parts($sent));

        self::assertFalse($read->hasExposure());
        self::assertNull($read->provenance);
    }

    public function testAMissingStatusIsRefusedByName(): void
    {
        $this->expectException(PayloadException::class);
        $this->expectExceptionMessage('status: is missing');

        new ProducerPayloadReader()->read([ProducerPayloadReader::SERIES => '{}']);
    }

    public function testAnotherContractIsRefusedByName(): void
    {
        $sent = SyntheticBulletin::over(self::BOX, new \DateTimeImmutable('2026-10-12 05:07'), 2);
        $parts = ProducerFiles::parts($sent);
        $parts[ProducerPayloadReader::STATUS] = str_replace(ProducerBulletin::CONTRACT, 'el-nino-producer/2', $parts[ProducerPayloadReader::STATUS]);

        $this->expectException(PayloadException::class);
        $this->expectExceptionMessage('el-nino-producer/2');

        new ProducerPayloadReader()->read($parts);
    }

    public function testASeriesAboutAnotherAreaIsRefused(): void
    {
        $sent = SyntheticBulletin::over(self::BOX, new \DateTimeImmutable('2026-10-12 05:07'), 2);
        $parts = ProducerFiles::parts($sent);
        $parts[ProducerPayloadReader::SERIES] = str_replace('"slug":"'.SyntheticBulletin::SLUG.'"', '"slug":"elsewhere"', $parts[ProducerPayloadReader::SERIES]);

        $this->expectException(PayloadException::class);
        $this->expectExceptionMessage('series: names a different area from status');

        new ProducerPayloadReader()->read($parts);
    }

    public function testTextThatIsNotJsonIsRefusedByPart(): void
    {
        $sent = SyntheticBulletin::over(self::BOX, new \DateTimeImmutable('2026-10-12 05:07'), 2);
        $parts = ProducerFiles::parts($sent);
        $parts[ProducerPayloadReader::ROADS] = '<html>';

        $this->expectException(PayloadException::class);
        $this->expectExceptionMessage('roads: is not JSON');

        new ProducerPayloadReader()->read($parts);
    }

    public function testAGridCellThatIsNeitherANumberNorNullIsRefused(): void
    {
        $sent = SyntheticBulletin::over(self::BOX, new \DateTimeImmutable('2026-10-12 05:07'), 2);
        $parts = ProducerFiles::parts($sent);
        /** @var array<string, mixed> $series */
        $series = json_decode($parts[ProducerPayloadReader::SERIES], true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($series['recent']) && \is_array($series['recent']['grid_pct']) && \is_array($series['recent']['grid_pct'][0]));
        $series['recent']['grid_pct'][0][0] = 'wet';
        $parts[ProducerPayloadReader::SERIES] = json_encode($series, \JSON_THROW_ON_ERROR);

        $this->expectException(PayloadException::class);
        $this->expectExceptionMessage('series.recent.grid_pct');

        new ProducerPayloadReader()->read($parts);
    }
}
