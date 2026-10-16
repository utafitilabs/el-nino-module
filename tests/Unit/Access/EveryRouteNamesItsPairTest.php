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

namespace Uhifadhi\ElNino\Tests\Unit\Access;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Access\Grant;
use Uhifadhi\ElNino\Access\ElNinoConcerns;
use Uhifadhi\ElNino\Controller\IngestController;

/**
 * Every page and every write of this module names the pair it enforces, every
 * pair named is one this module declares, and every pair declared is enforced
 * somewhere. The ingest door is the one route that is deliberately open: a
 * producer is not a person, and the bearer token is its whole authentication.
 */
final class EveryRouteNamesItsPairTest extends TestCase
{
    /** @var list<string> routes that enforce no pair, each with the reason it is open */
    private const array OPEN = [
        'el_nino_ingest', // a producer's bearer token admits it; see IngestController
    ];

    public function testEveryRouteThisModuleShipsNamesThePairItEnforces(): void
    {
        $ungated = [];
        foreach (self::routes() as [$file, $name, $pairs]) {
            if ([] === $pairs && !\in_array($name, self::OPEN, true)) {
                $ungated[] = $file.' -> '.$name;
            }
        }
        sort($ungated);
        self::assertSame([], $ungated, 'These routes enforce nothing: '.implode(', ', $ungated));
    }

    public function testEveryPairARouteNamesIsOneThisModuleDeclares(): void
    {
        $strangers = [];
        foreach (self::routes() as [$file, $name, $pairs]) {
            foreach ($pairs as $pair) {
                if (!\in_array($pair, self::declaredPairs(), true)) {
                    $strangers[] = $file.' -> '.$name.' ('.$pair.')';
                }
            }
        }
        self::assertSame([], $strangers, 'These routes name a pair this module does not declare: '.implode(', ', $strangers));
    }

    public function testEveryPairThisModuleDeclaresIsEnforcedSomewhere(): void
    {
        $enforced = [];
        foreach (self::routes() as [, , $pairs]) {
            foreach ($pairs as $pair) {
                $enforced[$pair] = true;
            }
        }
        $idle = array_values(array_filter(self::declaredPairs(), static fn (string $pair): bool => !isset($enforced[$pair])));
        self::assertSame([], $idle, 'These pairs are declared and nothing enforces them: '.implode(', ', $idle));
    }

    public function testTheOpenRouteIsTheIngestDoorAndNothingElse(): void
    {
        self::assertSame([IngestController::PATH], self::pathsOf(self::OPEN));
    }

    /**
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    private static function routes(): array
    {
        $routes = [];
        foreach (glob(\dirname(__DIR__, 3).'/src/Controller/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all('/#\[Route\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*name:\s*\'([a-z_]+)\'(?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)\]\s*((?:#\[[^\]]*\]\s*)*)public function/s', $source, $matches, \PREG_SET_ORDER);
            foreach ($matches as $match) {
                preg_match_all('/IsGranted\(\'([a-z-]+\.[a-z]+)\'/', $match[2], $granted);
                $routes[] = [basename($file), $match[1], array_values(array_unique($granted[1]))];
            }
        }
        self::assertNotEmpty($routes, 'the controllers declare routes');

        return $routes;
    }

    /** @return list<string> */
    private static function declaredPairs(): array
    {
        $pairs = [];
        foreach (new ElNinoConcerns()->concerns() as $concern) {
            foreach ($concern->verbs() as $verb) {
                $pairs[] = (string) Grant::of($concern->key(), $verb);
            }
        }

        return $pairs;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function pathsOf(array $names): array
    {
        $paths = [];
        foreach (glob(\dirname(__DIR__, 3).'/src/Controller/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            foreach ($names as $name) {
                if (preg_match('/#\[Route\((self::PATH|\'[^\']+\'),\s*name:\s*\''.preg_quote($name, '/').'\'/', $source, $m)) {
                    $paths[] = 'self::PATH' === $m[1] ? IngestController::PATH : trim($m[1], '\'');
                }
            }
        }

        return $paths;
    }
}
