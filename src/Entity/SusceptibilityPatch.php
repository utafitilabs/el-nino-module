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
 * ONE PATCH OF ONE SUSCEPTIBILITY CLASS, as the producer polygonised it:
 * class 1 low to class 4 very high, a relative ranking inside the area and
 * never a depth or a forecast.
 */
#[ORM\Entity]
#[ORM\Table(name: 'el_nino_susceptibility')]
#[ORM\Index(name: 'idx_el_nino_susceptibility_run_class', columns: ['run_id', 'klass'])]
class SusceptibilityPatch
{
    public const int VERY_HIGH = 4;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: ExposureRun::class, inversedBy: 'patches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ExposureRun $run;

    #[ORM\Column(type: 'polygon')]
    private string $geom;

    #[ORM\Column(name: 'klass', type: 'smallint')]
    private int $class;

    #[ORM\Column(length: 20)]
    private string $name;

    public function __construct(ExposureRun $run, string $geom, int $class, string $name)
    {
        $this->run = $run;
        $this->geom = $geom;
        $this->class = max(1, min(self::VERY_HIGH, $class));
        $this->name = $name;
        $run->addPatch($this);
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

    public function getClass(): int
    {
        return $this->class;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
