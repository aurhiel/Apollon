<?php

namespace App\Controller;

// Entities
use App\Entity\Artist;
use App\Entity\Vinyl;
use App\Entity\Image;
use App\Entity\Advert;

// Forms
use App\Form\ArtistType;
use App\Form\VinylType;
use App\Form\BookingType;

// Services
use App\Service\FileUploader;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
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
        $flashbag = $request->getSession()->getFlashBag();

        // If asked > launch import
        if ($request->request->get('import-launch') !== null) {
            if (false) {
                $em = $this->getDoctrine()->getManager();
                /** @var ArtistRepository */
                $r_artist = $em->getRepository(Artist::class);
                /** @var VinylRepository */
                $r_vinyl = $em->getRepository(Vinyl::class);
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
                    $flashbag->add($return['slug_status'], $return['message_status']);
                } else {
                    // Set message if file is empty / not found
                    $flashbag->add('notice',
                      'Le fichier CSV est vide ou n\'a pas été trouvé (localisation: "' . $this->csvParsingOptions['finder_in'] . '' . $this->csvParsingOptions['finder_name'] . '").');
                }
            } else {
              $flashbag->add('notice', 'Importation du CSV désactivé par sécurité.');
          }
        }

        return $this->render('import-csv.html.twig', [
            'user' => $user,
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
                // Remove related images
                $images = $entity->getImages();
                foreach ($images as $key => $image) {
                    // Delete image (file deleted in ImageListener)
                    $em->remove($image);
                }

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
        $base_url     = 'https://youtube.googleapis.com/youtube/v3/search?key='. $this->getParameter('g_auth_key') . '&maxResults=1';
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
              $query = $entity->{$methodGetTrack}() . ' - ' . implode(', ', $artists);

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
                      'artists' => implode(', ', $artists)
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
            'meta'        => [
                'title' => $artist->getName()
            ],
            'core_class'  => 'app-artist-single',
            'user'        => $user,
            'artist'      => $artist,
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
     * @Route("/images/{id}/supprimer", name="image_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function image_delete($id, Request $request, AuthorizationCheckerInterface $authChecker)
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve advert to update
        /** @var ImageRepository */
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
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/switch-theme/{theme_slug}", name="switch-theme")
     */
    public function switchTheme($theme_slug, Request $request): Response
    {
        // Try to retrieve route name from `referer`
        $routeToRedirect = 'home';
        $routeInfos = [];
        if (null !== ($referer = $request->headers->get('referer'))) {
            $refererPathInfo = Request::create($referer)->getPathInfo();

            $routeInfos = $this->get('router')->match($refererPathInfo);
            $routeToRedirect = $routeInfos['_route'] ?? $routeToRedirect;
            unset($routeInfos['_route']);
            unset($routeInfos['_controller']);
        }

        // Assign theme if available
        if (in_array($theme_slug, ['light', 'dark'])) {
            $response = new Response();
            $response->headers->setCookie(Cookie::create('APP_THEME', $theme_slug));
            $response->sendHeaders();
        }

        // Redirect to home or guessed referer
        return $this->redirectToRoute($routeToRedirect, $routeInfos);
    }

    /**
     * @Route("/{vinyl_id}", name="home", defaults={"vinyl_id"=null})
     */
    public function index($vinyl_id, Request $request, Security $security, AuthorizationCheckerInterface $authChecker, FileUploader $fileUploader): Response
    {
        $em     = $this->getDoctrine()->getManager();
        $return = array();
        $user   = $security->getUser();
        $artist_added = null;
        $form_booking = $this->createForm(BookingType::class, new Advert(), [
            'action' => $this->generateUrl('booking'),
        ]);

        // Retrieve vinyl if asked ($is_vinyl_edit = true) or new one
        $r_vinyl        = $em->getRepository(Vinyl::class);
        $vinyl_edit     = $r_vinyl->findOneById($vinyl_id);
        $is_vinyl_edit  = null !== $vinyl_edit;
        $vinyl          = ($is_vinyl_edit === true) ? $vinyl_edit : new Vinyl();

        // Only admin user can add vinyls & artists
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            $artist = new Artist();

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

                    // Upload advert images
                    $images = $form_vinyl->get('images')->getData();
                    foreach ($images as $imgData) {
                        // Upload new advert image
                        $imageFileName = $fileUploader->upload($imgData, '/vinyls/' . $vinyl->getId());

                        // Create & persist new Image entity after upload
                        $image = new Image();
                        $image->setFilename($imageFileName);

                        // Add new image to current vinyl
                        $vinyl->addImage($image);

                        $em->persist($image);
                        $em->flush();
                    }

                    // Clear/reset form
                    $vinyl      = new Vinyl();
                    $form_vinyl = $this->createForm(VinylType::class, $vinyl);
                } catch (\Exception $e) {
                    // Something goes wrong
                    $em->clear();

                    $return = array(
                        'query_status'    => 0,
                        'slug_status'     => 'error',
                        'exception'       => $e->getMessage() . ', at line: '.$e->getLine(),
                        'message_status'  => sprintf(
                          'Un problème est survenu lors de la %s du vinyle.',
                          ($is_vinyl_edit === true ? 'modification' : 'sauvegarde')
                        ),
                    );
                }
            }

            // Set flash message if $return has message_status
            if (isset($return['message_status']) && !empty($return['message_status'])) {
                $request->getSession()->getFlashBag()->add(
                    (isset($return['slug_status']) ? $return['slug_status'] : 'notice'),
                    $return['message_status']
                );

                // Redirect on home after editing a vinyl
                if ($is_vinyl_edit === true)
                    return $this->redirectToRoute('home');
            }
        }

        // Retrieve vinyls
        $r_vinyl = $em->getRepository(Vinyl::class);
        $vinyls = $r_vinyl->findAll();

        // Get some additionnals data
        $nb_vinyls_sold = $r_vinyl->countVinylsSold();

        return $this->render('app.html.twig', [
            'user'          => $user,
            'form_booking'  => $form_booking->createView(),
            'form_artist'   => isset($form_artist) ? $form_artist->createView() : null,
            'form_vinyl'    => isset($form_vinyl) ? $form_vinyl->createView() : null,
            'vinyls'        => $vinyls,
            'is_vinyl_edit' => $is_vinyl_edit,
            'vinyl_to_edit' => $vinyl,
            'total_vinyls'        => $r_vinyl->countAll(),
            'total_vinyls_cover'  => $r_vinyl->countAllWithCover(),
            'nb_vinyls_sold'      => $nb_vinyls_sold,
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

    private function searchArtistPhoto(HttpClientInterface $client, string $artist_name)
    {
        $base_url = 'https://customsearch.googleapis.com/customsearch/v1?key='. $this->getParameter('g_auth_key') . '&cx=' . $this->getParameter('g_search_cx') . '&searchType=image&imgSize=medium&num=1';
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
