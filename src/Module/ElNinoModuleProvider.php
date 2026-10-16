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

namespace Uhifadhi\ElNino\Module;

use Uhifadhi\Contracts\ModuleProviderInterface;
use Uhifadhi\Contracts\ModuleProviderTrait;

/**
 * WHAT THIS MODULE SAYS ABOUT ITSELF TO THE REGISTRY.
 *
 * One bundle, one module. Tagged `uhifadhi.module` by hand in the bundle;
 * `registry:sync` reads it and gives the module a catalogue row and every area
 * a parked row an administrator switches on.
 */
final class ElNinoModuleProvider implements ModuleProviderInterface
{
    use ModuleProviderTrait;

    /**
     * The slug is the module's identity everywhere: the ledger, the routes, the
     * seams. Letters only, because the shell's configure route and the registry's
     * parking gate read a slug as `[a-z]+`; the concern key keeps its hyphen.
     */
    public const string SLUG = 'elnino';

    public function __construct(
        private readonly string $category,
    ) {
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function name(): string
    {
        return 'El Niño watch';
    }

    /** The one sentence the catalogue prints under the name — what the module is, for a stranger. */
    public function description(): string
    {
        return 'The Pacific this week, the chance El Niño lasts into the short rains, the rain of the last weeks here, and who loses road access if it floods.';
    }

    public function category(): string
    {
        return $this->category;
    }

    public function dataSource(): string
    {
        return 'NOAA CPC · IRI · CHIRPS · a flood-exposure analysis';
    }

    public function icon(): string
    {
        return 'cloud-rain';
    }

    public function entryRoute(): string
    {
        return 'el_nino_watch';
    }
}
