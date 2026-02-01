<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $frontendUrl = 'http://localhost:4200'
    ) {
    }

    public function sendPasswordResetEmail(User $user, string $token): void
    {
        $resetUrl = $this->frontendUrl . '/reset-password/' . $token;

        $email = (new Email())
            ->from('noreply@dockerapp.local')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->html($this->getPasswordResetTemplate($user, $resetUrl));

        $this->mailer->send($email);
    }

    public function sendEmailVerification(User $user, string $token): void
    {
        $verifyUrl = $this->frontendUrl . '/verify-email?token=' . $token;

        $email = (new Email())
            ->from('noreply@dockerapp.local')
            ->to($user->getEmail())
            ->subject('Vérification de votre adresse email')
            ->html($this->getEmailVerificationTemplate($user, $verifyUrl));

        $this->mailer->send($email);
    }

    public function sendAccountSuspendedNotification(User $user, ?string $reason = null, ?\DateTimeInterface $until = null): void
    {
        $email = (new Email())
            ->from('noreply@dockerapp.local')
            ->to($user->getEmail())
            ->subject('Votre compte a été suspendu')
            ->html($this->getAccountSuspendedTemplate($user, $reason, $until));

        $this->mailer->send($email);
    }

    public function sendAccountDeleteApprovedNotification(User $user): void
    {
        $email = (new Email())
            ->from('noreply@dockerapp.local')
            ->to($user->getEmail())
            ->subject('Votre demande de suppression de compte a été approuvée')
            ->html($this->getAccountDeleteApprovedTemplate($user));

        $this->mailer->send($email);
    }

    private function getPasswordResetTemplate(User $user, string $resetUrl): string
    {
        $name = $user->getNomComplet() ?? $user->getEmail();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Réinitialisation de mot de passe</h1>
        </div>
        <div class="content">
            <p>Bonjour {$name},</p>
            <p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :</p>
            <a href="{$resetUrl}" class="button">Réinitialiser mon mot de passe</a>
            <p>Ce lien est valide pendant <strong>1 heure</strong>.</p>
            <p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.</p>
            <div class="footer">
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getEmailVerificationTemplate(User $user, string $verifyUrl): string
    {
        $name = $user->getNomComplet() ?? $user->getEmail();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Vérification de votre email</h1>
        </div>
        <div class="content">
            <p>Bonjour {$name},</p>
            <p>Merci de votre inscription ! Veuillez cliquer sur le bouton ci-dessous pour vérifier votre adresse email :</p>
            <a href="{$verifyUrl}" class="button">Vérifier mon email</a>
            <p>Ce lien est valide pendant <strong>24 heures</strong>.</p>
            <p>Si vous n'avez pas créé de compte, vous pouvez ignorer cet email.</p>
            <div class="footer">
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getAccountSuspendedTemplate(User $user, ?string $reason, ?\DateTimeInterface $until): string
    {
        $name = $user->getNomComplet() ?? $user->getEmail();
        $reasonText = $reason ? "<p><strong>Raison :</strong> {$reason}</p>" : '';
        $untilText = $until ? "<p><strong>Jusqu'au :</strong> " . $until->format('d/m/Y H:i') . "</p>" : '<p>Suspension permanente.</p>';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .footer { margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Compte suspendu</h1>
        </div>
        <div class="content">
            <p>Bonjour {$name},</p>
            <p>Nous vous informons que votre compte a été suspendu.</p>
            {$reasonText}
            {$untilText}
            <p>Si vous pensez qu'il s'agit d'une erreur, veuillez contacter le support.</p>
            <div class="footer">
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getAccountDeleteApprovedTemplate(User $user): string
    {
        $name = $user->getNomComplet() ?? $user->getEmail();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .footer { margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Compte supprimé</h1>
        </div>
        <div class="content">
            <p>Bonjour {$name},</p>
            <p>Nous vous confirmons que votre demande de suppression de compte a été approuvée.</p>
            <p>Votre compte et vos données ont été supprimés de notre système.</p>
            <p>Nous vous remercions d'avoir utilisé nos services.</p>
            <div class="footer">
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
