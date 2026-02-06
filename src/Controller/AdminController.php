<?php

namespace App\Controller;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/courses')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // list of the courses
    #[Route('', name: 'admin_courses_list')]
    public function listCourses(EntityManagerInterface $em): Response
    {
        $courses = $em->getRepository(Course::class)->findAll();

        return $this->render('admin/courses/list.html.twig', [
            'courses' => $courses,
        ]);
    }

    // Create a new course
    #[Route('/new', name: 'admin_courses_new')]
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
    #[Route('/edit/{id}', name: 'admin_courses_edit')]
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
    #[Route('/delete/{id}', name: 'admin_courses_delete', methods: ['POST'])]
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
}
