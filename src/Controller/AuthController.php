<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Retrieve sent JSON data
        $data = json_decode($request->getContent(), true);

        // Basic validation
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['firstName']) || !isset($data['lastName'])) {
            return $this->json(['error' => 'Champs obligatoires manquants'], Response::HTTP_BAD_REQUEST);
        }

        // Check if user already exists
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['error' => 'Lutilisateur existe déjà'], Response::HTTP_CONFLICT);
        }

        // Create a new user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPhone($data['phone'] ?? null);
        $user->setCreatedAt(new \DateTimeImmutable());

        // Define the role (USER by default)
        $user->setRoles(['ROLE_USER']);

        // Hash password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Save to data base
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()

            ]
        ], Response::HTTP_CREATED);
    }
}
