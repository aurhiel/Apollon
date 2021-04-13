<?php

namespace App\Entity;

use App\Repository\ArtistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ArtistRepository::class)
 */
class Artist
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\ManyToMany(targetEntity=Vinyl::class, mappedBy="artists")
     */
    private $vinyls;

    public function __construct()
    {
        $this->vinyls = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|Vinyl[]
     */
    public function getVinyls(): Collection
    {
        return $this->vinyls;
    }

    public function addVinyl(Vinyl $vinyl): self
    {
        if (!$this->vinyls->contains($vinyl)) {
            $this->vinyls[] = $vinyl;
            $vinyl->addArtist($this);
        }

        return $this;
    }

    public function removeVinyl(Vinyl $vinyl): self
    {
        if ($this->vinyls->removeElement($vinyl)) {
            $vinyl->removeArtist($this);
        }

        return $this;
    }

    public function getVinylsQuantity($value='')
    {
        $quantity = 0;
        foreach ($this->vinyls as $vinyl) {
            $quantity += $vinyl->getQuantity();
        }

        return $quantity;
    }
}
