<?php

namespace App\Entity;

use App\Repository\VinylRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VinylRepository::class)
 */
class Vinyl
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $rpm = 45;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $trackFaceA;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $trackFaceAYoutubeID;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $trackFaceB;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $trackFaceBYoutubeID;

    /**
     * @ORM\ManyToMany(targetEntity=Artist::class, inversedBy="vinyls")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private $artists;

    /**
     * @ORM\Column(type="smallint")
     */
    private $quantity = 1;

    /**
     * @ORM\Column(type="smallint")
     */
    private $quantitySold = 0;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $quantityWithCover = 0;

    /**
     * @ORM\OneToMany(targetEntity=InSale::class, mappedBy="vinyl")
     */
    private $inSales;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $notes;

    /**
     * @ORM\OneToMany(targetEntity=Image::class, mappedBy="vinyl")
     */
    private $images;

    public function __construct()
    {
        $this->artists = new ArrayCollection();
        $this->inSales = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRpm(): ?int
    {
        return $this->rpm;
    }

    public function setRpm(int $rpm): self
    {
        $this->rpm = $rpm;

        return $this;
    }

    public function getTrackFaceA(): ?string
    {
        return $this->trackFaceA;
    }

    public function setTrackFaceA(string $trackFaceA): self
    {
        $this->trackFaceA = $trackFaceA;

        return $this;
    }

    public function getTrackFaceAYoutubeID(): ?string
    {
        return $this->trackFaceAYoutubeID;
    }

    public function setTrackFaceAYoutubeID(?string $trackFaceAYoutubeID): self
    {
        $this->trackFaceAYoutubeID = $trackFaceAYoutubeID;

        return $this;
    }

    public function getTrackFaceB(): ?string
    {
        return $this->trackFaceB;
    }

    public function setTrackFaceB(string $trackFaceB): self
    {
        $this->trackFaceB = $trackFaceB;

        return $this;
    }

    public function getTrackFaceBYoutubeID(): ?string
    {
        return $this->trackFaceBYoutubeID;
    }

    public function setTrackFaceBYoutubeID(?string $trackFaceBYoutubeID): self
    {
        $this->trackFaceBYoutubeID = $trackFaceBYoutubeID;

        return $this;
    }

    /**
     * @return Collection|Artist[]
     */
    public function getArtists(): Collection
    {
        return $this->artists;
    }

    public function addArtist(Artist $artist): self
    {
        if (!$this->artists->contains($artist)) {
            $this->artists[] = $artist;
        }

        return $this;
    }

    public function removeArtist(Artist $artist): self
    {
        $this->artists->removeElement($artist);

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantitySold(): ?int
    {
        return $this->quantitySold;
    }

    public function setQuantitySold(int $quantitySold): self
    {
        $this->quantitySold = $quantitySold;

        return $this;
    }

    public function getQuantityAvailable()
    {
        $qty_in_sale = 0;
        foreach ($this->getInSales() as $inSale) {
            $qty_in_sale += $inSale->getQuantity();
        }

        return $this->quantity - $qty_in_sale;
    }

    public function getQuantityWithCover(): ?int
    {
        return $this->quantityWithCover;
    }

    public function setQuantityWithCover(?int $quantityWithCover): self
    {
        $this->quantityWithCover = $quantityWithCover;

        return $this;
    }

    /**
     * @return Collection|InSale[]
     */
    public function getInSales(): Collection
    {
        return $this->inSales;
    }

    public function addInSale(InSale $inSale): self
    {
        if (!$this->inSales->contains($inSale)) {
            $this->inSales[] = $inSale;
            $inSale->setVinyl($this);
        }

        return $this;
    }

    public function removeInSale(InSale $inSale): self
    {
        if ($this->inSales->removeElement($inSale)) {
            // set the owning side to null (unless already changed)
            if ($inSale->getVinyl() === $this) {
                $inSale->setVinyl(null);
            }
        }

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return Collection|Image[]
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Image $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setVinyl($this);
        }

        return $this;
    }

    public function removeImage(Image $image): self
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getVinyl() === $this) {
                $image->setVinyl(null);
            }
        }

        return $this;
    }
}
