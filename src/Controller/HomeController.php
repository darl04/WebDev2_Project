<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectAuthenticatedUser();
        }

        return $this->render('home/index.html.twig');
    }

    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('about/index.html.twig');
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('contact/index.html.twig');
    }

    #[Route('/collections', name: 'app_collection', methods: ['GET'])]
    public function collection(): Response
    {
        return $this->render('collection/index.html.twig');
    }

    private function redirectAuthenticatedUser(): Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('app_products_index');
    }
}
