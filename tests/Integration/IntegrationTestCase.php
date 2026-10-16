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

namespace Uhifadhi\ElNino\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;

/**
 * Boots the kernel and rebuilds the schema per test, so every assertion is
 * about what was actually stored in a real PostGIS database.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;
        self::rebuildSchema($this->em);
    }

    public static function rebuildSchema(EntityManagerInterface $em): void
    {
        $connection = $em->getConnection();
        $tables = $connection->fetchFirstColumn(<<<'SQL'
            SELECT c.relname
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND NOT EXISTS (
                  SELECT 1 FROM pg_depend d
                  WHERE d.objid = c.oid AND d.deptype = 'e'
              )
            SQL);
        if ([] !== $tables) {
            $connection->executeStatement(\sprintf(
                'DROP TABLE IF EXISTS %s CASCADE',
                implode(', ', array_map(
                    static function (mixed $table) use ($connection): string {
                        \assert(\is_string($table));

                        return $connection->quoteSingleIdentifier($table);
                    },
                    $tables,
                )),
            ));
        }
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
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

    /** A made-up reserve, over nobody's ground. */
    protected function seedArea(string $name = 'Seed reserve'): AreaOfInterest
    {
        $area = new AreaOfInterest()->setSource('test fixture')->setName($name)->setGeom(
            '{"type":"MultiPolygon","coordinates":[[[[12.2,-5.8],[12.5,-5.8],[12.5,-5.5],[12.2,-5.5],[12.2,-5.8]]]]}',
        );
        $this->em->persist($area);
        $this->em->flush();

        return $area;
    }

    /** @return array{west: float, south: float, east: float, north: float} */
    protected static function seedBox(): array
    {
        return ['west' => 12.2, 'south' => -5.8, 'east' => 12.5, 'north' => -5.5];
    }

    protected function service(string $id): object
    {
        return static::getContainer()->get('test_public.'.$id);
    }
}
