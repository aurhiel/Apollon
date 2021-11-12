<?php

namespace App\Controller;

// Entities
use App\Entity\Artist;
use App\Entity\Vinyl;
use App\Entity\Image;
use App\Entity\Advert;
use App\Entity\InSale;

// Forms
use App\Form\ArtistType;
use App\Form\VinylType;
use App\Form\AdvertType;

// Services
use App\Service\FileUploader;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppController extends AbstractController
{
    const G_AUTH_KEY = 'AIzaSyAa0biHVpJuov67kzhKwZo2CANor-Z8H3w';

    const G_SEARCH_CX = 'ac4fe16cc213c6af9';

    /**
     * @Route("/connexion", name="login")
     * @IsGranted("ROLE_VIEWER")
     */
    public function login(Security $security)
    {
        $user = $security->getUser();

        // No direct access, redirect to home after login
        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/importer-csv", name="import_csv")
     * @IsGranted("ROLE_ADMIN")
     */
    public function import_csv(Request $request, Security $security, AuthorizationCheckerInterface $authChecker)
    {
        $user = $security->getUser();
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
                        'query_status'    => 1,
                        'slug_status'     => 'success',
                        'message_status'  => 'Importation des vinyles effectuée avec succès.',
                        'id_entity'       => $vinyl->getId()
                    );
                } catch (\Exception $e) {
                    // Something goes wrong
                    $em->clear();

                    $return = array(
                        'query_status'    => 0,
                        'slug_status'     => 'error',
                        'exception'       => $e->getMessage(),
                        'message_status'  => 'Un problème est survenu lors de l\'importation des vinyles.'
                    );
                }

                // Set $return message
                $request->getSession()->getFlashBag()->add($return['slug_status'], $return['message_status']);
            } else {
                // Set message if file is empty / not found
                $request->getSession()->getFlashBag()->add('notice',
                  'Le fichier CSV est vide ou n\'a pas été trouvé (localisation: "' . $this->csvParsingOptions['finder_in'] . '' . $this->csvParsingOptions['finder_name'] . '").');
            }
        }

        return $this->render('import-csv.html.twig', [
            'user'          => $user,
            // 'form_artist' => $form_artist->createView(),
            // 'form_vinyl'  => $form_vinyl->createView(),
            // 'vinyls'      => $vinyls,
        ]);
    }

    /**
     * @Route("/vinyles/{id}/supprimer", name="vinyl_delete")
     * @IsGranted("ROLE_ADMIN")
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
     * @Route("/vinyles/{id}/{trackFace}/youtube-id", name="vinyl_get_youtube_id")
     */
    public function vinyl_get_youtube_id($id, $trackFace, Request $request, HttpClientInterface $client)
    {
        $base_url     = 'https://youtube.googleapis.com/youtube/v3/search?key='. self::G_AUTH_KEY . '&maxResults=1';
        $em           = $this->getDoctrine()->getManager();
        $return_data  = [];

        // Retrieve item to update quantity
        $repo   = $em->getRepository(Vinyl::class);
        $entity = $repo->findOneById($id);

        if ($entity !== null && ($trackFace == 'A' || $trackFace == 'B')) {
          $methodGetYTID = "getTrackFace{$trackFace}YoutubeID";
          $methodGetTrack = "getTrackFace{$trackFace}";
          $youtubeID = $entity->{$methodGetYTID}();

          // Retrieve track artists list in an array
          $artists = [];
          foreach ($entity->getArtists() as $artist) {
              $artists[] = $artist->getName();
          }

          // Search for a YouTube video only if we don't have any yet
          if (is_null($youtubeID)) {
              // Create query (track name + artists names)
              $query = $entity->{$methodGetTrack}() . ' - ' . implode($artists, ', ');

              // Execute request to retrieve some YouTube videos
              $response = $client->request(
                  'GET',
                  $base_url . '&q=' . $query
              );
              $r_array = $response->toArray();

              // Check if there is some results > store YouTube ID in database (for later use)
              if (!empty($r_array) && isset($r_array['items']) && isset($r_array['items'][0])) {
                  $youtubeID = $r_array['items'][0]['id']['videoId'];
                  $methodSetYTID = "setTrackFace{$trackFace}YoutubeID";
                  // Set YouTube ID found
                  $entity->{$methodSetYTID}($youtubeID);
                  $em->persist($entity);

                  // Try to save (flush) or clear
                  try {
                      // Flush OK !
                      $em->flush();
                  } catch (\Exception $e) {
                      // Something goes wrong
                      $em->clear();

                      $return_data = [
                          'query_status'    => 0,
                          'slug_status'     => 'error',
                          'exception'       => $e->getMessage(),
                          'message_status'  => 'Un problème est survenu lors la sauvegarde de l\'ID YouTube en base de données.'
                      ];
                  }
              }
          }

          // If Youtube ID has been found > return it or display an error message
          if (!is_null($youtubeID)) {
              $return_data = array(
                  'query_status'  => 1,
                  'slug_status'   => 'success',
                  'id_entity'     => $entity->getId(),
                  'youtube_id'    => $youtubeID,
                  'vinyl'         => [
                      'track'   => $entity->{$methodGetTrack}(),
                      'artists' => implode($artists, ', ')
                  ],
              );
          } elseif (empty($return_data)) {
              $return_data = [
                  'query_status'    => 0,
                  'slug_status'     => 'error',
                  'message_status'  => 'Aucun ID YouTube n\'a pu être trouvé pour la face ' . $trackFace . ' du vinyle.'
              ];
          }
        } else {
            // Data to return/display
            $return_data = [
                'query_status'      => 0,
                'slug_status'       => 'error',
                'message_status'    => 'Le vinyle avec pour ID: "' . $id . '" n\'existe pas en base de données.'
            ];
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
    }

    /**
     * @Route("/vinyles/{id}/quantite/{type_update}", name="vinyl_update_quantity")
     * @IsGranted("ROLE_ADMIN")
     */
    public function vinyl_update_quantity($id, $type_update, Request $request, AuthorizationCheckerInterface $authChecker)
    {
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
    }

    /**
     * @Route("/vinyles/{id}/quantite-vendue/{type_update}", name="vinyl_update_quantity_sold")
     * @IsGranted("ROLE_ADMIN")
     */
    public function vinyl_update_quantity_sold($id, $type_update, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve item to update quantity
        $repo   = $em->getRepository(Vinyl::class);
        $entity = $repo->findOneById($id);

        if ($entity !== null) {
            // Update new vinyl quantity sold & persist it
            $entity->setQuantitySold($entity->getQuantitySold() + (($type_update == '-1') ? -1 : 1));
            $em->persist($entity);

            // Try to save (flush) or clear
            try {
                // Flush OK !
                $em->flush();

                $return_data = array(
                    'query_status'    => 1,
                    'message_status'  => 'Modification de la quantité vendue effectuée avec succès.',
                    'id_entity'       => $entity->getId(),
                    'new_quantity'    => $entity->getQuantitySold(),
                    'total_vinyls'    => $repo->countAll()
                );
            } catch (\Exception $e) {
                // Something goes wrong
                $em->clear();

                $return_data = array(
                    'query_status'    => 0,
                    'exception'       => $e->getMessage(),
                    'message_status'  => 'Un problème est survenu lors de la modification de la quantité vendue.'
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
    }


    /**
     * @Route("/vinyles/{id}/quantite-pochette/{type_update}", name="vinyl_update_quantity_cover")
     * @IsGranted("ROLE_ADMIN")
     */
    public function vinyl_update_quantity_cover($id, $type_update, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve item to update quantity
        $repo   = $em->getRepository(Vinyl::class);
        $entity = $repo->findOneById($id);

        if ($entity !== null) {
            // Update new vinyl quantity sold & persist it
            $entity->setQuantityWithCover($entity->getQuantityWithCover() + (($type_update == '-1') ? -1 : 1));
            $em->persist($entity);

            // Try to save (flush) or clear
            try {
                // Flush OK !
                $em->flush();

                $return_data = array(
                    'query_status'    => 1,
                    'message_status'  => 'Modification de la quantité des vinyles avec une pochette effectuée avec succès.',
                    'id_entity'       => $entity->getId(),
                    'new_quantity'    => $entity->getQuantityWithCover(),
                    'total_vinyls'    => $repo->countAll()
                );
            } catch (\Exception $e) {
                // Something goes wrong
                $em->clear();

                $return_data = array(
                    'query_status'    => 0,
                    'exception'       => $e->getMessage(),
                    'message_status'  => 'Un problème est survenu lors de la modification de la quantité des vinyles avec une pochette.'
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
            'meta'    => [
                'title' => 'Artistes'
            ],
            'user'    => $user,
            'artists' => $artists,
        ]);
    }

    /**
     * @Route("/artistes/{id}", name="artists_infos")
     */
    public function artists_infos($id, Security $security, HttpClientInterface $client, FileUploader $fileUploader)
    {
        $em = $this->getDoctrine()->getManager();
        $user = $security->getUser();

        // Retrieve item to delete
        $r_artist = $em->getRepository(Artist::class);
        $artist   = $r_artist->findOneById($id);

        // Retrieve total vinyls quantity (with duplicate vinyls)
        // $r_vinyl = $em->getRepository(Vinyl::class);

        // Check if artist has a photo, if not > search and download it
        if (is_null($artist->getAvatarFilename())) {
            $img_googled = $this->searchArtistPhoto($client, $artist->getName());
            if ($img_googled) {
                $artist_slug = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $artist->getName());
                $avatarFileName = $fileUploader->upload($img_googled, '/avatars', $artist_slug . '.' . pathinfo($img_googled, PATHINFO_EXTENSION));
                $artist->setAvatarFilename($avatarFileName);
            }
            // Persist & flush new artist's avatar
            $em->persist($artist);
            $em->flush();
        }

        return $this->render('artist-single.html.twig', [
            'meta'    => [
                'title' => $artist->getName()
            ],
            'user'    => $user,
            'artist'  => $artist,
        ]);
    }

    /**
     * @Route("/artistes/{id}/supprimer", name="artist_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function artist_delete($id, Request $request, AuthorizationCheckerInterface $authChecker)
    {
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

        // No direct access
        return $this->redirectToRoute('artists');
    }

    /**
     * @Route("/annonces/{id}", name="adverts", defaults={"id"=null})
     */
    public function adverts($id, Request $request, Security $security, AuthorizationCheckerInterface $authChecker, FileUploader $fileUploader)
    {
        $em     = $this->getDoctrine()->getManager();
        $user   = $security->getUser();

        // Retrieve advert if asked ($advert_entity != null) or new one
        $r_advert     = $em->getRepository(Advert::class);
        $advert_edit  = $r_advert->findOneById($id);
        $is_edit  = !is_null($advert_edit);
        $advert   = ($is_edit === true) ? $advert_edit : new Advert();

        // Only admin user can add vinyls & artists
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            // 1) Build advert forms
            $form_advert = $this->createForm(AdvertType::class, $advert);

            // 2) Handle advert forms
            $form_advert->handleRequest($request);

            // 3) Save advert
            if ($form_advert->isSubmitted() && $form_advert->isValid()) {
                $em->persist($advert);

                // 4) Try to save (flush) or clear
                try {
                    // Flush OK !
                    $em->flush();

                    $return = array(
                        'query_status'    => 1,
                        'slug_status'     => 'success',
                        'message_status'  => (($is_edit === true) ? 'Modification' : 'Ajout' ) . ' de l\'annonce effectuée avec succès.',
                        'id_entity'       => $advert->getId()
                    );

                    // Assign added advert to re-used later
                    $advert_added = $advert;

                    // Assign vinyls to created advert
                    $r_vinyl    = $em->getRepository(Vinyl::class);
                    $vinyls_qty = $request->get('advert_vinyl_qty');
                    $vinyls     = $r_vinyl->findById(array_keys($vinyls_qty));

                    foreach ($vinyls as $vinyl) {
                        $vinyl_qty  = ((isset($vinyls_qty[$vinyl->getId()]) && isset($vinyls_qty[$vinyl->getId()][0])) ? (int)$vinyls_qty[$vinyl->getId()][0] : 0);
                        $inSale     = new InSale();

                        if ($vinyl_qty > 0) {
                            $inSale->setVinyl($vinyl);
                            $inSale->setAdvert($advert);
                            $inSale->setQuantity($vinyl_qty);

                            // Persist & flush new vinyl in sale
                            $em->persist($inSale);
                            $em->flush();
                        }
                    }

                    // Upload advert images
                    $images = $form_advert->get('images')->getData();
                    foreach ($images as $imgData) {
                        // Upload new advert image
                        $imageFileName = $fileUploader->upload($imgData, '/adverts');

                        // Create & persist new Image entity after upload
                        $image = new Image();
                        $image->setFilename($imageFileName);

                        // Add new image to current advert
                        $advert->addImage($image);

                        $em->persist($image);
                        $em->flush();
                    }

                    // Clear/reset form
                    $advert       = new Advert();
                    $form_advert  = $this->createForm(AdvertType::class, $advert);
                } catch (\Exception $e) {
                    // Something goes wrong
                    $em->clear();

                    $return = array(
                        'query_status'    => 0,
                        'slug_status'     => 'error',
                        'exception'       => $e->getMessage(),
                        'message_status'  => 'Un problème est survenu lors de la ' . ($is_edit === true ? 'modification' : 'sauvegarde') . ' de l\'annonce.'
                    );
                }
            }

            // Set flash message if $return has message_status
            if (isset($return['message_status']) && !empty($return['message_status'])) {
                $request->getSession()->getFlashBag()->add(
                    (isset($return['slug_status']) ? $return['slug_status'] : 'notice'),
                    $return['message_status']
                );

                // Redirect on adverts home after editing one
                if ($is_edit === true)
                    return $this->redirectToRoute('adverts');
            }
        }

        // Retrieve adverts
        $r_advert = $em->getRepository(Advert::class);
        $adverts  = $r_advert->findAll();

        // Retrieve vinyls to use in advert form and create "InSale" related
        //  to a new advert
        $r_vinyl        = $em->getRepository(Vinyl::class);
        $vinyls_to_sale = $r_vinyl->findAllAvailableForSale();

        usort($vinyls_to_sale, function($a, $b) {
            $a_str = null;
            $b_str = null;
            $a_first_artist = $a->getArtists()->first();
            $b_first_artist = $b->getArtists()->first();
            // Check if an artist is defined
            if (is_object($a_first_artist) && is_object($b_first_artist)) {
                $a_str = $a_first_artist->getName();
                $b_str = $b_first_artist->getName();
            }

            // Remove accents
            $this->removeAccents($a_str);
            $this->removeAccents($b_str);

            // Re-order only if "a" and "b" string are defined
            if (!is_null($a_str) && !is_null($b_str))
                return strcmp($a_str, $b_str);
        });

        // Get some data
        $r_in_sale          = $em->getRepository(InSale::class);
        $nb_vinyls_in_sale  = $r_in_sale->countVinylsInSale();
        $nb_vinyls_sold     = $r_vinyl->countVinylsSold();
        $total_prices       = $r_advert->countTotalPrices();
        $total_checkout     = $r_advert->countTotalPricesCheckout();

        return $this->render('adverts.html.twig', [
            'meta'    => [
                'title' => 'Annonces'
            ],
            'user'            => $user,
            'form_advert'     => isset($form_advert) ? $form_advert->createView() : null,
            'adverts'         => $adverts,
            'is_advert_edit'  => $is_edit,
            'advert_to_edit'  => $advert,
            'total_vinyls'    => $r_vinyl->countAll(),
            'vinyls_to_sale'  => $vinyls_to_sale,
            'nb_vinyls_in_sale' => $nb_vinyls_in_sale,
            'nb_vinyls_sold'    => $nb_vinyls_sold,
            'total_prices'      => (float)$total_prices,
            'total_prices_checkout' => (float)$total_checkout,
        ]);
    }

    /**
     * @Route("/annonces/{id}/supprimer", name="advert_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function advert_delete($id, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve item to delete
        $repo   = $em->getRepository(Advert::class);
        $entity = $repo->findOneById($id);

        if ($entity !== null) {
            $upload_dir = $this->getParameter('uploads_directory');
            // Remove related images
            $images = $entity->getImages();
            foreach ($images as $key => $image) {
                // Delete image (file deleted in ImageListener)
                $em->remove($image);
            }

            // Remove related in sales
            $in_sales = $entity->getInSales();
            foreach ($in_sales as $inSale) {
                $em->remove($inSale);
            }

            // Delete advert & flush
            $em->remove($entity);
            $em->flush();

            // Set $return success message
            $return = array(
                'query_status'    => 1,
                'slug_status'     => 'success',
                'message_status'  => 'L\'annonce a bien été supprimée.'
            );

        } else {
            $return = array(
                'query_status'    => 0,
                'slug_status'     => 'error',
                'message_status'  => 'L\'annonce avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.'
            );
        }

        // Set flash message if $return has message_status
        if (isset($return['message_status']) && !empty($return['message_status'])) {
            $request->getSession()->getFlashBag()->add(
                (isset($return['slug_status']) ? $return['slug_status'] : 'notice'),
                $return['message_status']
            );
        }

        // No direct access
        return $this->redirectToRoute('adverts');
    }

    /**
     * @Route("/annonces/images/{id}/supprimer", name="advert_image_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function advert_image_delete($id, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve advert to update
        $repo   = $em->getRepository(Image::class);
        $image  = $repo->findOneById($id);
        // Set default message as error
        $return = array(
            'query_status'    => 0,
            'slug_status'     => 'error',
            'message_status'  => 'Un problème est survenu lors de la suppression de l\'image avec pour ID: ' . $id
        );

        if ($image !== null) {
            $deleted_img = $image;
            // Delete image entity
            $em->remove($image);
            $em->flush();

            // Set message
            $return = array(
                'query_status'    => 1,
                'slug_status'     => 'success',
                'message_status'  => 'L\'image a bien été supprimée.',
                'image_deleted'   => [
                    'id'        => $id,
                    'filename'  => $deleted_img->getFilename()
                ]
            );
        } else {
            // Set message saying that image doesn't exist in DB
            $return['message_status'] = 'L\'image avec pour ID: ' . $id . ' n\'existe pas en base de données.';
        }

        // Display return data as JSON when using AJAX or redirect to home
        if ($request->isXmlHttpRequest()) {
            return $this->json($return);
        } else {
            // Set message in flashbag on direct access
            $request->getSession()->getFlashBag()->add($return['slug_status'], $return['message_status']);

            // No direct access
            return $this->redirectToRoute('adverts');
        }
    }

    /**
     * @Route("/annonces/{id}/est-vendue/{isSold}", name="advert_update_is_sold")
     * @IsGranted("ROLE_ADMIN")
     */
    public function advert_update_is_sold($id, $isSold, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve advert to update
        $repo   = $em->getRepository(Advert::class);
        $advert = $repo->findOneById($id);

        if ($advert !== null) {
            try {
                $advert_is_sold = ($isSold === '1');
                // Update advert is sold or not
                $advert->setIsSold($advert_is_sold);

                // TODO Retrieve vinyls & update their quantities
                $inSales = $advert->getInSales();
                foreach ($inSales as $is) {
                  $vinyl    = $is->getVinyl();
                  $max_qty  = $vinyl->getQuantity();
                  $old_qty_sold = $vinyl->getQuantitySold();
                  $qty_sold     = $is->getQuantity();

                  // Up / Down vinyls quantity sold
                  if ($advert_is_sold == true) {
                      if ($old_qty_sold + $qty_sold <= $max_qty)
                          $vinyl->setQuantitySold($old_qty_sold + $qty_sold);
                  } else {
                      if ($old_qty_sold - $qty_sold >= 0)
                          $vinyl->setQuantitySold($old_qty_sold - $qty_sold);
                  }
                }

                // Flush database
                $em->flush();

                // Set $return success message
                $return = array(
                    'query_status'    => 1,
                    'slug_status'     => 'success',
                    'message_status'  => (($advert_is_sold) ? 'L\'annonce a bien été passée à l\'état vendue.' : 'L\'annonce est de nouveau en vente.')
                );
            } catch (\Exception $e) {
                $return = array(
                    'query_status'    => 0,
                    'slug_status'     => 'error',
                    'exception'       => $e->getMessage(),
                    'message_status'  => 'Un problème est survenu lors du changement de l\'état est vendu de l\'annonce.'
                );
            }

        } else {
            $return = array(
                'query_status'    => 0,
                'slug_status'     => 'error',
                'message_status'  => 'L\'annonce avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.'
            );
        }

        // Display return data as JSON when using AJAX or redirect to home
        if ($request->isXmlHttpRequest()) {
            return $this->json($return);
        } else {
            // Set message in flashbag on direct access
            $request->getSession()->getFlashBag()->add($return['slug_status'], $return['message_status']);

            // No direct access
            return $this->redirectToRoute('adverts');
        }
    }

    /**
     * @Route("/{order_by}/{direction}", name="home", defaults={"order_by"=null,"direction"=null})
     */
    public function index($order_by, $direction, Request $request, Security $security, AuthorizationCheckerInterface $authChecker, FileUploader $fileUploader): Response
    {
        $em     = $this->getDoctrine()->getManager();
        $vinyl  = new Vinyl();
        $artist = new Artist();
        $return = array();
        $user   = $security->getUser();
        $artist_added = null;

        // Only admin user can add vinyls & artists
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            // 1) Build artist forms
            $form_artist = $this->createForm(ArtistType::class, $artist);

            // 2) Handle artist forms
            $form_artist->handleRequest($request);

            // 3) Save artist
            if ($form_artist->isSubmitted() && $form_artist->isValid()) {
                $r_artist = $em->getRepository(Artist::class);

                // Check if artist is already in database
                if (is_null($r_artist->findOneByName($artist->getName()))) {
                    /** @var UploadedFile $documentFile */
                    $avatarFile = $form_artist->get('avatar')->getData();

                    if ($avatarFile) {
                        $avatarFileName = $fileUploader->upload($avatarFile, '/avatars');
                        $artist->setAvatarFilename($avatarFileName);
                    }

                    $em->persist($artist);

                    // 4) Try to save (flush) or clear
                    try {
                        // Flush OK !
                        $em->flush();

                        $return = array(
                            'query_status'    => 1,
                            'slug_status'     => 'success',
                            'message_status'  => 'Sauvegarde de l\'artiste effectuée avec succès.',
                            'id_entity'       => $artist->getId()
                        );

                        // Assign added artist to re-used later
                        $artist_added = $artist;

                        // Clear/reset form
                        $artist       = new Artist();
                        $form_artist  = $this->createForm(ArtistType::class, $artist);
                    } catch (\Exception $e) {
                        // Something goes wrong
                        $em->clear();

                        $return = array(
                            'query_status'    => 0,
                            'slug_status'     => 'error',
                            'exception'       => $e->getMessage(),
                            'message_status'  => 'Un problème est survenu lors de la sauvegarde de l\'artiste.'
                        );
                    }
                } else {
                    // If artist already exist > add message status & clear/reset form
                    $return = array(
                        'query_status'    => 0,
                        'slug_status'     => 'notice',
                        'message_status'  => 'L\'artiste "' . $artist->getName() . '" est déjà présent dans la base de données et n\'a donc pas été ajouté.',
                        'id_entity'       => $artist->getId()
                    );
                    $artist       = new Artist();
                    $form_artist  = $this->createForm(ArtistType::class, $artist);
                }
            }

            // 1) Build vinyl forms
            $form_vinyl = $this->createForm(VinylType::class, $vinyl);

            // 2) Handle vinyl forms
            $form_vinyl->handleRequest($request);

            // 3) Save vinyl
            if ($form_vinyl->isSubmitted() && $form_vinyl->isValid()) {
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
                            'query_status'    => 0,
                            'slug_status'     => 'notice',
                            'message_status'  => 'Le vinyle existe déjà en base de donnée, sa quantité a donc été augmentée (quantité: ' . $vinyl_existing->getQuantity() . ').',
                            'id_entity'       => $vinyl_existing->getId()
                        );

                        // Clear/reset form
                        $vinyl      = new Vinyl();
                        $form_vinyl = $this->createForm(VinylType::class, $vinyl);
                    } catch (\Exception $e) {
                        // Something goes wrong
                        $em->clear();

                        $return = array(
                            'query_status'    => 0,
                            'slug_status'     => 'error',
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
                            'query_status'    => 1,
                            'slug_status'     => 'success',
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
                            'query_status'    => 0,
                            'slug_status'     => 'error',
                            'exception'       => $e->getMessage(),
                            'message_status'  => 'Un problème est survenu lors de la sauvegarde du vinyle.'
                        );
                    }
                }
            }

            // Set flash message if $return has message_status
            if (isset($return['message_status']) && !empty($return['message_status'])) {
                $request->getSession()->getFlashBag()->add(
                    (isset($return['slug_status']) ? $return['slug_status'] : 'notice'),
                    $return['message_status']
                );
            }
        }

        // Retrieve vinyls
        $r_vinyl = $em->getRepository(Vinyl::class);
        $vinyls = $r_vinyl->findAll();

        // Re-order vinyls if asked
        if(!is_null($order_by) && !is_null($direction)) {
            usort($vinyls, function($a, $b) use($order_by, $direction) {
                $a_str = null;
                $b_str = null;
                if ($order_by == 'track-face-a') {
                    $a_str = $a->getTrackFaceA();
                    $b_str = $b->getTrackFaceA();
                } elseif ($order_by == 'track-face-b') {
                    $a_str = $a->getTrackFaceB();
                    $b_str = $b->getTrackFaceB();
                } elseif ($order_by == 'artist') {
                    $a_first_artist = $a->getArtists()->first();
                    $b_first_artist = $b->getArtists()->first();
                    // Check if an artist is defined
                    if (is_object($a_first_artist) && is_object($b_first_artist)) {
                        $a_str = $a_first_artist->getName();
                        $b_str = $b_first_artist->getName();
                    }
                }

                // Remove accents
                $this->removeAccents($a_str);
                $this->removeAccents($b_str);

                // Re-order only if "a" and "b" string are defined
                if (!is_null($a_str) && !is_null($b_str))
                    return ($direction == 'asc') ? strcmp($a_str, $b_str) : strcmp($b_str, $a_str);
            });
        }

        // Get some additionnals data
        $nb_vinyls_sold = $r_vinyl->countVinylsSold();

        return $this->render('app.html.twig', [
            'user'          => $user,
            'form_artist'   => isset($form_artist) ? $form_artist->createView() : null,
            'form_vinyl'    => isset($form_vinyl) ? $form_vinyl->createView() : null,
            'vinyls'        => $vinyls,
            'total_vinyls'        => $r_vinyl->countAll(),
            'total_vinyls_cover'  => $r_vinyl->countAllWithCover(),
            'nb_vinyls_sold'      => $nb_vinyls_sold,
            'vinyls_order_by'     => $order_by,
            'vinyls_direction'    => $direction,
            'artist_added'        => $artist_added,
        ]);
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

    private function removeAccents(&$string)
    {
        $string = strtr(utf8_decode($string), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
        return $string;
    }

    private function searchArtistPhoto(HttpClientInterface $client, string $artist_name)
    {
        $base_url = 'https://customsearch.googleapis.com/customsearch/v1?key='. self::G_AUTH_KEY . '&cx=' . self::G_SEARCH_CX . '&searchType=image&imgSize=medium&num=1';
        $img_url = false;

        // Check if $client and $artist_name are correctly defined
        if (!empty($client) && !empty($artist_name)) {
            // Search for an artist photo on Google
            $response = $client->request(
                'GET',
                $base_url . '&q=' . $artist_name
            );

            if ($response->getStatusCode() == 200) {
                $r_array = $response->toArray();

                $img_url = null;
                // Check if there is some results > retrieve & download artist photo
                if (!empty($r_array) && isset($r_array['items']) && isset($r_array['items'][0]))
                    $img_url = $r_array['items'][0]['link'];
            } else {
                dump($response->getStatusCode(), $response->getInfo());
            }
        }

        return $img_url;
    }
}
