<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST', 'GET'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        // Support both JSON and form/query parameters, and both POST and GET.
        $username = null;
        $plainPassword = null;
        $agreeTerms = false;

        if ($request->isMethod('GET')) {
            // e.g. /api/register?username=...&plainPassword=...&agreeTerms=1
            $username = $request->query->get('username');
            $plainPassword = $request->query->get('plainPassword');
            $agreeTerms = filter_var($request->query->get('agreeTerms'), FILTER_VALIDATE_BOOLEAN);
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
            $plainPassword = $data['plainPassword'] ?? null;
            $agreeTerms = isset($data['agreeTerms']) ? filter_var($data['agreeTerms'], FILTER_VALIDATE_BOOLEAN) : false;
        }

        if (!$username || !$plainPassword) {
            return $this->json(['error' => 'username and plainPassword are required'], 400);
        }

        if (!$agreeTerms) {
            return $this->json(['error' => 'You must agree to the terms'], 400);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $errors = $validator->validate($user);
        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->json(['errors' => $messages], 400);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        // Create JWT token, similar to handson_b ApiLoginController.
        $token = $jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
            ],
        ], 201);
    }
}

