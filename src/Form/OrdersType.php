<?php

namespace App\Form;

use App\Entity\Orders;
use App\Entity\Products;
use App\Entity\Customer;
use App\Repository\ProductsRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrdersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name', // Change this to whatever field you want to display
                'placeholder' => 'Select a customer',
            ])
            ->add('products', EntityType::class, [
                'class' => Products::class,
                'choice_label' => 'name', // Change this to whatever field you want to display
                'multiple' => true,
                'expanded' => false, // Set to true for checkboxes, false for select
                'placeholder' => 'Select products',
                'query_builder' => function (ProductsRepository $productsRepository) {
                    return $productsRepository->createQueryBuilder('p')
                        ->andWhere('LOWER(p.collectionType) <> :rentalOnly OR p.collectionType IS NULL')
                        ->setParameter('rentalOnly', 'mascots');
                },
            ])
            ->add('quantity', null, [
                'label' => 'Quantity',
                'attr' => ['min' => 1],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Pending' => 'Pending',
                    'Delivered' => 'Delivered',
                    'Cancelled' => 'Cancelled',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orders::class,
        ]);
    }
}