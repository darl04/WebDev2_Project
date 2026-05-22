<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        // Support both JSON and form/query parameters, and both POST and GET.
        $username = null;
        $password = null;

        if ($request->isMethod('GET')) {
            // e.g. /api/manual-login?username=...&password=...
            $username = $request->query->get('username');
            $password = $request->query->get('password');
        } else {
            // Prefer JSON body when Content-Type is application/json.
            $contentType = $request->headers->get('Content-Type', '');
            if (str_starts_with($contentType, 'application/json')) {
                $data = json_decode($request->getContent(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new JsonResponse(['error' => 'Malformed JSON body'], 400);
                }
            } else {
                // Fallback to form data.
                $data = $request->request->all();
            }

            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;
        }

        if (!$username || !$password) {
            return new JsonResponse(['error' => 'Username and password are required'], 400);
        }

        $user = $userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 401);
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        $token = $jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
            ],
        ]);
    }
}

