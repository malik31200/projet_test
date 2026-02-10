<?php

namespace App\Controller\Web;

use App\Entity\Course;
use App\Entity\Session;
use App\Entity\Registration;
use App\Entity\SessionBook;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WebController extends AbstractController
{
    // Home Page
    #[Route('/', name: 'app_home')]
    public function home(EntityManagerInterface $em): Response
    {
        $courses = $em->getRepository(Course::class)->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->render('web/home.html.twig', [
            'courses' => $courses,
        ]);
    }

    // List of courses
    #[Route('/courses', name: 'app_courses')]
    public function courses(EntityManagerInterface $em): Response
    {
        $courses = $em->getRepository(Course::class)->findBy(['isActive' => true]);

        return $this->render('web/courses.html.twig', [
            'courses' => $courses,
        ]);
    }

    //list of sessions
    #[Route('/sessions', name: 'app_sessions')]
    public function sessions(EntityManagerInterface $em): Response
    {
        $sessions = $em->getRepository(Session::class)
            ->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', 'scheduled')
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('web/sessions.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    // Book a session (Web version) - Redirect to payment
    #[Route('/session/{id}/book', name: 'app_session_book', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function bookSession(int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $session = $em->getRepository(Session::class)->find($id);

        if (!$session) {
            $this->addFlash('error', 'Session non trouvée');
            return $this->redirectToRoute('app_sessions');
        }

        // Business checks
        if ($session->getStatus() !== 'scheduled') {
            $this->addFlash('error', 'Cette session n\'est pas disponible à la réservation');
            return $this->redirectToRoute('app_sessions');
        }

        if ($session->getAvailableSpots() <= 0) {
            $this->addFlash('error', 'Plus de places disponibles');
            return $this->redirectToRoute('app_sessions');
        }

        // Check if already registered (only confirmed registrations)
        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'user' => $user,
            'session' => $session,
            'status' => 'confirmed'
        ]);

        if ($existingRegistration) {
            $this->addFlash('error', 'Vous êtes déjà inscrit à cette session');
            return $this->redirectToRoute('app_dashboard');
        }

        // Redirect to payment page
        return $this->redirectToRoute('app_payment_session', ['id' => $id]);
    }

    // Cancel a registration
    #[Route('/registration/{id}/cancel', name: 'app_registration_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancelRegistration(int $id, EntityManagerInterface $em, \App\Services\StripeService $stripeService = null): Response
    {
        $user = $this->getUser();
        $registration = $em->getRepository(Registration::class)->find($id);

        if (!$registration) {
            $this->addFlash('error', 'Réservation non trouvée');
            return $this->redirectToRoute('app_dashboard');
        }

        // Vérifier que c'est bien la réservation de l'utilisateur
        if ($registration->getUser() !== $user) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à annuler cette réservation');
            return $this->redirectToRoute('app_dashboard');
        }

        // Vérifier que la réservation n'est pas déjà annulée
        if ($registration->getStatus() === 'cancelled') {
            $this->addFlash('error', 'Cette réservation est déjà annulée');
            return $this->redirectToRoute('app_dashboard');
        }

        // Vérifier qu'on peut annuler (au moins 24h avant la session)
        $now = new \DateTimeImmutable();
        $sessionStart = $registration->getSession()->getStartTime();
        $hoursDiff = ($sessionStart->getTimestamp() - $now->getTimestamp()) / 3600;

        if ($hoursDiff < 24) {
            $this->addFlash('error', 'Vous ne pouvez plus annuler cette session (délai minimum : 24h avant le début)');
            return $this->redirectToRoute('app_dashboard');
        }

        // Annuler la réservation
        $registration->setStatus('cancelled');
        $registration->setCancelledAt(new \DateTimeImmutable());

        // Remettre la place disponible
        $session = $registration->getSession();
        $session->setAvailableSpots($session->getAvailableSpots() + 1);

        // Trouver le paiement associé et traiter le remboursement
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'registration' => $registration
        ]);

        $refundProcessed = false;
        if ($payment) {
            $paymentIntentId = $payment->getStripePaymentId();
            
            // Vérifier si c'est un vrai PaymentIntent ID (commence par "pi_")
            if ($paymentIntentId && strpos($paymentIntentId, 'pi_') === 0) {
                if ($stripeService) {
                    try {
                        // Créer le remboursement directement avec le PaymentIntent ID
                        $refund = $stripeService->createRefund($paymentIntentId);
                        
                        $refundProcessed = true;
                        $this->addFlash('success', 'Remboursement de ' . $payment->getAmount() . '€ effectué avec succès. Vous serez remboursé sous 5-10 jours ouvrés.');
                    } catch (\Stripe\Exception\InvalidRequestException $e) {
                        $this->addFlash('error', 'Erreur Stripe: ' . $e->getMessage());
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur remboursement: ' . $e->getMessage());
                    }
                } else {
                    $this->addFlash('warning', 'Service Stripe non disponible.');
                }
            } else {
                $this->addFlash('info', 'ID de paiement invalide (' . $paymentIntentId . '). Remboursement manuel requis.');
            }
        }

        $em->flush();

        if (!$refundProcessed && $payment) {
            $this->addFlash('info', 'Votre réservation a été annulée. Un remboursement manuel sera traité.');
        } elseif (!$refundProcessed) {
            $this->addFlash('success', 'Votre réservation a été annulée avec succès. Une place a été libérée.');
        }
        
        return $this->redirectToRoute('app_dashboard');
    }

    // User Dashboard
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $registrations = $em->getRepository(Registration::class)->findBy(
            ['user' => $user],
            ['registeredAt' => 'DESC'],
        );
        
        $payments = $em->getRepository(Payment::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            10
        );

        return $this->render('web/dashboard.html.twig', [
            'registrations' => $registrations,
            'payments' => $payments,
        ]);
    }

    // Admin Page
    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_ADMIN')] //temporairement commenté pour tester
    public function admin(EntityManagerInterface $em): Response
    {
        $coursesCount = $em->getRepository(Course::class)->count([]);
        $sessionsCount = $em->getRepository(Session::class)->count([]);
        $usersCount = $em->getRepository(Registration::class)
            ->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.user)')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('web/admin.html.twig', [
            'coursesCount' => $coursesCount,
            'sessionsCount' => $sessionsCount,
            'usersCount' => $usersCount,
        ]);
    }
}
