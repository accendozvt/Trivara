<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function s($v) {
    return htmlspecialchars(strip_tags(trim($v ?? '')), ENT_QUOTES, 'UTF-8');
}

// Honeypot — bots fill the hidden "website" field, humans don't
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

// Collect fields (handles different field names across forms)
$name        = s($_POST['name'] ?? $_POST['full_name'] ?? '');
$email       = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$phone       = s($_POST['phone'] ?? '');
$role        = s($_POST['role'] ?? $_POST['roleInterest'] ?? '');
$stamp       = s($_POST['stamp_type'] ?? $_POST['stampType'] ?? '');
$nationality = s($_POST['nationality'] ?? '');
$message     = s($_POST['message'] ?? '');
$source      = s($_POST['form_source'] ?? 'Website Contact');

// Basic validation
if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
    exit;
}

// Extra fields for staff request form
$org_name       = s($_POST['org_name'] ?? '');
$sector         = s($_POST['sector'] ?? '');
$staff_count    = s($_POST['staff_count'] ?? '');
$placement_type = s($_POST['placement_type'] ?? '');
$start_date     = s($_POST['start_date'] ?? '');

// Build HTML email body
$rows = array_filter([
    'Source'         => $source,
    'Organisation'   => $org_name,
    'Name'           => $name,
    'Email'          => "<a href='mailto:{$email}'>{$email}</a>",
    'Phone'          => "<a href='tel:{$phone}'>{$phone}</a>",
    'Role'           => $role,
    'Sector'         => $sector,
    'Staff Count'    => $staff_count,
    'Placement Type' => $placement_type,
    'Start Date'     => $start_date,
    'Stamp Type'     => $stamp,
    'Nationality'    => $nationality,
    'Message'        => nl2br($message),
]);

$rows_html = '';
foreach ($rows as $label => $value) {
    $rows_html .= "<tr>
        <td style='padding:10px 14px;border:1px solid #e2e8f0;background:#f5f7fa;font-weight:600;color:#1B2F5E;width:130px;font-size:13px;'>{$label}</td>
        <td style='padding:10px 14px;border:1px solid #e2e8f0;color:#4A5568;font-size:13px;'>{$value}</td>
    </tr>";
}

$body = "
<div style='font-family:Inter,Arial,sans-serif;max-width:600px;margin:0 auto;'>
  <div style='background:linear-gradient(135deg,#091324 0%,#122a52 55%,#0d2140 100%);padding:24px 28px;border-radius:10px 10px 0 0;'>
    <h2 style='color:white;margin:0;font-size:20px;'>New Enquiry — Trivara Website</h2>
    <p style='color:rgba(255,255,255,0.6);margin:4px 0 0;font-size:13px;'>{$source}</p>
  </div>
  <div style='border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;overflow:hidden;'>
    <table style='border-collapse:collapse;width:100%;'>
      {$rows_html}
    </table>
  </div>
  <p style='color:#9CA3AF;font-size:11px;margin-top:16px;'>Sent from trivara.ie contact form</p>
</div>";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO);
    $mail->addReplyTo($email, $name);

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $subject = $role ? "New Enquiry: {$role} - {$name}" : "New Enquiry from {$name}";
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nRole: {$role}\nStamp: {$stamp}\nNationality: {$nationality}\nMessage: {$message}";

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $mail->ErrorInfo]);
}
