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

namespace Uhifadhi\ElNino\Devkit;

use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandIo;
use Uhifadhi\Contracts\Devkit\CommandProviderInterface;
use Uhifadhi\ElNino\Controller\IngestController;
use Uhifadhi\ElNino\Model\PayloadException;
use Uhifadhi\ElNino\Service\BulletinIngestService;
use Uhifadhi\ElNino\Service\ProducerPayloadReader;
use Uhifadhi\ElNino\Service\WatchService;

/**
 * A DEVELOPMENT DOOR FOR A PRODUCER'S FILES ON DISK: the same reader and the
 * same ingest the push goes through, pointed at a directory a build wrote.
 */
final readonly class ElNinoCommandProvider implements CommandProviderInterface
{
    public function __construct(
        private AreaOfInterestRepository $areas,
        private WatchService $watches,
        private ProducerPayloadReader $reader,
        private BulletinIngestService $ingest,
    ) {
    }

    public function commands(): array
    {
        return [
            new CommandDescriptor(
                'el-nino:ingest',
                'Ingest a producer build from a directory, as a push would. Usage: el-nino:ingest <area-uuid> <directory>',
                fn (array $arguments, CommandIo $io): int => $this->ingestFromDirectory($arguments, $io),
            ),
        ];
    }

    /**
     * @param list<string> $arguments
     */
    private function ingestFromDirectory(array $arguments, CommandIo $io): int
    {
        if (2 !== \count($arguments)) {
            $io->error('Usage: el-nino:ingest <area-uuid> <directory>');

            return 1;
        }
        [$uuid, $directory] = $arguments;
        $area = $this->areas->findOneByUuid($uuid);
        if (null === $area) {
            $io->error(\sprintf('No area has the uuid "%s".', $uuid));

            return 1;
        }
        $directory = rtrim($directory, '/');
        if (!is_dir($directory)) {
            $io->error(\sprintf('"%s" is not a directory.', $directory));

            return 1;
        }
        $parts = [];
        foreach (IngestController::PARTS as $path => $name) {
            $file = $directory.'/'.$path;
            if (is_file($file)) {
                $text = file_get_contents($file);
                if (false !== $text) {
                    $parts[$name] = $text;
                }
            }
        }
        try {
            $bulletin = $this->reader->read($parts);
            $watch = $this->watches->forArea($area);
            if (null === $watch->getProducerSlug()) {
                $this->watches->saveWords($area, $bulletin->producerSlug, $watch->getAnalysisUrl(), $watch->getAreaRecord());
                $io->write(\sprintf('The area had no producer; it now reads from "%s".', $bulletin->producerSlug));
            }
            $report = $this->ingest->ingest($this->watches->forArea($area), $bulletin);
        } catch (PayloadException $e) {
            $io->error('Refused: '.$e->getMessage());

            return 1;
        }
        $io->write(\sprintf(
            '%s bulletin built %s UTC: %d weeks, exposure layers %s.',
            $report->duplicate ? 'Already had the' : 'Stored the',
            $report->builtAt->format('Y-m-d H:i'),
            $report->weeks,
            $report->exposure,
        ));

        return 0;
    }
}
