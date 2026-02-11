<?php

namespace App\Controller\Web;

use App\Entity\Registration;
use App\Entity\Session;
use App\Entity\SessionBook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookingController extends AbstractController
{
    #[Route('/booking/reserve/{id}', name: 'booking_reserve', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reserve(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Retrieve the session
        $session = $em->getRepository(Session::class)->find($id);

        if (!$session) {
            $this->addFlash('error', 'Session non trouvée');
            return $this->redirectToRoute('app_sessions');
        }

        // Check if the session is available
        if ($session->getAvailableSpots() <= 0) {
            $this->addFlash('error', 'Il n\'y a plus de places disponibles pour cette session.');
            return $this->redirectToRoute('app_sessions');
        }

        // Check if the user has already registered this session
        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'user' => $user,
            'session' => $session,
            'status' => 'confirmed'
        ]);

        if ($existingRegistration) {
            $this->addFlash('error', 'Vous avez déjà réservé cette session.');
            return $this->redirectToRoute('app_sessions');
        }

        // Check if the user has a sessionbook with remaining sessions
        $sessionBook = $em->createQueryBuilder()
            ->select('sb')
            ->from(SessionBook::class, 'sb')
            ->where('sb.user = :user')
            ->andWhere('sb.remainingSessions > 0')
            ->andWhere('sb.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('sb.expiresAt', 'ASC') // Use the sessionbook that expires the first
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Create the registration
        $registration = new Registration();
        $registration->setUser($user);
        $registration->setSession($session);
        $registration->setStatus('confirmed');
        $registration->setRegisteredAt(new \DateTimeImmutable());

        // If a sessionbook is available, use it
        if($sessionBook) {
            $registration->setSessionBook($sessionBook);
            $sessionBook->setRemainingSessions($sessionBook->getRemainingSessions() - 1);
            $em->persist($sessionBook);

            $this->addFlash('success', 'Session réservée avec succès en utilisant votre carnet ! Il vous reste ' . $sessionBook->getRemainingSessions() . ' session(s).');
        } else {
            // registration direct without a sessionbokk
            $this->addFlash('success', 'Session réservée avec succès !');
        }

        // Decrease the number of available places
        $session->setAvailableSpots($session->getAvailableSpots() - 1);

        // save to database
        $em->persist($registration);
        $em->persist($session);
        $em->flush();

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/booking/cancel/{id}', name: 'booking_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Retrieve the registration
        $registration = $em->getRepository(Registration::class)->find($id);
        
        if (!$registration) {
            $this->addFlash('error', 'Réservation non trouvée.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Check that this is indeed the user's registration.
        if ($registration->getUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas annuler cette réservation.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Check if registartion is confirmed
        if ($registration->getStatus() !== 'confirmed') {
            $this->addFlash('error', 'Cette réservation est déjà annulée.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Cancel the registration
        $registration->setStatus('cancelled');
        $registration->setCancelledAt(new \DateTimeImmutable());

        // Free the spot in the session
        $session = $registration->getSession();
        $session->setAvailableSpots($session->getAvailableSpots() +1);

        // if a sessionbook was used, recredit it
        if ($registration->getSessionBook()) {
            $sessionBook = $registration->getSessionBook();
            $sessionBook->setRemainingSessions($sessionBook->getRemainingSessions() +1);
            $em->persist($sessionBook);

            $this->addFlash('success', 'Réservation annulée avec succès. Votre crédit de session a été restauré.');
        } else {
            $this->addFlash('success', 'Réservation annulée avec succès.');
        }

        $em->persist($registration);
        $em->persist($session);
        $em->flush();

        return $this->redirectToRoute('app_dashboard');
    }
}
