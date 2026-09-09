<?php

namespace App\Form;

use App\Entity\Partner;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PartnerType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'label' => 'Nom',
                ]
            )
            ->add(
                'description',
                TextareaType::class,
                [
                    'label' => 'Description',
                    'required' => false,
                    'attr' => [
                        'rows' => 5,
                    ],
                ]
            )
            ->add(
                'websiteUrl',
                UrlType::class,
                [
                    'label' =>
                        'Site internet',
                ]
            )
            ->add(
                'logoUrl',
                UrlType::class,
                [
                    'label' =>
                        'URL du logo',
                    'required' => false,
                ]
            )
            ->add(
                'priority',
                IntegerType::class,
                [
                    'label' =>
                        'Priorité d’affichage',
                    'help' =>
                        'Les valeurs les plus élevées sont affichées en premier.',
                ]
            )
            ->add(
                'isActive',
                CheckboxType::class,
                [
                    'label' => 'Actif',
                    'required' => false,
                ]
            );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Partner::class,
        ]);
    }
}
