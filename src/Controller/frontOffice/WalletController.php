<?php

namespace App\Controller\frontOffice;

use App\Entity\Wallet;
use App\Entity\WalletCurrency;
use App\Entity\Utilisateur;
use App\Entity\Currency;
use App\Entity\CreditCard;
use App\Service\ConversionService;
use App\Service\BlockchainService;
use App\Service\TransactionManager;
use App\Form\WalletType;
use App\Form\TransactionType;
use App\Form\CreditCardType; 
use App\Repository\WalletRepository;
use App\Repository\CurrencyRepository;
use App\Repository\TransactionRepository;
use App\Repository\WalletCurrencyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Service\StripeService;

#[Route('/wallet')]
class WalletController extends AbstractController
{
    #[Route('/', name: 'app_wallet_index', methods: ['GET', 'POST'])]
    public function index(
        WalletRepository $walletRepository,
        CurrencyRepository $currencyRepo,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // 1. User Management
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);

        if (!$user) {
            throw $this->createNotFoundException("Test user not found.");
        }

        // 2. Wallet Creation Form
        $newWallet = new Wallet();
        $walletForm = $this->createForm(WalletType::class, $newWallet);
        $walletForm->handleRequest($request);

        if ($walletForm->isSubmitted() && $walletForm->isValid()) {
            $newWallet->setUtilisateur($user);
            $newWallet->setSolde(0.0);
            
            do {
                $rib = (string)random_int(10000000, 99999999);
                $exists = $walletRepository->findOneBy(['rib' => $rib]);
            } while ($exists);
            
            $newWallet->setRib($rib);
            $newWallet->setDateCreation(new \DateTime());
            $newWallet->setStatut('actif');

            $entityManager->persist($newWallet);
            $entityManager->flush();

            $this->addFlash('success', 'New wallet created successfully!');
            return $this->redirectToRoute('app_wallet_index');
        }

        // 3. Data Retrieval for View
        $walletsList = $walletRepository->findBy(['utilisateur' => $user]);
        $allCurrencies = $currencyRepo->findAll();
        $transactionForm = $this->createForm(TransactionType::class);

        // 4. Active Credit Card Management
        $creditCard = $entityManager->getRepository(CreditCard::class)->findOneBy([
            'utilisateur' => $user,
            'statut' => 'ACTIVE'
        ]);

        // Form for EDITING an existing card
        $cardFormView = null;
        if ($creditCard) {
            $cardFormView = $this->createForm(CreditCardType::class, $creditCard, ['is_edit' => true])->createView();
        }

        // ADDITION: Form for CREATING a new card (used by addCardModal)
        $newCardForm = $this->createForm(CreditCardType::class, new CreditCard(), [
            'is_edit' => false,
            'action' => $this->generateUrl('app_card_new') 
        ]);

