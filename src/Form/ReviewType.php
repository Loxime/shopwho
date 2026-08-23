<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Note',
                'choices' => ['5 étoiles' => 5, '4 étoiles' => 4, '3 étoiles' => 3, '2 étoiles' => 2, '1 étoile' => 1],
                'expanded' => true,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire (facultatif)',
                'required' => false,
                'attr' => ['maxlength' => 3000, 'rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Review::class]);
    }
}
