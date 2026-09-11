<?php

namespace App\Form;

use App\Entity\ExperimentVariant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExperimentVariantType extends AbstractType
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
                        'Par exemple control ou candidate.',
                ]
            )
            ->add(
                'name',
                TextType::class,
                [
                    'label' => 'Nom',
                ]
            )
            ->add(
                'weight',
                IntegerType::class,
                [
                    'label' => 'Poids',
                    'attr' => [
                        'min' => 1,
                    ],
                    'help' =>
                        'Poids relatif dans la répartition. Deux variantes à 50/50 ont par exemple chacune un poids de 50.',
                ]
            );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' =>
                ExperimentVariant::class,
        ]);
    }
}
