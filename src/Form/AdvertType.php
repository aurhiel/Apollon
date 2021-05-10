<?php

namespace App\Form;

use App\Entity\Advert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class AdvertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, [
                'label'       => 'form_advert.title.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_advert.title.placeholder',
                    // 'class'       => 'required-giga'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'form_advert.description.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_advert.description.placeholder',
                    // 'class' => 'required-giga'
                ]
  					])
            ->add('price', NumberType::class, [
                'label'         => 'form_advert.price.label',
                'label_attr'    => [ 'class' => 'visually-hidden'],
                'scale'         => 2,
                'html5'         => true,
                'attr'          => [
                    'placeholder'   => 'form_advert.price.placeholder',
                    'class'         => 'text-right',
                    'autocomplete'  => 'off',
                    'step'          => '0.01'
                ],
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
            'data_class' => Advert::class,
        ]);
    }
}
