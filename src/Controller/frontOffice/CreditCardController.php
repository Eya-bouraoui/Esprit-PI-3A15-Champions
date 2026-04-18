<?php

namespace App\Controller\frontOffice;

use App\Entity\CreditCard;
use App\Entity\Utilisateur;
use App\Form\CreditCardType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\StripeService;

#[Route('/credit-card')]
class CreditCardController extends AbstractController
{
#[Route('/new', name: 'app_card_new', methods: ['POST'])]
public function new(Request $request, EntityManagerInterface $em, StripeService $stripeService): Response
{
    $card = new CreditCard();
    $user = $this->getUser() ?: $em->getRepository(Utilisateur::class)->find(1);
    
    $form = $this->createForm(CreditCardType::class, $card, ['is_edit' => false]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        try {
            // 1. On crée un VRAI client dans ton Dashboard Stripe via le service
            // Cela générera un ID unique comme "cus_ABC123"
            $stripeCustomer = $stripeService->createCustomer($user->getEmail());
            $card->setStripeCustomerId($stripeCustomer->id);

            // 2. On utilise 'pm_card_visa' pour la carte de test
            // Note : Pour avoir un pm_ unique, il faudra intégrer Stripe.js plus tard
            $card->setStripePaymentMethodId('pm_card_visa'); 
            
            $card->setUtilisateur($user);
            $card->setStatut('ACTIVE');
            
            $em->persist($card);
            $em->flush();

            $this->addFlash('success', 'Card added and linked to Stripe with Customer ID: ' . $stripeCustomer->id);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Stripe Error: ' . $e->getMessage());
        }
    } else {
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('danger', $error->getMessage());
        }
    }

    return $this->redirectToRoute('app_wallet_index');
}
    #[Route('/{id}/edit', name: 'app_card_edit', methods: ['POST'])]
    public function edit(CreditCard $card, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CreditCardType::class, $card, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Card updated successfully.');
        } else {
            // Extraction précise des erreurs de validation (ex: date d'expiration)
            $errors = $form->getErrors(true);
            
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    // Utilisation du tag 'danger' pour correspondre à alert-danger (rouge)
                    $this->addFlash('danger', $error->getMessage());
                }
            } else {
                $this->addFlash('danger', 'An error occurred while updating the card.');
            }
        }

        return $this->redirectToRoute('app_wallet_index'); 
    }

    #[Route('/{id}/delete', name: 'app_card_delete', methods: ['POST', 'GET'])]
    public function delete(CreditCard $card, EntityManagerInterface $em): Response
    {
        // Désactivation de la carte (Soft delete) pour le projet Champions
        $card->setStatut('DELETED');
        $em->flush();

        $this->addFlash('info', 'The card has been removed.');
        return $this->redirectToRoute('app_wallet_index');
    }
}