<?php
/**
 * Unified PHP Mail Submission Engine with Google reCAPTCHA verification
 * Receives sanitizable POST contact values from the Canvas public contact page
 * Supports both clean PHPMailer SMTP definitions and standard PHP mail() fallbacks
 */
$config = include '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Attempt to include PHPMailer via typical package structures
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback paths for manual directory uploads
    @include_once 'PHPMailer/src/Exception.php';
    @include_once 'PHPMailer/src/PHPMailer.php';
    @include_once 'PHPMailer/src/SMTP.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recaptcha_secret = '6Le30xwtAAAAADWwZQeCPgsPs-GBhj6j9atvSgQr';
    $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

    if (empty($recaptcha_response)) {
        $error_msg = urlencode("Please check the 'I'm not a robot' security box.");
        header("Location: contact.html?status=error&msg=$error_msg");
        exit;
    }

    // Call Verification Endpoint (with cURL fallback for hosting environments with allow_url_fopen disabled)
    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $verify_data = [
        'secret' => $recaptcha_secret,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $verify_success = false;

    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verify_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $response_keys = json_decode($result, true);
        $verify_success = !empty($response_keys["success"]);
    } else {
        // Fallback context helper if cURL is absent
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($verify_data)
            ]
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($verify_url, false, $context);

        $response_keys = json_decode($result, true);
        $verify_success = !empty($response_keys["success"]);
    }

    if (!$verify_success) {
        $error_msg = urlencode("Spam protection check failed. Please complete the reCAPTCHA again.");
        header("Location: contact.html?status=error&msg=$error_msg");
        exit;
    }

    // 2. Sanitization & Input Filters
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);

    // 3. Field Validation
    if (!$name || !$email || !$phone || !$subject || !$message) {
        $error_msg = urlencode("Please fill out all mandatory fields with a valid email and phone number.");
        header("Location: contact.html?status=error&msg=$error_msg");
        exit;
    }

    // 4. Email Body Generation
    $to_email = "contact@nvkmuslimjamaath.in"; // Aligned with the email listed on your page
    $email_subject = "[Jamaath Inquiry Desk] " . $subject . " (From: " . $name . ")";

    $html_message = "
    <html>
    <head>
        <title>NVK Muslim Jamaath - New Inquiry Logged</title>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; padding: 20px; }
            .container { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; max-width: 600px; margin: 0 auto; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .header { background-color: #065f46; color: #ffffff; padding: 24px; text-align: center; }
            .header h2 { margin: 0; font-size: 20px; font-weight: bold; }
            .header p { margin: 4px 0 0; font-size: 11px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; }
            .content { padding: 32px; }
            .section-title { font-size: 11px; text-transform: uppercase; color: #64748b; letter-spacing: 1px; font-weight: bold; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; margin-top: 0; margin-bottom: 16px; }
            .data-grid { display: table; width: 100%; margin-bottom: 24px; }
            .data-row { display: table-row; }
            .data-label { display: table-cell; font-weight: bold; width: 140px; padding: 8px 0; font-size: 13px; color: #475569; }
            .data-value { display: table-cell; padding: 8px 0; font-size: 13px; color: #0f172a; }
            .msg-card { background-color: #f1f5f9; border-left: 4px solid #10b981; padding: 16px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #1e293b; white-space: pre-wrap; }
            .footer { background-color: #f8fafc; text-align: center; padding: 16px; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Administrative Inquiry</h2>
                <p>NVK Muslim Jamaath Public Website</p>
            </div>
            <div class='content'>
                <h4 class='section-title'>Sender Specifications</h4>
                <div class='data-grid'>
                    <div class='data-row'>
                        <div class='data-label'>Sender Name:</div>
                        <div class='data-value'><strong>" . htmlspecialchars($name) . "</strong></div>
                    </div>
                    <div class='data-row'>
                        <div class='data-label'>Email:</div>
                        <div class='data-value'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></div>
                    </div>
                    <div class='data-row'>
                        <div class='data-label'>Phone:</div>
                        <div class='data-value'>" . htmlspecialchars($phone) . "</div>
                    </div>
                    <div class='data-row'>
                        <div class='data-label'>Subject Category:</div>
                        <div class='data-value'><span style='background-color:#f0fdf4; color:#166534; padding:2px 8px; border-radius:4px; font-weight:bold; font-size:11px;'>" . htmlspecialchars($subject) . "</span></div>
                    </div>
                </div>
                
                <h4 class='section-title'>Detailed Message Content</h4>
                <div class='msg-card'>" . nl2br(htmlspecialchars($message)) . "</div>
            </div>
            <div class='footer'>
                This inquiry was routed directly from the public web form. (reCAPTCHA Verified)
            </div>
        </div>
    </body>
    </html>
    ";

    // 5. Dispatch Routine Execution
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Instantiate secure PHPMailer instance
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contact@nvkmuslimjamaath.in';
            $mail->Password   = $config['dbPassword'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->isMail(); // Defaults back to standard PHP system configurations
            $mail->setFrom('no-reply@nvkmuslimjamaath.in', 'NVK Muslim Jamaath');
            $mail->addAddress($to_email);
            $mail->addReplyTo($email, $name);

            $mail->isHTML(true);
            $mail->Subject = $email_subject;
            $mail->Body = $html_message;

            $mail->send();
            header("Location: contact.html?status=success");
            exit;
        } catch (Exception $e) {
            $err = urlencode("Mailer failed to send: " . $mail->ErrorInfo);
            header("Location: contact.html?status=error&msg=$err");
            exit;
        }
    } else {
        // Fallback to native PHP mail() headers if PHPMailer is absent
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: NVK Muslim Jamaath <no-reply@nvkmuslimjamaath.in>" . "\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";

        if (@mail($to_email, $email_subject, $html_message, $headers)) {
            header("Location: contact.html?status=success");
            exit;
        } else {
            $err = urlencode("The native server mail configuration failed to dispatch this message.");
            header("Location: contact.html?status=error&msg=$err");
            exit;
        }
    }
} else {
    header("Location: contact.html");
    exit;
}