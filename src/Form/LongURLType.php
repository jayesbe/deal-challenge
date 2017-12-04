<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\NotBlank;

class LongURLType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('url', UrlType::class, [
            'label' => 'Long URL',
            'default_protocol' => 'https',
            'attr' => [
                'placeholder' => 'https://www.',
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'Long URL must not be blank.'
                ]),
            ]
        ])
        ->add('save', SubmitType::class, [
            'label' => 'Shorten',
            'validation_groups' => false
        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // uncomment if you want to bind to a class
            //'data_class' => URL::class,
        ]);
    }
}
