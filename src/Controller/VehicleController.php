<?php
namespace App\Controller;

use App\Entity\Vehicle;
use App\Form\VehicleType;
use App\Entity\Photo;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/vehicle')]
class VehicleController extends AbstractController
{
    #[Route('/', name: 'vehicle_index', methods: ['GET'])]
    public function index(Request $request, VehicleRepository $vehicleRepository, EntityManagerInterface $em): Response
    {

        // Exemple de récupération des filtres et des marques (comme précédemment)
        $marque     = $request->query->get('marque');
        $prixMax    = $request->query->get('prix');
        $disponible = $request->query->get('disponible');

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

        // Préparer les filtres pour le formulaire
        $filters = [
            'marque'     => $marque,
            'prix'       => $prixMax,
            'disponible' => $disponible,
        ];

        // Récupérer les marques distinctes
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

    #[Route('/new', name: 'vehicle_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $vehicle = new Vehicle();
        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Récupérer la liste des fichiers uploadés via le champ "images"
            // qui est non mappé (mapped => false)
            $uploadedFiles = $form->get('images')->getData();
            // Paramètre photos_directory défini dans config/services.yaml
            $photosDirectory = $this->getParameter('photos_directory');

            if ($uploadedFiles) {
                // On parcourt chaque fichier
                foreach ($uploadedFiles as $uploadedFile) {
                    if ($uploadedFile instanceof UploadedFile) {
                        // Générer un nom de fichier unique
                        $newFilename = uniqid().'.'.$uploadedFile->guessExtension();
                        // Déplacer le fichier dans le répertoire cible
                        $uploadedFile->move($photosDirectory, $newFilename);

                        // Créer une entité Photo, et la lier au Vehicle
                        $photo = new Photo();
                        $photo->setPath('uploads/photos/' . $newFilename);
                        $photo->setVehicle($vehicle);

                        $vehicle->addPhoto($photo);
                    }
                }
            }

            // Persister le véhicule + flush
            $em->persist($vehicle);
            $em->flush();
            return $this->redirectToRoute('vehicle_index');
        }

        return $this->render('vehicle/new.html.twig', [
            'form' => $form->createView(),
            'vehicle' => $vehicle,
        ]);
    }

    #[Route('/{id}/edit', name: 'vehicle_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Vehicle $vehicle, EntityManagerInterface $em): Response
    {
        // Exemple de vérification d'une réservation active pour verrouiller le champ "disponible"
        $now = new \DateTime();
        $activeReservation = $em->getRepository(\App\Entity\Reservation::class)
            ->createQueryBuilder('r')
            ->where('r.vehicle = :vehicle')
            ->andWhere('r.dateFin > :now')
            ->setParameter('vehicle', $vehicle)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();

        $options = [];
        if ($activeReservation) {
            $options['disponible_disabled'] = true;
            $this->addFlash('info', "Le statut 'indisponible' est verrouillé tant que la réservation en cours n'est pas terminée.");
        }

        $form = $this->createForm(VehicleType::class, $vehicle, $options);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Traitement éventuel des uploads de photos similaire à la méthode new
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

    #[Route('/{id}', name: 'vehicle_show', methods: ['GET'])]
    public function show(Vehicle $vehicle): Response
    {
        return $this->render('vehicle/show.html.twig', [
            'vehicle' => $vehicle,
        ]);
    }

}

