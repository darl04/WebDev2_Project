<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Products;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Products::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a product',
                'label' => 'Product',
                'required' => true,
            ])
            ->add('quantity', null, [
                'label' => 'Quantity',
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Enter quantity'
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'In Stock' => 'In Stock',
                    'Low Stock' => 'Low Stock',
                    'Out of Stock' => 'Out of Stock',
                    'Reserved' => 'Reserved',
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
            'data_class' => Stock::class,
        ]);
    }
}

