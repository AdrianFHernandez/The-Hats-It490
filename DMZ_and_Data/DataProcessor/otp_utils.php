<?php
require __DIR__ . '/vendor/autoload.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function sendOTP($phoneNumber, $otpCode) {
    
    $accountSid = $_ENV['TWILIO_ACCOUNT_SID'];
    $authToken = $_ENV['TWILIO_AUTH_TOKEN'];
    $verifyServiceSid = $_ENV['TWILIO_VERIFY_SERVICE_SID'];

    $channel = 'sms'; 
	// Remove the return statement for prod, rn its to not burn through the credits
    // return buildResponse("SEND_OTP_CODE_RESPONSE", "SUCCESS", [
    //         "message" => "OTP sent successfully- $otpCode",
    //         "status" => true,
    //     ]);
    
    $twilio = new Client($accountSid, $authToken);

    try {
        
        $verification = $twilio->verify->v2->services($verifyServiceSid)
            ->verifications
            ->create(
                $phoneNumber,
                $channel,
                ['customCode' => $otpCode]
            );

        
        return buildResponse("SEND_OTP_CODE_RESPONSE", "SUCCESS", [
            "message" => "OTP sent successfully",
            "status" => $verification->status,
        ]);
    } catch (TwilioException $e) {
        
        return buildResponse("SEND_OTP_CODE_RESPONSE", "FAILED", [
            "error" => $e->getMessage(),
            "twilioCode" => $e->getCode()
        ]);
    } catch (Exception $e) {
        return buildResponse("SEND_OTP_CODE_RESPONSE", "FAILED", [
            "error" => "Unexpected error: " . $e->getMessage()
        ]);
    }
}

?>

