<?php

namespace App\Entity;

use App\Repository\AdvertRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AdvertRepository::class)
 */
class Advert
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
    private $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    private $name;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $price;

    /**
     * @ORM\OneToMany(targetEntity=InSale::class, mappedBy="advert")
     */
    private $inSales;

    /**
     * @ORM\OneToMany(targetEntity=Image::class, mappedBy="advert")
     */
    private $images;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isSold = false;

    public function __construct()
    {
        $this->inSales = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

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
            $inSale->setAdvert($this);
        }

        return $this;
    }

    public function removeInSale(InSale $inSale): self
    {
        if ($this->inSales->removeElement($inSale)) {
            // set the owning side to null (unless already changed)
            if ($inSale->getAdvert() === $this) {
                $inSale->setAdvert(null);
            }
        }

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
            $image->setAdvert($this);
        }

        return $this;
    }

    public function removeImage(Image $image): self
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getAdvert() === $this) {
                $image->setAdvert(null);
            }
        }

        return $this;
    }

    public function getIsSold(): ?bool
    {
        return $this->isSold;
    }

    public function setIsSold(?bool $isSold): self
    {
        $this->isSold = $isSold;

        return $this;
    }

    public function getKey(): ?string
    {
        return null !== $this->name ? substr(md5($this->name), 0, 14) : null ;
    }

    public function getVinylsByArtists(): array
    {
        $vinylsByArtists = [];

        foreach ($this->inSales as $inSale) {
            $vinyl = $inSale->getVinyl();
            $artistsNames = [];
            foreach ($vinyl->getArtists() as $artist) {
                $artistsNames[] = $artist->getName();
            }
            $artistsNames = implode(', ', $artistsNames);

            if (false === array_key_exists($artistsNames, $vinylsByArtists)) {
                $vinylsByArtists[$artistsNames] = [];
            }

            $vinylsByArtists[$artistsNames][] = sprintf(
                '%s / %s',
                $vinyl->getTrackFaceA(),
                $vinyl->getTrackFaceB()
            );
        }

        return $vinylsByArtists;
    }
}
