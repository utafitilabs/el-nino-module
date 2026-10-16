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

namespace Uhifadhi\ElNino\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\ElNino\Entity\AreaWatch;

/**
 * @extends ServiceEntityRepository<AreaWatch>
 */
final class AreaWatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AreaWatch::class);
    }

    public function findOneByArea(AreaOfInterest $area): ?AreaWatch
    {
        return $this->findOneBy(['area' => $area]);
    }

    public function findOneByTokenHash(string $hash): ?AreaWatch
    {
        return $this->findOneBy(['tokenHash' => $hash]);
    }
}
