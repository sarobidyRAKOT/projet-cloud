<?php
// src/Service/EmailService.php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\EmailToken;

class EmailService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendEmailConfirmation(string $userEmail, EmailToken $emailToken): void
    {
        $emailMessage = (new Email())
            ->from('no-reply@votresite.com')
            ->to($userEmail)
            ->subject('Confirmez votre adresse email')
            ->html('<p>Cliquez sur le lien suivant pour confirmer votre adresse email :</p>' .
                   '<p><a href="http://localhost:8000/api/validate-email/' . $emailToken->getToken() . '">Confirmer mon email</a></p>');

        $this->mailer->send($emailMessage);
    }
}
