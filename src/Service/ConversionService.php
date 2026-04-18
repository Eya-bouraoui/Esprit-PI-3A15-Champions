<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ConversionService
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function getExchangeRate(string $from, string $to): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) return 1.0;

        // URL CryptoCompare comme dans ton Java
        $url = "https://min-api.cryptocompare.com/data/price?fsym={$from}&tsyms={$to}";
        
        $response = $this->client->request('GET', $url);
        $data = $response->toArray();

        if (isset($data[$to])) {
            return (float) $data[$to];
        }

        throw new \Exception("Impossible de récupérer le taux pour " . $to);
    }
}