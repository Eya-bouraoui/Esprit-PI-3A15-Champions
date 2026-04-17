<?php

namespace App\Command;

use App\Entity\Trade;

use App\Repository\TradeRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:bot:trading',
    description: 'Bot qui surveille les ordres LIMIT PENDING et les execute automatiquement via CoinGecko.',
)]
class BotTradingCommand extends Command
{
    private string $geckoBase = 'https://api.coingecko.com/api/v3';

    private array $geckoIds = [
        'BTC'   => 'bitcoin',
        'ETH'   => 'ethereum',
        'XRP'   => 'ripple',
        'BNB'   => 'binancecoin',
        'SOL'   => 'solana',
        'ADA'   => 'cardano',
        'DOGE'  => 'dogecoin',
        'DOT'   => 'polkadot',
        'AVAX'  => 'avalanche-2',
        'LINK'  => 'chainlink',
        'UNI'   => 'uniswap',
        'ATOM'  => 'cosmos',
        'LTC'   => 'litecoin',
        'MATIC' => 'matic-network',
        'ESP'   => 'espers',
        'ZAMA'  => 'zama',
        'SENT'  => 'sentinel',
        'RLUSD' => 'ripple-usd',
    ];

    public function __construct(
        private HttpClientInterface    $httpClient,
        private EntityManagerInterface $em,
        private TradeRepository        $tradeRepo,
        private WalletRepository       $walletRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>============================================</info>');
        $output->writeln('<info>   BOT TRADING DEMARRE                      </info>');
        $output->writeln('<info>   API : CoinGecko (pas de blocage geo)     </info>');
        $output->writeln('<info>   Cycle : toutes les 15 secondes           </info>');
        $output->writeln('<info>============================================</info>');
        $output->writeln('');

        $cycle = 0;
        while (true) {
            $cycle++;
            $this->em->clear();

            $output->writeln(sprintf(
                '<comment>[%s] Cycle #%d ----------------------------------------</comment>',
                date('H:i:s'), $cycle
            ));

            $this->processPendingTrades($output);
            $output->writeln('');
            sleep(15);
        }

        return Command::SUCCESS;
    }

    private function processPendingTrades(OutputInterface $output): void
    {
        $pendingTrades = $this->tradeRepo->findBy([
            'status'    => 'PENDING',
            'orderMode' => 'LIMIT',
        ]);

        if (empty($pendingTrades)) {
            $output->writeln('  Aucun ordre LIMIT PENDING trouve.');
            return;
        }

        $output->writeln(sprintf('  <info>%d ordre(s) PENDING a surveiller...</info>', count($pendingTrades)));

        // Grouper par symbole pour limiter les appels API
        $tradesBySymbol = [];
        foreach ($pendingTrades as $trade) {
            $symbol = $this->getTradeSymbol($trade);
            if (!$symbol) {
                $output->writeln(sprintf('  <e>Trade #%d : symbole introuvable, ignore.</e>', $trade->getId()));
                continue;
            }
            $tradesBySymbol[$symbol][] = $trade;
        }

        foreach ($tradesBySymbol as $symbol => $trades) {
            $geckoId = $this->geckoIds[$symbol] ?? null;

            if (!$geckoId) {
                $output->writeln(sprintf('  Symbole "%s" absent de $geckoIds.', $symbol));
                continue;
            }

            $marketPrice = $this->getCoinGeckoPrice($geckoId);

            if ($marketPrice === null) {
                $output->writeln(sprintf('  Prix indisponible pour %s.', $symbol));
                continue;
            }

            $output->writeln(sprintf(
                '  %s/USDT => Prix marche : $%s',
                $symbol,
                number_format($marketPrice, 6)
            ));

            foreach ($trades as $trade) {
                $this->evaluateTrade($trade, $symbol, $marketPrice, $output);
            }

            sleep(1); // limite rate CoinGecko gratuit
        }
    }

