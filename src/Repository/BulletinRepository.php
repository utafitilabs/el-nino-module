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
use Uhifadhi\ElNino\Entity\Bulletin;

/**
 * @extends ServiceEntityRepository<Bulletin>
 */
final class BulletinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bulletin::class);
    }

    /** The newest bulletin an area has, by the time the producer built it. */
    public function findLatestForArea(AreaOfInterest $area): ?Bulletin
    {
        return $this->findOneBy(['area' => $area], ['builtAt' => 'DESC']);
    }

    public function findOneByAreaAndBuiltAt(AreaOfInterest $area, \DateTimeImmutable $builtAt): ?Bulletin
    {
        return $this->findOneBy(['area' => $area, 'builtAt' => $builtAt]);
    }

    public function countForArea(AreaOfInterest $area): int
    {
        return $this->count(['area' => $area]);
    }
}
