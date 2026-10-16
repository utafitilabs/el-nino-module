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

/**
 * ONE HOMESTEAD THE ANALYSIS ROUTED: where it is, the zone and the village it
 * belongs to, and whether it keeps road access once the flagged roads go. A
 * producer that routes people one household at a time sends these; one that
 * does not sends none, and the page counts destinations instead.
 */
#[ORM\Entity]
#[ORM\Table(name: 'el_nino_boma')]
#[ORM\Index(name: 'idx_el_nino_boma_run_status', columns: ['run_id', 'status'])]
class Boma
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: ExposureRun::class, inversedBy: 'bomas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ExposureRun $run;

    #[ORM\Column(type: 'point')]
    private string $geom;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $zone;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $village;

    #[ORM\Column(length: 30)]
    private string $status;

    /** Minutes to the nearest response base on the normal network. */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $minNormal;

    /** The same, with the flagged roads removed; null where no route remains. */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $minFlooded;

    public function __construct(ExposureRun $run, string $geom, ?string $zone, ?string $village, string $status, ?float $minNormal, ?float $minFlooded)
    {
        $this->run = $run;
        $this->geom = $geom;
        $this->zone = $zone;
        $this->village = $village;
        $this->status = $status;
        $this->minNormal = $minNormal;
        $this->minFlooded = $minFlooded;
        $run->addBoma($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRun(): ExposureRun
    {
        return $this->run;
    }

    public function getGeom(): string
    {
        return $this->geom;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function getVillage(): ?string
    {
        return $this->village;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isCutOff(): bool
    {
        return Destination::CUT_OFF === $this->status;
    }

    public function getMinNormal(): ?float
    {
        return $this->minNormal;
    }

    public function getMinFlooded(): ?float
    {
        return $this->minFlooded;
    }
}
