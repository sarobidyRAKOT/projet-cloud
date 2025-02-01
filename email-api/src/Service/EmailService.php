<?php

namespace App\Service;

use App\Entity\EmailToken;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendEmailConfirmation(string $userEmail, EmailToken $emailToken, int $codePin)
    {
        $validationLink = 'http://localhost:8000/api/validate-email/' . $emailToken->getToken();

        $emailMessage = (new Email())
            ->from('rakotomalalamiharimiakajatotok@gmail')
            ->to($userEmail)
            ->subject('Confirmez votre compte')
            ->html("
                <p>Votre code PIN est : <strong>{$codePin}</strong></p>
                <p>Cliquez sur le lien suivant pour valider votre compte :</p>
                <p><a href='{$validationLink}'>Valider mon compte</a></p>
            ");

        $this->mailer->send($emailMessage);
    }
}
