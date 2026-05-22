<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/customer')]
final class CustomerController extends AbstractController
{
    #[Route(name: 'app_customer_index', methods: ['GET'])]
    public function index(CustomerRepository $customerRepository): Response
    {
        return $this->render('customer/index.html.twig', [
            'customers' => $customerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // automatically set the creation time with current timezone
            if (!$customer->getCreatedAt()) {
                $customer->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'UTC')));
            }
            $customer->setCreatedBy($this->getUser());

            $entityManager->persist($customer);
            $entityManager->flush();

            $this->addFlash('success', 'Customer created successfully.');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // permission check: admin can edit anything, staff can edit items created by admin or themselves
            $user = $this->getUser();
            if ($user instanceof User) {
                $isAdmin = $this->isGranted('ROLE_ADMIN');
                $isStaff = $this->isGranted('ROLE_STAFF');
                $isOwner = $customer->getCreatedBy()?->getId() === $user->getId();
                $createdByAdmin = in_array('ROLE_ADMIN', $customer->getCreatedBy()?->getRoles() ?? []);
                
                if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                    throw $this->createAccessDeniedException('You do not have permission to edit this customer.');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Customer updated successfully.');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        // permission check: admin can delete anything, staff can delete items created by admin or themselves
        $user = $this->getUser();
        if ($user instanceof User) {
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $isStaff = $this->isGranted('ROLE_STAFF');
            $isOwner = $customer->getCreatedBy()?->getId() === $user->getId();
            $createdByAdmin = in_array('ROLE_ADMIN', $customer->getCreatedBy()?->getRoles() ?? []);
            
            if (!$isAdmin && !($isStaff && ($isOwner || $createdByAdmin)) && !$isOwner) {
                throw $this->createAccessDeniedException('You do not have permission to delete this customer.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->request->get('_token'))) {
            $entityManager->remove($customer);
            $entityManager->flush();
            $this->addFlash('success', 'Customer deleted successfully.');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
