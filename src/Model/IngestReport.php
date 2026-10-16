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

namespace Uhifadhi\ElNino\Model;

/**
 * WHAT ONE INGEST DID, for the answer the producer reads and the line the
 * console prints.
 */
final readonly class IngestReport
{
    public const string EXPOSURE_IMPORTED = 'imported';
    public const string EXPOSURE_KEPT = 'kept';
    public const string EXPOSURE_NONE = 'none';

    /**
     * @param self::EXPOSURE_* $exposure
     */
    public function __construct(
        public bool $duplicate,
        public \DateTimeImmutable $builtAt,
        public int $weeks,
        public string $exposure,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->duplicate ? 'duplicate' : 'stored',
            'duplicate' => $this->duplicate,
            'built_utc' => $this->builtAt->format('Y-m-d H:i'),
            'weeks' => $this->weeks,
            'exposure' => $this->exposure,
        ];
    }
}
