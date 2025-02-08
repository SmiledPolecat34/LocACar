<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PhotoRepository;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    // Stocke l'emplacement (chemin ou URL) de la photo
    #[ORM\Column(type:"string", length:255)]
    private ?string $path = null;

    // Position de la photo pour l'ordre d'affichage (par défaut 0)
    #[ORM\Column(type:"integer", options: ["default" => 0])]
    private ?int $position = 0;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Vehicle::class, inversedBy:"photos")]
    #[ORM\JoinColumn(nullable:false, onDelete:"CASCADE")]
    private ?\App\Entity\Vehicle $vehicle = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getVehicle(): ?\App\Entity\Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(\App\Entity\Vehicle $vehicle): self
    {
        $this->vehicle = $vehicle;
        return $this;
    }
}
