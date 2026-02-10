<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Registration;
use App\Entity\Session;
use App\Services\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class StripePaymentController extends AbstractController
{
    // Page de paiement pour une session
    #[Route('/payment/session/{id}', name: 'app_payment_session')]
    #[IsGranted('ROLE_USER')]
    public function paymentPage(int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $session = $em->getRepository(Session::class)->find($id);

        if (!$session) {
            $this->addFlash('error', 'Session non trouvée');
            return $this->redirectToRoute('app_sessions');
        }

        // Vérifications
        if ($session->getStatus() !== 'scheduled') {
            $this->addFlash('error', 'Cette session n\'est pas disponible');
            return $this->redirectToRoute('app_sessions');
        }

        if ($session->getAvailableSpots() <= 0) {
            $this->addFlash('error', 'Plus de places disponibles');
            return $this->redirectToRoute('app_sessions');
        }

        // Vérifier si déjà inscrit (seulement les réservations confirmées)
        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'user' => $user,
            'session' => $session,
            'status' => 'confirmed'
        ]);

        if ($existingRegistration) {
            $this->addFlash('error', 'Vous êtes déjà inscrit à cette session');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('payment/session.html.twig', [
            'session' => $session,
        ]);
    }

    // Créer une session de paiement Stripe
    #[Route('/payment/session/{id}/checkout', name: 'app_payment_checkout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCheckoutSession(
        int $id,
        EntityManagerInterface $em,
        StripeService $stripeService
    ): Response {
        $user = $this->getUser();
        $session = $em->getRepository(Session::class)->find($id);

        if (!$session) {
            $this->addFlash('error', 'Session non trouvée');
            return $this->redirectToRoute('app_sessions');
        }

        try {
            // Créer une session Stripe Checkout
            $checkoutSession = $stripeService->createCheckoutSession(
                [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $session->getCourse()->getName(),
                            'description' => 'Session du ' . $session->getStartTime()->format('d/m/Y à H:i'),
                        ],
                        'unit_amount' => (int)($session->getCourse()->getPrice() * 100), // En centimes
                    ],
                    'quantity' => 1,
                ]],
                $this->generateUrl('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
                $this->generateUrl('app_payment_cancel', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
                ['course_session_id' => (string)$id]
            );

            // Stocker les IDs dans la session PHP pour validation
            $phpSession = $this->container->get('request_stack')->getSession();
            $phpSession->set('pending_session_id', $id);
            $phpSession->set('stripe_checkout_session_id', $checkoutSession->id);

            return $this->redirect($checkoutSession->url);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création du paiement: ' . $e->getMessage());
            return $this->redirectToRoute('app_payment_session', ['id' => $id]);
        }
    }

    // Page de succès après paiement
    #[Route('/payment/success', name: 'app_payment_success')]
    #[IsGranted('ROLE_USER')]
    public function paymentSuccess(Request $request, EntityManagerInterface $em, StripeService $stripeService): Response
    {
        $user = $this->getUser();
        $phpSession = $this->container->get('request_stack')->getSession();
        
        // Récupérer les données depuis la session PHP
        $sessionId = $phpSession->get('pending_session_id');
        $stripeCheckoutSessionId = $phpSession->get('stripe_checkout_session_id');

        if (!$sessionId || !$stripeCheckoutSessionId) {
            $this->addFlash('error', 'Session expirée ou invalide');
            return $this->redirectToRoute('app_sessions');
        }

        try {
            // Récupérer la session Stripe pour obtenir le PaymentIntent
            $stripeSession = $stripeService->retrieveCheckoutSession($stripeCheckoutSessionId);
            $paymentIntentId = $stripeSession->payment_intent;
            
            if (!$paymentIntentId) {
                $this->addFlash('error', 'PaymentIntent manquant');
                return $this->redirectToRoute('app_sessions');
            }
            
            // Nettoyer la session PHP
            $phpSession->remove('pending_session_id');
            $phpSession->remove('stripe_checkout_session_id');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la vérification du paiement: ' . $e->getMessage());
            return $this->redirectToRoute('app_sessions');
        }

        $session = $em->getRepository(Session::class)->find($sessionId);

        if (!$session) {
            $this->addFlash('error', 'Session non trouvée');
            return $this->redirectToRoute('app_sessions');
        }

        // Vérifier si la réservation existe déjà (seulement les confirmées)
        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'user' => $user,
            'session' => $session,
            'status' => 'confirmed'
        ]);

        if (!$existingRegistration) {
            // Créer le paiement avec le PaymentIntent ID (nécessaire pour les remboursements)
            $payment = new Payment();
            $payment->setUser($user);
            $payment->setAmount($session->getCourse()->getPrice());
            $payment->setStripePaymentId($paymentIntentId);
            $payment->setCreatedAt(new \DateTimeImmutable());

            // Créer la réservation
            $registration = new Registration();
            $registration->setUser($user);
            $registration->setSession($session);
            $registration->setRegisteredAt(new \DateTimeImmutable());
            $registration->setStatus('confirmed');

            // Lier le paiement à la réservation
            $payment->setRegistration($registration);

            // Décrémenter les places
            $session->setAvailableSpots($session->getAvailableSpots() - 1);

            $em->persist($payment);
            $em->persist($registration);
            $em->flush();
        }

        return $this->render('payment/success.html.twig', [
            'session' => $session,
        ]);
    }

    // Page d'annulation de paiement
    #[Route('/payment/cancel/{id}', name: 'app_payment_cancel')]
    #[IsGranted('ROLE_USER')]
    public function paymentCancel(int $id, EntityManagerInterface $em): Response
    {
        $session = $em->getRepository(Session::class)->find($id);

        return $this->render('payment/cancel.html.twig', [
            'session' => $session,
        ]);
    }

    // Webhook Stripe pour confirmer les paiements
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function stripeWebhook(Request $request, EntityManagerInterface $em): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $_ENV['STRIPE_WEBHOOK_SECRET']
            );
            
            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                
                // Mettre à jour le statut du paiement si nécessaire
                // Logique à implémenter selon vos besoins
            }
            
            return new Response('', 200);
        } catch (\Exception $e) {
            return new Response('Webhook error: ' . $e->getMessage(), 400);
        }
    }
}
