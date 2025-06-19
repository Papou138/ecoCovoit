<?php

/**
 * Envoie un email.
 * @param string $to Destinataire
 * @param string $subject Sujet
 * @param string $message Corps du message
 * @param string $headers (optionnel) En-têtes supplémentaires
 * @return bool true si envoyé, false sinon
 */
function envoyer_email($to, $subject, $message, $headers = '') {
    // Ici, tu peux personnaliser l'envoi (ex: ajout d'un expéditeur par défaut)
    if (empty($headers)) {
        $headers = "From: noreply@ecocovoit.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    // Utilise la fonction mail native de PHP
    return mail($to, $subject, $message, $headers);
}
