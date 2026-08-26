<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'given-name'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner votre prénom.'),
                    new Length(max: 100),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'family-name'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner votre nom.'),
                    new Length(max: 100),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner votre adresse e-mail.'),
                    new Email(message: 'Veuillez saisir une adresse e-mail valide.'),
                    new Length(max: 180),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'invalid_message' => 'Les nouveaux mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'required' => false,
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'required' => false,
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new Length(
                        min: 12,
                        max: 4096,
                        minMessage: 'Le nouveau mot de passe doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'help' => 'Confirmez votre mot de passe actuel pour enregistrer les modifications.',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre mot de passe actuel.'),
                ],
            ]);
    }
}
