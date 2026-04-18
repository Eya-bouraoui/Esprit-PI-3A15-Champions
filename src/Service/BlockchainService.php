<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\Blockchain;
use App\Entity\Notification;
use App\Repository\BlockchainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twilio\Rest\Client;

class BlockchainService 
{
    private $managerRegistry;
    private $em;
    private $repository;
    private $params; // 2. AJOUTER CETTE PROPRIÉTÉ
    private const AES_KEY = "Champions_Secure";

    // 3. METTRE À JOUR LE CONSTRUCTEUR
    public function __construct(
        ManagerRegistry $managerRegistry, 
        BlockchainRepository $repository,
        ParameterBagInterface $params // <--- Symfony va injecter le service ici
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->em = $managerRegistry->getManager();
        $this->repository = $repository;
        $this->params = $params; // <--- C'est ici qu'on remplit $this->params !
    }

    private function ensureManagerIsOpen(): void {
        if (!$this->em->isOpen()) {
            $this->em = $this->managerRegistry->resetManager();
        }
    }

    private function encrypt(string $data): string {
        return base64_encode(openssl_encrypt($data, 'AES-128-ECB', self::AES_KEY));
    }

    private function decrypt(string $data): string {
        return openssl_decrypt(base64_decode($data), 'AES-128-ECB', self::AES_KEY);
    }

public function addBlock(Transaction $t): void 
{
    $this->ensureManagerIsOpen();
    $lastBlock = $this->repository->findOneBy([], ['block_index' => 'DESC']);
    $previousHashOnly = "0000";
    $encryptedBackupOfPrevious = "";
    $newIndex = 1;

    if ($lastBlock) {
        $previousHashOnly = $lastBlock->getCurrent_hash();
        $newIndex = $lastBlock->getBlock_index() + 1;

        // --- LOGIQUE SOURCE (RIB ou CARTE) ---
        $sourceLabel = "N/A";
        if ($lastBlock->getWalletSource()) {
            $sourceLabel = $lastBlock->getWalletSource()->getRib();
        } elseif ($lastBlock->getType() === 'RECHARGE' && $lastBlock->getTransaction() && $lastBlock->getTransaction()->getCreditCard()) {
            // Affichage : ******** + Last4Digits
            $sourceLabel = "********" . $lastBlock->getTransaction()->getCreditCard()->getLast4Digits();
        }

        $dataToBackup = sprintf(
            "ID:%d|MT:%.2f|TYP:%s|SRC:%s|DST:%s",
            $lastBlock->getTransaction() ? $lastBlock->getTransaction()->getIdTransaction() : 0,
            $lastBlock->getMontant(),
            $lastBlock->getType(),
            $sourceLabel,
            $lastBlock->getWalletDestination() ? $lastBlock->getWalletDestination()->getRib() : "EXTERNE"
        );
        $encryptedBackupOfPrevious = $this->encrypt($dataToBackup);
    }

    $formattedAmount = sprintf("%.2f", $t->getMontant());
    $newHash = $this->generateHash($t->getIdTransaction() . "|" . $previousHashOnly . "|" . $formattedAmount);
    $storageValue = $previousHashOnly . ";" . $encryptedBackupOfPrevious;

    $block = new Blockchain();
    $block->setTransaction($t);
    $block->setBlock_index($newIndex);
    $block->setPrevious_hash($storageValue);
    $block->setCurrent_hash($newHash);
    $block->setMontant($t->getMontant());
    $block->setType($t->getType());
    $block->setWalletSource($t->getWalletSource());
    $block->setWalletDestination($t->getWalletDestination());

    // --- SAUVEGARDE ---
    $this->em->persist($block);
    $this->em->flush();

    // --- NOTIFICATION WHATSAPP ---
    // On appelle la notification après le flush pour être sûr que la transaction est scellée
    $this->sendDetailedWhatsApp($t);
}
    public function verifyIntegrity(): void {
        $blocks = $this->repository->findBy([], ['block_index' => 'ASC']);
        $lastKnownHash = "0000";
        $expectedIndex = 1;

        foreach ($blocks as $block) {
            $this->ensureManagerIsOpen();
            $currentIndex = $block->getBlock_index();
            $parts = explode(';', $block->getPrevious_hash());
            $storedPrevHash = $parts[0];
            $storedCurrentHash = $block->getCurrent_hash();

            // 1. RECALCUL DU HASH
            $formattedAmount = sprintf("%.2f", $block->getMontant());
            $recalculatedHash = $this->generateHash($block->getTransaction()->getIdTransaction() . "|" . $storedPrevHash . "|" . $formattedAmount);

            if ($recalculatedHash !== $storedCurrentHash) {
                $this->createAlert("BLOCKCHAIN_CORRUPTED", "🚨 ALERTE : Hash invalide au bloc #" . $currentIndex);
            }

            // 2. DÉTECTION SUPPRESSION
            if ($currentIndex > $expectedIndex) {
                $this->handleDeleteDetected($expectedIndex, $parts);
                $expectedIndex = $currentIndex;
            }

            // 3. DÉTECTION UPDATE
            if (count($parts) > 1 && !empty($parts[1])) {
                $decrypted = $this->decrypt($parts[1]);
                $d = $this->parseBackupChain($decrypted);
                
                try {
                    $oldId = (int) $d['ID'];
                    $oldAmount = (float) $d['MT'];
                    $this->checkAndTriggerRupture($oldId, $oldAmount, $d);
                } catch (\Exception $e) {}
            }

            // 4. RUPTURE LIEN
            if ($storedPrevHash !== $lastKnownHash) {
                $this->createAlert("BLOCKCHAIN_CORRUPTED", "🚨 RUPTURE : Lien brisé au bloc #" . $currentIndex);
            }

            $lastKnownHash = $storedCurrentHash;
            $expectedIndex++;
        }
    }

