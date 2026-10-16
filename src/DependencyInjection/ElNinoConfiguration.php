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

namespace Uhifadhi\ElNino\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * The configuration tree, as a static so the bundle's configure() and a plain
 * Processor in a unit test share it verbatim.
 *
 * Everything an installation decides once is here; everything an AREA decides
 * — which producer feeds it, the token it admits, the words of its own record —
 * is on the module's configure page and in the database, never in this tree.
 */
final class ElNinoConfiguration
{
    public const string DEFAULT_CATEGORY = 'pressure';

    /** Sixteen mebibytes: the susceptibility polygons alone are two. */
    public const int DEFAULT_INGEST_BODY_CAP = 16 * 1024 * 1024;

    /** How many weeks the week-by-week tab walks back through. */
    public const int DEFAULT_WEEKS_SHOWN = 26;

    public static function define(NodeDefinition|ArrayNodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('The el_nino root node must be an array node.');
        }

        $root
            ->children()
                ->scalarNode('module_category')
                    ->info('Catalogue category the El Niño watch tile is filed under in each area.')
                    ->defaultValue(self::DEFAULT_CATEGORY)->cannotBeEmpty()
                ->end()
                ->integerNode('ingest_body_cap')
                    ->info('The most bytes one part of a producer bulletin may carry.')
                    ->min(1024)
                    ->defaultValue(self::DEFAULT_INGEST_BODY_CAP)
                ->end()
                ->integerNode('weeks_shown')
                    ->info('How many of the weeks a bulletin carries the week-by-week tab walks through.')
                    ->min(1)
                    ->defaultValue(self::DEFAULT_WEEKS_SHOWN)
                ->end()
            ->end();
    }
}
