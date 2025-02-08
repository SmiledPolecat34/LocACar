<?php
namespace App\Controller;

use App\Entity\Vehicle;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/favorite')]
class FavoriteController extends AbstractController
{
    #[Route('/toggle/{id}', name: 'favorite_toggle', methods: ['GET'])]
    public function toggle(Vehicle $vehicle, EntityManagerInterface $em): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', "Vous devez être connecté pour gérer vos favoris.");
            return $this->redirectToRoute('app_login');
        }
        if ($user->getFavorites()->contains($vehicle)) {
            $user->removeFavorite($vehicle);
            $this->addFlash('success', "Véhicule retiré des favoris.");
        } else {
            $user->addFavorite($vehicle);
            $this->addFlash('success', "Véhicule ajouté aux favoris.");
        }
        $em->flush();
        return $this->redirectToRoute('vehicle_index');
    }

    #[Route('/list', name: 'favorite_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', "Vous devez être connecté pour accéder à vos favoris.");
            return $this->redirectToRoute('app_login');
        }
        
        $favorites = $user->getFavorites();
        $reservationMapping = [];
        // Pour chaque véhicule favori, rechercher la réservation effectuée par l'utilisateur
        foreach ($favorites as $vehicle) {
            $reservation = $em->getRepository(Reservation::class)
                ->findOneBy([
                    'vehicle' => $vehicle,
                    'client'  => $user
                ]);
            // Si une réservation existe, on stocke son ID, sinon on met null
            $reservationMapping[$vehicle->getId()] = $reservation ? $reservation->getId() : null;
        }
        
        return $this->render('favorite/list.html.twig', [
            'favorites' => $favorites,
            'reservationMapping' => $reservationMapping,
        ]);
    }

    #[Route('/remove/{id}', name: 'favorite_remove', methods: ['GET'])]
    public function remove(Vehicle $vehicle, EntityManagerInterface $em): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', "Vous devez être connecté pour retirer un véhicule de vos favoris.");
            return $this->redirectToRoute('app_login');
        }
        $user->removeFavorite($vehicle);
        $em->flush();
        $this->addFlash('success', "Véhicule retiré des favoris.");
        return $this->redirectToRoute('vehicle_index');
    }
}
