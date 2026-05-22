<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/email-verification', name: 'api_email_verification_')]
class ApiEmailVerificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailVerificationService $emailVerificationService,
    ) {
    }

    #[Route('/resend', name: 'resend', methods: ['POST'])]
    public function resendVerification(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        if ($user->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Email already verified.',
            ], 400);
        }

        $verificationToken = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);
        $this->entityManager->flush();

        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);

        return $this->json([
            'success' => true,
            'message' => 'Verification email sent successfully.',
        ], 200);
    }

    #[Route('/verification-status', name: 'status', methods: ['GET'])]
    public function verificationStatus(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        return $this->json([
            'success' => true,
            'isVerified' => $user->isVerified(),
            'email' => $user->getEmail(),
        ], 200);
    }
}
