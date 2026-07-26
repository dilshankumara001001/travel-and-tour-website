<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require '../vendor/autoload.php';   
if (isset($_POST["send"])) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'dileepadilshan46@gmail.com'; 
        $mail->Password   = 'futvipxtqvfukpcw'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($_POST["email"], $_POST["name"]);
        $mail->addAddress('dileepadilshan46@gmail.com'); 
        $mail->addReplyTo($_POST["email"], $_POST["name"]);

        $mail->isHTML(true);
        $mail->Subject = $_POST["subject"];
        $mail->Body    = $_POST["message"];

        $mail->send();
        echo "<script>alert('Message sent successfully!'); document.location.href = 'index.php';</script>";
        
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}
?>