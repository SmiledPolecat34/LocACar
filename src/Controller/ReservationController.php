<?php
namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Vehicle;
use App\Entity\Comment;
use App\Form\ReservationType;
use App\Form\CommentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'reservation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException("Vous devez être connecté pour voir vos réservations.");
        }
        // Si admin, on affiche toutes les réservations ; sinon, uniquement celles du client connecté
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $reservations = $em->getRepository(Reservation::class)->findAll();
        } else {
            $reservations = $em->getRepository(Reservation::class)->findBy(['client' => $user]);
        }
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/new/{vehicleId}', name: 'reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, int $vehicleId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $vehicle = $em->getRepository(Vehicle::class)->find($vehicleId);
        if (!$vehicle) {
            throw $this->createNotFoundException("Véhicule non trouvé");
        }

        $reservation = new Reservation();
        $reservation->setVehicle($vehicle);
        $reservation->setClient($user);
        $reservationForm = $this->createForm(ReservationType::class, $reservation);
        $reservationForm->handleRequest($request);

        if ($reservationForm->isSubmitted() && $reservationForm->isValid()) {
            $newDateDebut = $reservation->getDateDebut();
            $newDateFin   = $reservation->getDateFin();

            // Vérification : si le créneau demandé chevauche une réservation existante pour ce véhicule
            $existingReservation = $em->getRepository(Reservation::class)
                ->createQueryBuilder('r')
                ->where('r.vehicle = :vehicle')
                ->andWhere('r.dateDebut < :newDateFin')
                ->andWhere('r.dateFin > :newDateDebut')
                ->setParameter('vehicle', $vehicle)
                ->setParameter('newDateDebut', $newDateDebut)
                ->setParameter('newDateFin', $newDateFin)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingReservation) {
                $this->addFlash('error', "Le véhicule est déjà réservé dans ce créneau.");
                return $this->redirectToRoute('reservation_show', ['id' => $existingReservation->getId()]);
            }

            $nbJours = $newDateDebut->diff($newDateFin)->days;
            $reservation->setPrixTotal($nbJours * $vehicle->getPrixJournalier());
            // Ici, on ne modifie pas le champ global "disponible" du véhicule.
            $em->persist($reservation);
            $em->flush();

            $this->addFlash('success', "Réservation effectuée du " .
                $newDateDebut->format('d/m/Y') . " au " . $newDateFin->format('d/m/Y'));
            return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
        }

        // Pour la page de création, on affiche aussi les commentaires existants pour ce véhicule
        $commentRepo = $em->getRepository(Comment::class);
        $comments = $commentRepo->findBy(['vehicle' => $vehicle]);
        $total = 0;
        foreach ($comments as $c) {
            $total += $c->getNote();
        }
        $average = count($comments) > 0 ? round($total / count($comments), 1) : null;

        // Vérifier si l'utilisateur a déjà laissé un commentaire pour ce véhicule
        $existingComment = $commentRepo->findOneBy([
            'vehicle' => $vehicle,
            'client'  => $user,
        ]);
        $commentFormView = null;
        if (!$existingComment) {
            $comment = new Comment();
            $comment->setVehicle($vehicle);
            $comment->setClient($user);
            $commentForm = $this->createForm(CommentType::class, $comment);
            $commentForm->handleRequest($request);
            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $em->persist($comment);
                $em->flush();
                $this->addFlash('success', "Votre commentaire a été ajouté.");
                return $this->redirectToRoute('reservation_new', ['vehicleId' => $vehicle->getId()]);
            }
            $commentFormView = $commentForm->createView();
        }

        return $this->render('reservation/new.html.twig', [
            'reservationForm' => $reservationForm->createView(),
            'commentForm'     => $commentFormView,
            'vehicle'         => $vehicle,
            'comments'        => $comments,
            'average'         => $average,
            'existingComment' => $existingComment,
        ]);
    }

    #[Route('/{id}', name: 'reservation_show', methods: ['GET', 'POST'])]
    public function showReservation(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof \App\Entity\User) {
            $this->addFlash('error', "Vous devez être connecté pour accéder à cette réservation.");
            return $this->redirectToRoute('app_login');
        }
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && $user->getId() !== $reservation->getClient()->getId()) {
            $this->addFlash('error', "Vous n'avez pas accès à cette réservation.");
            return $this->redirectToRoute('reservation_index');
        }

        $comments = $em->getRepository(Comment::class)->findBy(['vehicle' => $reservation->getVehicle()]);
        $total = 0;
        foreach ($comments as $c) {
            $total += $c->getNote();
        }
        $average = count($comments) > 0 ? round($total / count($comments), 1) : null;

        // Vérifier si l'utilisateur a déjà commenté ce véhicule
        $existingComment = $em->getRepository(Comment::class)->findOneBy([
            'vehicle' => $reservation->getVehicle(),
            'client'  => $user,
        ]);

        $commentFormView = null;
        if (!$existingComment) {
            $comment = new Comment();
            $comment->setVehicle($reservation->getVehicle());
            $comment->setClient($user);
            $commentForm = $this->createForm(CommentType::class, $comment);
            $commentForm->handleRequest($request);
            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $em->persist($comment);
                $em->flush();
                $this->addFlash('success', "Votre commentaire a été ajouté.");
                return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
            }
            $commentFormView = $commentForm->createView();
        }

        // Récupérer toutes les réservations pour ce véhicule (pour afficher les créneaux réservés)
        $vehicleReservations = $em->getRepository(Reservation::class)
            ->findBy(['vehicle' => $reservation->getVehicle()], ['dateDebut' => 'ASC']);

        return $this->render('reservation/show.html.twig', [
            'reservation'         => $reservation,
            'comments'            => $comments,
            'average'             => $average,
            'commentForm'         => $commentFormView,
            'existingComment'     => $existingComment,
            'vehicleReservations' => $vehicleReservations,
        ]);
    }

    #[Route('/{id}/edit', name: 'reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException("Vous devez être connecté pour modifier une réservation.");
        }
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && $user->getId() !== $reservation->getClient()->getId()) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à modifier cette réservation.");
        }
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && new \DateTime() >= $reservation->getDateDebut()) {
            $this->addFlash('error', "Vous ne pouvez pas modifier une réservation déjà commencée.");
            return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
        }

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nbJours = $reservation->getDateDebut()->diff($reservation->getDateFin())->days;
            $reservation->setPrixTotal($nbJours * $reservation->getVehicle()->getPrixJournalier());
            $em->flush();
            $this->addFlash('success', "Réservation modifiée avec succès.");
            return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/edit.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException("Vous devez être connecté pour annuler une réservation.");
        }
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && $user->getId() !== $reservation->getClient()->getId()) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à annuler cette réservation.");
        }
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && new \DateTime() >= $reservation->getDateDebut()) {
            $this->addFlash('error', "Vous ne pouvez pas annuler une réservation déjà commencée.");
            return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
        }

        if ($this->isCsrfTokenValid('cancel' . $reservation->getId(), $request->request->get('_token'))) {
            $reservation->getVehicle()->setDisponible(true);
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', "Réservation annulée avec succès.");
        } else {
            $this->addFlash('error', "Token CSRF invalide.");
        }
    
        return $this->redirectToRoute('reservation_index');
    }
}
