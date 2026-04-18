<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\ConversionRepository;

#[ORM\Entity(repositoryClass: ConversionRepository::class)]
#[ORM\Table(name: 'conversion')]
class Conversion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_conversion = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8, nullable: false)]
    private ?string $amount_from = null;

    // --- AJOUT : Propriété pour stocker le montant final converti ---
    #[ORM\Column(type: 'decimal', precision: 18, scale: 8, nullable: false)]
    private ?string $amount_to = null;

    #[ORM\ManyToOne(targetEntity: Currency::class, inversedBy: 'conversionsFrom')]
    #[ORM\JoinColumn(name: 'currency_from', referencedColumnName: 'id_currency')]
    private ?Currency $currencyFrom = null;

    #[ORM\ManyToOne(targetEntity: Currency::class, inversedBy: 'conversionsTo')]
    #[ORM\JoinColumn(name: 'currency_to', referencedColumnName: 'id_currency')]
    private ?Currency $currencyTo = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8, nullable: false)]
    private ?string $exchange_rate = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'conversion')]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getId_conversion(): ?int { return $this->id_conversion; }

    public function getAmount_from(): ?string { return $this->amount_from; }
    public function setAmount_from(string $amount_from): self { 
        $this->amount_from = $amount_from; 
        return $this; 
    }

    // --- AJOUT : Getter et Setter pour amount_to ---
    public function getAmount_to(): ?string { 
        return $this->amount_to; 
    }
    public function setAmount_to(string $amount_to): self { 
        $this->amount_to = $amount_to; 
        return $this; 
    }

    public function getCurrencyFrom(): ?Currency { return $this->currencyFrom; }
    public function setCurrencyFrom(?Currency $currencyFrom): self { 
        $this->currencyFrom = $currencyFrom; 
        return $this; 
    }

    public function getCurrencyTo(): ?Currency { return $this->currencyTo; }
    public function setCurrencyTo(?Currency $currencyTo): self { 
        $this->currencyTo = $currencyTo; 
        return $this; 
    }

    public function getExchange_rate(): ?string { return $this->exchange_rate; }
    public function setExchange_rate(string $exchange_rate): self { 
        $this->exchange_rate = $exchange_rate; 
        return $this; 
    }

    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreated_at(\DateTimeInterface $created_at): self { 
        $this->created_at = $created_at; 
        return $this; 
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection { return $this->transactions; }
}