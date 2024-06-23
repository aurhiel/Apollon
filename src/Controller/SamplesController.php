<?php

namespace App\Controller;

use App\Entity\Sample;
use App\Repository\SampleRepository;
use App\Repository\VinylRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @IsGranted("ROLE_ADMIN")
 */
class SamplesController extends AbstractController
{
    private VinylRepository $vinylRepository;
    private SampleRepository $sampleRepository;

    public function __construct(
        VinylRepository $vinylRepository,
        SampleRepository $sampleRepository
    ) {
        $this->vinylRepository = $vinylRepository;
        $this->sampleRepository = $sampleRepository;
    }

    /**
     * @Route("/exemplaires", name="samples_create", methods="POST", priority=10)
     */
    public function create(Request $request): Response
    {
        $payload = $request->request;
        $vinyl = $this->vinylRepository->findRequired($payload->getInt('vinyl-id'));

        // Create the new sample
        $sample = (new Sample())
            ->setVinyl($vinyl)
            ->setRateFaceA($payload->getInt('rate-face-a'))
            ->setRateFaceB($payload->getInt('rate-face-b'))
            ->setHasCover($payload->getBoolean('has-cover'))
            ->setHasGenericCover($payload->getBoolean('has-generic-cover'))
            ->setRateCover($payload->getBoolean('has-cover') ? $payload->getInt('rate-cover') : null)
            ->setPrice(!empty($payload->get('price')) ? (float) $payload->get('price') : null)
            ->setDetails(!empty($payload->get('details')) ? $payload->get('details') : null)
        ;

        $em = $this->getDoctrine()->getManager();

        // Save new sample !
        $em->persist($sample);
        $em->flush();

        return $this->json([
            'id' => $sample->getId(),
            'rateFaceA' => $sample->getRateFaceA(),
            'rateFaceB' => $sample->getRateFaceB(),
            'rateCover' => $sample->getRateCover(),
            'hasCover' => $sample->getHasCover(),
            'hasGenericCover' => $sample->getHasGenericCover(),
            'price' => $sample->getPrice() ?? 0.0,
            'details' => $sample->getDetails()
        ]);
    }

    /**
     * @Route("/exemplaires/{sample_id}", name="samples_delete", methods="DELETE", priority=10)
     */
    public function delete(int $sample_id): Response
    {
        $em = $this->getDoctrine()->getManager();
        $sample = $this->sampleRepository->findRequired($sample_id);

        $em->remove($sample);
        $em->flush();

        return $this->json([], Response::HTTP_NO_CONTENT);
    }
}
