<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\Column(type:"string", length:255)]
    private ?string $marque = null;

    #[ORM\Column(type:"string", length:255)]
    private ?string $immatriculation = null;

    #[ORM\Column(type:"float")]
    private ?float $prixJournalier = null;

    #[ORM\Column(type:"boolean")]
    private ?bool $disponible = true;

    #[ORM\OneToMany(mappedBy:"vehicle", targetEntity:Photo::class, cascade:["persist","remove"], orphanRemoval:true)]
    private Collection $photos;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): self
    {
        $this->marque = $marque;
        return $this;
    }

    public function getImmatriculation(): ?string
    {
        return $this->immatriculation;
    }

    public function setImmatriculation(string $immatriculation): self
    {
        $this->immatriculation = $immatriculation;
        return $this;
    }

    public function getPrixJournalier(): ?float
    {
        return $this->prixJournalier;
    }

    public function setPrixJournalier(float $prixJournalier): self
    {
        $this->prixJournalier = $prixJournalier;
        return $this;
    }

    public function getDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): self
    {
        $this->disponible = $disponible;
        return $this;
    }

    /**
     * @return Collection|Photo[]
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): self
    {
        if (!$this->photos->contains($photo)) {
            $this->photos[] = $photo;
            $photo->setVehicle($this);
        }
        return $this;
    }

    public function removePhoto(Photo $photo): self
    {
        if ($this->photos->removeElement($photo)) {
            if ($photo->getVehicle() === $this) {
                $photo->setVehicle(null);
            }
        }
        return $this;
    }
}
