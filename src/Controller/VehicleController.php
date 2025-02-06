<?php
namespace App\Controller;

use App\Entity\Vehicle;
use App\Form\VehicleType;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vehicle')]
class VehicleController extends AbstractController
{
    #[Route('/', name: 'vehicle_index', methods: ['GET'])]
    public function index(Request $request, VehicleRepository $vehicleRepository, EntityManagerInterface $em): Response
    {
        // Récupérer les filtres depuis l'URL (query string)
        $marque     = $request->query->get('marque');
        $prixMax    = $request->query->get('prix');   // Prix maximum souhaité (€/jour)
        $disponible = $request->query->get('disponible'); // '1' pour disponible, '0' pour indisponible, ou vide pour tous

        // Construction du QueryBuilder pour filtrer les véhicules
        $qb = $vehicleRepository->createQueryBuilder('v');
        if ($marque) {
            $qb->andWhere('v.marque = :marque')
               ->setParameter('marque', $marque);
        }
        if ($prixMax) {
            $qb->andWhere('v.prixJournalier <= :prixMax')
               ->setParameter('prixMax', $prixMax);
        }
        if ($disponible !== null && $disponible !== '') {
            $boolDisponible = ($disponible == '1');
            $qb->andWhere('v.disponible = :disponible')
               ->setParameter('disponible', $boolDisponible);
        }
        $vehicles = $qb->getQuery()->getResult();

        // Préparer les filtres pour pré-remplir le formulaire
        $filters = [
            'marque'     => $marque,
            'prix'       => $prixMax,
            'disponible' => $disponible,
        ];

        $conn = $em->getConnection();
        $sql = 'SELECT DISTINCT marque FROM vehicle ORDER BY marque ASC';
        $stmt = $conn->executeQuery($sql);
        $marquesData = $stmt->fetchAllAssociative();
        $marques = array_map(function ($row) {
            return $row['marque'];
        }, $marquesData);

        return $this->render('vehicle/index.html.twig', [
            'vehicles' => $vehicles,
            'filters'  => $filters,
            'marques'  => $marques,
        ]);
    }

    #[Route('/new', name: 'vehicle_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $vehicle = new Vehicle();
        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($vehicle);
            $em->flush();
            return $this->redirectToRoute('vehicle_index');
        }
        return $this->render('vehicle/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'vehicle_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Vehicle $vehicle, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('vehicle_index');
        }
        return $this->render('vehicle/edit.html.twig', [
            'vehicle' => $vehicle,
            'form'    => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'vehicle_delete', methods: ['POST'])]
    public function delete(Request $request, Vehicle $vehicle, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $vehicle->getId(), $request->request->get('_token'))) {
            $em->remove($vehicle);
            $em->flush();
        }
        return $this->redirectToRoute('vehicle_index');
    }
}
