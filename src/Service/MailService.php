<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendVerificationCode(string $toEmail, int $code): void
    {
        $email = (new Email())
            ->from('eya.bouraoui2005@gmail.com')
            ->to($toEmail)
            ->subject('Security Verification Code - ChampionsPi')
            ->html("
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h1 style='color: #1a56db;'>Wallet Verification</h1>
                    <p>Hello,</p>
                    <p>To complete your wallet recharge, please use the following security code:</p>
                    <div style='background-color: #f3f4f6; padding: 20px; text-align: center; border-radius: 8px;'>
                        <h2 style='color: #047857; letter-spacing: 5px; font-size: 32px; margin: 0;'>$code</h2>
                    </div>
                    <p style='margin-top: 20px;'>This code is temporary and for your security. If you did not request this, please ignore this email.</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #666;'>The ChampionsPi FinTech Team</p>
                </div>
            ");

        $this->mailer->send($email);
    }
}