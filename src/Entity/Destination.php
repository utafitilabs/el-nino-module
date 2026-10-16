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
 * A PLACE THE ANALYSIS ASKED ABOUT: a village, a post, a lodge, a hospital —
 * with whether it is cut off once the flagged roads go, and how many people
 * live within reach of it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'el_nino_destination')]
#[ORM\Index(name: 'idx_el_nino_destination_run', columns: ['run_id'])]
class Destination
{
    public const string CUT_OFF = 'cut off';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: ExposureRun::class, inversedBy: 'destinations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ExposureRun $run;

    #[ORM\Column(type: 'point')]
    private string $geom;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $kind;

    /** `cut off`, or whatever else the producer says — the page only asks about the first. */
    #[ORM\Column(length: 30)]
    private string $status;

    /** People within reach, or null where the producer did not know. */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $population;

    public function __construct(ExposureRun $run, string $geom, string $name, ?string $kind, string $status, ?int $population)
    {
        $this->run = $run;
        $this->geom = $geom;
        $this->name = $name;
        $this->kind = $kind;
        $this->status = $status;
        $this->population = null !== $population && $population < 0 ? null : $population;
        $run->addDestination($this);
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isCutOff(): bool
    {
        return self::CUT_OFF === $this->status;
    }

    public function getPopulation(): ?int
    {
        return $this->population;
    }
}