    private function parseBackupChain(string $decrypted): array {
        $data = explode('|', $decrypted);
        $result = [];
        foreach ($data as $item) {
            $kv = explode(':', $item, 2);
            if (count($kv) === 2) {
                $result[$kv[0]] = $kv[1];
            }
        }
        return $result;
    }

    private function handleDeleteDetected(int $missingIdx, array $nextBlockParts): void {
        $msg = "🚨 ALERTE : DELETE_detected\n";
        $msg .= "------------------------------------------\n";
        $msg .= "❌ Bloc disparu à l'index : " . $missingIdx . "\n";

        if (count($nextBlockParts) > 1) {
            $d = $this->parseBackupChain($this->decrypt($nextBlockParts[1]));
            $msg .= "📝 Type : " . ($d['TYP'] ?? 'N/A') . "\n";
            $msg .= "💰 Montant : " . ($d['MT'] ?? '0.00') . " DT\n";
            $msg .= "💳 Source : " . ($d['SRC'] ?? 'N/A') . "\n";
            $msg .= "📥 Destination : " . ($d['DST'] ?? 'N/A') . "\n";
        }
        $msg .= "------------------------------------------\n";
        $this->createAlert("DELETE_DETECTED", $msg);
    }

    private function checkAndTriggerRupture(int $transId, float $backupAmount, array $d): void {
        $this->ensureManagerIsOpen();
        $transaction = $this->em->getRepository(Transaction::class)->find($transId);
        if ($transaction) {
            $currentAmount = (float) $transaction->getMontant();
            if (abs($currentAmount - $backupAmount) > 0.001) {
                $msg = "🚨 ALERTE : UPDATE_detected\n";
                $msg .= "------------------------------------------\n";
                $msg .= "📝 Type : " . ($d['TYP'] ?? 'N/A') . "\n";
                $msg .= "💳 Source : " . ($d['SRC'] ?? 'N/A') . "\n";
                $msg .= "📥 Destination : " . ($d['DST'] ?? 'N/A') . "\n";
                $msg .= "------------------------------------------\n";
                $msg .= "📜 ANCIEN : " . number_format($backupAmount, 2, '.', '') . " DT\n";
                $msg .= "🕵️ NOUVEAU : " . number_format($currentAmount, 2, '.', '') . " DT\n";
                $msg .= "------------------------------------------\n";
                
                $this->createAlert("UPDATE_DETECTED", $msg, $transId);
                $this->breakChainOnUpdate($transId, $currentAmount);
            }
        }
    }

