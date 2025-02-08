<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PhotoRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    // Emplacement (URL ou chemin) de la photo, persisté
    #[ORM\Column(type:"string", length:255)]
    private ?string $path = null;

    // Position de la photo (ordre d'affichage)
    #[ORM\Column(type:"integer", options: ["default" => 0])]
    private ?int $position = 0;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Vehicle::class, inversedBy:"photos")]
    #[ORM\JoinColumn(nullable:false, onDelete:"CASCADE")]
    private ?\App\Entity\Vehicle $vehicle = null;

    // Propriété non mappée pour l'upload (ne sera pas persistée en base)
    private ?UploadedFile $file = null;

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

    // Getter et setter pour la propriété file
    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): self
    {
        $this->file = $file;
        return $this;
    }
}
