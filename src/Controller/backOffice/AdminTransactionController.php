<?php

namespace App\Controller\backOffice;

use App\Service\BlockchainService;
use App\Repository\NotificationRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminTransactionController extends AbstractController
{
    #[Route('/admin/transactions', name: 'app_admin_transactions', methods: ['GET'])]
    public function adminTransactions(
        Request $request, 
        TransactionRepository $repo, 
        BlockchainService $blockchainService,
        NotificationRepository $notifRepo
    ): Response
    {
        // 1. Audit de la blockchain (insère des notifs si fraude détectée)
        $blockchainService->verifyIntegrity();

        $userName = $request->query->get('user_name');
        $type = $request->query->get('type');

        // 2. Filtrage des transactions
        if ($userName || $type) {
            $transactions = $repo->findByFilters($userName, $type);
        } else {
            $transactions = $repo->findBy([], ['dateTransaction' => 'DESC']);
        }

        // 3. Récupération des notifications réelles depuis TA table 'notification'
        // On filtre par is_read = false (ou null selon ton entité)
        $notifications = $notifRepo->findBy(['is_read' => [false, null]], ['created_at' => 'DESC']);
        $count = count($notifications);

        return $this->render('admin_panel/transactions.html.twig', [
            'transactions' => $transactions,
            'current_user_name' => $userName,
            'current_type' => $type,
            'notifications' => $notifications, 
            'unreadNotificationsCount' => $count 
        ]);
    }

    #[Route('/admin/transactions/{id}', name: 'app_admin_transaction_show', methods: ['GET'])]
    public function show(int $id, TransactionRepository $repo): Response
    {
        $transaction = $repo->find($id);

        if (!$transaction) {
            throw $this->createNotFoundException('Transaction not found.');
        }

        return $this->render('admin_panel/transaction_show.html.twig', [
            'transaction' => $transaction,
        ]);
    }
}