    public function breakChainOnUpdate(int $transactionId, float $newAmount): void {
        $this->ensureManagerIsOpen();
        $block = $this->repository->findOneBy(['transaction' => $transactionId]);
        if ($block) {
            $prevHash = explode(';', $block->getPrevious_hash())[0];
            $corruptedHash = $this->generateHash($transactionId . "|" . $prevHash . "|" . sprintf("%.2f", $newAmount));
            $block->setCurrent_hash($corruptedHash);
            $this->em->flush();
        }
    }

    public function generateHash(string $data): string {
        return hash('sha256', $data);
    }

    private function createAlert(string $type, string $message, ?int $txId = null): void {
        $this->ensureManagerIsOpen();
        $repo = $this->em->getRepository(Notification::class);
        $exists = $txId 
            ? $repo->findOneBy(['id_transaction' => $txId, 'type_notification' => $type])
            : $repo->findOneBy(['message' => $message, 'type_notification' => $type]);

        if (!$exists) {
            $notif = new Notification();
            $notif->setIdTransaction($txId);
            $notif->setTypeNotification($type);
            $notif->setMessage($message);
            $notif->setCreatedAt(new \DateTime());
            $notif->setIsRead(false);
            $this->em->persist($notif);
            $this->em->flush();
        }
    }

private function sendDetailedWhatsApp(Transaction $t): void 
{
    $destWallet = $t->getWalletDestination();
    if (!$destWallet) {
        return;
    }

    // Ton numéro de test (Format international requis)
    $userPhone = "+21695558576"; 

    try {
        $client = new \Twilio\Rest\Client(
            $this->params->get('twilio_sid'),
            $this->params->get('twilio_token')
        );

        // --- PRÉPARATION DES DONNÉES ---
        $type = strtoupper($t->getType());
        $amount = number_format($t->getMontant(), 2, '.', ' ');
        $currency = $t->getCurrency() ? $t->getCurrency()->getNom() : 'DT';
        $date = $t->getDateTransaction()->format('d/m/Y H:i');
        
        // Identification Source
        $source = "N/A";
        if ($t->getWalletSource()) {
            $source = "💳 RIB: " . $t->getWalletSource()->getRib();
        } elseif ($t->getCreditCard()) {
            $source = "💳 Carte: **** " . $t->getCreditCard()->getLast4Digits();
        }

        // Identification Destination
        $dest = $t->getWalletDestination() ? "📥 RIB: " . $t->getWalletDestination()->getRib() : "Externe";

        // --- CONSTRUCTION DU MESSAGE ---
        // --- MESSAGE CONSTRUCTION (English Version) ---
        $messageBody = "🔔 *ChampionsPi Notification*\n\n";
        $messageBody .= "✅ *Transaction Successful!*\n";
        $messageBody .= "------------------------------------------\n";
        $messageBody .= "📝 *Type:* " . $type . "\n";
        $messageBody .= "💰 *Amount:* " . $amount . " " . $currency . "\n";
        $messageBody .= "📅 *Date:* " . $date . "\n";
        $messageBody .= "------------------------------------------\n";
        $messageBody .= "📤 *Source:* " . ($t->getWalletSource() ? "RIB: " . $t->getWalletSource()->getRib() : ($t->getCreditCard() ? "Card: **** " . $t->getCreditCard()->getLast4Digits() : "N/A")) . "\n";
        $messageBody .= "📥 *Destination:* " . ($t->getWalletDestination() ? "RIB: " . $t->getWalletDestination()->getRib() : "External") . "\n";
        $messageBody .= "------------------------------------------\n";
        $messageBody .= "🛡️ _Secured by Champions Blockchain_";

        // --- ENVOI DU MESSAGE LIBRE ---
        $client->messages->create(
            "whatsapp:" . $userPhone,
            [
                "from" => "whatsapp:" . $this->params->get('twilio_whatsapp_from'),
                "body" => $messageBody // Utilisation du body au lieu du contentSid
            ]
        );

    } catch (\Exception $e) {
        error_log("Twilio Error: " . $e->getMessage());
    }
}
}