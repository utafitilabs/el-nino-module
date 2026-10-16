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
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\RegistryBundle\Service\RegistrySyncService;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\ElNino\Devkit\SyntheticBulletin;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;
use Uhifadhi\ElNino\Service\BulletinIngestService;
use Uhifadhi\ElNino\Service\WatchService;
use Uhifadhi\ElNino\Tests\Integration\Fixtures\FixedWatchVoter;
use Uhifadhi\ElNino\Tests\Integration\IntegrationTestCase;

/**
 * WHAT AN INSTALLATION DOES, IN FIXTURES: the registry reconciled, the module
 * switched on for every area, somebody signed in, a bulletin ingested.
 */
trait EveryAreaRunsTheWatch
{
    protected function everyAreaRunsTheWatch(EntityManagerInterface $em): void
    {
        $em->flush();
        $sync = static::getContainer()->get('test_public.'.RegistrySyncService::class);
        \assert($sync instanceof RegistrySyncService);
        $sync->sync();
        $areaModules = static::getContainer()->get('test_public.'.AreaModuleService::class);
        \assert($areaModules instanceof AreaModuleService);
        foreach ($em->getRepository(AreaOfInterest::class)->findAll() as $area) {
            $areaModules->install($area, ElNinoModuleProvider::SLUG);
        }
    }

    protected function signIn(KernelBrowser $client, EntityManagerInterface $em, string $email = FixedWatchVoter::READER_EMAIL): User
    {
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User()->setPassword('x')->setEmail($email)->setFirstName('Vera')->setLastName('Viewer');
            $em->persist($user);
            $em->flush();
        }
        $client->loginUser($user);

        return $user;
    }

    protected function seedWatchedArea(EntityManagerInterface $em, string $name = 'Seed reserve', int $weeks = 6): AreaOfInterest
    {
        $area = new AreaOfInterest()->setSource('test fixture')->setName($name)->setGeom(
            '{"type":"MultiPolygon","coordinates":[[[[12.2,-5.8],[12.5,-5.8],[12.5,-5.5],[12.2,-5.5],[12.2,-5.8]]]]}',
        );
        $em->persist($area);
        $em->flush();
        $watches = static::getContainer()->get('test_public.'.WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->saveWords($area, SyntheticBulletin::SLUG, 'https://example.test/analysis', 'Two of the four El Niño seasons on record brought more than double the normal short rains here.');
        $ingest = static::getContainer()->get('test_public.'.BulletinIngestService::class);
        \assert($ingest instanceof BulletinIngestService);
        $ingest->ingest($watches->forArea($area), SyntheticBulletin::over(
            ['west' => 12.2, 'south' => -5.8, 'east' => 12.5, 'north' => -5.5],
            new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC')),
            $weeks,
        ));

        return $area;
    }

    protected static function freshSchema(EntityManagerInterface $em): void
    {
        IntegrationTestCase::rebuildSchema($em);
    }
}
