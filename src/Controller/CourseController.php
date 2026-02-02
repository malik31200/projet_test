<?php

namespace App\Controller;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/courses')]
#[IsGranted('ROLE_ADMIN')]
class CourseController extends AbstractController
{
    // GET /api/admin/courses - List all the courses
    #[Route('', name: 'api_courses_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $courses = $em ->getRepository(Course::class)->findAll();

        $data = array_map(function(Course $course) {
            return [
                'id' => $course->getId(),
                'name' => $course->getName(),
                'description' => $course->getDescription(),
                'duration' => $course->getDuration(),
                'maxParticipants' => $course->getMaxParticipants(),
                'price' => $course->getPrice(),
                'isActive' => $course->isActive(),
            ];
        }, $courses);

        return $this->json($data);
    }

    // POST /api/admin/courses - Create a course
    #[Route('', name: 'api_courses_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getcontent(), true);

        // Validation basic
        if (!isset($data['name']) || !isset($data['duration']) || !isset($data['maxParticipants']) || !isset($data['price'])) {
            return $this->json(['erreur' => 'Champs obligatoires manquants'], Response:: HTTP_BAD_REQUEST);
        }

        // Create a course
        $course = new Course();
        $course->setName($data['name']);
        $course->setDescription($data['description']);
        $course->setDuration($data['duration']);
        $course->setMaxParticipants($data['maxParticipants']);
        $course->setPrice($data['price']);
        $course->setIsActive($data['isActive']);
        $course->setCreatedAt(new \DateTimeImmutable());

        $em->persist($course);
        $em->flush();

        return $this->json([
            'message' => 'Cours crée avec succès',
            'course' => [
                'id' => $course->getId(),
                'name' => $course->getName(),
            ]
        ], Response::HTTP_CREATED);
    }

    //GET /api/admin/course/{id} - Show a course
    #[Route('/{id}', name: 'api_courses_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): JsonResponse
    {
        $course = $em ->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Cours non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' =>$course->getId(),
            'name' => $course->getName(),
            'description' => $course->getDescription(),
            'duration' => $course->getDuration(),
            'maxParticipants' => $course->getmaxParticipants(),
            'price' => $course->getPrice(),
            'isActive' => $course->isActive(),
            'createdAt' => $course->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    // PUT /api/admin/courses/{id} - Update a course
    #[Route('/{id}', name: 'api_courses_update', methods: ['PUT'])]
    public function update(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $course = $em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Cours non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) $course->setName($data['name']);
        if (isset($data['description'])) $course->setDescription($data['description']);
        if (isset($data['duration'])) $course->setDuration($data['duration']);
        if (isset($data['maxParticipants'])) $course->setMaxParticipants($data['maxParticipants']);
        if (isset($data['price'])) $course->setPrice($data['price']);
        if (isset($data['isActive'])) $course->setIsActive($data['isActive']);

        $em->flush();

        return $this->json(['message' => 'Cours modifié avec succès']);
    }

    // DELETE /api/admin/courses/{id} - Delete a course
    #[Route('/{id}', name: 'api_courses_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $course = $em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Cours non trouvé'],Response::HTTP_NOT_FOUND);
        }

        $em->remove($course);
        $em->flush();

        return $this->json(['message' => 'Cours supprimé avec succès']);
    }
}