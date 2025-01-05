<?php

namespace App\Controller;

use App\Entity\Image;
use App\Entity\Advert;
use App\Entity\InSale;
use App\Form\AdvertType;
use App\Form\BookingType;
use App\Repository\AdvertRepository;
use App\Repository\InSaleRepository;
use App\Repository\SampleRepository;
use App\Repository\VinylRepository;
use App\Service\FileUploader;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class AdvertController extends AbstractController
{
    private AdvertRepository $advertRepository;
    private InSaleRepository $inSaleRepository;
    private SampleRepository $sampleRepository;
    private VinylRepository $vinylRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private KernelInterface $kernel;

    public function __construct(
        AdvertRepository $advertRepository,
        InSaleRepository $inSaleRepository,
        SampleRepository $sampleRepository,
        VinylRepository $vinylRepository,
        MailerInterface $mailer,
        Environment $twig,
        KernelInterface $kernel
    ) {
        $this->advertRepository = $advertRepository;
        $this->inSaleRepository = $inSaleRepository;
        $this->sampleRepository = $sampleRepository;
        $this->vinylRepository = $vinylRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->kernel = $kernel;
    }

    /**
     * @Route("/annonces/{id}", name="adverts", defaults={"id"=null})
     */
    public function adverts(?int $id, Request $request, Security $security, AuthorizationCheckerInterface $authChecker, FileUploader $fileUploader): Response
    {
        $em = $this->getDoctrine()->getManager();
        $user = $security->getUser();

        // Retrieve advert if asked ($advert_entity != null) or new one
        $advertEdit = $this->advertRepository->findOneById($id);
        $isEdit = !is_null($advertEdit);
        $advert = ($isEdit === true) ? $advertEdit : new Advert();

        // Only admin user can add vinyls & artists
        if(true === $authChecker->isGranted('ROLE_ADMIN')) {
            // 1) Build advert forms
            $formAdvert = $this->createForm(AdvertType::class, $advert);

            // 2) Handle advert forms
            $formAdvert->handleRequest($request);

            // 3) Save advert
            if ($formAdvert->isSubmitted() && $formAdvert->isValid()) {
                $em->persist($advert);

                // 4) Try to save (flush) or clear
                try {
                    // Flush OK !
                    $em->flush();

                    $return = array(
                        'query_status' => 1,
                        'slug_status' => 'success',
                        'message_status' => (($isEdit === true) ? 'Modification' : 'Ajout' ) . ' de l\'annonce effectuée avec succès.',
                        'id_entity' => $advert->getId()
                    );

                    // Assign added advert to re-use later
                    // $advertAdded = $advert;

                    // Assign vinyls to created advert
                    $this->convertVinylsSelectedToInSales($advert, $request->get('advert_vinyl_selected'), true);

                    // Upload advert images
                    $images = $formAdvert->get('images')->getData();
                    foreach ($images as $imgData) {
                        // Upload new advert image
                        $imageFileName = $fileUploader->upload($imgData, '/adverts/'.$advert->getId());

                        // Create & persist new Image entity after upload
                        $image = new Image();
                        $image->setFilename($imageFileName);

                        // Add new image to current advert
                        $advert->addImage($image);

                        $em->persist($image);
                    }
                    $em->flush();

                    // Clear/reset form
                    $advert = new Advert();
                    $formAdvert = $this->createForm(AdvertType::class, $advert);
                } catch (\Exception $e) {
                    // Something goes wrong
                    $em->clear();

                    $return = array(
                        'query_status' => 0,
                        'slug_status' => 'error',
                        'exception' => $e->getMessage() . ', at line: '.$e->getLine(),
                        'message_status' => sprintf(
                          'Un problème est survenu lors de la %s de l\'annonce.',
                          ($isEdit === true ? 'modification' : 'sauvegarde')
                        ),
                    );
                }
            }

            // Set flash message if $return has message_status
            if (isset($return['message_status']) && !empty($return['message_status'])) {
                /** @var Session $session */
                $session = $request->getSession();
                $session->getFlashBag()->add(
                    (isset($return['slug_status']) ? $return['slug_status'] : 'notice'),
                    $return['message_status']
                );

                // Redirect on adverts home after editing one
                if ($isEdit === true)
                    return $this->redirectToRoute('adverts');
            }
        }

        // Retrieve vinyls to use in advert form and create "InSale" related to a new advert
        $vinylsToSale = $this->vinylRepository->findAllAvailableForSale();
        usort($vinylsToSale, function($a, $b) {
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

        // Get advert's vinyls by ID
        $advertVinyls = [];
        if (null !== $advert->getId()) {
            foreach ($advert->getInSales() as $inSale) {
                $advertVinyls[$inSale->getVinyl()->getId()] = $inSale;
            }
        }

        return $this->render('adverts/list.html.twig', [
            'meta' => [
                'title' => 'Annonces'
            ],
            'user'              => $user,
            'form_advert'       => isset($formAdvert) ? $formAdvert->createView() : null,
            'adverts'           => $this->advertRepository->findAll(!$authChecker->isGranted('ROLE_VIEWER')),
            'nb_adverts_sold'   => $this->advertRepository->countAllSold(),
            'is_advert_edit'    => $isEdit,
            'advert_to_edit'    => $advert,
            'advert_vinyls'     => $advertVinyls,
            'total_vinyls'      => (int) $this->vinylRepository->countAll(),
            'vinyls_to_sale'    => $vinylsToSale,
            'nb_vinyls_in_sale' => (int) $this->inSaleRepository->countVinylsInSale(),
            'nb_vinyls_sold'    => (int) $this->vinylRepository->countVinylsSold(),
            'total_prices'      => (float) $this->advertRepository->countTotalPrices(),
            'total_prices_checkout' => (float) $this->advertRepository->countTotalPricesCheckout(),
        ]);
    }

    /**
     * @Route("/annonces/{id}-{slug}.html", priority=10, name="advert_infos", defaults={"key"=null})
     */
    public function advert_info(int $id, string $slug, Request $request, Security $security): Response
    {
        $user = $security->getUser();
        $advert = $this->advertRepository->findOneById($id);

        if (null !== $advert->getKey() && $advert->getKey() !== $request->query->get('key')) {
          return $this->redirectToRoute('adverts');
        }

        return $this->render('adverts/single.html.twig', [
          'meta' => [
              'title' => $advert->getTitle() . ' - Annonces',
          ],
          'user' => $user,
          'advert' => $advert,
      ]);
    }

    /**
     * @Route("/annonces/{id}/supprimer", name="advert_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function advert_delete($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $this->advertRepository->findOneById($id);

        if ($entity !== null) {
            // Remove related images
            $images = $entity->getImages();
            foreach ($images as $image) {
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
                'query_status' => 1,
                'slug_status' => 'success',
                'message_status' => 'L\'annonce a bien été supprimée.'
            );

        } else {
            $return = array(
                'query_status' => 0,
                'slug_status' => 'error',
                'message_status' => 'L\'annonce avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.'
            );
        }

        // Set flash message if $return has message_status
        if (isset($return['message_status']) && !empty($return['message_status'])) {
            /** @var Session $session */
            $session = $request->getSession();
            $session->getFlashBag()->add(
                (isset($return['slug_status']) ? $return['slug_status'] : 'notice'),
                $return['message_status']
            );
        }

        // No direct access
        return $this->redirectToRoute('adverts');
    }

    /**
     * @Route("/annonces/{id}/est-vendue/{isSold}", name="advert_update_is_sold")
     * @IsGranted("ROLE_ADMIN")
     */
    public function advert_update_is_sold(int $id, string $isSold, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();
        $advert = $this->advertRepository->findOneById($id);

        if ($advert !== null) {
            try {
                $isSold = ($isSold === '1');
                // Update advert is sold or not
                $advert->setIsSold($isSold);

                // Retrieve vinyls & update their quantities
                $inSales = $advert->getInSales();
                foreach ($inSales as $is) {
                    $vinyl = $is->getVinyl();
                    $max_qty = $vinyl->getQuantity();
                    $old_qty_sold = $vinyl->getQuantitySold();
                    $qty_sold = $is->getQuantity();

                    // Update sample status if sold or not
                    if (null !== $is->getSample()) {
                        $is->getSample()->setIsSold($isSold);
                    }

                    // Up / Down vinyls quantity sold
                    $vinyl->setQuantitySold((true === $isSold)
                        ? min($old_qty_sold + $qty_sold, $max_qty)
                        : max($old_qty_sold - $qty_sold, 0)
                    );
                }

                // Flush database
                $em->flush();

                // Set $return success message
                $return = array(
                    'query_status' => 1,
                    'slug_status' => 'success',
                    'message_status' => ($isSold ? 'L\'annonce a bien été passée à l\'état vendue.' : 'L\'annonce est de nouveau en vente.')
                );
            } catch (\Exception $e) {
                $return = array(
                    'query_status' => 0,
                    'slug_status' => 'error',
                    'exception' => $e->getMessage(),
                    'message_status' => 'Un problème est survenu lors du changement de l\'état est vendu de l\'annonce.'
                );
            }
        } else {
            $return = array(
                'query_status' => 0,
                'slug_status' => 'error',
                'message_status' => 'L\'annonce avec pour ID: <b>' . $id . '</b> n\'existe pas en base de données.'
            );
        }

        // Display return data as JSON when using AJAX or redirect to home
        if ($request->isXmlHttpRequest()) {
            return $this->json($return);
        } else {
            /**
             * Set message in flashbag on direct access & then redirect
             *
             * @var Session $session
             */
            $session = $request->getSession();
            $session->getFlashBag()->add($return['slug_status'], $return['message_status']);

            return $this->redirectToRoute('adverts');
        }
    }

    /**
     * @Route("/reservation", name="booking")
     */
    public function booking(Request $request): Response
    {
        $booking = new Advert();
        $formBooking = $this->createForm(BookingType::class, $booking);

        $formBooking->handleRequest($request);
        if ($formBooking->isSubmitted() && $formBooking->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($booking);

            // Try to save (flush) or clear
            try {
                $em->flush();

                $return = [
                    'query_status' => 1,
                    'slug_status' => 'success',
                    'message_status' => 'Réservation effectuée avec succès, vous serez contacté au plus vite !',
                    'id_entity' => $booking->getId()
                ];

                // Assign vinyls to created advert
                $vinyls = $this->convertVinylsSelectedToInSales($booking, $request->get('advert_vinyl_selected'));

                try {
                    $booking = $request->get('booking');
                    $this->reorderVinylsByArtists($vinyls);

                    // Send email to notify admin
                    $email = (new TemplatedEmail())
                        ->from(new Address($this->getParameter('app.admin.email'), $this->getParameter('app.admin.name')))
                        ->to(new Address($this->getParameter('app.contact.email'), $this->getParameter('app.contact.name')))
                        ->subject('Nouvelle réservation de vinyle !')

                        ->htmlTemplate('emails/new-booking.html.twig')
                        ->textTemplate('emails/new-booking.text.twig')

                        ->context([
                            'booking' => [
                                'customer_name' => $booking['name'],
                                'description' => $booking['description'],
                                'price' => $booking['price']
                            ],
                            'vinyls_selected' => $vinyls,
                        ])
                    ;

                    // Debug purpose only on dev env.
                    if ('dev' === $this->kernel->getEnvironment() && false) {
                        $renderer = new BodyRenderer($this->twig);
                        $renderer->render($email);

                        echo $email->getHtmlBody();
                        exit;
                    }

                    $this->mailer->send($email);

                    $return['message_status'] = 'Réservation effectuée avec succès, un email a été envoyé, vous serez contacté au plus vite !';
                } catch (\Exception $e) {
                }
            } catch (\Exception $e) {
                $em->clear();

                $return = [
                    'query_status' => 0,
                    'slug_status' => 'error',
                    'exception' => $e->getMessage() . ', at line: '.$e->getLine(),
                    'message_status' => 'Un problème est survenu lors de la réservation, veuillez ré-essayer ultérieurement ou me contacter.',
                ];
            }

            // Set flash message if $return has message_status
            if (isset($return['message_status']) && !empty($return['message_status'])) {
                /** @var Session $session */
                $session = $request->getSession();
                $session->getFlashBag()->add(
                    (isset($return['slug_status']) ? $return['slug_status'] : 'notice'),
                    $return['message_status']
                );
            }
        }

        return $this->redirectToRoute('home');
    }

    private function convertVinylsSelectedToInSales(Advert $advert, array $vinylsSelected, bool $resetInSales = false): array
    {
        $em = $this->getDoctrine()->getManager();

        // Remove old vinyls "in sale"
        if (true === $resetInSales) {
            foreach ($advert->getInSales() as $inSale) {
                $em->remove($inSale);
            }
        }

        $vinyls = $this->vinylRepository->findById(array_keys($vinylsSelected));
        foreach ($vinyls as $vinyl) {
            $vinylInput = $vinylsSelected[$vinyl->getId()];
            $quantity = isset($vinylInput['qty']) ? (int) $vinylInput['qty'] : 0;
            $inSale = new InSale();

            if ($quantity > 0) {
                $inSale->setVinyl($vinyl);
                $inSale->setAdvert($advert);
                $inSale->setQuantity($quantity);

                if (isset($vinylInput['sample'])) {
                    $inSale->setSample(
                        $this->sampleRepository->findOneById((int) $vinylInput['sample'])
                    );
                }

                // Persist & flush new vinyl in sale
                $em->persist($inSale);
            }
        }

        // Flush new in sales in database
        $em->flush();

        return $vinyls;
    }

    private function reorderVinylsByArtists(array &$vinyls): void
    {
        usort($vinyls, function($a, $b) {
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
    }

    private function removeAccents(string &$string): string
    {
        $string = strtr(utf8_decode($string), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
        return $string;
    }
}