        return $this->render('wallet/wallet.html.twig', [
            'wallets' => $walletsList,
            'walletForm' => $walletForm->createView(),
            'cardForm' => $cardFormView,           
            'newCardForm' => $newCardForm->createView(), 
            'all_available_currencies' => $allCurrencies,
            'transactionForm' => $transactionForm->createView(),
            'creditCard' => $creditCard,
        ]);
    }

    #[Route('/{id}/add-currency', name: 'app_wallet_add_currency', methods: ['POST'])]
    public function addCurrency(Request $request, Wallet $wallet, CurrencyRepository $currencyRepo, EntityManagerInterface $entityManager): Response
    {
        $idCurrency = $request->request->get('id_currency');
        $nomCurrency = $request->request->get('nom_currency');

        foreach ($wallet->getWalletCurrencys() as $existingWc) {
            if ($existingWc->getCurrency() && $existingWc->getCurrency()->getId() == $idCurrency) {
                $this->addFlash('error', 'This currency already exists in this wallet.');
                return $this->redirectToRoute('app_wallet_index');
            }
        }

        $currency = $currencyRepo->find($idCurrency);
        if (!$currency) {
            $this->addFlash('error', 'Currency not found.');
            return $this->redirectToRoute('app_wallet_index');
        }

        $wc = new WalletCurrency();
        $wc->setWallet($wallet);
        $wc->setCurrency($currency);
        $wc->setNomCurrency($nomCurrency ?: $currency->getNom());
        $wc->setSolde(0.0);
        $wallet->setDateDerniereModification(new \DateTime());

        $entityManager->persist($wc);
        $entityManager->flush();

        $this->addFlash('success', "Currency " . $wc->getNomCurrency() . " added successfully.");
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/{id}/edit', name: 'app_wallet_edit', methods: ['POST'])]
    public function edit(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        $statut = $request->request->get('statut');
        if ($statut) {
            $wallet->setStatut($statut);
            $wallet->setDateDerniereModification(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Status updated successfully.');
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/{id}/delete', name: 'app_wallet_delete', methods: ['POST'])]
    public function delete(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$wallet->getIdWallet(), $request->request->get('_token'))) {
            if (!$wallet->getWalletCurrencys()->isEmpty()) {
                $this->addFlash('error', 'Cannot delete: the wallet contains currencies.');
            } else {
                $entityManager->remove($wallet);
                $entityManager->flush();
                $this->addFlash('success', 'Wallet deleted successfully.');
            }
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/currency/{id}/delete', name: 'app_wallet_currency_delete', methods: ['GET', 'POST'])]
    public function deleteCurrency(WalletCurrency $wc, EntityManagerInterface $entityManager): Response
    {
        if ($wc->getSolde() == 0) {
            $entityManager->remove($wc);
            $entityManager->flush();
            $this->addFlash('success', 'Currency removed from wallet.');
        } else {
            $this->addFlash('error', 'Cannot remove a currency with a positive balance.');
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/stats/chart-data', name: 'app_wallet_chart_data', methods: ['GET'])]
    public function getChartData(WalletCurrencyRepository $wcRepo, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);
        $data = $wcRepo->sumBalancesByUser($user);
        return new JsonResponse($data);
    }

    #[Route('/export/statement', name: 'app_wallet_export_pdf')]
    public function exportStatement(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);
        $wallets = $entityManager->getRepository(Wallet::class)->findBy(['utilisateur' => $user]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('wallet/statement_pdf.html.twig', [
            'user' => $user,
            'wallets' => $wallets,
            'date' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="champions_statement.pdf"'
        ]);
    }

#[Route('/{id}/history-json', name: 'app_wallet_history_json', methods: ['GET'])]
public function getHistoryJson(int $id, WalletRepository $walletRepository, TransactionRepository $transactionRepository): JsonResponse
{
    $wallet = $walletRepository->find($id);
    if (!$wallet) return new JsonResponse(['error' => 'Wallet not found'], 404);

    $transactions = $transactionRepository->createQueryBuilder('t')
        ->leftJoin('t.conversion', 'c') // On joint la table conversion
        ->addSelect('c')
        ->where('t.walletSource = :wallet OR t.walletDestination = :wallet')
        ->setParameter('wallet', $wallet)
        ->orderBy('t.dateTransaction', 'DESC')
        ->getQuery()
        ->getResult();

    $data = [];
    foreach ($transactions as $t) {
        $isDebit = ($t->getWalletSource() && $t->getWalletSource()->getIdWallet() === $wallet->getIdWallet());
        $isConversion = (strtoupper($t->getType()) === 'CONVERSION');
        
        // Initialisation par défaut
        $displayAmount = $t->getMontant();
        $displayCurrency = $t->getCurrency() ? $t->getCurrency()->getNom() : '';
        $counterparty = 'N/A';

        // LOGIQUE SPÉCIFIQUE POUR CONVERSION
        if ($isConversion && $t->getConversion()) {
            if (!$isDebit) {
                // Si on est le destinataire de la conversion, on montre ce qu'on a REÇU
                $displayAmount = (float)$t->getConversion()->getAmount_to();
                $displayCurrency = $t->getConversion()->getCurrencyTo() ? $t->getConversion()->getCurrencyTo()->getNom() : $displayCurrency;
            }
        }

        // Gestion de la contrepartie (Recharge vs Transfert)
        if (strtolower($t->getType()) === 'recharge') {
            if ($t->getCreditCard()) {
                $last4 = $t->getCreditCard()->getLast4Digits(); 
                $counterparty = $last4 ? "**** **** " . $last4 : "Credit Card";
            } else {
                $counterparty = "External Source"; 
            }
        } else {
            $counterparty = $isDebit ? 
                ($t->getWalletDestination() ? $t->getWalletDestination()->getRib() : 'N/A') :
                ($t->getWalletSource() ? $t->getWalletSource()->getRib() : 'N/A');
        }

        $data[] = [
            'date' => $t->getDateTransaction() ? $t->getDateTransaction()->format('d/m/Y H:i') : 'N/A',
            'type' => strtoupper($t->getType()), 
            'amount' => number_format($displayAmount, 2), // Utilise le montant calculé
            'currency' => $displayCurrency,             // Utilise la devise calculée
            'counterparty' => $counterparty,
            'direction' => $isDebit ? 'out' : 'in'
        ];
    }
    return new JsonResponse($data);
}
 // STEP 1: Send the email and store data in session
#[Route('/recharge', name: 'app_wallet_recharge', methods: ['POST'])]
public function initiateRecharge(
    Request $request, 
    \App\Service\MailService $mailService, 
    EntityManagerInterface $em
): Response {
    $walletId = $request->request->get('wallet_id');
    $currencyId = $request->request->get('currency_id');
    $amount = (float)$request->request->get('amount');

    // Generate 6-digit verification code
    $verificationCode = random_int(100000, 999999);

    // Save data in session
    $session = $request->getSession();
    $session->set('pending_recharge', [
        'wallet_id' => $walletId,
        'currency_id' => $currencyId,
        'amount' => $amount,
        'code' => $verificationCode
    ]);

    // Send the email
    $user = $this->getUser() ?: $em->getRepository(Utilisateur::class)->find(1);
    try {
        $mailService->sendVerificationCode($user->getEmail(), $verificationCode);
        $this->addFlash('info', 'A verification code has been sent to your email.');
    } catch (\Exception $e) {
        $this->addFlash('error', 'Failed to send verification email: ' . $e->getMessage());
        return $this->redirectToRoute('app_wallet_index');
    }

    // Redirect to index with a trigger for the popup
    return $this->redirectToRoute('app_wallet_index', ['verify' => 1]);
}

// STEP 2: Verify the code and execute Stripe/Blockchain logic
#[Route('/recharge/confirm', name: 'app_wallet_recharge_confirm', methods: ['POST'])]
public function confirmRecharge(
    Request $request, 
    StripeService $stripeService, 
    EntityManagerInterface $em,
    \App\Service\BlockchainService $blockchainService
): Response {
    $session = $request->getSession();
    $pending = $session->get('pending_recharge');
    $inputCode = $request->request->get('verification_code');

    // Security check
    if (!$pending || $inputCode != $pending['code']) {
        $this->addFlash('error', 'Invalid verification code. Please try again.');
        return $this->redirectToRoute('app_wallet_index');
    }

    // Recover data from session
    $walletId = $pending['wallet_id'];
    $currencyId = $pending['currency_id'];
    $amount = $pending['amount'];

    // --- YOUR ORIGINAL STRIPE & BLOCKCHAIN LOGIC ---
    $user = $this->getUser() ?: $em->getRepository(Utilisateur::class)->find(1);
    $wallet = $em->getRepository(Wallet::class)->find($walletId);
    $currency = $em->getRepository(Currency::class)->find($currencyId);
    $card = $em->getRepository(CreditCard::class)->findOneBy(['utilisateur' => $user, 'statut' => 'ACTIVE']);

    if (!$card || !$card->getStripePaymentMethodId()) {
        $this->addFlash('error', 'No active card found.');
        return $this->redirectToRoute('app_wallet_index');
    }

    try {
        if (!$card->getStripeCustomerId()) {
            $customer = $stripeService->createCustomer($user->getEmail() ?? 'user@example.com');
            $card->setStripeCustomerId($customer->id);
            $em->flush();
        }

        $intent = $stripeService->createPaymentIntent(
            $amount, 
            $currency->getNom(), 
            $card->getStripeCustomerId(), 
            $card->getStripePaymentMethodId()
        );

        if ($intent->status === 'succeeded') {
            // 1. Update WalletCurrency balance
            $walletCurrency = $em->getRepository(WalletCurrency::class)->findOneBy([
                'wallet' => $wallet,
                'currency' => $currency
            ]);

            if (!$walletCurrency) {
                $walletCurrency = new \App\Entity\WalletCurrency();
                $walletCurrency->setWallet($wallet);
                $walletCurrency->setCurrency($currency);
                $walletCurrency->setNomCurrency($currency->getNom());
                $walletCurrency->setSolde($amount);
                $em->persist($walletCurrency);
            } else {
                $walletCurrency->setSolde($walletCurrency->getSolde() + $amount);
            }

            // 2. Create Transaction entity
            $transaction = new \App\Entity\Transaction();
            $transaction->setWalletDestination($wallet);
            $transaction->setWalletSource(null);
            $transaction->setCreditCard($card);
            $transaction->setMontant($amount);
            $transaction->setCurrency($currency);
            $transaction->setType('RECHARGE');
            $transaction->setStatut('COMPLETED');
            $transaction->setDateTransaction(new \DateTime());

            $em->persist($transaction);
            $em->flush(); 

            // 3. Blockchain Logic
            try {
                $blockchainService->addBlock($transaction);
            } catch (\Exception $e) {
                // Blockchain error is optional, we don't block the successful recharge
            }

            // Clean session and success message
            $session->remove('pending_recharge');
            $this->addFlash('success', 'Wallet recharged and transaction secured in Blockchain!');
        }
    } catch (\Exception $e) {
        $this->addFlash('error', 'Error: ' . $e->getMessage());
    }

    return $this->redirectToRoute('app_wallet_index');
}
#[Route('/convert', name: 'app_wallet_convert', methods: ['POST'])]
    public function convert(
        Request $request, 
        EntityManagerInterface $em, 
        TransactionManager $transactionManager
    ): Response {
        try {
            $amountFrom = (float)$request->request->get('amount_from');
            $walletSourceId = $request->request->get('wallet_source');
            $walletDestId = $request->request->get('wallet_destination');
            $currencyFromName = $request->request->get('currency_from_name');
            $currencyToName = $request->request->get('currency_to_name');

            $wSource = $em->getRepository(Wallet::class)->find($walletSourceId);
            $wDest = $em->getRepository(Wallet::class)->find($walletDestId);
            $curFrom = $em->getRepository(Currency::class)->findOneBy(['nom' => $currencyFromName]);
            $curTo = $em->getRepository(Currency::class)->findOneBy(['nom' => $currencyToName]);

            if (!$wSource || !$wDest || !$curFrom || !$curTo) {
                throw new \Exception("Required data missing (Wallet or Currency).");
            }

            // Appel au TransactionManager avec correction des noms de méthodes (getIdCurrency)
            $transactionManager->execute(
                $wSource->getRib(),
                $wDest->getRib(),
                $amountFrom,
                'CONVERSION',
                $curFrom->getId(), 
                $curTo->getId()
            );

            $this->addFlash('success', "✅ Transaction successfully sealed and recorded.");

        } catch (\Exception $e) {
            // Le message d'erreur inclura les restrictions Fiat/Crypto venant du service
            $this->addFlash('error', "Transaction Failed: " . $e->getMessage());
        }

        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/get-rate', name: 'app_wallet_get_rate', methods: ['GET'])]
    public function getRate(Request $request, ConversionService $convServ): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $amount = (float)$request->query->get('amount');

        if (!$from || !$to || $amount <= 0) {
            return new JsonResponse(['error' => 'Paramètres invalides'], 400);
        }

        try {
            $rate = $convServ->getExchangeRate($from, $to);
            return new JsonResponse([
                'rate' => $rate,
                'result' => $amount * $rate
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
