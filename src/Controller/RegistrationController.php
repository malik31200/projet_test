<?php

namespace App\Controller;

use App\Entity\Registration;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/registrations')]
#[IsGranted('ROLE_USER')] // All roads need to be connected
class RegistrationController extends AbstractController
{
    // POST /api/registrations - Book a session
    #[Route('', name: 'api_registration_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // 1. Retrieve the logged-in user from the JWT token
        $user = $this->getUser();
        
        // 2. Decode the JSON request
        $data = json_decode($request->getContent(), true);

        if (!isset($data['sessionId'])) {
            return $this->json(['error' => 'sessionId requis'], 400);
        }

        // 3. Retrieve the session
        $session = $em->getRepository(Session::class)->find($data['sessionId']);

        if (!$session) {
            return $this->json(['error' => 'Session non trouvée'], 404);
        }

        // Business checks
        if ($session->getStatus() !== 'scheduled') {
            return $this->json(['error' => 'Cette session n\'est pas disponible à la réservation'], 400);
        }

        if ($session->getAvailableSpots() <= 0) {
            return $this->json(['error' => 'Plus de places disponibles'], 400);
        }

        // 5. Check that the user is not already registered (no duplicates)
        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'user' => $user,
            'session' => $session
        ]);

        if ($existingRegistration) {
            return $this->json(['error' => 'Vous êtes déjà inscrit à cette session'], 400);
        }

        // 6. Create the session
        $registration = new Registration();
        $registration->setUser($user);
        $registration->setSession($session);
        $registration->setRegisteredAt(new \DateTimeImmutable());
        $registration->setStatus('confirmed');

        // 7. Decrement the number of available places
        $session->setAvailableSpots($session->getAvailableSpots() - 1);

        // 8. Persist
        $em->persist($registration);
        $em->flush();
        
        return $this->json([
            'id' => $registration->getId(),
            'session' => [
                'id' => $session->getId(),
                'course' => $session->getCourse()->getName(),
                'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
            ],
            'status' => $registration->getStatus(),
            'registeredAt' => $registration->getRegisteredAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    // GET /api/registrations - List my registrations
    #[Route('', name: 'api_registration_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        // Retrieve only the registration of the logged-in user
        $user = $this->getUser();

        $registrations = $em->getRepository(Registration::class)->findBy(
            ['user' => $user],
            ['registeredAt' => 'DESC'] // Sort by date (descending)
        );

        $data = array_map(function(Registration $registration) {
            return [
                'id' => $registration->getId(),
                'session' => [
                    'id' => $registration->getSession()->getId(),
                    'course' => [
                        'id' => $registration->getSession()->getCourse()->getId(),
                        'name' => $registration->getSession()->getCourse()->getName(),
                    ],
                    'startTime' => $registration->getSession()->getStartTime()->format('Y-m-d H:i:s'),
                    'endTime' => $registration->getSession()->getEndTime()->format('Y-m-d H:i:s'),
                ],
                'status' => $registration->getStatus(),
                'registeredAt' => $registration->getRegisteredAt()->format('Y-m-d H:i:s'),
            ];
        }, $registrations);

        return $this->json($data);
    }

    // DELETE /api/registrations/{id} - Cancel a registration
    #[Route('/{id}', name: 'api_registration_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $registration = $em ->getRepository(Registration::class)->find($id);

        if (!$registration) {
            return $this->json(['error' => 'Réservation non trouvée'], 404);
        }

        // SECURITY: Check that the reservation belongs to the connected user
        if ($registration->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        // Free up space in the session
        $session = $registration->getSession();
        $session->setAvailableSpots($session->getAvailableSpots() + 1);

        // Delete the registration
        $em->remove($registration);
        $em->flush();

        return $this->json(['message' => 'Réservation annulée avec succès']);
    }
}
