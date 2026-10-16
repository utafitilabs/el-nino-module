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

namespace Uhifadhi\ElNino\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * Everything tagged as devkit content in this kernel, so a test can run what
 * `fixtures:seed` would without the devkit installed.
 */
final class CollectedContentProviders
{
    /** @var list<ContentProviderInterface> */
    private array $providers;

    /**
     * @param iterable<ContentProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = array_values([...$providers]);
    }

    /** @return list<ContentProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    public function byKey(string $key): ?ContentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->key() === $key) {
                return $provider;
            }
        }

        return null;
    }
}
