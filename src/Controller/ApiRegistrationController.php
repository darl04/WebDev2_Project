<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class ApiRegistrationController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        EmailVerificationService $emailVerificationService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Username, email, and password are required.',
            ], 400);
        }

        $username = trim((string) $data['username']);
        $email = trim((string) $data['email']);
        $plainPassword = (string) $data['password'];

        if (mb_strlen($username) < 3) {
            return $this->json([
                'success' => false,
                'message' => 'Username must be at least 3 characters.',
            ], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'A valid email address is required.',
            ], 400);
        }

        if (mb_strlen($plainPassword) < 8) {
            return $this->json([
                'success' => false,
                'message' => 'Password must be at least 8 characters.',
            ], 400);
        }

        $repo = $entityManager->getRepository(User::class);

        if ($repo->findOneBy(['username' => $username])) {
            return $this->json([
                'success' => false,
                'message' => 'Username is already taken.',
            ], 409);
        }

        if ($repo->findOneBy(['email' => $email])) {
            return $this->json([
                'success' => false,
                'message' => 'Email is already registered.',
            ], 409);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $user->setRoles(['ROLE_USER']);

        $verificationToken = $emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);
        $user->setIsVerified(false);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $messages,
            ], 400);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        try {
            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        } catch (\Throwable) {
            // Registration should still succeed even if email sending fails.
        }

        return $this->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles' => $user->getRoles(),
            ],
        ], 201);
    }
}
