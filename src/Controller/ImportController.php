<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Vinyl;
use App\Repository\ArtistRepository;
use App\Repository\VinylRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

class ImportController extends AbstractController
{
    private string $environment;
    private ArtistRepository $artistRepository;
    private VinylRepository $vinylRepository;

    public function __construct(
        KernelInterface $kernel,
        ArtistRepository $artistRepository,
        VinylRepository $vinylRepository
    ) {
        $this->environment = $kernel->getEnvironment();
        $this->artistRepository = $artistRepository;
        $this->vinylRepository = $vinylRepository;
    }

    /**
     * @Route("/importer-csv", priority=15, name="import_csv")
     * @IsGranted("ROLE_ADMIN")
     */
    public function import_csv(Request $request, Security $security, AuthorizationCheckerInterface $authChecker)
    {
        $flashbag = $request->getSession()->getFlashBag();

        // If asked > launch import
        if ($request->request->get('import-launch') !== null) {
            if ('dev' === $this->environment) {
                $em = $this->getDoctrine()->getManager();
                $vinyls_csv = $this->parseCSV();

                // Clear database (vinyls & artists)
                if ($request->request->get('import-clear-db') === 'on') {
                    $this->vinylRepository->resetDatabase();
                    $this->artistRepository->resetDatabase();
                }

                // Import vinyls from CSV file if not empty
                if (!empty($vinyls_csv)) {

                    // Loop on CSV vinyls
                    foreach ($vinyls_csv as $data) {
                        $vinyl = new Vinyl();

                        // Set new vinyl fields
                        // // Quantity
                        $vinyl->setQuantity((int) $data[0]);
                        // // Tracks
                        $vinyl->setTrackFaceA($data[1]);
                        $vinyl->setTrackFaceB($data[2]);
                        // // Artist
                        $d_artists = explode('/', $data[3]);
                        foreach ($d_artists as $artist_name) {
                            // If artist already exist, retrieve it, or create a new one
                            $artist = $this->artistRepository->findOneByName(trim($artist_name));
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
                    $flashbag->add('notice',
                      'Le fichier CSV est vide ou n\'a pas été trouvé (localisation: "' . $this->csvParsingOptions['finder_in'] . '' . $this->csvParsingOptions['finder_name'] . '").');
                }
            } else {
                $flashbag->add('notice', 'Importation du CSV activée seulement en `dev` par sécurité.');
            }
        }

        return $this->render('import-csv.html.twig', [
            'user' => $security->getUser(),
        ]);
    }

    private $csvParsingOptions = array(
        'finder_in' => '../public/uploads/',
        'finder_name' => 'vinyls.csv',
        'ignoreFirstLine' => true,
        'delimiter' => ','
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

        if (!isset($csv)) {
            throw new \RuntimeException(sprintf('Cannot find CSV file to import (in: %s, name: %s)', $this->csvParsingOptions['finder_in'], $this->csvParsingOptions['finder_name']));
        }

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
}