<?php

namespace App\Form;

use App\Entity\CreditCard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CreditCardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        if (!$isEdit) {
            $builder->add('fullNumber', TextType::class, [
                'mapped' => false,
                'label' => 'Card Number (16 digits)',
                'required' => false, // Désactive le 'required' HTML5
                'attr' => [
                    'placeholder' => '1234567812345678',
                    'class' => 'form-control-custom',
                    'maxlength' => 16
                ],
                'constraints' => [
                    new Assert\NotBlank(message: "The card number is required"),
                    new Assert\Length(
                        min: 16, 
                        max: 16, 
                        exactMessage: "The card number must contain exactly 16 digits"
                    ),
                    new Assert\Regex(
                        pattern: '/^\d+$/', 
                        message: "Only digits are allowed"
                    )
                ]
            ]);
        }

        $builder->add('cardHolderName', TextType::class, [
            'label' => 'Card Holder Name',
            'required' => false,
            'disabled' => $isEdit,
            'attr' => ['class' => 'form-control-custom']
        ]);

        $builder->add('expiryMonth', IntegerType::class, [
            'label' => 'Month (MM)',
            'required' => false,
            'attr' => ['class' => 'form-control-custom', 'placeholder' => 'MM']
        ])
        ->add('expiryYear', IntegerType::class, [
            'label' => 'Year (YYYY)',
            'required' => false,
            'attr' => ['class' => 'form-control-custom', 'placeholder' => 'YYYY']
        ]);

        // Événement pour transformer le numéro complet en "Last 4 digits"
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($isEdit) {
            $card = $event->getData();
            $form = $event->getForm();

            if (!$isEdit && $form->has('fullNumber')) {
                $fullNumber = $form->get('fullNumber')->getData();
                if ($fullNumber && strlen($fullNumber) === 16) {
                    $card->setLast4Digits(substr($fullNumber, -4));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreditCard::class,
            'is_edit' => false,
            // Désactive globalement la validation HTML5 sur ce formulaire
            'attr' => ['novalidate' => 'novalidate']
        ]);
    }
}