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
    // Récupérer les filtres depuis l'URL (query string)
    $marque     = $request->query->get('marque');
    $prixMax    = $request->query->get('prix');
    $disponible = $request->query->get('disponible');

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

    // Pour chaque véhicule, on "analyse" les données en base pour voir s'il y a une réservation active.
    // Une réservation active est celle dont la date de début est passée et dont la date de fin n'est pas encore atteinte.
    $now = new \DateTime();
    foreach ($vehicles as $vehicle) {
        $activeReservation = $em->getRepository(\App\Entity\Reservation::class)
            ->createQueryBuilder('r')
            ->where('r.vehicle = :vehicle')
            ->andWhere('r.dateDebut <= :now')
            ->andWhere('r.dateFin > :now')
            ->setParameter('vehicle', $vehicle)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();

        if ($activeReservation) {
            $vehicle->setDisponible(false);
        } else {
            $vehicle->setDisponible(true);
        }
    }
    // Mettre à jour la base avec ces modifications
    $em->flush();

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
    
    #[Route('/new', name: 'vehicle_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $vehicle = new Vehicle();
        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {

            // Récupérer les fichiers uploadés depuis le champ "images"
            /** @var UploadedFile[] $uploadedFiles */
            $uploadedFiles = $form->get('images')->getData();
            $photosDirectory = $this->getParameter('photos_directory');
            
            if ($uploadedFiles) {
                foreach ($uploadedFiles as $uploadedFile) {
                    if ($uploadedFile instanceof UploadedFile) {
                        // Générer un nom unique pour le fichier
                        $newFilename = uniqid() . '.' . $uploadedFile->guessExtension();
                        // Déplacer le fichier dans le dossier de destination
                        $uploadedFile->move($photosDirectory, $newFilename);
                        
                        // Créer une entité Photo et lier au véhicule
                        $photo = new Photo();
                        $photo->setPath('uploads/photos/' . $newFilename);
                        $photo->setVehicle($vehicle);
                        // Ajout de la photo au véhicule
                        $vehicle->addPhoto($photo);
                    }
                }
            }
            
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

