<?php

namespace App\Controller;

use App\Entity\Session;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/api/admin/sessions')]
#[IsGranted('ROLE_ADMIN')]
class SessionController extends AbstractController
{
    // GET /api/admin/sessions - List all the sessions
    #[Route('', name: 'api_sessions_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $sessions = $em ->getRepository(Session::class)->findAll();

        $data = array_map(function(Session $session) {
            return [
                'id' => $session->getId(),
                'course' => [
                    'id' => $session->getCourse()->getId(),
                    'name' => $session->getCourse()->getName(),
                ],
                'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $session->getEndTime()->format('Y-m-d H:i:s'),
                'availableSpots' => $session->getAvailableSpots(),
                'status' => $session->getStatus(),
            ];
        }, $sessions);

        return $this->json($data);
    }

    // POST /api/admin/sessions - Create a session
    #[Route('', name: 'api_sessions_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        //Validation
        if (!isset($data['courseId']) || !isset($data['startTime']) || !isset($data['endTime']) || !isset($data['status'])) {
            return $this->json(['error' => 'Champs obligatoires manquants'], Response::HTTP_BAD_REQUEST);   
        }

        // Check if course existing
        $course = $em->getRepository(Course::class)->find($data['courseId']);
        if (!$course) {
            return $this->json(['error' => 'Cours non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Créer la session
        $session = new Session();
        $session->setCourse($course);
        $session->setStartTime(new \DateTimeImmutable($data['startTime']));
        $session->setEndTime(new \DateTimeImmutable($data['endTime']));
        $session->setAvailableSpots($course->getMaxParticipants());
        $session->setStatus($data['status']);
        $session->setCreatedAt(new \DateTimeImmutable());

        $em->persist($session);
        $em->flush();

        return $this->json([
            'message' => 'session créée avec succès',
            'session' => [
                'id' => $session->getId(),
                'course' => $course->getName(),
                'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $session->getEndTime()->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_CREATED);
    }

    // GET /api/admin/sessions/{id} - Show a session
    #[Route('/{id}', name: 'api_session_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): JsonResponse
    {
        $session = $em->getRepository(Session::class)->find($id);

        if(!$session) {
            return $this->json(['error' => 'Session non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $session->getId(),
            'course' => [
                'id' => $session->getCourse()->getId(),
                'name' => $session->getCourse()->getName(),
                'duration' => $session->getCourse()->getDuration(),
                'price' => $session->getCourse()->getPrice(),
            ],
            'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
            'endTime' => $session->getEndTime()->format('Y-m-d H:i:s'),
            'availableSpots' => $session->getAvailableSpots(),
            'status' => $session->getStatus(),
        ]);
    }

    //PUT /api/admin/session/{id} - Update a session
    #[Route('/{id}', name: 'api_session_update', methods: ['PUT'])]
    public function update(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $session = $em->getRepository(Session::class)->find($id);

        if(!$session) {
            return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);    
        }

        $data = json_decode($request->getContent(), true);

        // if we change a course
        if(isset($data['courseId'])) {
            $course = $em->getRepository(Course::class)->find($data['courseId']);
            if (!$course) {
                return $this->json(['error' => 'Cours non trouvé'], Response::HTTP_NOT_FOUND);  
            }
            $session->setCourse($course);
        }

        if (isset($data['startTime'])) {
            $session->setStartTime(new \DateTimeImmutable($data['startTime']));
        }
        if (isset($data['endTime'])) {
            $session->setEndTime(new \DateTimeImmutable($data['endTime']));
        }
        if (isset($data['availableSpots'])) {
            $session->setAvailableSpots($data['availableSpots']);
        }
        if (isset($data['status'])) {
            $session->setStatus($data['status']);
        }

        $em->flush();

        return $this->json(['message' => 'Session modifié avec succès']);
    }

    //DELETE /api/admin/session/{id} - Delete a session
    #[Route('/{id}', name: 'api_session_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $session = $em->getRepository(Session::class)->find($id);

        if (!$session) {
            return $this->json(['error' => 'Session non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($session);
        $em->flush();

        return $this->json(['message' => 'Session supprimée avec succès']);
    }
}