    private function evaluateTrade(Trade $trade, string $symbol, float $marketPrice, OutputInterface $output): void
    {
        $targetPrice = (float) $trade->getPrice();
        $tradeType   = strtoupper($trade->getTradeType());
        $quantity    = (float) $trade->getQuantity();

        $output->writeln(sprintf(
            '    Trade #%d | %s | Cible: $%s | Marche: $%s | Qty: %s',
            $trade->getId(),
            $tradeType,
            number_format($targetPrice, 6),
            number_format($marketPrice, 6),
            $quantity
        ));

        $shouldExecute = false;

        if ($tradeType === 'BUY' && $marketPrice <= $targetPrice) {
            $shouldExecute = true;
            $output->writeln('    <info>BUY declenche : marche <= cible</info>');
        } elseif ($tradeType === 'SELL' && $marketPrice >= $targetPrice) {
            $shouldExecute = true;
            $output->writeln('    <info>SELL declenche : marche >= cible</info>');
        } else {
            $output->writeln('    En attente des conditions...');
        }

        if ($shouldExecute) {
            $this->executeTradeWithWallet($trade, $marketPrice, $output);
        }
    }

    private function executeTradeWithWallet(Trade $trade, float $marketPrice, OutputInterface $output): void
    {
        $userId    = $trade->getUserId();
        $wallet    = $this->walletRepo->findOneBy(['utilisateur' => $userId]);
        $quantity  = (float) $trade->getQuantity();
        $total     = $marketPrice * $quantity;
        $tradeType = strtoupper($trade->getTradeType());

        if (!$wallet) {
            $output->writeln('    <e>Wallet introuvable pour userId=' . $userId . '</e>');
            return;
        }

        $solde = (float) $wallet->getSolde();

        if ($tradeType === 'BUY') {
            if ($solde < $total) {
                $output->writeln(sprintf('    Solde insuffisant : besoin $%.2f, dispo $%.2f', $total, $solde));
                $trade->setStatus('CANCELLED');
                $this->em->flush();
                return;
            }
            $wallet->setSolde($solde - $total);
        } elseif ($tradeType === 'SELL') {
            $wallet->setSolde($solde + $total);
        } else {
            return;
        }

        // PENDING -> COMPLETED
        $trade->setStatus('COMPLETED');
        $trade->setPrice((string) $marketPrice);

        if (method_exists($trade, 'setExecutedAt')) {
            $trade->setExecutedAt(new \DateTime());
        }

        $this->em->persist($wallet);
        $this->em->persist($trade);
        $this->em->flush();

        $output->writeln(sprintf(
            '    <info>TRADE #%d EXECUTE | %s | Qty: %.6f @ $%.6f | Total: $%.2f | Solde: $%.2f</info>',
            $trade->getId(),
            $tradeType,
            $quantity,
            $marketPrice,
            $total,
            (float) $wallet->getSolde()
        ));
    }

    private function getTradeSymbol(Trade $trade): ?string
    {
        // Cas 1 : relation Asset
        if (method_exists($trade, 'getAsset') && $trade->getAsset()) {
            return strtoupper($trade->getAsset()->getSymbol());
        }

        // Cas 2 : champ symbol direct sur le trade
        if (method_exists($trade, 'getSymbol') && $trade->getSymbol()) {
            return strtoupper(str_replace('USDT', '', $trade->getSymbol()));
        }

        // Cas 3 : via assetId en DB
        if (method_exists($trade, 'getAssetId') && $trade->getAssetId()) {
            try {
                $row = $this->em->getConnection()->fetchAssociative(
                    'SELECT symbol FROM asset WHERE id = :id',
                    ['id' => $trade->getAssetId()]
                );
                if ($row) return strtoupper($row['symbol']);
            } catch (\Exception) {}
        }

        return null;
    }

    private function getCoinGeckoPrice(string $geckoId): ?float
    {
        try {
            $response = $this->httpClient->request('GET', $this->geckoBase . '/simple/price', [
                'query'   => ['ids' => $geckoId, 'vs_currencies' => 'usd'],
                'timeout' => 10,
            ]);
            $data = $response->toArray();
            return isset($data[$geckoId]['usd']) ? (float) $data[$geckoId]['usd'] : null;
        } catch (\Exception) {
            return null;
        }
    }
}