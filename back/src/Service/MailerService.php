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

    private function getBaseStyles(): string
    {
        return <<<CSS
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
            line-height: 1.7;
            color: #1a1a2e;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        .header {
            padding: 48px 40px 32px;
            text-align: center;
        }
        .icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .content {
            padding: 0 40px 48px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 16px;
        }
        .message {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 32px;
            line-height: 1.8;
        }
        .button-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .button {
            display: inline-block;
            padding: 16px 48px;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.3);
        }
        .info-box {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin: 24px 0;
            border-left: 4px solid;
        }
        .info-box p {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }
        .info-box strong {
            color: #1a1a2e;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 32px 0;
        }
        .footer {
            text-align: center;
            padding: 32px 40px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
        }
        .footer .brand {
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
        }
        @media (max-width: 600px) {
            body { padding: 20px 12px; }
            .header, .content { padding-left: 24px; padding-right: 24px; }
            .header h1 { font-size: 24px; }
            .button { padding: 14px 32px; }
        }
CSS;
    }

    private function getPasswordResetTemplate(User $user, string $resetUrl): string
    {
        $name = $user->getNomComplet() ?? $user->getEmail();
        $styles = $this->getBaseStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="icon-wrapper" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    🔐
                </div>
                <h1 style="color: #1a1a2e;">Réinitialisation de mot de passe</h1>
            </div>
            <div class="content">
                <p class="greeting">Bonjour {$name},</p>
                <p class="message">
                    Vous avez demandé la réinitialisation de votre mot de passe.
                    Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe sécurisé.
                </p>

                <div class="button-wrapper">
                    <a href="{$resetUrl}" class="button" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        Réinitialiser mon mot de passe
                    </a>
                </div>

                <div class="info-box" style="border-color: #f5576c;">
                    <p>⏱️ Ce lien expire dans <strong>1 heure</strong></p>
                </div>

                <div class="divider"></div>

                <p class="message" style="font-size: 14px; margin-bottom: 0;">
                    Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.
                    Votre mot de passe restera inchangé.
                </p>
            </div>
            <div class="footer">
                <p class="brand">DockerApp</p>
                <p>Cet email a été envoyé automatiquement</p>
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
        $styles = $this->getBaseStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de votre email</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="icon-wrapper" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    ✉️
                </div>
                <h1 style="color: #1a1a2e;">Bienvenue parmi nous !</h1>
            </div>
            <div class="content">
                <p class="greeting">Bonjour {$name},</p>
                <p class="message">
                    Merci de vous être inscrit ! Pour activer votre compte et accéder à toutes les fonctionnalités,
                    veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous.
                </p>

                <div class="button-wrapper">
                    <a href="{$verifyUrl}" class="button" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        Vérifier mon adresse email
                    </a>
                </div>

                <div class="info-box" style="border-color: #667eea;">
                    <p>⏱️ Ce lien expire dans <strong>24 heures</strong></p>
                </div>

                <div class="divider"></div>

                <p class="message" style="font-size: 14px; margin-bottom: 0;">
                    Si vous n'avez pas créé de compte sur notre plateforme, vous pouvez ignorer cet email en toute sécurité.
                </p>
            </div>
            <div class="footer">
                <p class="brand">DockerApp</p>
                <p>Cet email a été envoyé automatiquement</p>
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
        $styles = $this->getBaseStyles();

        $reasonHtml = $reason ? "
            <div class=\"info-box\" style=\"border-color: #ef4444; background: #fef2f2;\">
                <p><strong>📋 Raison :</strong> {$reason}</p>
            </div>
        " : '';

        $untilHtml = $until
            ? "<p style=\"font-size: 14px; color: #64748b;\">📅 <strong>Jusqu'au :</strong> " . $until->format('d/m/Y à H:i') . "</p>"
            : "<p style=\"font-size: 14px; color: #ef4444;\">⚠️ Cette suspension est permanente.</p>";

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte suspendu</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="icon-wrapper" style="background: linear-gradient(135deg, #fca5a5 0%, #ef4444 100%);">
                    ⚠️
                </div>
                <h1 style="color: #1a1a2e;">Compte suspendu</h1>
            </div>
            <div class="content">
                <p class="greeting">Bonjour {$name},</p>
                <p class="message">
                    Nous vous informons que votre compte a été temporairement suspendu.
                    Pendant cette période, vous ne pourrez pas accéder à votre espace personnel.
                </p>

                {$reasonHtml}
                {$untilHtml}

                <div class="divider"></div>

                <div class="info-box" style="border-color: #3b82f6; background: #eff6ff;">
                    <p>💬 Si vous pensez qu'il s'agit d'une erreur ou si vous souhaitez contester cette décision,
                    n'hésitez pas à contacter notre équipe support.</p>
                </div>
            </div>
            <div class="footer">
                <p class="brand">DockerApp</p>
                <p>Cet email a été envoyé automatiquement</p>
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
        $styles = $this->getBaseStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte supprimé</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="icon-wrapper" style="background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);">
                    👋
                </div>
                <h1 style="color: #1a1a2e;">Au revoir !</h1>
            </div>
            <div class="content">
                <p class="greeting">Bonjour {$name},</p>
                <p class="message">
                    Nous vous confirmons que votre demande de suppression de compte a été traitée avec succès.
                </p>

                <div class="info-box" style="border-color: #64748b;">
                    <p>✅ Votre compte et toutes vos données personnelles ont été définitivement supprimés de notre système,
                    conformément à notre politique de confidentialité.</p>
                </div>

                <div class="divider"></div>

                <p class="message">
                    Nous vous remercions d'avoir utilisé nos services. Si vous changez d'avis à l'avenir,
                    vous serez toujours le bienvenu pour créer un nouveau compte.
                </p>

                <p class="message" style="font-size: 14px; color: #94a3b8; margin-top: 24px;">
                    Nous espérons avoir été à la hauteur de vos attentes. N'hésitez pas à nous faire part de vos retours.
                </p>
            </div>
            <div class="footer">
                <p class="brand">DockerApp</p>
                <p>Cet email a été envoyé automatiquement</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
