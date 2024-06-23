<?php

namespace App\Form;

// Entities
use App\Entity\Vinyl;
use App\Entity\Artist;
use App\Entity\Sample;
// Repositories
use App\Repository\ArtistRepository;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Types
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class VinylType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('rpm', IntegerType::class, [
                'label' => 'form_vinyl.rpm.label',
                'attr' => [
                    'placeholder' => 'form_vinyl.rpm.placeholder',
                    'min' => 33,
                    'max' => 100
                ]
            ])
            ->add('track_face_A', TextType::class, [
                'label' => 'form_vinyl.track_face_a.label',
                'attr' => [ 'placeholder' => 'form_vinyl.track_face_a.placeholder' ]
            ])
            ->add('track_face_B', TextType::class, [
                'label' => 'form_vinyl.track_face_b.label',
                'attr' => [ 'placeholder' => 'form_vinyl.track_face_b.placeholder' ]
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'form_vinyl.qty.label',
                'attr' => [
                    'placeholder' => 'form_vinyl.qty.placeholder',
                    'min' => 1
                ]
            ])
            ->add('quantity_sold', IntegerType::class, [
                'label' => 'form_vinyl.qty_sold.label',
                'attr' => [
                    'placeholder' => 'form_vinyl.qty_sold.placeholder',
                    'min' => 0
                ]
            ])
            ->add('quantity_with_cover', IntegerType::class, [
                'label' => 'form_vinyl.qty_cover.label',
                'attr' => [
                    'placeholder' => 'form_vinyl.qty_cover.placeholder',
                    'min' => 0
                ]
            ])
            ->add('artists', EntityType::class, [
                'class' => Artist::class,
                'label' => 'form_vinyl.artists.label',
                'query_builder' => function (ArtistRepository $repo) {
                    // Re-order artists by their name
                    return $repo->createQueryBuilder('a')
                        ->addOrderBy('a.name', 'ASC');
                },
                'choice_label' => function ($artist) {
                    return $artist->getName();
                },
                'multiple' => true,
                'expanded' => true
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'form_vinyl.notes.label',
                'label_attr' => [ 'class' => 'form-label fw-bold' ],
                'attr' => [ 'placeholder' => 'form_vinyl.notes.placeholder' ],
                'required' => false,
            ])
            ->add('samples', EntityType::class, [
                'class' => Sample::class,
                'label' => 'form_vinyl.samples.label',
                'label_attr' => [ 'class' => 'col-form-label fw-bold'],
                'attr' => [ 'class' => 'visually-hidden' ],
                'multiple' => true,
                'required' => false,
            ])
            ->add('images', FileType::class,[
                'label' => 'form_basic.images.label',
                'label_attr' => [ 'class' => 'col-form-label fw-bold'],
                'attr' => [ 'class' => 'form-control' ],
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                // unmapped fields can't define their validation using annotations
                // in the associated entity, so you can use the PHP constraint classes
                // 'constraints' => [
                //     new File([
                //         'maxSize' => '4096k',
                //         'mimeTypes' => [ 'image/*' ],
                //         'mimeTypesMessage' => 'Please upload a valid Image file',
                //     ])
                // ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'form_basic.save.label',
                'attr' => [ 'class' => 'btn-send btn-primary' ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Vinyl::class,
        ]);
    }
}
