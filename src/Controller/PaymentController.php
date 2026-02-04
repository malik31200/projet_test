<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Registration;
use App\Entity\Session;
use App\Entity\SessionBook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payments')]
#[IsGranted('ROLE_USER')]
class PaymentController extends AbstractController
{
    // POST /api/payments/session-book - Buy a session-book
    #[Route('/session-book', name: 'api_payment_session_book', methods: ['POST'])]
    public function purchaseSessionBook(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        //Validation
        if (!isset($data['name']) || !isset($data['totalSessions']) || !isset($data['price'])) {
            return $this->json(['error' => 'Le nom, le total de carnet et le prix sont requis'], 400);    
        }

        // 1. Create the payment (simulated for now)
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setAmount($data['price']);
        $payment->setStripePaymentId('SIMULATED_' . uniqid()); // Simulation Stripe
        $payment->setCreatedAt(new \DateTimeImmutable());
        
        // 2. Create the session-book
        $sessionBook = new SessionBook();
        $sessionBook->setUser($user);
        $sessionBook->setName($data['name']);
        $sessionBook->setTotalSessions($data['totalSessions']);
        $sessionBook->setRemainingSessions($data['totalSessions']);
        $sessionBook->setPrice($data['price']);
        $sessionBook->setCreatedAt(new \DateTimeImmutable());

        // Expiration date: 1 year by default
        if (isset($data['expiresAt'])) {
            try {
                $expiresAt = new \DateTimeImmutable($data['expiresAt']);
                $sessionBook->setExpiresAt($expiresAt);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Format de date invalide'], 400);
            }
        } else {
            $sessionBook->setExpiresAt((new \DateTimeImmutable())->modify('+1 year'));
        }

        // 3. Link payment to session-book
        $payment->setSessionBook($sessionBook);

        // 4. persist all
        $em->persist($payment);
        $em->persist($sessionBook);
        $em->flush();

        return $this->json([
            'payment' => [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'sessionBook' => [
                'id' => $sessionBook->getId(),
                'name' => $sessionBook->getName(),
                'totalSessions' => $sessionBook->getTotalSessions(),
                'remainingSessions' => $sessionBook->getRemainingSessions(),
                'expiresAt' => $sessionBook->getExpiresAt()->format('Y-m-d H:i:s'),
            ],
            'message' => 'Forfait acheté avec succès',
        ], 201);
    }

    // POST /api/payments/registration - Pay a session direct
    #[Route('/registration', name: 'api_payment_registration', methods: ['POST'])]
    public function purchaseRegistration(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['sessionId'])) {
            return $this->json(['error' => 'SessionId requis'], 400);
        }

        // 1. Retrieve the session
        $session = $em->getRepository(Session::class)->find($data['sessionId']);

        if (!$session) {
            return $this->json(['error' => 'Session non trouvée'], 404);
        }

        // Check bussiness
        if ($session->getStatus() !== 'scheduled') {
            return $this->json(['error' => 'Cette session n\'est pas disponible'], 400);
        }
        if ($session->getAvailableSpots() <= 0) {
            return $this->json(['error' => 'Plus de places disponibles'], 400);
        }
        
        // Check that the user is not already registered
        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'user' => $user,
            'session' => $session
        ]);

        if ($existingRegistration) {
            return $this->json(['error' => 'Vous êtes déjà inscrit pour ce cours'], 400);
        }

        // Create the payment
        $payment = new Payment();
        $payment->setUser($user);
        $payment->setAmount($session->getCourse()->getPrice()); // Prix du cours
        $payment->setStripePaymentId('SIMULATED_' . uniqid());
        $payment->setCreatedAt(new \DateTimeImmutable());

        // 4. Create the registration
        $registration = new Registration();
        $registration->setUser($user);
        $registration->setSession($session);
        $registration->setRegisteredAt(new \DateTimeImmutable());
        $registration->setStatus('confirmed');

        // 5. Link payment to reservation
        $payment->setRegistration($registration);

        // 6. Decrease the number of available places
        $session->setAvailableSpots($session->getAvailableSpots() - 1);

        // Persist all
        $em->persist($payment);
        $em->persist($registration);
        $em->flush();

        return $this->json([
            'payment' => [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'registration' => [
                'id' => $registration->getId(),
                'session' => [
                    'id' => $session->getId(),
                    'course' => $session->getCourse()->getName(),
                    'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                ],
                'status' => $registration->getStatus(),
            ],
            'message' => 'Séance réservée et payée avec succès',
        ], 201);
    }

    // GET /api/payments - History of my payments
    #[Route('', name: 'api_payment_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $payments = $em->getRepository(Payment::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $data = array_map(function(Payment $payment) {
            $result = [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            ];

            // If it's a session-book payment
            if ($payment->getSessionBook()) {
                $result['type'] = 'session_book';
                $result['sessionBook'] = [
                    'id' => $payment->getSessionBook()->getId(),
                    'name' => $payment->getSessionBook()->getName(),
                ];
            }

            // if it's a unique payment
            if ($payment->getRegistration()) {
                $result['type'] = 'registration';
                $result['registration'] = [
                    'id' => $payment->getRegistration()->getId(),
                    'session' => [
                        'id' => $payment->getRegistration()->getSession()->getId(),
                        'course' => $payment->getRegistration()->getSession()->getCourse()->getName(),
                    ],
                ];
            }
            return $result;
        }, $payments);

        return $this->json($data);
    }
}
