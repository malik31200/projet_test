<?php

namespace App\Controller;

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
    // POST /api/session-books - Buy a session-book
    #[Route('', name: 'api_session_book_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        // Validation of required fields
        if (!isset($data['name']) || !isset($data['totalSessions']) || !isset($data['price'])) {
            return $this->json(['error' => 'Le nom, la quantité et le prix doivent être saisies'], 400);   
        }

        // Create a session-book
        $sessionBook = new SessionBook();
        $sessionBook->setUser($user);
        $sessionBook->setName($data['name']);
        $sessionBook->setTotalSessions($data['totalSessions']);
        $sessionBook->setRemainingSessions($data['totalSessions']);
        $sessionBook->setPrice($data['price']);
        $sessionBook->setCreatedAt(new \DateTimeImmutable());

        // Date of expiration optionnelle (par défaut 1 an)
        if (isset($data['expiresAt'])) {
            try {
                $expiresAt = new \DateTimeImmutable($data['expiresAt']);
                $sessionBook->setExpiresAt($expiresAt);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Format de date invalide pour expiresAt'], 400);
            }
        } else {
            // Par défaut : expire dans 1 an 
            $sessionBook->setExpiresAt((new \DateTimeImmutable())->modify('+12 months'));
        }
        
        $em->persist($sessionBook);
        $em->flush();
        
        return $this->json([
            'id' => $sessionBook->getId(),
            'name' => $sessionBook->getName(),
            'totalSessions' => $sessionBook->getTotalSessions(),
            'remainingSessions' => $sessionBook->getRemainingSessions(),
            'price' => $sessionBook->getPrice(),
            'createdAt' => $sessionBook->getCreatedAt()->format('Y-m-d H:i:s'),
            'expiresAt' => $sessionBook->getExpiresAt()->format('Y-m-d H:i:s'),
        ], 201);
    }
    
    // GET /api/session-books - my sesion-books
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
