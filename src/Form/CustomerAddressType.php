<?php

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerAddressType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'given-name',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'family-name',
                ],
            ])
            ->add('line1', TextType::class, [
                'label' => 'Adresse',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'address-line1',
                ],
            ])
            ->add('line2', TextType::class, [
                'label' => 'Complément d’adresse',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'address-line2',
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'postal-code',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'address-level2',
                ],
            ])
            ->add('countryCode', CountryType::class, [
                'label' => 'Pays',
                'empty_data' => '',
                'preferred_choices' => [
                    'FR',
                    'BE',
                    'CH',
                    'CA',
                ],
                'attr' => [
                    'autocomplete' => 'country',
                ],
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
