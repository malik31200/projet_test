<?php

namespace App\Controller\Web;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // ==================== HANDLE THE COURSES ====================
    // list of the courses
    #[Route('/admin/courses', name: 'admin_courses_list')]
    public function listCourses(EntityManagerInterface $em): Response
    {
        $courses = $em->getRepository(Course::class)->findAll();

        return $this->render('admin/courses/list.html.twig', [
            'courses' => $courses,
        ]);
    }

    // Create a new course
    #[Route('/admin/courses/new', name: 'admin_courses_new')]
    public function newCourse(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $course = new Course();
            $course->setName($request->request->get('name'));
            $course->setDescription($request->request->get('description'));
            $course->setDuration((int) $request->request->get('duration'));
            $course->setMaxParticipants((int) $request->request->get('max_participants'));
            $course->setPrice($request->request->get('price'));
            $course->setIsActive($request->request->get('is_active') === '1');
            $course->setCreatedAt(new \DateTimeImmutable());

            $em->persist($course);
            $em->flush();

            $this->addFlash('success', 'Cours créé avec succès !');
            return $this->redirectToRoute('admin_courses_list');
        }
        
        return $this->render('admin/courses/form.html.twig', [
                'course' => null,
            ]);
    }

    // Edit a course
    #[Route('/admin/courses/edit/{id}', name: 'admin_courses_edit')]
    public function editCourse(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $course = $em->getRepository(Course::class)->find($id);

        if (!$course) {
            $this->addFlash('error', 'Cours non trouvé');
            return $this->redirectToRoute('admin_courses_list');
        }

        if ($request->isMethod('POST')) {
            $course->setName($request->request->get('name'));
            $course->setDescription($request->request->get('description'));
            $course->setDuration((int) $request->request->get('duration'));
            $course->setMaxParticipants((int) $request->request->get('max_participants'));
            $course->setPrice($request->request->get('price'));
            $course->setIsActive($request->request->get('is_active') === '1');

            $em->flush();

            $this->addFlash('success', 'Cours modifié avec succès !');
            return $this->redirectToRoute('admin_courses_list');
        }

        return $this->render('admin/courses/form.html.twig', [
            'course' => $course,
        ]);
    }

    // Delete a course
    #[Route('/admin/courses/delete/{id}', name: 'admin_courses_delete', methods: ['POST'])]
    public function deleteCourse(int $id, EntityManagerInterface $em): Response
    {
        $course = $em->getRepository(Course::class)->find($id);

        if($course) {
            $em->remove($course);
            $em->flush();
            $this->addFlash('success', 'Cours supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Cours non trouvé');
        }

        return $this->redirectToRoute('admin_courses_list');
    }

    // ==================== HANDLE THE SESSIONS ====================

    // List of the sessions
    #[Route('/admin/sessions', name: 'admin_sessions_list')]
    public function list(EntityManagerInterface $em): Response
    {
        $sessions = $em->getRepository(\App\Entity\Session::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.course', 'c')
            ->addSelect('c')
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/sessions/list.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    // Create a new session
    #[Route('/admin/sessions/new', name: 'admin_sessions_new')]
    public function newSession(Request $request, EntityManagerInterface $em): Response
    {
        $courses = $em->getRepository(Course::class)->findBy(['isActive' => true]);

        if ($request->isMethod('POST')) {
            $courseId = $request->request->get('course_id');
            $course = $em->getRepository(Course::class)->find($courseId);

            if (!$course) {
                $this->addFlash('error', 'Cours non trouvé');
                return $this->redirectToRoute('admin_sessions_new');
            }

            $session = new \App\Entity\Session();
            $session->setCourse($course);
            $session->setStartTime(new \DateTimeImmutable($request->request->get('start_time')));
            $session->setEndTime(new \DateTimeImmutable($request->request->get('end_time')));
            $session->setAvailableSpots((int) $request->request->get('available_spots'));
            $session->setStatus($request->request->get('status'));
            $session->setCreatedAt(new \DateTimeImmutable($request->request->get('created_at')));

            $em->persist($session);
            $em->flush();

            $this->addFlash('success', 'Session créée avec succès !');
            return $this->redirectToRoute('admin_sessions_list');
        }

        return $this->render('admin/sessions/form.html.twig', [
            'session' => null,
            'courses' => $courses,
        ]);
    }

    // Edit a session
    #[Route('/admin/sessions/edit/{id}', name: 'admin_sessions_edit')]
    public function editSession($id, Request $request, EntityManagerInterface $em): Response
    {
        $session = $em->getRepository(\App\Entity\Session::class)->find($id);
        $courses = $em->getRepository(Course::class)->findBy(['isActive' => true]);

        if (!$session) {
            $this->addFlash('error', 'Session non trouvée');
            return $this->redirectToRoute('admin_sessions_list');
        }

        if ($request->isMethod('POST')) {
            $courseId = $request->request->get('course_id');
            $course = $em->getRepository(Course::class)->find($courseId);

            if ($course) {
                $session->setCourse($course);
            }

            $session->setStartTime(new \DateTimeImmutable($request->request->get('start_time')));
            $session->setEndTime(new \DateTimeImmutable($request->request->get('end_time')));
            $session->setAvailableSpots((int) $request->request->get('available_spots'));
            $session->setStatus($request->request->get('status'));

            $em->flush();

            $this->addFlash('success', 'Session modifiée avec succès !');
            return $this->redirectToRoute('admin_sessions_list');
        }

        return $this->render('admin/sessions/form.html.twig', [
            'session' => $session,
            'courses' => $courses,
        ]);
    }

    // Delete a session
    #[Route('/admin/sessions/delete/{id}', name: 'admin_sessions_delete')]
    public function deleteSession($id, EntityManagerInterface $em): Response
    {
        $session = $em->getRepository(\App\Entity\Session::class)->find($id);

        if (!$session) {
            $this->addFlash('error', 'Session non trouvée');
            return $this->redirectToRoute('admin_sessions_list');
        } 
        
        // check if there are registrations
        $registrations = $em->getRepository(\App\Entity\Registration::class)->findBy(['session' => $session]);

        if(count($registrations) > 0) {
            $this->addFlash('error', 'Impossible de supprimer cette session : '. count($registrations) . 'réservation(s) existe(nt). Annulez d\'abord les réservations.');
            return $this->redirectToRoute('admin_sessions_list');
        }

        $em->remove($session);
        $em->flush();
        $this->addFlash('success', 'Session supprimée avec succès !');

        return $this->redirectToRoute('admin_sessions_list');
        
    }

    // ==================== GESTION DES RÉSERVATIONS ====================

    // list of registrations
    #[Route('/admin/registrations', name: 'admin_registrations_list')]
    public function listRegistrations(Request $request, EntityManagerInterface $em): Response
    {
        $status = $request->query->get('status', 'all');

        $qb = $em->getRepository(\App\Entity\Registration::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.session', 's')
            ->leftJoin('s.course', 'c')
            ->addSelect('u', 's', 'c')
            ->orderBy('r.registeredAt', 'ASC');

        if ($status != 'all') {
            $qb->where('r.status = :status')
                ->setParameter('status', $status);
        }

        $registrations = $qb->getQuery()->getResult();

        // statistics
        $totalRegistrations = count($registrations);
        $activeCount = $em->getRepository(\App\Entity\Registration::class)->count(['status' => 'confirmed']);
        $cancelledCount = $em->getRepository(\App\Entity\Registration::class)->count(['status' => 'cancelled']);

        return $this->render('admin/registrations/list.html.twig', [
            'registrations' => $registrations,
            'currentStatus' => $status,
            'totalRegistrations' => $totalRegistrations,
            'activeCount' => $activeCount,
            'cancelledCount' => $cancelledCount,
        ]);
    }

    // Cancelled a registration
    #[Route('/admin/registrations/cancel/{id}', name: 'admin_registrations_cancel', methods: ['POST'])]
    public function cancelRegistration($id, EntityManagerInterface $em): Response
    {
        $registration = $em->getRepository(\App\Entity\Registration::class)->find($id);

        if (!$registration) {
            $this->addFlash('error', 'Réservation non trouvée');
            return $this->redirectToRoute('admin_registrations_list');
        }

        if ($registration->getStatus() === 'cancelled') {
            $this->addFlash('error', 'Cette réservation est déjà annulée');
            return $this->redirectToRoute('admin_registrations_list');
        }

        // Cancelled the registration
        $registration->setStatus('cancelled');
        $registration->setCancelledAt(new \DateTimeImmutable());

        // Free up a space in the session
        $session = $registration->getSession();
        if ($session) {
            $session->setAvailableSpots($session->getAvailableSpots() + 1);
        }
        $em->flush();

        $this->addFlash('success', 'Réservation annulée avec succès ! Une place a été libérée.');
        return $this->redirectToRoute('admin_registrations_list');
    }

    // ==================== HANDLE USERS ====================

    // List of users
    #[Route('/admin/users', name: 'admin_users_list')]
    public function listUsers(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(\App\Entity\User::class)->findAll();

        // Statistics
        $totalUsers = count($users);
        $adminCount = 0;
        $userCount = 0;

        foreach ($users as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                $adminCount++;
            } else {
                $userCount++;
            }
        }

        return $this->render('admin/users/list.html.twig', [
            'users' => $users,
            'totalUsers' => $totalUsers,
            'adminCount' => $adminCount,
            'userCount' => $userCount,
        ]);
    }

    // See a detail of an user
    #[Route('/admin/users/{id}', name: 'admin_users_show')]
    public function showUser(int $id, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(\App\Entity\User::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('admin_users_list');
        }

        // Retrieve history
        $registrations = $em->getRepository(\App\Entity\Registration::class)->findBy(
            ['user' => $user],
            ['registeredAt' => 'DESC']
        );

        $sessionBooks = $em->getRepository(\App\Entity\SessionBook::class)->findBy (
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $payments = $em->getRepository(\App\Entity\Payment::class)->findBy (
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
            'registrations' => $registrations,
            'sessionBooks' => $sessionBooks,
            'payments' => $payments,
        ]);
    }

    // Change a user's role
    #[Route('/admin/users/{id}/toggle-role', name: 'admin_users_toggle_role', methods: ['POST'])]
    public function toggleRole(int $id, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(\App\Entity\User::class)->find($id);

        if(!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('admin_users_list');
        }

        // Do not change your own role
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre rôle');
            return $this->redirectToRoute('admin_users_list');
        }

        // Toggle between ROLE_USER and ROLE_ADMIN
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles)) {
            $user->setRoles(['ROLE_USER']);
            $this->addFlash('success', 'Utilisateur rétrogradé en User');           
        } else {
            $user->setRoles(['ROLE_ADMIN']);
            $this->addFlash('success', 'Utilisateur promu en Admin');
        }

        $em->flush();

        return $this->redirectToRoute('admin_users_list');
    }
}
