<?php


namespace APP\core;

use APP\core\base\View;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


/**
 * Класс для отправки писем.
 * Class Mail
 * @package APP\core\base
 */
class PHPM
{

    /**
     * @param $view
     * @param $subject
     * @param $data
     * @param $mailConfig
     * @param string $layout
     */
    public static function sendMail($view,$subject, $data, $recipient, $layout = 'MAIL')
    {
        $vObj = new View(['controller' => 'Mail'], $layout, $view);


        $mailHtml = $vObj->render($data,true);



        $mail = new PHPMailer(true);

        try {

            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->SMTPAuth = true;


            //Server settings
            $mail->SMTPDebug = 0;
//            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output

            $mail->Host       =  MAILHOST;                    // Set the SMTP server to send through
         //   $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = MAILUSERNAME;                     // SMTP username
            $mail->Password   = MAILPASSWORD;                               // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
            $mail->Port       = 465;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

            //Recipients
            $mail->setFrom(CONFIG['BASEMAIL']['email'], CONFIG['BASEMAIL']['name']);
            $mail->addAddress($recipient, 'Joe User');     // Add a recipient
//            $mail->addAddress('ellen@example.com');               // Name is optional
            $mail->addReplyTo(CONFIG['BASEMAIL']['email'], CONFIG['BASEMAIL']['name']);
//            $mail->addCC('cc@example.com');
//            $mail->addBCC('bcc@example.com');

            // Attachments
//            $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
//            $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $mailHtml;
            $mail->AltBody = 'Пожалуйста используйте для чтения письма клиент с HTML';

            $mail->send();
        //    echo 'Письмо отправлено';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            exit();
        }




    }
}