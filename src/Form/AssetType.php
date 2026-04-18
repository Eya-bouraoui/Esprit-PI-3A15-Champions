<?php

namespace App\Form;

use App\Entity\Asset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;

class AssetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('symbol', TextType::class, [
                'label' => 'Symbol',
                'attr'  => [
                    'placeholder' => 'e.g. BTC, ETH, XRP',
                    'maxlength'   => 10,
                ],
                'constraints' => [
                    new NotBlank(message: 'Symbol is required.'),
                    new Length(
                        min: 2, max: 10,
                        minMessage: 'Symbol must be at least {{ limit }} characters.',
                        maxMessage: 'Symbol cannot exceed {{ limit }} characters.'
                    ),
                    new Regex(
                        pattern: '/^[A-Za-z0-9]+$/',
                        message: 'Symbol must contain only letters and numbers.'
                    ),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'Full Name',
                'attr'  => [
                    'placeholder' => 'e.g. Bitcoin',
                    'maxlength'   => 100,
                ],
                'constraints' => [
                    new NotBlank(message: 'Name is required.'),
                    new Length(
                        min: 2, max: 100,
                        minMessage: 'Name must be at least {{ limit }} characters.',
                        maxMessage: 'Name cannot exceed {{ limit }} characters.'
                    ),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label'       => 'Type',
                'placeholder' => '-- Select a type --',
                'required'    => true,
                'choices'     => [
                    'Cryptocurrency' => Asset::TYPE_CRYPTO,
                    'Stock'          => Asset::TYPE_STOCK,
                    'Forex'          => Asset::TYPE_FOREX,
                ],
                'constraints' => [
                    new NotBlank(message: 'Please select an asset type.'),
                    new Choice(
                        choices: [Asset::TYPE_CRYPTO, Asset::TYPE_STOCK, Asset::TYPE_FOREX],
                        message: 'Invalid asset type selected.'
                    ),
                ],
            ])
            ->add('market', ChoiceType::class, [
                'label'       => 'Market',
                'placeholder' => '-- Select a market --',
                'required'    => true,
                'choices'     => [
                    'Binance'  => Asset::MARKET_BINANCE,
                    'Crypto' => Asset::MARKET_CRYPTO,
                    'NYSE'   => Asset::MARKET_NYSE,
                    'NASDAQ' => Asset::MARKET_NASDAQ,
                ],
                'constraints' => [
                    new NotBlank(message: 'Please select a market.'),
                    new Choice(
                        choices: [ Asset::MARKET_BINANCE, Asset::MARKET_CRYPTO, Asset::MARKET_NYSE, Asset::MARKET_NASDAQ],
                        message: 'Invalid market selected.'
                    ),
                ],
            ])
            ->add('currentPrice', NumberType::class, [
                'label' => 'Current Price (USD)',
                'scale' => 8,
                'attr'  => ['placeholder' => 'e.g. 45000.00'],
                'constraints' => [
                    new NotBlank(message: 'Price is required.'),
                    new Positive(message: 'Price must be greater than zero.'),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label'    => 'Status',
                'required' => true,
                'choices'  => [
                    'Active'   => Asset::STATUS_ACTIVE,
                    'Pending'  => Asset::STATUS_PENDING,
                    'Disabled' => Asset::STATUS_INACTIVE,
                ],
                'constraints' => [
                    new NotBlank(message: 'Please select a status.'),
                    new Choice(
                        choices: [Asset::STATUS_ACTIVE, Asset::STATUS_PENDING, Asset::STATUS_INACTIVE],
                        message: 'Invalid status selected.'
                    ),
                ],
            ]);

       
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form  = $event->getForm();
            $asset = $event->getData();

            if (!$asset instanceof Asset) {
                return;
            }

            if ($asset->getType() === Asset::TYPE_CRYPTO 
    && !in_array($asset->getMarket(), [Asset::MARKET_CRYPTO, Asset::MARKET_BINANCE])) {
    $form->get('market')->addError(
        new FormError('A cryptocurrency asset must be on the Crypto or Binance market.')
    );
}
            if (in_array($asset->getType(), [Asset::TYPE_STOCK, Asset::TYPE_FOREX]) && $asset->getMarket() === Asset::MARKET_CRYPTO) {
                $form->get('market')->addError(
                    new FormError('Stock and Forex assets cannot be on the Crypto market.')
                );
            }

           
            $price = (float) $asset->getCurrentPrice();
            if ($asset->getType() === Asset::TYPE_CRYPTO && $price < 0.00000001) {
                $form->get('currentPrice')->addError(
                    new FormError('Crypto price must be at least 0.00000001.')
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Asset::class]);
    }
}