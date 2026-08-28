<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

class DeleteAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir votre mot de passe actuel.'
                    ),
                ],
            ])
            ->add('confirmation', TextType::class, [
                'label' => 'Confirmation',
                'mapped' => false,
                'help' => 'Saisissez SUPPRIMER pour confirmer.',
                'attr' => [
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez confirmer la suppression.'
                    ),
                    new EqualTo(
                        value: 'SUPPRIMER',
                        message: 'Saisissez exactement SUPPRIMER pour confirmer.',
                    ),
                ],
            ]);
    }
}
