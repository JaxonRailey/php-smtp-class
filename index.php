<?php

include 'smtp.class.php';

$smtp = new Smtp();

try {
    // if present, show debugging
    $smtp->debug();

    // set host, port and encryption
    $smtp->host('smtp.example.com', 587, 'tls');

    // set username, password and if authentication is required
    $smtp->auth('user@example.com', 'password', true);

    // set sender's email and name
    $smtp->from('user@example.com', 'Sender');

    // set reply's email and name
    $smtp->reply('user@example.com', 'Sender');

    // set recipient's email and name
    $smtp->to('user@example.com', 'Recipient');

    // set copy-recipient's email and name
    $smtp->cc('user@example.com', 'Recipient Copy');

    // set blind copy-recipient's email and name
    $smtp->bcc('user@example.com', 'Recipient Blind Copy');

    // set subject
    $smtp->subject('Awesome class');

    // set body in HTML format
    $smtp->html('<h1>This is an example</h1>');

    // set body in text format
    $smtp->text('You can use HTML or text format');

    // set attachment
    $smtp->attachment('attachment.pdf');

    // send email, if it returns true the email has been sent
    $smtp->send();
} catch (Exception  $e) {
    echo $e->getMessage(); die;
}