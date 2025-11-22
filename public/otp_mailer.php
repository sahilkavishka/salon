<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__.'/../vendor/autoload.php';

function sendOTP($to, $otp){
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'salonora@gmail.com'; // your Gmail
        $mail->Password = 'pahamunegama'; // App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('salonora@gmail.com', 'Salonora');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Your Salonora OTP Code';
        $mail->Body = "<h3>Your OTP code is: <b>$otp</b></h3><p>Valid for 5 minutes.</p>";

        $mail->send();
    } catch (Exception $e){
        error_log("OTP Mail Error: ".$mail->ErrorInfo);
    }
}
