<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Products;
use App\Form\ProductsType;
use App\Repository\ActivityLogRepository;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Entity\User;

#[Route('/products')]
final class ProductsController extends AbstractController
{
    #[Route(name: 'app_products_index', methods: ['GET'])]
    public function index(Request $request, ProductsRepository $productsRepository, ActivityLogRepository $activityLogRepository): Response
    {
        $selectedCategory = trim((string) $request->query->get('category', ''));

        // Determine sort order from query param
        $sort = (string) $request->query->get('sort', 'default');
        $orderBy = ['collectionType' => 'ASC', 'name' => 'ASC'];
        if ($sort === 'price_asc') {
            $orderBy = ['price' => 'ASC'];
        } elseif ($sort === 'price_desc') {
            $orderBy = ['price' => 'DESC'];
        }

        $allProducts = $productsRepository->findBy([], $orderBy);
        $allProducts = array_values(array_filter($allProducts, static function (Products $product): bool {
            return strtolower((string) $product->getCollectionType()) !== 'mascots';
        }));

        $products = $selectedCategory !== ''
            ? array_values(array_filter($allProducts, static function (Products $product) use ($selectedCategory): bool {
                return $product->getCollectionType() === $selectedCategory;
            }))
            : $allProducts;

        $categoryCounts = [];
        foreach ($allProducts as $product) {
            $categoryName = $product->getCollectionType() ?: 'General';
            $categoryCounts[$categoryName] = ($categoryCounts[$categoryName] ?? 0) + 1;
        }

        return $this->render('products/index.html.twig', [
            'products' => $products,
            'categories' => $categoryCounts,
            'selectedCategory' => $selectedCategory,
            'sort' => $sort,
            'productAdditionHistory' => $activityLogRepository->findProductAdditionHistory(),
        ]);
    }

    #[Route('/new', name: 'app_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('You do not have permission to create products.');
        }
        $product = new Products();
        $form = $this->createForm(ProductsType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/products',
                        $newFilename
                    );
                    $product->setImage('/uploads/products/'.$newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error uploading image: '.$e->getMessage());
                }
            }

            // set owner
            $product->setCreatedBy($this->getUser());

            $entityManager->persist($product);
            $entityManager->flush();

            $user = $this->getUser();
            $history = new ActivityLog();
            $history->setUserId(is_object($user) && method_exists($user, 'getId') ? $user->getId() : null);
            $history->setUsername(is_object($user) && method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null);
            $roles = is_object($user) && method_exists($user, 'getRoles') ? $user->getRoles() : [];
            $history->setRole(is_array($roles) && count($roles) ? $roles[0] : null);
            $history->setAction('CREATE');
            $history->setTarget(sprintf('Products (id: %s) - %s', $product->getId(), $product->getName()));

            $entityManager->persist($history);
            $entityManager->flush();

            $this->addFlash('success', 'Product created successfully.');

            return $this->redirectToRoute('app_products_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('products/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_products_show', methods: ['GET'])]
    public function show(Products $product): Response
    {
        return $this->render('products/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_products_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Products $product, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if (!$this->canManageProduct($product)) {
            throw $this->createAccessDeniedException('You do not have permission to edit this product.');
        }

        $form = $this->createForm(ProductsType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                // Delete old image if exists
                $oldImage = $product->getImage();
                if ($oldImage) {
                    $oldImagePath = $this->getParameter('kernel.project_dir').'/public'.$oldImage;
                    if (file_exists($oldImagePath)) {
                        try {
                            // Try to delete the old image, but don't fail if it's locked
                            @unlink($oldImagePath);
                        } catch (\Exception $e) {
                            // Silently ignore file deletion errors (file might be locked on Windows)
                            // The old file will remain, but the new one will be saved
                        }
                    }
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/products',
                        $newFilename
                    );
                    $product->setImage('/uploads/products/'.$newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error uploading image: '.$e->getMessage());
                }
            }

            $entityManager->flush();

            $user = $this->getUser();
            $history = new ActivityLog();
            $history->setUserId(is_object($user) && method_exists($user, 'getId') ? $user->getId() : null);
            $history->setUsername(is_object($user) && method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null);
            $roles = is_object($user) && method_exists($user, 'getRoles') ? $user->getRoles() : [];
            $history->setRole(is_array($roles) && count($roles) ? $roles[0] : null);
            $history->setAction('UPDATE');
            $history->setTarget(sprintf('Products (id: %s) - %s', $product->getId(), $product->getName()));

            $entityManager->persist($history);
            $entityManager->flush();

            $this->addFlash('success', 'Product updated successfully.');

            return $this->redirectToRoute('app_products_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('products/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_products_delete', methods: ['POST'])]
    public function delete(Request $request, Products $product, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canManageProduct($product)) {
            throw $this->createAccessDeniedException('You do not have permission to delete this product.');
        }

        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
            $this->addFlash('success', 'Product deleted successfully.');
        }

        return $this->redirectToRoute('app_products_index', [], Response::HTTP_SEE_OTHER);
    }

    private function canManageProduct(Products $product): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return true;
        }

        $createdBy = $product->getCreatedBy();
        $isOwner = $createdBy?->getId() === $user->getId();

        return $isOwner;
    }
}
