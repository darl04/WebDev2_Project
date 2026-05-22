<?php

namespace App\Controller;

use App\Repository\CustomerRepository;
use App\Repository\OrdersRepository;
use App\Repository\ProductsRepository;
use App\Repository\RentalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ProductsRepository $productsRepository,
        CustomerRepository $customerRepository,
        OrdersRepository $ordersRepository,
        RentalRepository $rentalRepository
    ): Response
    {
        // Get counts
        $totalProducts = count($productsRepository->findAll());
        $totalCustomers = count($customerRepository->findAll());
        $totalOrders = count($ordersRepository->findAll());
        
        // Calculate revenue from orders (excluding Pending status)
        $orders = $ordersRepository->findAll();
        $orderRevenue = 0;
        foreach ($orders as $order) {
            // Only count revenue from orders that are not Pending
            if ($order->getStatus() !== 'Pending') {
                foreach ($order->getProducts() as $product) {
                    $orderRevenue += $product->getPrice() * $order->getQuantity();
                }
            }
        }
        
        // Calculate revenue from rentals (only count active/completed rentals, not cancelled)
        $rentals = $rentalRepository->findAll();
        $rentalRevenue = 0;
        foreach ($rentals as $rental) {
            // Only count revenue from rentals that are Rented or Returned (not Cancelled)
            if ($rental->getStatus() !== 'Cancelled' && $rental->getRentalPrice() !== null) {
                $rentalRevenue += (float) $rental->getRentalPrice();
            }
        }
        
        // Total revenue = orders + rentals
        $totalRevenue = $orderRevenue + $rentalRevenue;
        
        return $this->render('dashboard/index.html.twig', [
            'totalProducts' => $totalProducts,
            'totalCustomers' => $totalCustomers,
            'totalOrders' => $totalOrders,
            'revenue' => $totalRevenue,
            'orderRevenue' => $orderRevenue,
            'rentalRevenue' => $rentalRevenue,
        ]);
    }
}
