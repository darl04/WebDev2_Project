<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Products;

class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $productsData = [
            ['name' => 'Vintage Clock', 'price' => '1999', 'description' => 'A beautiful vintage clock.', 'collectionType' => 'home', 'image' => 'clock.jpg'],
            ['name' => 'Leather Wallet', 'price' => '499', 'description' => 'Handmade leather wallet.', 'collectionType' => 'accessories', 'image' => 'wallet.jpg'],
            ['name' => 'Bluetooth Speaker', 'price' => '2999', 'description' => 'Portable speaker with deep bass.', 'collectionType' => 'electronics', 'image' => 'speaker.jpg'],
        ];

        foreach ($productsData as $p) {
            $product = new Products();
            $product->setName($p['name']);
            $product->setPrice($p['price']);
            $product->setDescription($p['description']);
            $product->setCollectionType($p['collectionType']);
            $product->setImage($p['image']);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
