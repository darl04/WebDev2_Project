<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Entity\Stock;
use App\Entity\Products;
use App\Repository\CustomerRepository;
use App\Form\OrdersType;
use App\Repository\OrdersRepository;
use App\Repository\StockRepository;
use App\Repository\ProductsRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/orders')]
final class OrdersController extends AbstractController
{
    #[Route(name: 'app_orders_index', methods: ['GET'])]
    public function index(OrdersRepository $ordersRepository): Response
    {
        return $this->render('orders/index.html.twig', [
            'orders' => $ordersRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_orders_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        StockRepository $stockRepository,
        ProductsRepository $productsRepository,
        CustomerRepository $customerRepository
    ): Response
    {
        $order = new Orders();

        $productId = $request->query->getInt('product');
        if ($productId > 0) {
            $selectedProduct = $productsRepository->find($productId);
            if ($selectedProduct) {
                $order->addProduct($selectedProduct);
            }
        }

        $user = $this->getUser();
        if ($user instanceof User && $user->getEmail()) {
            $matchedCustomer = $customerRepository->findOneBy(['email' => $user->getEmail()]);
            if ($matchedCustomer) {
                $order->setCustomer($matchedCustomer);
            }
        }

        $form = $this->createForm(OrdersType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // set owner
            $order->setCreatedBy($this->getUser());
            
            // set status to Pending if not set
            if (!$order->getStatus()) {
                $order->setStatus('Pending');
            }

            // automatically set the creation time with current timezone
            if (!$order->getCreatedAt()) {
                $order->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())));
            }

            $entityManager->persist($order);
            $entityManager->flush();

            // Reduce stock for each product in the order (only if not cancelled)
            if ($order->getStatus() !== 'Cancelled') {
                $this->reduceStockForOrder($order, $stockRepository, $entityManager);
                $entityManager->flush(); // Flush stock changes
            }

            $this->addFlash('success', 'Order created successfully.');

            return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('orders/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_show', methods: ['GET'])]
    public function show(Orders $order): Response
    {
        return $this->render('orders/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_orders_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Orders $order, EntityManagerInterface $entityManager, StockRepository $stockRepository): Response
    {
        // Store original state before form processing
        $originalProducts = [];
        foreach ($order->getProducts() as $product) {
            $originalProducts[] = $product;
        }
        $originalQuantity = $order->getQuantity();
        $originalStatus = $order->getStatus();

        $form = $this->createForm(OrdersType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // permission check: admin can edit anything, staff can edit items created by admin or themselves
            $user = $this->getUser();
            if ($user instanceof User) {
                $isAdmin = $this->isGranted('ROLE_ADMIN');
                $isStaff = $this->isGranted('ROLE_STAFF');
                $isOwner = $order->getCreatedBy()?->getId() === $user->getId();
                $createdByAdmin = in_array('ROLE_ADMIN', $order->getCreatedBy()?->getRoles() ?? []);
                
                if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                    throw $this->createAccessDeniedException('You do not have permission to edit this order.');
                }
            }

            // Handle stock changes
            $productsChanged = !$this->productsEqual($originalProducts, $order->getProducts());
            $quantityChanged = $originalQuantity !== $order->getQuantity();
            $statusChanged = $originalStatus !== $order->getStatus();

            // If products or quantity changed, restore old stock and reduce new stock
            if ($productsChanged || $quantityChanged) {
                // Restore stock for original products (if order wasn't cancelled)
                if ($originalStatus !== 'Cancelled') {
                    $originalProductsCollection = new \Doctrine\Common\Collections\ArrayCollection($originalProducts);
                    $this->restoreStockForOrder($originalProductsCollection, $originalQuantity, $stockRepository, $entityManager);
                }
                // Reduce stock for new products (if order is not cancelled)
                if ($order->getStatus() !== 'Cancelled') {
                    $this->reduceStockForOrder($order, $stockRepository, $entityManager);
                }
            }

            // If only status changed
            if (!$productsChanged && !$quantityChanged && $statusChanged) {
                if ($originalStatus !== 'Cancelled' && $order->getStatus() === 'Cancelled') {
                    // Order was cancelled - restore stock
                    $this->restoreStockForOrder($order->getProducts(), $order->getQuantity(), $stockRepository, $entityManager);
                } elseif ($originalStatus === 'Cancelled' && $order->getStatus() !== 'Cancelled') {
                    // Order was uncancelled - reduce stock
                    $this->reduceStockForOrder($order, $stockRepository, $entityManager);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Order updated successfully.');

            return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('orders/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_delete', methods: ['POST'])]
    public function delete(Request $request, Orders $order, EntityManagerInterface $entityManager, StockRepository $stockRepository): Response
    {
        // permission check: admin can delete anything, staff can delete items created by admin or themselves
        $user = $this->getUser();
        if ($user instanceof User) {
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $isStaff = $this->isGranted('ROLE_STAFF');
            $isOwner = $order->getCreatedBy()?->getId() === $user->getId();
            $createdByAdmin = in_array('ROLE_ADMIN', $order->getCreatedBy()?->getRoles() ?? []);
            
            if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                throw $this->createAccessDeniedException('You do not have permission to delete this order.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            // Restore stock before deleting order (only if order wasn't cancelled)
            if ($order->getStatus() !== 'Cancelled') {
                $this->restoreStockForOrder($order->getProducts(), $order->getQuantity(), $stockRepository, $entityManager);
            }

            $entityManager->remove($order);
            $entityManager->flush();
            $this->addFlash('success', 'Order deleted successfully.');
        }

        return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Reduce stock for all products in an order
     */
    private function reduceStockForOrder(Orders $order, StockRepository $stockRepository, EntityManagerInterface $entityManager): void
    {
        $orderQuantity = $order->getQuantity();

        foreach ($order->getProducts() as $product) {
            $stock = $stockRepository->findOneByProduct($product);

            if ($stock) {
                $currentQuantity = $stock->getQuantity();
                $newQuantity = max(0, $currentQuantity - $orderQuantity);
                $stock->setQuantity($newQuantity);
                
                // Update stock status if quantity reaches 0
                if ($newQuantity === 0) {
                    $stock->setStatus('Out of Stock');
                } else {
                    $stock->setStatus('In Stock');
                }

                $stock->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())));
                $entityManager->persist($stock);
            }
        }
    }

    /**
     * Restore stock for products (used when order is cancelled or deleted)
     */
    private function restoreStockForOrder(\Doctrine\Common\Collections\Collection|array $products, int $quantity, StockRepository $stockRepository, EntityManagerInterface $entityManager): void
    {
        foreach ($products as $product) {
            $stock = $stockRepository->findOneByProduct($product);

            if ($stock) {
                $currentQuantity = $stock->getQuantity();
                $newQuantity = $currentQuantity + $quantity;
                $stock->setQuantity($newQuantity);
                $stock->setStatus('In Stock');
                $stock->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())));
                $entityManager->persist($stock);
            }
        }
    }

    /**
     * Check if two product collections are equal
     */
    private function productsEqual(array|\Doctrine\Common\Collections\Collection $products1, \Doctrine\Common\Collections\Collection $products2): bool
    {
        $count1 = is_array($products1) ? count($products1) : $products1->count();
        $count2 = $products2->count();

        if ($count1 !== $count2) {
            return false;
        }

        $ids1 = [];
        foreach ($products1 as $product) {
            $ids1[] = $product->getId();
        }

        $ids2 = [];
        foreach ($products2 as $product) {
            $ids2[] = $product->getId();
        }

        sort($ids1);
        sort($ids2);

        return $ids1 === $ids2;
    }
}
