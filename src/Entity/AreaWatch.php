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

namespace Uhifadhi\ElNino\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\ElNino\Repository\AreaWatchRepository;

/**
 * HOW ONE AREA IS WATCHED: which producer feeds it, the token that producer
 * posts with, and the words the area adds to the page in its own voice.
 *
 * One row per area, made the first time somebody opens the producer section.
 * The token is kept as a hash and shown once, the way the team keeps a field
 * token; the producer's slug is what a bulletin must name to be accepted here.
 */
#[ORM\Entity(repositoryClass: AreaWatchRepository::class)]
#[ORM\Table(name: 'el_nino_watch')]
#[ORM\UniqueConstraint(name: 'uniq_el_nino_watch_area', columns: ['area_id'])]
#[ORM\Index(name: 'idx_el_nino_watch_token', columns: ['token_hash'])]
class AreaWatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: AreaOfInterest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AreaOfInterest $area;

    /** The producer's own name for the area, which every file it sends carries as `area.slug`. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $producerSlug = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tokenHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokenIssuedAt = null;

    /** Where the analysis behind the exposure layers can be read, if the area wants to link it. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $analysisUrl = null;

    /** The area's own record, in its own words: what past El Niño seasons did on this ground. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $areaRecord = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastIngestAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(AreaOfInterest $area, ?\DateTimeImmutable $now = null)
    {
        $this->uuid = Uuid::v7();
        $this->area = $area;
        $this->createdAt = $now ?? new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getArea(): AreaOfInterest
    {
        return $this->area;
    }

    public function getProducerSlug(): ?string
    {
        return $this->producerSlug;
    }

    public function setProducerSlug(?string $slug): static
    {
        $slug = null === $slug ? null : trim($slug);
        $this->producerSlug = '' === $slug ? null : $slug;
        $this->touch();

        return $this;
    }

    public function hasToken(): bool
    {
        return null !== $this->tokenHash;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function getTokenIssuedAt(): ?\DateTimeImmutable
    {
        return $this->tokenIssuedAt;
    }

    /** Keeps the hash, never the token: the token is handed back once and forgotten here. */
    public function holdToken(string $hash, \DateTimeImmutable $at): static
    {
        $this->tokenHash = $hash;
        $this->tokenIssuedAt = $at;
        $this->touch($at);

        return $this;
    }

    public function withdrawToken(\DateTimeImmutable $at): static
    {
        $this->tokenHash = null;
        $this->tokenIssuedAt = null;
        $this->touch($at);

        return $this;
    }

    public function getAnalysisUrl(): ?string
    {
        return $this->analysisUrl;
    }

    public function setAnalysisUrl(?string $url): static
    {
        $url = null === $url ? null : trim($url);
        $this->analysisUrl = '' === $url ? null : $url;
        $this->touch();

        return $this;
    }

    public function getAreaRecord(): ?string
    {
        return $this->areaRecord;
    }

    public function setAreaRecord(?string $record): static
    {
        $record = null === $record ? null : trim($record);
        $this->areaRecord = '' === $record ? null : $record;
        $this->touch();

        return $this;
    }

    public function getLastIngestAt(): ?\DateTimeImmutable
    {
        return $this->lastIngestAt;
    }

    public function markIngested(\DateTimeImmutable $at): static
    {
        $this->lastIngestAt = $at;
        $this->touch($at);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(?\DateTimeImmutable $at = null): void
    {
        $this->updatedAt = $at ?? new \DateTimeImmutable();
    }
}
