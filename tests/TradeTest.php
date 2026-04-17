<?php

namespace App\Tests\Controller\frontOffice;

use App\Entity\Trade;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TradeControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createTrade(string $status = 'PENDING'): Trade
    {
        $trade = new Trade();
        $trade->setAssetId(1);
        $trade->setTradeType('BUY');
        $trade->setOrderMode('MARKET');
        $trade->setQuantity(1.0);
        $trade->setStatus($status);
        $trade->setCreatedAt(new \DateTime());

        $this->em->persist($trade);
        $this->em->flush();

        return $trade;
    }

  
    public function testIndexLoads(): void
    {
        $this->client->request('GET', '/trade');
        $this->assertResponseIsSuccessful();
    }

   
    public function testNewFormLoads(): void
    {
        $this->client->request('GET', '/trade/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#trade-form');
    }

    public function testShowLoads(): void
    {
        $trade = $this->createTrade();
        $this->client->request('GET', '/trade/' . $trade->getId());
        $this->assertResponseIsSuccessful();
    }

   
    public function testEditExecutedTradeIsBlocked(): void
    {
        $trade = $this->createTrade('EXECUTED');
        $this->client->request('GET', '/trade/' . $trade->getId() . '/edit');
        $this->assertResponseRedirects('/trade/' . $trade->getId());
    }

    public function testDeletePendingTrade(): void
    {
        $trade = $this->createTrade('PENDING');
        $id    = $trade->getId();

        $this->client->request('POST', '/trade/' . $id . '/delete', [
            '_token' => static::getContainer()
                ->get('security.csrf.token_manager')
                ->getToken('delete' . $id)
                ->getValue(),
        ]);

        $this->assertResponseRedirects('/trade');
        $this->assertNull($this->em->find(Trade::class, $id));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}