<?php

namespace App\Controller;

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

        $sessionBooks = $em->getRepository(SessionBook::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
        
        $payments = $em->getRepository(Payment::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            5
        );


        return $this->render('web/dashboard.html.twig', [
            'registrations' => $registrations,
            'sessionBooks' => $sessionBooks,
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
