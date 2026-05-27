<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Entity\StockAdjustment;
use App\Entity\User;
use App\Form\StockType;
use App\Repository\StockAdjustmentRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stock')]
final class StockController extends AbstractController
{
    #[Route(name: 'app_stock_index', methods: ['GET'])]
    public function index(StockRepository $stockRepository, StockAdjustmentRepository $stockAdjustmentRepository): Response
    {
        return $this->render('stock/index.html.twig', [
            'stocks' => $stockRepository->findAll(),
            'stockAdjustments' => $stockAdjustmentRepository->findRecentAdjustments(25),
        ]);
    }

    #[Route('/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('You do not have permission to create stock.');
        }
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Defensive: ensure a product was selected (DB requires product non-null)
            if (!$stock->getProduct()) {
                $this->addFlash('error', 'Please select a product for this stock entry.');
                return $this->render('stock/new.html.twig', [
                    'stock' => $stock,
                    'form' => $form,
                ]);
            }
            // set owner
            $stock->setCreatedBy($this->getUser());
            
            // set status to In Stock if not set
            if (!$stock->getStatus()) {
                $stock->setStatus('In Stock');
            }

            // automatically set the creation time
            if (!$stock->getCreatedAt()) {
                $stock->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'UTC')));
            }

            $entityManager->persist($stock);

            if (($stock->getQuantity() ?? 0) > 0) {
                $entityManager->persist($this->createStockAdjustment($stock, $this->getUser() instanceof User ? $this->getUser() : null, $stock->getQuantity() ?? 0));
            }

            $entityManager->flush();

            $this->addFlash('success', 'Stock created successfully.');

            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager, StockAdjustmentRepository $stockAdjustmentRepository): Response
    {
        $originalQuantity = $stock->getQuantity() ?? 0;
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // permission check: admin can edit anything, staff can edit items created by admin or themselves
            $user = $this->getUser();
            if ($user instanceof User) {
                $isAdmin = $this->isGranted('ROLE_ADMIN');
                $isStaff = $this->isGranted('ROLE_STAFF');
                $isOwner = $stock->getCreatedBy()?->getId() === $user->getId();
                $createdByAdmin = in_array('ROLE_ADMIN', $stock->getCreatedBy()?->getRoles() ?? []);
                
                if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                    throw $this->createAccessDeniedException('You do not have permission to edit this stock.');
                }
            }

            // Defensive: ensure a product is present when editing
            if (!$stock->getProduct()) {
                $this->addFlash('error', 'Stock must be associated with a product.');
                return $this->render('stock/edit.html.twig', [
                    'stock' => $stock,
                    'form' => $form,
                    'stockAdjustments' => $stockAdjustmentRepository->findBy(
                        ['stock' => $stock],
                        ['createdAt' => 'DESC', 'id' => 'DESC']
                    ),
                ]);
            }

            $newQuantity = $stock->getQuantity() ?? 0;
            $quantityAdded = $newQuantity - $originalQuantity;

            if ($quantityAdded > 0) {
                $entityManager->persist($this->createStockAdjustment($stock, $this->getUser() instanceof User ? $this->getUser() : null, $quantityAdded));
            }

            // Update the updatedAt timestamp
            $stock->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'UTC')));

            $entityManager->flush();
            $this->addFlash('success', 'Stock updated successfully.');

            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
            'stockAdjustments' => $stockAdjustmentRepository->findBy(
                ['stock' => $stock],
                ['createdAt' => 'DESC', 'id' => 'DESC']
            ),
        ]);
    }

    private function createStockAdjustment(Stock $stock, ?User $user, int $quantityAdded): StockAdjustment
    {
        $adjustment = new StockAdjustment();
        $adjustment->setStock($stock);
        $adjustment->setAddedBy($user);
        $adjustment->setQuantityAdded($quantityAdded);
        $adjustment->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'UTC')));

        return $adjustment;
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        // permission check: admin can delete anything, staff can delete items created by admin or themselves
        $user = $this->getUser();
        if ($user instanceof User) {
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $isStaff = $this->isGranted('ROLE_STAFF');
            $isOwner = $stock->getCreatedBy()?->getId() === $user->getId();
            $createdByAdmin = in_array('ROLE_ADMIN', $stock->getCreatedBy()?->getRoles() ?? []);
            
            if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                throw $this->createAccessDeniedException('You do not have permission to delete this stock.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->request->get('_token'))) {
            $entityManager->remove($stock);
            $entityManager->flush();
            $this->addFlash('success', 'Stock deleted successfully.');
        }

        return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
    }
}

