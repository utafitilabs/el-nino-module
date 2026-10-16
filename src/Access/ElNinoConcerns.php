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

namespace Uhifadhi\ElNino\Access;

use Uhifadhi\Contracts\Access\Concern;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Contracts\Access\ScopeKind;
use Uhifadhi\Contracts\Access\Verb;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;

/**
 * WHAT THERE IS TO HAVE A PERMISSION ABOUT IN THIS MODULE: one concern.
 *
 * Reading the watch is `el-nino.read`; naming the producer that feeds an
 * area, issuing the token it posts with, and writing the area's own words are
 * `el-nino.configure`. Declaring grants nobody anything — the positions page
 * gains two cells, and who ticks them is the organization's business.
 */
final readonly class ElNinoConcerns implements ConcernSourceInterface
{
    /** The key, spelt once, so a gate, a door and a test cannot disagree. */
    public const string WATCH = 'el-nino';

    public function declaredBy(): string
    {
        return 'El Niño watch';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::WATCH,
            label: 'El Niño watch',
            description: 'The weekly bulletin: the Pacific, the outlook, the rain over the area and the flood exposure — and which producer feeds it.',
            verbs: [Verb::Read, Verb::Configure],
            scopeKinds: [ScopeKind::Organization, ScopeKind::Area],
            moduleSlug: ElNinoModuleProvider::SLUG,
        );
    }
}
