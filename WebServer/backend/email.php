<?php
require 'vendor/autoload.php';
use SendGrid\Mail\Mail;
require 'webserver_backend.php';
require 'rabbitMQLib.inc';

function sendEmail($to, $subject, $content) {
    $email = new Mail();
    $email->setFrom("490hatsit@gmail.com", "Stock Site");
    $email->setSubject($subject);
    $email->addTo($to);
    $email->addContent("text/plain", $content);

    $sendgrid = new \SendGrid('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');
    try {
        $response = $sendgrid->send($email);
        return $response->statusCode();
    } catch (Exception $e) {
        echo 'Caught exception: '. $e->getMessage();
    }
}
?>