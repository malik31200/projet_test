<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PublicController extends AbstractController
{
    // GET /api/courses - List of active courses (PUBLIC)
    #[Route('/courses', name: 'api_public_courses', methods: ['GET'])]
    public function courses(EntityManagerInterface $em): JsonResponse
    {
        // We only retrieve active courses
        $courses = $em->getRepository(Course::class)->findBy(['isActive' => true]);

        $data = array_map(function(Course $course) {
            return [
                'id' => $course->getId(),
                'name' => $course->getName(),
                'description' => $course->getDescription(),
                'duration' => $course->getDuration(),
                'maxParticipants' => $course->getMaxParticipants(),
                'price' => $course->getPrice(),
            ];
        }, $courses);

        return $this->json($data);
    }

    // GET /api/sessions - List of available sessions (PUBLIC)
    #[Route('/sessions', name: 'api_public_sessions', methods: ['GET'])]
    public function sessions(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $queryBuilder = $em->getRepository(Session::class)->createQueryBuilder('s');

        // Filter: only "scheduled" sessions
        $queryBuilder->where('s.status = :status')
                    -> setParameter('status', 'scheduled');

        // Optional filter by course
        if ($request->query->has('courseId')) {
            $queryBuilder->andWhere('s.course = :courseId')
                        ->setParameter('courseId', $request->query->get('courseId'));
        }

        // Optional filter by date (sessions from a certain date)
        if ($request->query->has('date')) {
            $date = new \DateTimeImmutable($request->query->get('date'));
            $queryBuilder->andWhere('s.startTime >= :date')
                        ->setParameter('date', $date);
        }

        // Chronological order
        $queryBuilder->orderBy('s.startTime', 'ASC');

        $sessions = $queryBuilder->getQuery()->getResult();

        $data = array_map(function(Session $session) {
            return [
                'id' => $session->getId(),
                'course' => [
                    'id' => $session->getCourse()->getId(),
                    'name' => $session->getCourse()->getName(),
                    'price' => $session->getCourse()->getPrice(),
                ],
                'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $session->getEndTime()->format('Y-m-d H:i:s'),
                'availableSpots' => $session->getAvailableSpots(),
                'status' => $session->getStatus(),
            ];
        }, $sessions);

        return $this->json($data);
    }

    // GET /api/sessions/{id} - Details of a session (PUBLIC)
    #[Route('/sessions/{id}', name: 'api_public_session_show', methods: ['GET'])]
    public function showSession(int $id, EntityManagerInterface $em): JsonResponse
    {
        $session = $em->getRepository(Session::class)->find($id);

        if (!$session) {
            return $this->json(['error' => 'session non trouvée'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $session->getId(),
            'course' => [
                'id' => $session->getCourse()->getId(),
                'name' => $session->getCourse()->getName(),
                'description' => $session->getCourse()->getDescription(),
                'duration' => $session->getCourse()->getDuration(),
                'price' => $session->getCourse()->getPrice(),
            ],
            'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
            'endTime' => $session->getEndTime()->format('Y-m-d H:i:s'),
            'availableSpots' => $session->getAvailableSpots(),
            'status' => $session->getStatus(),
        ]);
    }
}