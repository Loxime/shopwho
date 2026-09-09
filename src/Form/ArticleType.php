<?php

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ArticleType extends AbstractType
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
                'slug',
                TextType::class,
                [
                    'label' => 'Slug',
                    'required' => false,
                    'help' =>
                        'Laisser vide pour le générer depuis le titre.',
                ]
            )
            ->add(
                'excerpt',
                TextareaType::class,
                [
                    'label' => 'Résumé',
                    'help' =>
                        'Résumé court utilisé sur la liste des articles et pour le SEO.',
                    'attr' => [
                        'rows' => 4,
                    ],
                ]
            )
            ->add(
                'content',
                TextareaType::class,
                [
                    'label' => 'Contenu',
                    'attr' => [
                        'rows' => 18,
                    ],
                ]
            )
            ->add(
                'coverImageUrl',
                UrlType::class,
                [
                    'label' =>
                        'URL de l’image de couverture',
                    'required' => false,
                ]
            )
            ->add(
                'isPublished',
                CheckboxType::class,
                [
                    'label' => 'Publié',
                    'required' => false,
                ]
            )
            ->add(
                'publishedAt',
                DateTimeType::class,
                [
                    'label' =>
                        'Date de publication',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' =>
                        'datetime_immutable',
                    'help' =>
                        'Une date future permet de planifier la publication.',
                ]
            );
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
