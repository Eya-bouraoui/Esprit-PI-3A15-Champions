<?php

namespace App\Entity;

use App\Repository\CreditCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CreditCardRepository::class)]
#[ORM\Table(name: "credit_card")]
class CreditCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_card", type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "card_holder_name", length: 100)]
    #[Assert\NotBlank(message: "Le nom du porteur est obligatoire")]
    private ?string $cardHolderName = null;

    #[ORM\Column(name: "last_4_digits", length: 4)]
    private ?string $last4Digits = null;

    #[ORM\Column(name: "expiry_month", type: "integer")]
    #[Assert\NotBlank(message: "Le mois est requis")]
    #[Assert\Range(min: 1, max: 12, notInRangeMessage: "Le mois doit être entre 1 et 12")]
    private ?int $expiryMonth = null;

    #[ORM\Column(name: "expiry_year", type: "integer")]
    #[Assert\NotBlank(message: "L'année est requise")]
    private ?int $expiryYear = null;

    #[ORM\Column(name: "stripe_customer_id", length: 100, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(name: "stripe_payment_method_id", length: 100, nullable: true)]
    private ?string $stripePaymentMethodId = null;

    #[ORM\Column(name: "date_ajout", type: "datetime")]
    private ?\DateTimeInterface $dateAjout = null;

    #[ORM\Column(name: "statut", type: "string", length: 20)]
    private string $statut = 'ACTIVE';

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "id_user", referencedColumnName: "id_user", nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function __construct()
    {
        $this->dateAjout = new \DateTime();
    }

    #[Assert\Callback]
    public function validateExpiration(ExecutionContextInterface $context): void
    {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');

        if ($this->expiryYear < $currentYear || 
           ($this->expiryYear === $currentYear && $this->expiryMonth < $currentMonth)) {
            
            $context->buildViolation("The expiration date must be in the future.")
                ->atPath('expiryMonth')
                ->addViolation();
        }
    }

    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getCardHolderName(): ?string { return $this->cardHolderName; }
    public function setCardHolderName(string $name): self {
        $this->cardHolderName = strtoupper($name);
        return $this;
    }

    public function getLast4Digits(): ?string { return $this->last4Digits; }
    public function setLast4Digits(string $digits): self {
        $this->last4Digits = $digits;
        return $this;
    }

    public function getExpiryMonth(): ?int { return $this->expiryMonth; }
    public function setExpiryMonth(int $month): self {
        $this->expiryMonth = $month;
        return $this;
    }

    public function getExpiryYear(): ?int { return $this->expiryYear; }
    public function setExpiryYear(int $year): self {
        $this->expiryYear = $year;
        return $this;
    }

    // AJOUT DES MÉTHODES MANQUANTES POUR STRIPE
    public function getStripeCustomerId(): ?string { return $this->stripeCustomerId; }
    public function setStripeCustomerId(?string $id): self {
        $this->stripeCustomerId = $id;
        return $this;
    }

    public function getStripePaymentMethodId(): ?string { return $this->stripePaymentMethodId; }
    public function setStripePaymentMethodId(?string $id): self {
        $this->stripePaymentMethodId = $id;
        return $this;
    }

    public function getDateAjout(): ?\DateTimeInterface { return $this->dateAjout; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self {
        $this->statut = $statut;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $user): self {
        $this->utilisateur = $user;
        return $this;
    }
}