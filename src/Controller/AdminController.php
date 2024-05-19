<?php

namespace App\Controller;

use App\Repository\ArtistRepository;
use App\Repository\ImageRepository;
use App\Repository\VinylRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;



/**
 * @IsGranted("ROLE_ADMIN")
 */
class AdminController extends AbstractController
{
    private ArtistRepository $artistRepository;
    private ImageRepository $imageRepository;
    private VinylRepository $vinylRepository;

    public function __construct(
        ArtistRepository $artistRepository,
        ImageRepository $imageRepository,
        VinylRepository $vinylRepository
    ) {
        $this->artistRepository = $artistRepository;
        $this->imageRepository = $imageRepository;
        $this->vinylRepository = $vinylRepository;
    }

    /**
     * @Route("/vinyles/{id}/supprimer", priority=10, name="vinyl_delete")
     */
    public function vinyl_delete($id, Request $request)
    {
        /** @var Session $session */
        $session = $request->getSession();
        $em = $this->getDoctrine()->getManager();

        // Retrieve item to delete
        $entity = $this->vinylRepository->findOneById($id);

        if ($entity !== null) {
            // Remove related images
            $images = $entity->getImages();
            foreach ($images as $image) {
                // Delete image (file deleted in ImageListener)
                $em->remove($image);
            }

            // Delete entity & flush
            $em->remove($entity);
            $em->flush();

            // Set success message
            $session->getFlashBag()->add('success',
                'Le vinyle a bien été supprimé.');
        } else {
            $session->getFlashBag()->add('error',
                'Le vinyle avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.');
        }

        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/vinyles/{id}/quantite/{type_update}", priority=10, name="vinyl_update_quantity")
     */
    public function vinyl_update_quantity($id, $type_update, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve item to update quantity
        $entity = $this->vinylRepository->findOneById($id);

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
                    'total_vinyls'    => $this->vinylRepository->countAll()
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
            /** @var Session $session */
            $session = $request->getSession();
            $session->getFlashBag()->add($return_data['slug_status'], $return_data['message_status']);

            // No direct access
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/vinyles/{id}/quantite-vendue/{type_update}", priority=10, name="vinyl_update_quantity_sold")
     */
    public function vinyl_update_quantity_sold($id, $type_update, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve item to update quantity
        $entity = $this->vinylRepository->findOneById($id);

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
                    'total_vinyls'    => $this->vinylRepository->countAll()
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
            /** @var Session $session */
            $session = $request->getSession();
            $session->getFlashBag()->add($return_data['slug_status'], $return_data['message_status']);

            // No direct access
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/vinyles/{id}/quantite-pochette/{type_update}", priority=10, name="vinyl_update_quantity_cover")
     */
    public function vinyl_update_quantity_cover($id, $type_update, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve item to update quantity
        $entity = $this->vinylRepository->findOneById($id);

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
                    'total_vinyls'    => $this->vinylRepository->countAll()
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
            /** @var Session $session */
            $session = $request->getSession();
            $session->getFlashBag()->add($return_data['slug_status'], $return_data['message_status']);

            // No direct access
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/artistes/{id}/supprimer", priority=10, name="artist_delete")
     */
    public function artist_delete($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Session $session */
        $session = $request->getSession();

        // Retrieve item to delete
        $entity = $this->artistRepository->findOneById($id);

        if ($entity !== null) {
            // Delete entity & flush
            $em->remove($entity);
            $em->flush();

            // Set success message
            $session->getFlashBag()->add('success',
              'L\'artiste a bien été supprimé.');
        } else {
            $session->getFlashBag()->add('error',
              'L\'artiste avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.');
        }

        // No direct access
        return $this->redirectToRoute('artists');
    }

    /**
     * @Route("/images/{id}/supprimer", priority=10, name="image_delete")
     */
    public function image_delete($id, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        // Retrieve advert to update
        $image  = $this->imageRepository->findOneById($id);
        // Set default message as error
        $return = [
            'query_status' => 0,
            'slug_status' => 'error',
            'message_status' => 'Un problème est survenu lors de la suppression de l\'image avec pour ID: ' . $id
        ];

        if ($image !== null) {
            $deleted_img = $image;
            // Delete image entity
            $em->remove($image);
            $em->flush();

            // Set message
            $return = [
                'query_status' => 1,
                'slug_status' => 'success',
                'message_status' => 'L\'image a bien été supprimée.',
                'image_deleted' => [
                    'id' => $id,
                    'filename' => $deleted_img->getFilename()
                ]
            ];
        } else {
            // Set message saying that image doesn't exist in DB
            $return['message_status'] = 'L\'image avec pour ID: ' . $id . ' n\'existe pas en base de données.';
        }

        // Display return data as JSON when using AJAX or redirect to home
        if ($request->isXmlHttpRequest()) {
            return $this->json($return);
        } else {
            // Set message in flashbag on direct access
            /** @var Session $session */
            $session = $request->getSession();
            $session->getFlashBag()->add($return['slug_status'], $return['message_status']);

            // No direct access
            return $this->redirectToRoute('home');
        }
    }
}
