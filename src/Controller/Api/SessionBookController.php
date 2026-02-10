<?php

namespace App\Controller\Api;

use App\Entity\SessionBook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/session-books')]
#[IsGranted('ROLE_USER')] // All the mandatory routes to being connected
class SessionBookController extends AbstractController
{
    // GET /api/session-books - my session-books
    #[Route('', name: 'api_session_book_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        // Retrieve all session-books of the user
        $sessionBooks = $em->getRepository(SessionBook::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $now = new \DateTimeImmutable();

        $data = array_map(function(SessionBook $sessionBook) use ($now) {
            // Calculate if the session-book is active (not expired and there are sessions remaining)
            $isExpired = $sessionBook->getExpiresAt() && $sessionBook->getExpiresAt() < $now;
            $isActive = $sessionBook->getRemainingSessions() > 0 && !$isExpired;

            return [
                'id' => $sessionBook->getId(),
                'name' => $sessionBook->getName(),
                'totalSessions' => $sessionBook->getTotalSessions(),
                'remainingSessions' => $sessionBook->getRemainingSessions(),
                'price' => $sessionBook->getPrice(),
                'createdAt' => $sessionBook->getCreatedAt()->format('Y-m-d H:i:s'),
                'expiresAt' => $sessionBook->getExpiresAt() ? $sessionBook->getExpiresAt()->format('Y-m-d H:i:s') : null,
                'isActive' => $isActive,
            ];
        }, $sessionBooks);

        return $this->json($data);
    }

    // GET /api/session-books/{id} - session-book details
    #[Route('/{id}', name: 'api_session_book_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $sessionBook = $em->getRepository(SessionBook::class)->find($id);

        if (!$sessionBook) {
            return $this->json(['error' => 'Carnet de réservation non trouvé'], 404);
        }

        // SECURITY: Verify that the session-book belongs to the logged-in user
        if ($sessionBook->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        $now = new \DateTimeImmutable();
        $isExpired = $sessionBook->getExpiresAt() && $sessionBook->getExpiresAt() < $now;
        $isActive = $sessionBook->getRemainingSessions() > 0 && !$isExpired;

        return $this->json([
            'id' => $sessionBook->getId(),
            'name' => $sessionBook->getName(),
            'totalSessions' => $sessionBook->getTotalSessions(),
            'remainingSessions' => $sessionBook->getRemainingSessions(),
            'price' => $sessionBook->getPrice(),
            'createdAt' => $sessionBook->getCreatedAt()->format('Y-m-d H:i:s'),
            'expiresAt' => $sessionBook->getExpiresAt() ? $sessionBook->getExpiresAt()->format('Y-m-d H:i:s') : null,
            'isActive' => $isActive,
        ]);
    }
}
