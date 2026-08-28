<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\SpecialOffer;
use App\Enum\SpecialOfferPlacement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SpecialOfferType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                'title',
                TextType::class,
                [
                    'label' => 'Titre',
                ]
            )
            ->add(
                'content',
                TextareaType::class,
                [
                    'label' => 'Contenu',
                    'attr' => [
                        'rows' => 4,
                    ],
                ]
            )
            ->add(
                'ctaLabel',
                TextType::class,
                [
                    'label' => 'Texte du bouton',
                    'required' => false,
                    'help' =>
                        'Exemple : Découvrir l’offre',
                ]
            )
            ->add(
                'targetUrl',
                TextType::class,
                [
                    'label' => 'Lien de redirection',
                    'required' => false,
                    'help' =>
                        'Chemin interne comme /?category=audio ou URL HTTP(S).',
                ]
            )
            ->add(
                'placement',
                EnumType::class,
                [
                    'label' => 'Emplacement',
                    'class' =>
                        SpecialOfferPlacement::class,
                    'choice_label' =>
                        static fn (
                            SpecialOfferPlacement $placement
                        ): string =>
                            $placement->label(),
                ]
            )
            ->add(
                'targetCategory',
                EntityType::class,
                [
                    'label' => 'Catégorie ciblée',
                    'class' => Category::class,
                    'choice_label' => 'name',
                    'placeholder' =>
                        'Toutes les catégories',
                    'required' => false,
                    'help' =>
                        'Optionnel. Permet d’identifier une offre destinée à une catégorie précise.',
                ]
            )
            ->add(
                'backgroundColor',
                ColorType::class,
                [
                    'label' => 'Couleur de fond',
                ]
            )
            ->add(
                'textColor',
                ColorType::class,
                [
                    'label' => 'Couleur du texte',
                ]
            )
            ->add(
                'priority',
                IntegerType::class,
                [
                    'label' => 'Priorité',
                    'attr' => [
                        'min' => 0,
                    ],
                    'help' =>
                        'Plus la valeur est élevée, plus l’offre est affichée en priorité.',
                ]
            )
            ->add(
                'startsAt',
                DateTimeType::class,
                [
                    'label' => 'Début de diffusion',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'help' =>
                        'Laisser vide pour une activation immédiate.',
                ]
            )
            ->add(
                'endsAt',
                DateTimeType::class,
                [
                    'label' => 'Fin de diffusion',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'help' =>
                        'Laisser vide pour une durée indéterminée.',
                ]
            )
            ->add(
                'isActive',
                CheckboxType::class,
                [
                    'label' => 'Offre activée',
                    'required' => false,
                ]
            )
            ->add(
                'experimentKey',
                TextType::class,
                [
                    'label' => 'Clé d’expérience',
                    'required' => false,
                    'help' =>
                        'Exemple : summer-offer-2026',
                ]
            )
            ->add(
                'experimentVariant',
                TextType::class,
                [
                    'label' => 'Variante',
                    'required' => false,
                    'help' =>
                        'Exemple : A, B ou control.',
                ]
            );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => SpecialOffer::class,
        ]);
    }
}
