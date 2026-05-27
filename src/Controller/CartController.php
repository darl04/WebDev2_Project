<?php

namespace App\Controller;

use App\Repository\ProductsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_cart_index')]
    public function index(Request $request, ProductsRepository $productsRepository): Response
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);

        $items = [];
        $total = 0.0;
        $count = 0;

        foreach ($cart as $productId => $entry) {
            $product = $productsRepository->find($productId);
            if (!$product) {
                continue;
            }

            if (strtolower((string) $product->getCollectionType()) === 'mascots') {
                // Mascots are rental-only; skip in sale cart
                continue;
            }

            if (is_array($entry)) {
                $quantity = (int) ($entry['quantity'] ?? $entry['qty'] ?? 1);
                $size = $entry['size'] ?? null;
            } else {
                $quantity = (int) $entry;
                $size = null;
            }

            $subtotal = ((float) $product->getPrice() ?: 0.0) * $quantity;
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'size' => $size,
                'subtotal' => $subtotal,
            ];

            $total += $subtotal;
            $count += $quantity;
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total,
            'count' => $count,
            'shipping' => $count > 0 ? 50.0 : 0.0,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add')]
    public function add(int $id, Request $request, ProductsRepository $productsRepository): Response
    {
        $product = $productsRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        if (strtolower((string) $product->getCollectionType()) === 'mascots') {
            $this->addFlash('error', 'Mascot items are available for rental only.');

            $referer = $request->headers->get('referer');
            if ($referer) {
                return $this->redirect($referer);
            }

            return $this->redirectToRoute('app_products_index');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        $size = $request->request->get('size', $request->query->get('size'));

        if (isset($cart[$id])) {
            if (is_array($cart[$id]) && (($cart[$id]['size'] ?? null) === $size)) {
                $cart[$id]['quantity'] = ($cart[$id]['quantity'] ?? 0) + 1;
            } else {
                $cart[$id] = [
                    'quantity' => 1,
                    'size' => $size,
                ];
            }
        } else {
            if ($size) {
                $cart[$id] = [
                    'quantity' => 1,
                    'size' => $size,
                ];
            } else {
                $cart[$id] = 1;
            }
        }

        $session->set('cart', $cart);

        // compute updated count
        $count = 0;
        foreach ($cart as $pid => $entry) {
            if (is_array($entry)) {
                $count += (int) ($entry['quantity'] ?? $entry['qty'] ?? 1);
            } else {
                $count += (int) $entry;
            }
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(["success" => true, "count" => $count, "message" => sprintf('Added "%s" to cart.', $product->getName())]);
        }

        $this->addFlash('success', sprintf('Added "%s" to cart.', $product->getName()));

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_products_index');
    }

    #[Route('/cart/update/{id}/{action}', name: 'app_cart_update')]
    public function update(int $id, string $action, Request $request, ProductsRepository $productsRepository): Response
    {
        $product = $productsRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        if (!isset($cart[$id])) {
            return $this->redirectToRoute('app_cart_index');
        }

        if ($action === 'plus') {
            if (is_array($cart[$id])) {
                $cart[$id]['quantity'] = ($cart[$id]['quantity'] ?? 0) + 1;
            } else {
                $cart[$id]++;
            }
        }

        if ($action === 'minus') {
            if (is_array($cart[$id])) {
                $cart[$id]['quantity'] = ($cart[$id]['quantity'] ?? 1) - 1;
                if ($cart[$id]['quantity'] <= 0) {
                    unset($cart[$id]);
                }
            } else {
                $cart[$id]--;
                if ($cart[$id] <= 0) {
                    unset($cart[$id]);
                }
            }
        }

        $session->set('cart', $cart);

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/cart/remove/{id}', name: 'app_cart_remove')]
    public function remove(int $id, Request $request): Response
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);

        if (isset($cart[$id])) {
            unset($cart[$id]);
        }

        $session->set('cart', $cart);

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/cart/clear', name: 'app_cart_clear')]
    public function clear(Request $request): Response
    {
        $request->getSession()->set('cart', []);

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/_cart/mini', name: 'app_cart_mini')]
    public function mini(Request $request, ProductsRepository $productsRepository): Response
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);

        $items = [];
        $count = 0;

        foreach ($cart as $productId => $entry) {
            $product = $productsRepository->find($productId);
            if (!$product) {
                continue;
            }

            if (strtolower((string) $product->getCollectionType()) === 'mascots') {
                continue;
            }

            if (is_array($entry)) {
                $quantity = (int) ($entry['quantity'] ?? $entry['qty'] ?? 1);
                $size = $entry['size'] ?? null;
            } else {
                $quantity = (int) $entry;
                $size = null;
            }

            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'size' => $size,
            ];
            $count += $quantity;
        }

        return $this->render('cart/_mini.html.twig', [
            'items' => $items,
            'count' => $count,
        ]);
    }

}
