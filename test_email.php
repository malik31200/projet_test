<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// Récupérer le DSN depuis .env
$dsn = $_ENV['MAILER_DSN'] ?? 'smtp://mailhog:1025';
echo "MAILER_DSN: $dsn\n";

try {
    // Créer le transport et le mailer
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);
    
    echo "Transport créé avec succès\n";
    
    // Créer l'email de test
    $email = (new Email())
        ->from('noreply@actualsport.com')
        ->to('test@example.com')
        ->subject('Test Email depuis Actual Sport')
        ->html('<h1>Test réussi !</h1><p>L\'envoi d\'email fonctionne correctement.</p>');
    
    echo "Email créé\n";
    
    // Envoyer l'email
    $mailer->send($email);
    
    echo "✅ EMAIL ENVOYÉ AVEC SUCCÈS !\n";
    echo "Vérifie http://localhost:8025 pour voir l'email\n";
    
} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
