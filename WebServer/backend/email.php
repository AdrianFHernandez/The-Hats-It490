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

    $sendgrid = new \SendGrid('process.env.SENDGRID_API_KEY');
    try {
        $response = $sendgrid->send($email);
        return $response->statusCode();
    } catch (Exception $e) {
        echo 'Caught exception: '. $e->getMessage();
    }
}
?>