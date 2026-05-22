<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter email'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Email is required',
                    ]),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'Username (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Optional display name'
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                    'User' => 'ROLE_USER',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check'],
                'choice_attr' => fn() => ['class' => 'form-check-input'],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Account Status',
                'choices' => [
                    'Active' => true,
                    'Disabled' => false,
                ],
                'multiple' => false,
                'expanded' => true,
                'required' => true,
                'placeholder' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'required' => !$options['is_edit'],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $options['is_edit'] ? '(Leave empty to keep current password)' : 'Enter password',
                    'autocomplete' => 'new-password',
                ],
                'constraints' => $options['is_edit'] ? [] : [
                    new NotBlank([
                        'message' => 'Password is required for new users',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
        $resolver->addAllowedTypes('is_edit', 'bool');
    }
}
