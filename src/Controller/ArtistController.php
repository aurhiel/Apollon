<?php

namespace App\Controller;

use App\Repository\ArtistRepository;
use App\Service\FileUploader;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ArtistController extends AbstractController
{
    private ArtistRepository $artistRepository;
    private HttpClientInterface $client;
    private FileUploader $fileUploader;

    public function __construct(
        ArtistRepository $artistRepository,
        HttpClientInterface $client,
        FileUploader $fileUploader
    ) {
        $this->artistRepository = $artistRepository;
        $this->client = $client;
        $this->fileUploader = $fileUploader;
    }

    /**
     * @Route("/artistes", name="artists")
     */
    public function artists(Security $security): Response
    {
        return $this->render('artists/list.html.twig', [
            'meta' => ['title' => 'Artistes'],
            'user' => $security->getUser(),
            'artists' => $this->artistRepository->findAll(),
        ]);
    }

    /**
     * @Route("/artistes/{id}-{slug}.html", name="artist_infos")
     */
    public function artist_infos(int $id, Security $security): Response
    {
        $artist = $this->artistRepository->findOneById($id);

        // Check if artist has a photo, if not > search and download it
        if (is_null($artist->getAvatarFilename())) {
            $em = $this->getDoctrine()->getManager();
            $imgGoogled = $this->searchArtistPhoto($artist->getName());

            if ($imgGoogled) {
                $artist_slug = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $artist->getName());
                $avatarFileName = $this->fileUploader->upload(
                    $imgGoogled,
                    '/avatars', $artist_slug . '.' . pathinfo(parse_url($imgGoogled, PHP_URL_PATH), PATHINFO_EXTENSION),
                );
                $artist->setAvatarFilename($avatarFileName);

                // Persist & flush new artist's avatar
                $em->persist($artist);
                $em->flush();
            }
        }

        return $this->render('artists/single.html.twig', [
            'meta' => [ 'title' => $artist->getName() ],
            'core_class' => 'app-artist-single',
            'user' => $security->getUser(),
            'artist' => $artist,
        ]);
    }

    /**
     * @return string|bool
     */
    private function searchArtistPhoto(string $artist_name)
    {
        $base_url = 'https://customsearch.googleapis.com/customsearch/v1?key='. $this->getParameter('g_auth_key') . '&cx=' . $this->getParameter('g_search_cx') . '&searchType=image&imgSize=medium';
        $img_url = false;

        // Check if $client and $artist_name are correctly defined
        if (!empty($this->client) && !empty($artist_name)) {
            // Search for an artist photo on Google
            $response = $this->client->request(
                'GET',
                $base_url . '&q=music%20artist%20"' . $artist_name . '"'
            );

            if ($response->getStatusCode() == 200) {
                $r_array = $response->toArray();
                $img_url = null;

                // Check if there is some results > retrieve & download artist photo
                if (!empty($r_array) && isset($r_array['items']) && count($r_array['items']) > 0) {
                    // Loop on items to avoid wikimedia.org ! (or when 403 ? But need to ping...)
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
