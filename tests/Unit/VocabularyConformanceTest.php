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

namespace Uhifadhi\ElNino\Tests\Unit;

use Uhifadhi\Bundle\AreaBundle\AreaBundle;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\ShellBundle\Test\VocabularyConformanceTestCase;

/**
 * Every class the templates write is shipped by somebody, every token the
 * own sheet spends is defined in the chain, and no shared selector is
 * restated — the shell's rule, run over this module's own sheet and templates.
 */
final class VocabularyConformanceTest extends VocabularyConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 2);
    }

    protected static function alias(): string
    {
        return 'el-nino';
    }

    /** @return list<string> */
    protected static function ownStylesheets(): array
    {
        return ['el-nino.css', 'configure.css'];
    }

    /** @return list<string> */
    protected static function linkedStylesheets(): array
    {
        return [
            ...parent::linkedStylesheets(),
            self::publicDirOf(AtlasBundle::class).'/map.css',
            self::publicDirOf(AtlasBundle::class).'/chart.css',
            self::publicDirOf(AreaBundle::class).'/area.css',
        ];
    }

    /** @param class-string $bundle */
    private static function publicDirOf(string $bundle): string
    {
        return \dirname(new \ReflectionClass($bundle)->getFileName() ?: '').'/public';
    }
}
