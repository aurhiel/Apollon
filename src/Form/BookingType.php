<?php

namespace App\Form;

use App\Entity\Advert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', HiddenType::class, [
                'label'       => 'form_booking.title.label',
                'label_attr'  => [ 'class' => 'form-label fw-bold'],
                'required'    => false,
                'attr'        => [
                    'placeholder' => 'form_booking.title.placeholder',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'form_booking.description.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'required'    => false,
                'attr'        => [
                    'placeholder' => 'form_booking.description.placeholder',
                ],
            ])
            ->add('name', TextType::class, [
                'label'       => 'form_booking.name.label',
                'label_attr'  => [ 'class' => 'form-label fw-bold'],
                'required'    => true,
                'attr'        => [
                    'placeholder' => 'form_booking.name.placeholder',
                ]
            ])
            ->add('price', NumberType::class, [
                'label'         => 'form_booking.price.label',
                'label_attr'    => [ 'class' => 'form-label fw-bold'],
                'scale'         => 2,
                'html5'         => true,
                'required'      => true,
                'attr'          => [
                    'placeholder'   => 'form_booking.price.placeholder',
                    'class'         => 'text-right',
                    'autocomplete'  => 'off',
                    'step'          => '0.01'
                ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'form_booking.save.label',
                'attr'  => [
                    'class' => 'btn-send btn-primary'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Advert::class,
        ]);
    }
}
