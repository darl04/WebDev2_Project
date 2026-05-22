<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser as GoogleResourceOwner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        /** @var GoogleResourceOwner $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $email = $googleUser->getEmail();
        $identifier = $email ?: (string) $googleUser->getId();

        return new SelfValidatingPassport(new UserBadge($identifier, function (string $userIdentifier) use ($email, $googleUser): User {
            if ($email) {
                $existingUser = $this->userRepository->findOneBy(['email' => $email]);

                if ($existingUser instanceof User) {
                    $roles = $existingUser->getRoles();
                    if (!in_array('ROLE_STAFF', $roles, true)) {
                        $existingUser->setRoles(array_values(array_unique(array_merge($roles, ['ROLE_STAFF']))));
                        $this->entityManager->flush();
                    }

                    return $existingUser;
                }
            }

            $user = new User();
            $user->setUsername($email ?: $userIdentifier);
            $user->setEmail($email ?: $userIdentifier);
            $user->setRoles(['ROLE_STAFF']);
            $user->setIsVerified(true);
            $user->setIsActive(true);

            $randomPassword = bin2hex(random_bytes(16));
            $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true) || in_array('ROLE_STAFF', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_products_index'));
    }

    public function onAuthenticationFailure(Request $request, \Throwable $exception): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}