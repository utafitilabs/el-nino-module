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

namespace Uhifadhi\ElNino\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\ElNino\Entity\AreaWatch;
use Uhifadhi\ElNino\Repository\AreaWatchRepository;

/**
 * THE WATCH OF AN AREA, AND THE ONE WAY A TOKEN IS ISSUED.
 *
 * A token is 32 random bytes, handed back once as hex, kept as its SHA-256.
 * Re-issuing replaces it; withdrawing it stops the producer until a new one
 * is issued. The slug and the two texts are the administrator's words.
 */
final class WatchService
{
    public const int TOKEN_BYTES = 32;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AreaWatchRepository $watches,
    ) {
    }

    /** The area's watch, made and saved on first sight so a token has a row to live on. */
    public function forArea(AreaOfInterest $area): AreaWatch
    {
        $watch = $this->watches->findOneByArea($area);
        if (null === $watch) {
            $watch = new AreaWatch($area);
            $this->entityManager->persist($watch);
            $this->entityManager->flush();
        }

        return $watch;
    }

    public function saveWords(AreaOfInterest $area, ?string $producerSlug, ?string $analysisUrl, ?string $areaRecord): AreaWatch
    {
        $watch = $this->forArea($area)
            ->setProducerSlug($producerSlug)
            ->setAnalysisUrl($analysisUrl)
            ->setAreaRecord($areaRecord);
        $this->entityManager->flush();

        return $watch;
    }

    /** Mints a token, keeps its hash, returns the token itself — the only time it is ever readable. */
    public function issueToken(AreaOfInterest $area, ?\DateTimeImmutable $now = null): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $this->forArea($area)->holdToken(self::hash($token), $now ?? new \DateTimeImmutable());
        $this->entityManager->flush();

        return $token;
    }

    public function withdrawToken(AreaOfInterest $area, ?\DateTimeImmutable $now = null): void
    {
        $this->forArea($area)->withdrawToken($now ?? new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /** The watch a bearer token belongs to, or nothing — and nothing says no more than that. */
    public function findByToken(string $token): ?AreaWatch
    {
        $token = trim($token);
        if ('' === $token || \strlen($token) > 4 * self::TOKEN_BYTES) {
            return null;
        }

        return $this->watches->findOneByTokenHash(self::hash($token));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
