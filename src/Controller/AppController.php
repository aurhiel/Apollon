<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Vinyl;
use App\Entity\Image;
use App\Entity\Advert;
use App\Form\ArtistType;
use App\Form\VinylType;
use App\Form\BookingType;
use App\Repository\ArtistRepository;
use App\Repository\VinylRepository;
use App\Service\FileUploader;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppController extends AbstractController
{
    private ArtistRepository $artistRepository;
    private VinylRepository $vinylRepository;

    public function __construct(
        ArtistRepository $artistRepository,
        VinylRepository $vinylRepository
    ) {
        $this->artistRepository = $artistRepository;
        $this->vinylRepository = $vinylRepository;
    }

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
     * @Route("/vinyles/{id}/{trackFace}/youtube-id", name="vinyl_get_youtube_id")
     */
    public function vinyl_get_youtube_id($id, $trackFace, Request $request, HttpClientInterface $client)
    {
        $base_url = 'https://youtube.googleapis.com/youtube/v3/search?key='. $this->getParameter('g_auth_key') . '&maxResults=1';
        $em = $this->getDoctrine()->getManager();
        $return_data = [];
        $entity = $this->vinylRepository->findOneById($id);

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
              if (!empty($r_array) && isset($r_array['items']) && isset($r_array['items'][0]) && isset($r_array['items'][0]['id']['videoId'])) {
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
     * @Route("/artistes", name="artists")
     */
    public function artists(Security $security)
    {
        return $this->render('artists.html.twig', [
            'meta' => [ 'title' => 'Artistes' ],
            'user' => $security->getUser(),
            'artists' => $this->artistRepository->findAll(),
        ]);
    }

    /**
     * @Route("/artistes/{id}", name="artists_infos")
     */
    public function artists_infos($id, Security $security, HttpClientInterface $client, FileUploader $fileUploader)
    {
        $artist = $this->artistRepository->findOneById($id);

        // Check if artist has a photo, if not > search and download it
        if (is_null($artist->getAvatarFilename())) {
            $em = $this->getDoctrine()->getManager();
            $imgGoogled = $this->searchArtistPhoto($client, $artist->getName());

            if ($imgGoogled) {
                $artist_slug = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $artist->getName());
                $avatarFileName = $fileUploader->upload(
                    $imgGoogled, 
                    '/avatars', $artist_slug . '.' . pathinfo(parse_url($imgGoogled, PHP_URL_PATH), PATHINFO_EXTENSION),
                );
                $artist->setAvatarFilename($avatarFileName);

                // Persist & flush new artist's avatar
                $em->persist($artist);
                $em->flush();
            }
        }

        return $this->render('artist-single.html.twig', [
            'meta' => [ 'title' => $artist->getName() ],
            'core_class' => 'app-artist-single',
            'user' => $security->getUser(),
            'artist' => $artist,
        ]);
    }

    /**
     * @Route("/{vinyl_id}", name="home", defaults={"vinyl_id"=null})
     */
    public function index($vinyl_id, Request $request, Security $security, AuthorizationCheckerInterface $authChecker, FileUploader $fileUploader): Response
    {
        $return = [];
        $artist_added = null;
        $form_booking = $this->createForm(BookingType::class, new Advert(), [
            'action' => $this->generateUrl('booking'),
        ]);

        // Retrieve vinyl if asked ($is_vinyl_edit = true) or new one
        $vinyl_edit     = $this->vinylRepository->findOneById($vinyl_id);
        $is_vinyl_edit  = null !== $vinyl_edit;
        $vinyl          = ($is_vinyl_edit === true) ? $vinyl_edit : new Vinyl();

        // Only admin user can add vinyls & artists
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            $em = $this->getDoctrine()->getManager();
            $artist = new Artist();

            // 1) Build artist forms
            $form_artist = $this->createForm(ArtistType::class, $artist);

            // 2) Handle artist forms
            $form_artist->handleRequest($request);

            // 3) Save artist
            if ($form_artist->isSubmitted() && $form_artist->isValid()) {
                // Check if artist is already in database
                if (is_null($this->artistRepository->findOneByName($artist->getName()))) {
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
                            'query_status' => 0,
                            'slug_status' => 'error',
                            'exception' => $e->getMessage(),
                            'message_status' => 'Un problème est survenu lors de la sauvegarde de l\'artiste.'
                        );
                    }
                } else {
                    // If artist already exist > add message status & clear/reset form
                    $return = array(
                        'query_status' => 0,
                        'slug_status' => 'notice',
                        'message_status' => 'L\'artiste "' . $artist->getName() . '" est déjà présent dans la base de données et n\'a donc pas été ajouté.',
                        'id_entity' => $artist->getId()
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
                        'query_status' => 1,
                        'slug_status' => 'success',
                        'message_status' => 'Sauvegarde du vinyle effectuée avec succès.',
                        'id_entity' => $vinyl->getId()
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
                        'query_status' => 0,
                        'slug_status' => 'error',
                        'exception' => $e->getMessage() . ', at line: '.$e->getLine(),
                        'message_status' => sprintf(
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
        
        $total_vinyls = (int) $this->vinylRepository->countAll();
        $nb_vinyls_sold = (int) $this->vinylRepository->countVinylsSold();

        return $this->render('app.html.twig', [
            'user' => $security->getUser(),
            'form_booking' => $form_booking->createView(),
            'form_artist' => isset($form_artist) ? $form_artist->createView() : null,
            'form_vinyl' => isset($form_vinyl) ? $form_vinyl->createView() : null,
            'vinyls' => $this->vinylRepository->findAll(),
            'artists' => $this->artistRepository->findAll(),
            'is_vinyl_edit' => $is_vinyl_edit,
            'vinyl_to_edit' => $vinyl,
            'total_vinyls' => $total_vinyls,
            'nb_vinyls_sold' => $nb_vinyls_sold,
            'total_vinyls_cover' => (int) $this->vinylRepository->countAllWithCover(),
            'nb_vinyls_cover_sold' => (int) $this->vinylRepository->countVinylsWithCoverSold(),
            'quantity_available' => $total_vinyls - $nb_vinyls_sold,
            'artist_added' => $artist_added,
        ]);
    }

    private function searchArtistPhoto(HttpClientInterface $client, string $artist_name)
    {
        $base_url = 'https://customsearch.googleapis.com/customsearch/v1?key='. $this->getParameter('g_auth_key') . '&cx=' . $this->getParameter('g_search_cx') . '&searchType=image&imgSize=medium';
        $img_url = false;

        // Check if $client and $artist_name are correctly defined
        if (!empty($client) && !empty($artist_name)) {
            // Search for an artist photo on Google
            $response = $client->request(
                'GET',
                $base_url . '&q=music%20artist%20"' . $artist_name . '"'
            );

            if ($response->getStatusCode() == 200) {
                $r_array = $response->toArray();
                $img_url = null;

                // Check if there is some results > retrieve & download artist photo
                if (!empty($r_array) && isset($r_array['items']) && count($r_array['items']) > 0) {
                    // TODO: Loop on items to avoid wikimedia.org ! (or when 403 ? But need to ping...)
                    foreach ($r_array['items'] as $item) {
                        if (false === str_contains($item['link'], 'wikimedia.org')) {
                            $img_url = $item['link'];
                            break;
                        }
                    }
                }
            } else {
                // dump($response->getStatusCode(), $response->getInfo());
                // exit;
            }
        }

        return $img_url;
    }
}
