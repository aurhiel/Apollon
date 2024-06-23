<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Vinyl;
use App\Repository\ArtistRepository;
use App\Repository\SampleRepository;
use App\Repository\VinylRepository;
use App\Service\CSVParser;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @IsGranted("ROLE_ADMIN")
 */
class ImportController extends AbstractController
{
    private const ARTISTS_SEPARATOR = '#';

    private string $environment;
    private ArtistRepository $artistRepository;
    private SampleRepository $sampleRepository;
    private VinylRepository $vinylRepository;
    private CSVParser $csvParser;

    public function __construct(
        ArtistRepository $artistRepository,
        SampleRepository $sampleRepository,
        VinylRepository $vinylRepository,
        KernelInterface $kernel,
        CSVParser $csvParser
    ) {
        $this->artistRepository = $artistRepository;
        $this->sampleRepository = $sampleRepository;
        $this->vinylRepository = $vinylRepository;
        $this->environment = $kernel->getEnvironment();
        $this->csvParser = $csvParser;
    }

    /**
     * @Route("/csv-manager", priority=15, name="csv_manager")
     */
    public function home(Security $security): Response
    {
        return $this->render('csv-manager/home.html.twig', [
            'user' => $security->getUser(),
            'csv_directory' => CSVParser::RELATIVE_DIRECTORY,
            'csv_files' => $this->csvParser->list(),
        ]);
    }

    /**
     * @Route("/csv-manager/import", priority=15, name="import_csv")
     */
    public function import_csv(Request $request): Response
    {
        /** @var Session $session */
        $session = $request->getSession();
        $flashbag = $session->getFlashBag();

        // If asked > launch import
        if ($request->request->get('import-launch') !== null) {
            if ('dev' === $this->environment) {
                $em = $this->getDoctrine()->getManager();
                $filename = $request->request->get('import-csv-file');
                $vinyls_csv = $this->csvParser->parse($filename);

                // Clear database (vinyls & artists)
                if ($request->request->get('import-clear-db') === 'on') {
                    $this->sampleRepository->resetDatabase();
                    $this->vinylRepository->resetDatabase();
                    $this->artistRepository->resetDatabase();
                }

                // Import vinyls from CSV file if not empty
                if (!empty($vinyls_csv)) {
                    // Loop on CSV vinyls
                    foreach ($vinyls_csv as $data) {
                        // Avoid invalid lines
                        if (!isset($data['face-a']) && !isset($data[1])) {
                            continue;
                        }

                        $vinyl = new Vinyl();
                        // Set new vinyl fields
                        //  > Tracks
                        $vinyl->setTrackFaceA(isset($data['face-a']) ? $data['face-a'] : $data[1]);
                        $vinyl->setTrackFaceB(isset($data['face-b']) ? $data['face-b'] : $data[2]);
                        //  > Artist(sb)
                        $d_artists = explode(self::ARTISTS_SEPARATOR, isset($data['artists']) ? $data['artists'] : $data[3]);
                        foreach ($d_artists as $artist_name) {
                            // If artist already exist, retrieve it, or create a new one
                            $artist = $this->artistRepository->findOneByName(trim($artist_name));
                            if (is_null($artist)) {
                                $artist = new Artist();
                                $artist->setName(trim($artist_name));

                                // Persist artist...
                                $em->persist($artist);
                                //  ... and flush, in order to retrieve later with `findOneByName()`
                                $em->flush();
                            }

                            // Add artist to current vinyl
                            $vinyl->addArtist($artist);
                        }
                        //  > Quantities
                        $vinyl->setQuantity((int) (isset($data['quantity']) ? $data['quantity'] : $data[0]));
                        if (isset($data['quantity-with-cover'])) { $vinyl->setQuantityWithCover((int) $data['quantity-with-cover']); }
                        if (isset($data['quantity-sold'])) { $vinyl->setQuantitySold((int) $data['quantity-sold']); }

                        // Persist vinyl
                        $em->persist($vinyl);
                    }

                    // Try to save all new artists & vinyls into database
                    try {
                        // Flush OK !
                        $em->flush();

                        $return = array(
                            'query_status' => 1,
                            'slug_status' => 'success',
                            'message_status' => 'Importation des vinyles effectuée avec succès.',
                            'id_entity' => $vinyl->getId()
                        );
                    } catch (\Exception $e) {
                        // Something goes wrong
                        $em->clear();

                        $return = array(
                            'query_status' => 0,
                            'slug_status' => 'error',
                            'exception' => $e->getMessage(),
                            'message_status' => 'Un problème est survenu lors de l\'importation des vinyles.'
                        );
                    }

                    // Set $return message
                    $flashbag->add($return['slug_status'], $return['message_status']);
                } else {
                    // Set message if file is empty / not found
                    $flashbag->add(
                        'notice',
                        sprintf(
                            'Le fichier CSV à importer est vide (localisation: "%s%s").',
                            CSVParser::MAIN_DIRECTORY,
                            $filename
                        )
                    );
                }
            } else {
                $flashbag->add('notice', 'Importation du CSV activée seulement en `dev` par sécurité.');
            }
        }

        return $this->redirectToRoute('csv_manager');
    }

    /**
     * @Route("/csv-manager/export", priority=15, name="export_csv")
     */
    public function export_csv(Request $request): Response
    {
        try {
            $filename = sprintf('vinyls.%s.csv', (new \DateTime())->format('Y-m-d-H:i:s'));
            $filepath = $this->csvParser->export(
                $filename,
                $this->formatVinyls($this->vinylRepository->findAll())
            );

            $return = array(
                'query_status' => 1,
                'slug_status' => 'success',
                'message_status' => sprintf(
                    'Vinyles exportés avec succès (fichier: <a href="%s">%s</a>).',
                    $filepath,
                    $filename
                )
            );
        } catch (\Exception $e) {
            $return = array(
                'query_status' => 0,
                'slug_status' => 'error',
                'exception' => $e->getMessage(),
                'message_status' => 'Un problème est survenu lors de l\'exportation des vinyles.'
            );
        }

        /** @var Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add($return['slug_status'], $return['message_status']);

        return $this->redirectToRoute('csv_manager');
    }

    /**
     * Format vinyls to prepare them for export
     *
     * @param Vinyl[] $vinyls
     */
    private function formatVinyls(array $vinyls): array
    {
        $formatted = [];

        foreach ($vinyls as $vinyl) {
            $artists = [];
            foreach ($vinyl->getArtists() as $artist) {
                $artists[] = $artist->getName();
            }

            $formatted[] = [
                'artists' => implode(self::ARTISTS_SEPARATOR, $artists),
                'face-a' => $vinyl->getTrackFaceA(),
                'face-b' => $vinyl->getTrackFaceB(),
                'quantity' => $vinyl->getQuantity(),
                'quantity-with-cover' => $vinyl->getQuantityWithCover(),
                'quantity-sold' => $vinyl->getQuantitySold()
            ];
        }

        return $formatted;
    }
}
