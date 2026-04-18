<?php

namespace App\Form;

use App\Entity\Trade;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;

class TradeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $assetChoices = $options['asset_choices'];
        $isEdit       = $options['is_edit'];

        $builder
            ->add('assetId', ChoiceType::class, [
                'label'       => 'Asset',
                'choices'     => $assetChoices,
                'placeholder' => '— Select an asset —',
                'required'    => true,
                'constraints' => [
                    new NotBlank(message: 'Please select an asset.'),
                ],
            ])
            ->add('tradeType', ChoiceType::class, [
                'label'   => 'Order Type',
                'choices' => [
                    'Buy'  => 'BUY',
                    'Sell' => 'SELL',
                ],
                'constraints' => [
                    new NotBlank(message: 'Order type is required.'),
                ],
            ])
            ->add('orderMode', ChoiceType::class, [
                'label'   => 'Order Mode',
                'choices' => [
                    'Market' => 'MARKET',
                    'Limit'  => 'LIMIT',
                ],
                'constraints' => [
                    new NotBlank(message: 'Order mode is required.'),
                ],
            ])
            ->add('price', NumberType::class, [
                'label'    => 'Limit Price (USD)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['placeholder' => 'e.g. 45000.50', 'min' => '0.01'],
                'constraints' => [
                    new Positive(message: 'Price must be a positive number.'),
                ],
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantity',
                'scale' => 8,
                'attr'  => ['placeholder' => 'e.g. 0.5', 'min' => '0.0001'],
                'constraints' => [
                    new NotBlank(message: 'Quantity is required.'),
                    
                    new Range(
                        min: 0.0001,
                        max: 1_000_000,
                        notInRangeMessage: 'Quantity must be between {{ min }} and {{ max }}.',
                    ),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label'   => 'Status',
                'choices' => $isEdit
                    ? [
                        'Pending'   => 'PENDING',
                        'Executed'  => 'EXECUTED',
                        'Cancelled' => 'CANCELLED',
                    ]
                    : [
                        'Pending'   => 'PENDING',
                        'Cancelled' => 'CANCELLED',
                    ],
                'constraints' => [
                    new NotBlank(message: 'Status is required.'),
                ],
            ])
        ;

        
        $builder->get('assetId')->addModelTransformer(new CallbackTransformer(
            fn($value): ?string => ($value === null || $value === 0) ? null : (string) $value,
            fn($value): int     => ($value === null || $value === '') ? 0 : (int) $value,
        ));

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form  = $event->getForm();
            $trade = $event->getData();

            if (!$trade instanceof Trade) {
                return;
            }

            $orderMode = $trade->getOrderMode();
            $price     = $trade->getPrice();

            
            if ($orderMode === 'LIMIT' && ($price === null || $price <= 0)) {
                $form->get('price')->addError(
                    new FormError('A limit price is required for Limit orders.')
                );
            }

           
            if ($orderMode === 'MARKET' && $price !== null) {
                $form->get('price')->addError(
                    new FormError('Market orders cannot have a limit price. Leave this field empty.')
                );
            }

       
            if ($trade->getAssetId() === 0) {
                $form->get('assetId')->addError(
                    new FormError('Please select a valid asset.')
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Trade::class,
            'asset_choices' => [],
            'is_edit'       => false,
        ]);
    }
}