<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Entity\WalletCurrency;
use App\Entity\Currency;
use App\Entity\Conversion;
use App\Repository\WalletRepository;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;

class TransactionManager 
{
    private $em;
    private $walletRepo;
    private $currencyRepo;
    private $blockchain;
    private $conversionService;

    public function __construct(
        EntityManagerInterface $em, 
        WalletRepository $wr, 
        CurrencyRepository $cr,
        BlockchainService $bs,
        ConversionService $cs
    ) {
        $this->em = $em;
        $this->walletRepo = $wr;
        $this->currencyRepo = $cr;
        $this->blockchain = $bs;
        $this->conversionService = $cs;
    }

    public function execute(string $ribSrc, string $ribDest, float $amount, string $type, int $currencyId, ?int $targetCurrencyId = null): void 
    {
        // 1. Chargement des entités
        $srcWallet = $this->walletRepo->findOneBy(['rib' => $ribSrc]);
        $destWallet = $this->walletRepo->findOneBy(['rib' => $ribDest]);
        $currency = $this->currencyRepo->find($currencyId);

        if (!$srcWallet) throw new \Exception("Source wallet not found.");
        if (!$destWallet) throw new \Exception("Recipient wallet not found.");
        if (!$currency) throw new \Exception("Currency not found.");
        if ($amount <= 0) throw new \Exception("Amount must be positive.");
        if ($srcWallet->getStatut() === 'bloque') throw new \Exception("Source wallet is blocked.");

        // 2. Variables de contrôle
        $srcType = strtolower($srcWallet->getTypeWallet()); // fiat, crypto, trading
        $destType = strtolower($destWallet->getTypeWallet());
        $currencyType = strtolower($currency->getTypeCurrency());
        $typeUpper = strtoupper($type);
        
        $isConversion = ($typeUpper === 'CONVERSION');
        $isTrade = in_array($typeUpper, ['ACHAT', 'VENTE', 'BUY', 'SELL']);

        // 3. LOGIQUE DE SÉCURITÉ INTER-WALLET (TA DEMANDE)
        
        // Règle A : Empêcher l'envoi direct Fiat -> Crypto/Trading sans conversion
        if ($srcType === 'fiat' && $destType !== 'fiat') {
            if (!$isConversion) {
                throw new \Exception("Transactions from Fiat to other wallets must be a 'CONVERSION'.");
            }
        }

        // Règle B : Paire Crypto <-> Trading (Autorisé si la devise est tagguée trading)
        $isCryptoTradingPair = ($srcType === 'crypto' && $destType === 'trading') || 
                               ($srcType === 'trading' && $destType === 'crypto');

        if ($isCryptoTradingPair) {
            // On vérifie si ton entité Currency a la méthode isTrading() ou similaire
            if (method_exists($currency, 'isTrading') && !$currency->isTrading()) {
                throw new \Exception("This currency is not authorized for trading transactions.");
            }
        }

        // Règle C : Interdiction si les types sont différents et ce n'est ni conversion ni trade
        if ($srcType !== $destType && !$isCryptoTradingPair && $srcType !== 'fiat') {
            if (!$isConversion && !$isTrade) {
                throw new \Exception("Forbidden transaction: Wallet types do not match and no conversion/trade specified.");
            }
        }

        // Règle D : Compatibilité de l'asset (Fiat ne va pas dans un wallet Crypto)
        if ($currencyType === 'fiat' && $srcType !== 'fiat') {
            throw new \Exception("Incompatible asset: Fiat currency cannot be stored in a $srcType wallet.");
        }

        // 4. TRANSACTION ATOMIQUE
        $this->em->getConnection()->beginTransaction();

        try {
            // A. Vérification du solde source
            $srcWC = $this->em->getRepository(WalletCurrency::class)->findOneBy([
                'wallet' => $srcWallet,
                'currency' => $currency
            ]);

            if (!$srcWC || $srcWC->getSolde() < $amount) {
                throw new \Exception("Insufficient balance in " . $currency->getNom());
            }

            $finalAmount = $amount;
            $targetCurrency = $currency;
            $conversionRecord = null;

            // B. LOGIQUE DE CONVERSION (Si applicable)
            if ($isConversion) {
                if (!$targetCurrencyId) throw new \Exception("Target currency ID is required for conversion.");
                
                $targetCurrency = $this->currencyRepo->find($targetCurrencyId);
                if (!$targetCurrency) throw new \Exception("Target currency not found.");

                $targetCurrencyType = strtolower($targetCurrency->getTypeCurrency());

                // Validation compatibilité destination finale
                if ($targetCurrencyType === 'fiat' && $destType !== 'fiat') {
                    throw new \Exception("Conversion failed: Target currency (Fiat) incompatible with $destType wallet.");
                }

                $rate = $this->conversionService->getExchangeRate($currency->getNom(), $targetCurrency->getNom());
                $finalAmount = $amount * $rate;

                $conversionRecord = new Conversion();
                $conversionRecord->setAmount_from((string)$amount);
                $conversionRecord->setAmount_to((string)$finalAmount); 
                $conversionRecord->setCurrencyFrom($currency);
                $conversionRecord->setCurrencyTo($targetCurrency);
                $conversionRecord->setExchange_rate((string)$rate);
                $conversionRecord->setCreated_at(new \DateTime());

                $this->em->persist($conversionRecord);
            }

            // C. MISE À JOUR DES SOLDES
            $srcWC->setSolde($srcWC->getSolde() - $amount);

            $destWC = $this->em->getRepository(WalletCurrency::class)->findOneBy([
                'wallet' => $destWallet,
                'currency' => $targetCurrency
            ]);

            if (!$destWC) {
                $destWC = new WalletCurrency();
                $destWC->setWallet($destWallet);
                $destWC->setCurrency($targetCurrency);
                $destWC->setNomCurrency($targetCurrency->getNom());
                $destWC->setSolde(0.0);
                $this->em->persist($destWC);
            }
            $destWC->setSolde($destWC->getSolde() + $finalAmount);

            // D. CRÉATION DE LA TRANSACTION
            $t = new Transaction();
            $t->setWalletSource($srcWallet)
              ->setWalletDestination($destWallet)
              ->setMontant($amount)
              ->setType($typeUpper)
              ->setStatut('VALID')
              ->setCurrency($currency)
              ->setDateTransaction(new \DateTime());
            
            if ($conversionRecord) {
                $t->setConversion($conversionRecord);
            }
            
            $this->em->persist($t);

            // E. AUDIT & BLOCKCHAIN
            if (method_exists($srcWallet, 'setDateDerniereModification')) {
                $srcWallet->setDateDerniereModification(new \DateTime());
            }
            if (method_exists($destWallet, 'setDateDerniereModification')) {
                $destWallet->setDateDerniereModification(new \DateTime());
            }

            $this->em->flush();
            $this->blockchain->addBlock($t);
            $this->em->flush();

            $this->em->getConnection()->commit();

        } catch (\Exception $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->getConnection()->rollBack();
            }
            throw new \Exception("Transaction failed: " . $e->getMessage());
        }
    }
}