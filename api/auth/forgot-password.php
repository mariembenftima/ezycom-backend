<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../../vendor/autoload.php';

$body  = getBody();
$email = trim($body['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, 'Adresse email invalide.');
}

$db = getDB();

$stmt = $db->prepare("SELECT id, prenom, nom FROM commercant WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    respond(200, 'Si cet email existe, un code a été envoyé.');
}

$fullName   = trim($user['prenom'] . ' ' . $user['nom']);
$code       = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
$db->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)")
   ->execute([$email, password_hash($code, PASSWORD_DEFAULT), $expires_at]);

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mariembenftima@gmail.com';
    $mail->Password   = 'nqfjwslmbuqvavfa';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('mariembenftima@gmail.com', 'Ezycom');
    $mail->addAddress($email, $fullName);
    $mail->isHTML(true);
    $mail->Subject = 'Code de réinitialisation Ezycom';
    $mail->Body    = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f8fafc;border-radius:16px'>
      <div style='text-align:center;margin-bottom:24px'>
        <span style='font-size:28px;font-weight:800;color:#1A2940'>Ezy</span>
        <span style='font-size:28px;font-weight:800;color:#29B6D8'>com</span>
      </div>
      <h2 style='color:#1A2940;text-align:center'>Réinitialisation du mot de passe</h2>
      <p style='color:#8A9AAA;text-align:center'>Bonjour <strong>{$fullName}</strong>,<br>
      Votre code expire dans <strong>15 minutes</strong>.</p>
      <div style='background:#fff;border-radius:12px;padding:24px;text-align:center;margin:24px 0;border:2px solid #29B6D8'>
        <span style='font-size:42px;font-weight:800;color:#29B6D8;letter-spacing:12px'>{$code}</span>
      </div>
      <p style='color:#8A9AAA;text-align:center;font-size:13px'>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
    </div>";

    $mail->send();
} catch (Exception $e) {
    error_log('PHPMailer: ' . $mail->ErrorInfo);
    respond(500, 'Erreur envoi email: ' . $mail->ErrorInfo);
}

respond(200, 'Code envoyé avec succès.');
