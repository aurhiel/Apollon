<?php

namespace App\Form;

// Entities
use App\Entity\Vinyl;
use App\Entity\Artist;

// Repositories
use App\Repository\ArtistRepository;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Types
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class VinylType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('rpm', IntegerType::class, [
                'label'       => 'form_vinyl.rpm.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_vinyl.rpm.placeholder',
                    'min'         => 33,
                    'max'         => 100
                ]
            ])
            ->add('track_face_A', TextType::class, [
                'label'       => 'form_vinyl.track_face_a.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_vinyl.track_face_a.placeholder',
                    // 'class'       => 'required-giga'
                ]
            ])
            ->add('track_face_B', TextType::class, [
                'label'       => 'form_vinyl.track_face_b.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_vinyl.track_face_b.placeholder',
                ]
            ])
            ->add('quantity', IntegerType::class, [
                'label'       => 'form_vinyl.qty.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_vinyl.qty.placeholder',
                    'min'         => 1
                ]
            ])
            ->add('quantity_sold', IntegerType::class, [
                'label'       => 'form_vinyl.qty_sold.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_vinyl.qty_sold.placeholder',
                    'min'         => 0
                ]
            ])
            ->add('artists', EntityType::class, [
                'class'         => Artist::class,
                'label'         => 'form_vinyl.artists.label',
                // 'label_attr'    => [ 'class' => 'visually-hidden'],
                'query_builder' => function (ArtistRepository $repo) {
                    // Re-order artists by their name
                    return $repo->createQueryBuilder('a')
                        ->addOrderBy('a.name', 'ASC');
                },
                'choice_label'  => function ($artist) {
                    return $artist->getName();
                },
                'multiple'  => true,
                'expanded'  => true,
                // 'attr'      => [
                //   'class' => 'custom-select'
                // ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'form_basic.save.label',
                'attr'  => [
                    'class' => 'btn-send btn-primary'
                ]
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
