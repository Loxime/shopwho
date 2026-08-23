<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label' => 'Nom'])
            ->add('slug', null, ['label' => 'Slug', 'required' => false, 'help' => 'Laisser vide pour le générer depuis le nom.'])
            ->add('description', TextareaType::class, ['label' => 'Description'])
            ->add('price', MoneyType::class, ['label' => 'Prix', 'currency' => 'EUR'])
            ->add('stock', null, ['label' => 'Stock'])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'choice_attr' => static fn (Category $category): array => ['data-slug' => $category->getSlug()],
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('imageUrl', null, ['label' => 'URL image', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'Actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
