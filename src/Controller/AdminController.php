<?php

namespace App\Controller;

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
            $course->setIsActive($request->request->get('is_active') === 1);
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
        $registrations = $em->getrepository(\App\Entity\Registration::class)->findBy(['session' => $session]);

        if(count($registrations) > 0) {
            $this->addFlash('error', 'Impossible de supprimer cette session : '. count($registrations) . 'réservation(s) existe(nt). Annulez d\'abord les réservations.');
            return $this->redirectToRoute('admin_sessions_list');
        }

        $em->remove($session);
        $em->flush();
        $this->addFlash('success', 'Session supprimée avec succès !');

        return $this->redirectToRoute('admin_sessions_list');
        
    }
}
