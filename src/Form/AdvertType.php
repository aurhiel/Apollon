<?php

namespace App\Form;

use App\Entity\Advert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
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
            ->add('name', TextType::class, [
                'label'       => 'form_advert.name.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'required'    => false,
                'attr'        => [
                    'placeholder' => 'form_advert.name.placeholder',
                    // 'class'       => 'required-giga'
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
            ->add('images', FileType::class,[
                'label'       => 'form_basic.images.label',
                'label_attr'  => [ 'class' => 'col-form-label fw-bold pt-0'],
                'attr'        => [ 'class' => 'form-control' ],
                'multiple'    => true,
                'mapped'      => false,
                'required'    => false,
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
