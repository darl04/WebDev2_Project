<?php

namespace App\Controller;

use App\Entity\Rental;
use App\Form\RentalType;
use App\Repository\RentalRepository;
use App\Entity\ActivityLog;
use App\Repository\ActivityLogRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rental')]
final class RentalController extends AbstractController
{
    #[Route(name: 'app_rental_index', methods: ['GET'])]
    public function index(RentalRepository $rentalRepository, ActivityLogRepository $activityLogRepository): Response
    {
        return $this->render('rental/index.html.twig', [
            'rentals' => $rentalRepository->findAll(),
            'rentalHistory' => $activityLogRepository->findEntityHistory('Rental'),
        ]);
    }

    #[Route('/new', name: 'app_rental_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rental = new Rental();
        $form = $this->createForm(RentalType::class, $rental);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // set owner
            $rental->setCreatedBy($this->getUser());
            
            // set status to Rented if not set
            if (!$rental->getStatus()) {
                $rental->setStatus('Rented');
            }

            // automatically set the creation time
            if (!$rental->getCreatedAt()) {
                $rental->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'UTC')));
            }

            $entityManager->persist($rental);
            $entityManager->flush();

            // record creation in activity log
            $user = $this->getUser();
            $history = new ActivityLog();
            $history->setUserId(is_object($user) && method_exists($user, 'getId') ? $user->getId() : null);
            $history->setUsername(is_object($user) && method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null);
            $roles = is_object($user) && method_exists($user, 'getRoles') ? $user->getRoles() : [];
            $history->setRole(is_array($roles) && count($roles) ? $roles[0] : null);
            $history->setAction('CREATE');
            $history->setTarget(sprintf('Rental (id: %s) - %s', $rental->getId(), method_exists($rental, 'getId') ? $rental->getId() : '')); 

            $entityManager->persist($history);
            $entityManager->flush();

            $this->addFlash('success', 'Rental created successfully.');

            return $this->redirectToRoute('app_rental_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rental/new.html.twig', [
            'rental' => $rental,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rental_show', methods: ['GET'])]
    public function show(Rental $rental): Response
    {
        return $this->render('rental/show.html.twig', [
            'rental' => $rental,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_rental_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rental $rental, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RentalType::class, $rental);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // permission check: admin can edit anything, staff can edit items created by admin or themselves
            $user = $this->getUser();
            if ($user instanceof User) {
                $isAdmin = $this->isGranted('ROLE_ADMIN');
                $isStaff = $this->isGranted('ROLE_STAFF');
                $isOwner = $rental->getCreatedBy()?->getId() === $user->getId();
                $createdByAdmin = in_array('ROLE_ADMIN', $rental->getCreatedBy()?->getRoles() ?? []);
                
                if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                    throw $this->createAccessDeniedException('You do not have permission to edit this rental.');
                }
            }

            $entityManager->flush();

            // record update in activity log
            $user = $this->getUser();
            $history = new ActivityLog();
            $history->setUserId(is_object($user) && method_exists($user, 'getId') ? $user->getId() : null);
            $history->setUsername(is_object($user) && method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null);
            $roles = is_object($user) && method_exists($user, 'getRoles') ? $user->getRoles() : [];
            $history->setRole(is_array($roles) && count($roles) ? $roles[0] : null);
            $history->setAction('UPDATE');
            $history->setTarget(sprintf('Rental (id: %s) - %s', $rental->getId(), method_exists($rental, 'getId') ? $rental->getId() : ''));

            $entityManager->persist($history);
            $entityManager->flush();

            $this->addFlash('success', 'Rental updated successfully.');

            return $this->redirectToRoute('app_rental_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rental/edit.html.twig', [
            'rental' => $rental,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rental_delete', methods: ['POST'])]
    public function delete(Request $request, Rental $rental, EntityManagerInterface $entityManager): Response
    {
        // permission check: admin can delete anything, staff can delete items created by admin or themselves
        $user = $this->getUser();
        if ($user instanceof User) {
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $isStaff = $this->isGranted('ROLE_STAFF');
            $isOwner = $rental->getCreatedBy()?->getId() === $user->getId();
            $createdByAdmin = in_array('ROLE_ADMIN', $rental->getCreatedBy()?->getRoles() ?? []);
            
            if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                throw $this->createAccessDeniedException('You do not have permission to delete this rental.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$rental->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rental);
            $entityManager->flush();
            $this->addFlash('success', 'Rental deleted successfully.');
        }

        return $this->redirectToRoute('app_rental_index', [], Response::HTTP_SEE_OTHER);
    }
}

