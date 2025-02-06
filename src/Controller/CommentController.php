<?php
namespace App\Controller;

use App\Entity\Vehicle;
use App\Entity\Reservation;
use App\Entity\Comment;
use App\Form\CommentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/comment')]
class CommentController extends AbstractController
{
    #[Route('/new/{vehicleId}', name: 'comment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, int $vehicleId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $vehicle = $em->getRepository(Vehicle::class)->find($vehicleId);
        if (!$vehicle) {
            throw $this->createNotFoundException("Véhicule non trouvé.");
        }

        $reservation = $em->getRepository(Reservation::class)
            ->findOneBy([
                'vehicle' => $vehicle,
                'client'  => $this->getUser()
            ]);
        if (!$reservation) {
            $this->addFlash('error', "Vous devez avoir loué ce véhicule au moins une fois pour laisser un commentaire.");
            return $this->redirectToRoute('vehicle_show', ['id' => $vehicle->getId()]);
        }

        $comment = new Comment();
        $comment->setVehicle($vehicle);
        $comment->setClient($this->getUser());

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($comment);
            $em->flush();
            $this->addFlash('success', "Votre commentaire a été ajouté.");
            return $this->redirectToRoute('vehicle_show', ['id' => $vehicle->getId()]);
        }

        return $this->render('comment/new.html.twig', [
            'commentForm' => $form->createView(),
            'vehicle'     => $vehicle,
        ]);
    }

    #[Route('/{id}/edit', name: 'comment_edit', methods: ['GET', 'POST'])]
    public function edit(Comment $comment, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException("Vous devez être connecté pour modifier ce commentaire.");
        }
        // Seul le propriétaire du commentaire ou un admin peut le modifier.
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && $user->getId() !== $comment->getClient()->getId()) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de modifier ce commentaire.");
        }

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', "Votre commentaire a été modifié avec succès.");
            // Rediriger vers la page précédente (par exemple, le détail de la réservation)
            return $this->redirect($request->headers->get('referer'));
        }

        return $this->render('comment/edit.html.twig', [
            'commentForm' => $form->createView(),
            'comment'     => $comment,
        ]);
    }

    /**
     * Permet à un utilisateur (ou à un admin) de supprimer son commentaire.
     */
    #[Route('/{id}/delete', name: 'comment_delete', methods: ['POST'])]
    public function delete(Comment $comment, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException("Vous devez être connecté pour supprimer ce commentaire.");
        }
        // Seul le propriétaire ou un admin peut supprimer le commentaire.
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && $user->getId() !== $comment->getClient()->getId()) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de supprimer ce commentaire.");
        }
        if ($this->isCsrfTokenValid('delete'.$comment->getId(), $request->request->get('_token'))) {
            $em->remove($comment);
            $em->flush();
            $this->addFlash('success', "Votre commentaire a été supprimé avec succès.");
        } else {
            $this->addFlash('error', "Token CSRF invalide.");
        }
        return $this->redirect($request->headers->get('referer'));
    }
}
