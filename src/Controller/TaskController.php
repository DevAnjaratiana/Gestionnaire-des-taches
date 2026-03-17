<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TaskController extends AbstractController
{
    // Méthode privée pour calculer les stats
    private function getStats(EntityManagerInterface $entityManager): array
    {
        $total = $entityManager->createQuery('SELECT COUNT(t) FROM App\Entity\Task t')->getSingleScalarResult();
        $completed = $entityManager->createQuery('SELECT COUNT(t) FROM App\Entity\Task t WHERE t.status = true')->getSingleScalarResult();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
        ];
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(EntityManagerInterface $entityManager, TaskRepository $taskRepository): Response
    {
        $stats = $this->getStats($entityManager);
        $latestTasks = $taskRepository->findBy([], ['createdAt' => 'DESC'], 5);

        return $this->render('task/dashboard.html.twig', [
            'stats' => $stats,
            'total_tasks' => $stats['total'],
            'completed_tasks' => $stats['completed'],
            'pending_tasks' => $stats['pending'],
            'latest_tasks' => $latestTasks,
        ]);
    }

    #[Route('/task', name: 'app_task_index', methods: ['GET'])]
    public function index(TaskRepository $taskRepository, EntityManagerInterface $entityManager): Response
    {
        return $this->render('task/index.html.twig', [
            'tasks' => $taskRepository->findAll(),
            'stats' => $this->getStats($entityManager), // Pour la sidebar
        ]);
    }

    #[Route('/task/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($task);
            $entityManager->flush();

            $this->addFlash('success', 'Tâche créée avec succès !');
            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form,
            'stats' => $this->getStats($entityManager), // Pour la sidebar
        ]);
    }

    #[Route('/task/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task, EntityManagerInterface $entityManager): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'stats' => $this->getStats($entityManager), // Pour la sidebar
        ]);
    }

    #[Route('/task/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Tâche modifiée avec succès !');
            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
            'stats' => $this->getStats($entityManager), // Pour la sidebar
        ]);
    }

    #[Route('/task/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($task);
            $entityManager->flush();
            $this->addFlash('success', 'Tâche supprimée avec succès !');
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/task/{id}/toggle', name: 'app_task_toggle', methods: ['POST'])]
    public function toggle(Task $task, EntityManagerInterface $entityManager): Response
    {
        $task->setStatus(!$task->isStatus());
        $entityManager->flush();

        $this->addFlash(
            $task->isStatus() ? 'success' : 'warning',
            $task->isStatus() ? 'Tâche marquée comme terminée !' : 'Tâche réouverte !'
        );

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }
}
