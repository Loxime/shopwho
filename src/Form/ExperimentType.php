<?php

namespace App\Form;

use App\Entity\Experiment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExperimentType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                'key',
                TextType::class,
                [
                    'label' => 'Clé technique',
                    'help' =>
                        'Identifiant stable utilisé par le code, par exemple homepage-recommendation-fallback.',
                ]
            )
            ->add(
                'name',
                TextType::class,
                [
                    'label' => 'Nom',
                    'help' =>
                        'Nom lisible de l’expérience.',
                ]
            )
            ->add(
                'trafficPercentage',
                IntegerType::class,
                [
                    'label' => 'Trafic exposé (%)',
                    'attr' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                    'help' =>
                        'Part des visiteurs consentants pouvant entrer dans l’expérience.',
                ]
            )
            ->add(
                'startsAt',
                DateTimeType::class,
                [
                    'label' => 'Début planifié',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'model_timezone' => 'UTC',
                    'view_timezone' => 'Europe/Paris',
                    'help' =>
                        'Optionnel. Heure de Paris. Laisser vide pour démarrer dès l’activation.',
                ]
            )
            ->add(
                'endsAt',
                DateTimeType::class,
                [
                    'label' => 'Fin planifiée',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'model_timezone' => 'UTC',
                    'view_timezone' => 'Europe/Paris',
                    'help' =>
                        'Optionnel. Heure de Paris. Laisser vide pour une durée indéterminée.',
                ]
            );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Experiment::class,
        ]);
    }
}
