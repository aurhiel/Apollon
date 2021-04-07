<?php

namespace App\Form;

use App\Entity\Artist;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ArtistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label'       => 'form_artist.name.label',
                'label_attr'  => [ 'class' => 'visually-hidden'],
                'attr'        => [
                    'placeholder' => 'form_artist.name.placeholder',
                    // 'class'       => 'required-giga'
                ]
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
            'data_class' => Artist::class,
        ]);
    }
}
