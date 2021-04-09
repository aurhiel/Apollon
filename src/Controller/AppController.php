<?php

namespace App\Controller;

// Entities
use App\Entity\Artist;
use App\Entity\Vinyl;

// Forms
use App\Form\ArtistType;
use App\Form\VinylType;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Finder\Finder;

/**
  * Require ROLE_VIEWER for *every* controller method in this class.
  *
  * @IsGranted("ROLE_VIEWER")
  */
class AppController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index(Request $request, Security $security, AuthorizationCheckerInterface $authChecker): Response
    {
        $em     = $this->getDoctrine()->getManager();
        $vinyl  = new Vinyl();
        $artist = new Artist();
        $return = array();
        $user   = $security->getUser();

        // Only admin user can add vinyls & artists
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            // 1) Build vinyl & artist forms
            $form_vinyl = $this->createForm(VinylType::class, $vinyl);
            $form_artist = $this->createForm(ArtistType::class, $artist);

            // 2) Handle vinyl & artist forms
            $form_vinyl->handleRequest($request);
            $form_artist->handleRequest($request);

            // 3) Save artist or vinyl
            if ($form_artist->isSubmitted() && $form_artist->isValid()) {
                $r_artist = $em->getRepository(Artist::class);

                // Check if artist is already in database
                if (is_null($r_artist->findOneByName($artist->getName()))) {
                    $em->persist($artist);

                    // 4) Try to save (flush) or clear
                    try {
                        // Flush OK !
                        $em->flush();

                        $return = array(
                            'query_status'    => 'success',
                            'message_status'  => 'Sauvegarde de l\'artiste effectuée avec succès.',
                            'id_entity'       => $artist->getId()
                        );

                        // Clear/reset form
                        $artist       = new Artist();
                        $form_artist  = $this->createForm(ArtistType::class, $artist);
                    } catch (\Exception $e) {
                        // Something goes wrong
                        $em->clear();

                        $return = array(
                            'query_status'    => 'error',
                            'exception'       => $e->getMessage(),
                            'message_status'  => 'Un problème est survenu lors de la sauvegarde de l\'artiste.'
                        );
                    }
                } else {
                    // If artist already exist > add message status & clear/reset form
                    $return = array(
                        'query_status'    => 'notice',
                        'message_status'  => 'L\'artiste "' . $artist->getName() . '" est déjà présent dans la base de données et n\'a donc pas été ajouté.',
                        'id_entity'       => $artist->getId()
                    );
                    $artist       = new Artist();
                    $form_artist  = $this->createForm(ArtistType::class, $artist);
                }
            } elseif ($form_vinyl->isSubmitted() && $form_vinyl->isValid()) {
                $r_vinyl = $em->getRepository(Vinyl::class);
                $vinyl_existing = $r_vinyl->findOneByTrackFaceA($vinyl->getTrackFaceA());
                $already_exist = (!is_null($vinyl_existing) ? ($vinyl_existing->getTrackFaceB() == $vinyl->getTrackFaceB()) : false);

                // Check if vinyl already exist, update his quantity instead
                //  of duplicating the vinyl
                if ($already_exist === true) {
                    // Increment vinyl quantity
                    $vinyl_existing->setQuantity($vinyl_existing->getQuantity() + 1);

                    // 4) Try to update quantity (flush) or clear
                    try {
                        // Flush OK !
                        $em->flush();

                        $return = array(
                            'query_status'    => 'notice',
                            'message_status'  => 'Le vinyle existe déjà en base de donnée,
                              sa quantité a donc été augmentée (quantité: ' . $vinyl_existing->getQuantity() . ').',
                            'id_entity'       => $vinyl_existing->getId()
                        );

                        // Clear/reset form
                        $vinyl      = new Vinyl();
                        $form_vinyl = $this->createForm(VinylType::class, $vinyl);
                    } catch (\Exception $e) {
                        // Something goes wrong
                        $em->clear();

                        $return = array(
                            'query_status'    => 'error',
                            'exception'       => $e->getMessage(),
                            'message_status'  => 'Un problème est survenu lors de la modification de la quantité du vinyle.'
                        );
                    }
                } else {
                    $em->persist($vinyl);

                    // 4) Try to save (flush) or clear
                    try {
                        // Flush OK !
                        $em->flush();

                        $return = array(
                            'query_status'    => 'success',
                            'message_status'  => 'Sauvegarde du vinyle effectuée avec succès.',
                            'id_entity'       => $vinyl->getId()
                        );

                        // Clear/reset form
                        $vinyl      = new Vinyl();
                        $form_vinyl = $this->createForm(VinylType::class, $vinyl);
                    } catch (\Exception $e) {
                        // Something goes wrong
                        $em->clear();

                        $return = array(
                            'query_status'    => 'error',
                            'exception'       => $e->getMessage(),
                            'message_status'  => 'Un problème est survenu lors de la sauvegarde du vinyle.'
                        );
                    }
                }
            }

            // Set flash message if $return has message_status
            if (isset($return['message_status']) && !empty($return['message_status'])) {
                $request->getSession()->getFlashBag()->add(
                    (isset($return['query_status']) ? $return['query_status'] : 'notice'),
                    $return['message_status']
                );
            }
        }

        // Retrieve vinyls
        $r_vinyl = $em->getRepository(Vinyl::class);
        $vinyls = $r_vinyl->findAll();

        return $this->render('app.html.twig', [
            'user'          => $user,
            'user_emoji'    => $this->getUserEmoji($user->getUsername()),
            'form_artist'   => isset($form_artist) ? $form_artist->createView() : null,
            'form_vinyl'    => isset($form_vinyl) ? $form_vinyl->createView() : null,
            'vinyls'        => $vinyls,
            'total_vinyls'  => $r_vinyl->countAll(),
        ]);
    }

    /**
     * @Route("/importer-csv", name="import_csv")
     */
    public function import_csv(Request $request)
    {
        // If asked > launch import
        if ($request->request->get('import-launch') !== null) {
            $em         = $this->getDoctrine()->getManager();
            $r_artist   = $em->getRepository(Artist::class);
            $r_vinyl    = $em->getRepository(Vinyl::class);
            $vinyls_csv = $this->parseCSV();

            // Clear database (vinyls & artists)
            if ($request->request->get('import-clear-db') === 'on') {
                $r_vinyl->resetDatabase();
                $r_artist->resetDatabase();
            }

            // Import vinyls from CSV file if not empty
            if (!empty($vinyls_csv)) {

                // Loop on CSV vinyls
                foreach ($vinyls_csv as $data) {
                    $vinyl = new Vinyl();

                    // Set new vinyl fields
                    // // Quantity
                    $vinyl->setQuantity((int)$data[0]);
                    // // Tracks
                    $vinyl->setTrackFaceA($data[1]);
                    $vinyl->setTrackFaceB($data[2]);
                    // // Artist
                    $d_artists = explode('/', $data[3]);
                    foreach ($d_artists as $artist_name) {
                        // If artist already exist, retrieve it, or create a new one
                        $artist = $r_artist->findOneByName(trim($artist_name));
                        if (is_null($artist)) {
                            $artist = new Artist();
                            $artist->setName(trim($artist_name));

                            // Persist artist & flush (in order to retrieve --
                            //  -- later with findOneByName())
                            $em->persist($artist);
                            $em->flush();
                        }

                        // Add artist to current vinyl
                        $vinyl->addArtist($artist);
                    }

                    // Persist vinyl
                    $em->persist($vinyl);
                }

                // Try to save all new artists & vinyls into database
                try {
                    // Flush OK !
                    $em->flush();

                    $return = array(
                        'query_status'    => 'success',
                        'message_status'  => 'Importation des vinyles effectuée avec succès.',
                        'id_entity'       => $vinyl->getId()
                    );
                } catch (\Exception $e) {
                    // Something goes wrong
                    $em->clear();

                    $return = array(
                        'query_status'    => 'danger',
                        'exception'       => $e->getMessage(),
                        'message_status'  => 'Un problème est survenu lors de l\'importation des vinyles.'
                    );
                }

                // Set $return message
                $request->getSession()->getFlashBag()->add($return['query_status'], $return['message_status']);
            } else {
                // Set message if file is empty / not found
                $request->getSession()->getFlashBag()->add('notice',
                  'Le fichier CSV est vide ou n\'a pas été trouvé (localisation: "' . $this->csvParsingOptions['finder_in'] . '' . $this->csvParsingOptions['finder_name'] . '").');
            }
        }

        return $this->render('import-csv.html.twig', [
            // 'form_artist' => $form_artist->createView(),
            // 'form_vinyl'  => $form_vinyl->createView(),
            // 'vinyls'      => $vinyls,
        ]);
    }

    /**
     * @Route("/vinyles/{id}/supprimer", name="vinyl_delete")
     */
    public function vinyl_delete($id, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            $em = $this->getDoctrine()->getManager();

            // Retrieve item to delete
            $repo   = $em->getRepository(Vinyl::class);
            $entity = $repo->findOneById($id);

            if ($entity !== null) {
                // Delete entity & flush
                $em->remove($entity);
                $em->flush();

                // Set success message
                $request->getSession()->getFlashBag()->add('success',
                  'Le vinyle a bien été supprimé.');
            } else {
                $request->getSession()->getFlashBag()->add('error',
                  'Le vinyle avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.');
            }
        }

        // No direct access
        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/vinyles/{id}/quantite/{type_update}", name="vinyl_update_quantity")
     */
    public function vinyl_update_quantity($id, $type_update, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            $em = $this->getDoctrine()->getManager();

            // Retrieve item to update quantity
            $repo   = $em->getRepository(Vinyl::class);
            $entity = $repo->findOneById($id);

            if ($entity !== null) {
                // Update new vinyl quantity & persist it
                $entity->setQuantity($entity->getQuantity() + (($type_update == '-1') ? -1 : 1));
                $em->persist($entity);

                // Try to save (flush) or clear
                try {
                    // Flush OK !
                    $em->flush();

                    $return_data = array(
                        'query_status'    => 1,
                        'message_status'  => 'Modification de la quantité effectuée avec succès.',
                        'id_entity'       => $entity->getId(),
                        'new_quantity'    => $entity->getQuantity(),
                        'total_vinyls'    => $repo->countAll()
                    );
                } catch (\Exception $e) {
                    // Something goes wrong
                    $em->clear();

                    $return_data = array(
                        'query_status'    => 0,
                        'exception'       => $e->getMessage(),
                        'message_status'  => 'Un problème est survenu lors de la modification de la quantité.'
                    );
                }
            } else {
                // Data to return/display
                $return_data = array(
                    'query_status'      => 0,
                    'slug_status'       => 'error',
                    'message_status'    => 'Le vinyle avec pour ID: "' . $id . '" n\'existe pas en base de données.'
                );
            }

            // Display return data as JSON when using AJAX or redirect to home
            if ($request->isXmlHttpRequest()) {
                return $this->json($return_data);
            } else {
                // Set message in flashbag on direct access
                $request->getSession()->getFlashBag()->add($return_data['slug_status'], $return_data['message_status']);

                // No direct access
                return $this->redirectToRoute('home');
            }
        } else {
            // Need admin roles
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/artistes", name="artists")
     */
    public function artists(Security $security)
    {
        $em   = $this->getDoctrine()->getManager();
        $user = $security->getUser();

        // Retrieve artists
        $r_artist = $em->getRepository(Artist::class);
        $artists  = $r_artist->findAll();

        return $this->render('artists.html.twig', [
            'user'        => $user,
            'user_emoji'  => $this->getUserEmoji($user->getUsername()),
            'artists'     => $artists,
        ]);
    }

    /**
     * @Route("/artistes/{id}/supprimer", name="artist_delete")
     */
    public function artist_delete($id, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            $em = $this->getDoctrine()->getManager();

            // Retrieve item to delete
            $repo   = $em->getRepository(Artist::class);
            $entity = $repo->findOneById($id);

            if ($entity !== null) {
                // Delete entity & flush
                $em->remove($entity);
                $em->flush();

                // Set success message
                $request->getSession()->getFlashBag()->add('success',
                  'L\'artiste a bien été supprimé.');
            } else {
                $request->getSession()->getFlashBag()->add('error',
                  'L\'artiste avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.');
            }
        }

        // No direct access
        return $this->redirectToRoute('artists');
    }

    private $csvParsingOptions = array(
        'finder_in'       => '../public/uploads/',
        'finder_name'     => 'vinyls.csv',
        'ignoreFirstLine' => true,
        'delimiter'       => ','
    );
    /**
     * Parse a csv file
     *
     * @return array
     */
    private function parseCSV()
    {
        $ignoreFirstLine = $this->csvParsingOptions['ignoreFirstLine'];

        $finder = new Finder();
        $finder->files()
            ->in($this->csvParsingOptions['finder_in'])
            ->name($this->csvParsingOptions['finder_name'])
        ;
        foreach ($finder as $file) { $csv = $file; }

        $rows = array();
        if (($handle = fopen($csv->getRealPath(), "r")) !== FALSE) {
            $i = 0;
            while (($data = fgetcsv($handle, null, $this->csvParsingOptions['delimiter'])) !== FALSE) {
                $i++;
                if ($ignoreFirstLine && $i == 1) { continue; }
                $rows[] = $data;
            }
            fclose($handle);
        }

        return $rows;
    }

    private function getUserEmoji($username)
    {
        $user_emoji = null;
        switch ($username) {
          case 'losovia':
            $user_emoji = '💕';
            break;
          case 'anderson':
            $user_emoji = '☕';
            break;
        }

        return $user_emoji;
    }
}
