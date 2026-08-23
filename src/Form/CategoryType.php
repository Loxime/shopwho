<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label' => 'Nom'])
            ->add('slug', null, ['label' => 'Slug', 'required' => false, 'help' => 'Laisser vide pour le générer depuis le nom.'])
            ->add('icon', null, ['label' => 'Classe Font Awesome', 'required' => false, 'help' => 'Exemple : fa-solid fa-laptop'])
            ->add('isFeatured', CheckboxType::class, ['label' => 'À la une', 'required' => false])
            ->add('showInNavigation', CheckboxType::class, ['label' => 'Afficher dans la navigation', 'required' => false])
            ->add('navigationPosition', null, ['label' => 'Position de navigation']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Category::class]);
    }
}
