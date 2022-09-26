<?php

    Class Smtp {

        protected $host;
        protected $port;
        protected $secure;
        protected $auth;
        protected $user;
        protected $pass;
        protected $to  = [];
        protected $cc  = [];
        protected $bcc = [];
        protected $from;
        protected $reply;
        protected $text;
        protected $html;
        protected $subject;
        protected $connection;
        protected $attachments = [];
        protected $mode     = 'text';
        protected $success  = true;
        protected $debug    = false;
        protected $charset  = 'UTF-8';
        protected $newline  = "\r\n";
        protected $encoding = '7bit';


        /**
          * Destruct method
          *
          * @return void
          */

        public function __destruct() {

            if ($this->connection) {
                $this->request('QUIT' . $this->newline);
                $this->response();
                fclose($this->connection);
            }
        }


        /**
          * Set debug param
          *
          * @return bool
          */

        public function debug(): bool {

            $this->debug = true;

            return true;
        }


        /**
          * Set hostname and port values
          *
          * @param string $host
          * @param int $port (optional)
          * @param string $secure (optional)
          *
          * @return bool
          */

        public function host(string $host, int $port = 25, string $secure = null): bool {

            $this->host   = $host;
            $this->port   = $port;
            $this->secure = $secure;

            return true;
        }


        /**
          * Set username and password values
          *
          * @param string $user
          * @param string $pass
          * @param bool $auth (optional)
          *
          * @return bool
          */

        public function auth(string $user, string $pass, bool $auth = true): bool {

            $this->user = $user;
            $this->pass = $pass;
            $this->auth = $auth;

            return true;
        }


        /**
          * Connect to server
          *
          * @return bool
          */

        public function connect(): bool {

            if (!$this->host && !$this->port && !$this->from) {
                throw new Exception('Invalid param');
            }

            if ($this->secure === 'ssl') {
                $this->host = 'ssl://' . $this->host;
            }

            $this->connection = fsockopen($this->host, $this->port, $errno, $errstr, 15);

            if ($this->code() !== 220) {
                throw new Exception('SMTP error');
            }

            $this->request(($this->auth ? 'EHLO' : 'HELO') . ' ' . $this->host . $this->newline);
            $this->response();

            if ($this->secure === 'tls') {
                $this->request('STARTTLS' . $this->newline, 220);
                stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->request(($this->auth ? 'EHLO' : 'HELO') . ' ' . $this->host . $this->newline, 250);
            }

            if ($this->auth) {
                $this->request('AUTH LOGIN' . $this->newline, 334);
                $this->request(base64_encode($this->user) . $this->newline, 334);
                $this->request(base64_encode($this->pass) . $this->newline, 235);
            }

            return $this->success;
        }


        /**
          * Set email and name of sender
          *
          * @param string $email
          * @param string $name (optional)
          *
          * @return bool
          */

        public function from(string $email, string $name = null): bool {

            $this->from = [
                'email' => $email,
                'name'  => $name
            ];

            return true;
        }


        /**
          * Set email and name of reply
          *
          * @param string $email
          * @param string $name (optional)
          *
          * @return bool
          */

        public function reply(string $email, string $name = null): bool {

            $this->reply = [
                'email' => $email,
                'name'  => $name
            ];

            return true;
        }


        /**
          * Set email and name of recipient
          *
          * @param string $email
          * @param string $name (optional)
          *
          * @return bool
          */

        public function to(string $email, string $name = null): bool {

            $this->to[] = [
                'email' => $email,
                'name'  => $name
            ];

            return true;
        }


        /**
          * Set email and name of cc recipient
          *
          * @param string $email
          * @param string $name (optional)
          *
          * @return bool
          */

        public function cc(string $email, string $name = null): bool {

            $this->cc[] = [
                'email' => $email,
                'name'  => $name
            ];

            return true;
        }


        /**
          * Set email and name of bcc recipient
          *
          * @param string $email
          * @param string $name (optional)
          *
          * @return bool
          */

        public function bcc(string $email, string $name = null): bool {

            $this->bcc[] = [
                'email' => $email,
                'name'  => $name,
            ];

            return true;
        }


        /**
          * Set subject
          *
          * @param string $subject
          *
          * @return bool
          */

        public function subject(string $subject): bool {

            $this->subject = $subject;

            return true;
        }


        /**
          * Set HTML content
          *
          * @param string $html
          *
          * @return bool
          */

        public function html(string $html): bool {

            $this->mode = 'html';
            $this->html = $html;

            return true;
        }


        /**
          * Set text content
          *
          * @param string $text
          *
          * @return bool
          */

        public function text(string $text): bool {

            $this->mode = 'text';
            $this->text = $text;

            return true;
        }


        /**
          * Set attachment
          *
          * @param string $file
          *
          * @return bool
          */

        public function attachment(string $file): bool {

            if (!is_file($file)) {
                throw new Exception('File ' . $file . ' not exists');
            }

            $this->attachments[] = $file;

            return true;
        }


        /**
          * Send email
          *
          * @return bool
          */

        public function send(): bool {

            if (!$this->connect() || !$this->delivery()) {
                throw new Exception('eMail not sent');
            }

            $this->init();

            return true;
        }

        /* PROTECTED METHODS */

        protected function headers(): string {

            $boundary = md5(uniqid(time()));

            $headers[] = 'From: ' . $this->format($this->from);
            $headers[] = 'Reply-To: ' . $this->format($this->reply ?? $this->from);
            $headers[] = 'Subject: ' . $this->subject;
            $headers[] = 'Date: ' . date('r');

            if (!empty($this->to)) {

                $string = '';

                foreach ($this->to as $r) {
                    $string .= $this->format($r) . ', ';
                }

                $string = substr($string, 0, -2);
                $headers[] = 'To: ' . $string;
            }

            if (!empty($this->cc)) {

                $string = '';

                foreach ($this->cc as $r) {
                    $string .= $this->format($r) . ', ';
                }

                $string = substr($string, 0, -2);
                $headers[] = 'CC: ' . $string;
            }

            if (empty($this->attachments)) {
                if ($this->mode == 'text') {
                    $headers[] = 'Content-Type: text/plain; charset="' . $this->charset . '"';
                    $headers[] = 'Content-Transfer-Encoding: ' . $this->encoding;
                    $headers[] = '';
                    $headers[] = $this->text;
                } else {
                    $headers[] = 'MIME-Version: 1.0';
                    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                    $headers[] = '';
                    $headers[] = 'This is a multi-part message in MIME format.';
                    $headers[] = '--' . $boundary;

                    $headers[] = 'Content-Type: text/plain; charset="' . $this->charset . '"';
                    $headers[] = 'Content-Transfer-Encoding: ' . $this->encoding;
                    $headers[] = '';
                    $headers[] = $this->text;
                    $headers[] = '--' . $boundary;

                    $headers[] = 'Content-Type: text/html; charset="' . $this->charset . '"';
                    $headers[] = 'Content-Transfer-Encoding: ' . $this->encoding;
                    $headers[] = '';
                    $headers[] = $this->html;
                    $headers[] = '--' . $boundary . '--';
                }
            } else {
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
                $headers[] = '';
                $headers[] = 'This is a multi-part message in MIME format.';
                $headers[] = '--' . $boundary;

                if ($this->mode == 'text') {
                    $headers[] = 'Content-Type: text/plain; charset="' . $this->charset . '"';
                    $headers[] = 'Content-Transfer-Encoding: ' . $this->encoding;
                    $headers[] = '';
                    $headers[] = $this->text;
                    $headers[] = '--' . $boundary;
                }

                if ($this->mode == 'html') {
                    $headers[] = 'Content-Type: text/html; charset="' . $this->charset . '"';
                    $headers[] = 'Content-Transfer-Encoding: ' . $this->encoding;
                    $headers[] = '';
                    $headers[] = $this->html;
                    $headers[] = '--' . $boundary;
                }

                foreach ($this->attachments as $attachment) {
                    $contents = file_get_contents($attachment);
                    if ($contents) {
                        $contents  = chunk_split(base64_encode($contents));
                        $headers[] = 'Content-Type: application/octet-stream; name="' . basename($attachment) . '"'; // use different content types here
                        $headers[] = 'Content-Transfer-Encoding: base64';
                        $headers[] = 'Content-Disposition: attachment';
                        $headers[] = '';
                        $headers[] = $contents;
                        $headers[] = '--' . $boundary;
                    }
                }

                $headers[sizeof($headers) - 1] .= '--';
            }

            $headers[] = '.';

            $email = '';

            foreach ($headers as $header) {
                $email .= $header . $this->newline;
            }

            return $email;
        }

        protected function delivery(): bool {

            $this->request('MAIL FROM: <' . $this->from['email'] . '>' . $this->newline);
            $this->response();
            $recipients = array_merge($this->to, $this->cc, $this->bcc);

            foreach ($recipients as $r) {
                $this->request('RCPT TO: <' . $r['email'] . '>' . $this->newline);
                $this->response();
            }

            $this->request('DATA' . $this->newline);
            $this->response();
            $this->request($this->headers(), 250);

            return $this->success;
        }

        protected function request($string, $code = null): void {

            if ($this->debug) {
                echo '<code><strong>' . $string . '</strong></code><br />';
            }

            fputs($this->connection, $string);

            if ($code) {
                if ($code != $this->code()) {
                    throw new Exception('SMTP error');
                }
            }
        }

        protected function response(): string {

            $response = '';
            if ($this->connection) {
                while ($string = fgets($this->connection, 4096)) {
                    $response .= $string;
                    if (substr($string, 3, 1) === ' ') {
                        break;
                    }
                }

                if ($this->debug) {
                    echo '<code>' . $response . '</code><br />';
                }
            }

            return $response;
        }

        protected function code(): int {

            return (int)substr($this->response(), 0, 3);
        }

        protected function format($recipient): string {

            if ($recipient['name']) {
                return $recipient['name'] . ' <' . $recipient['email'] . '>';
            } else {
                return '<' . $recipient['email'] . '>';
            }
        }

        protected function init(): void {

            $this->to  = [];
            $this->cc  = [];
            $this->bcc = [];
            $this->from;
            $this->reply;
            $this->text;
            $this->html;
            $this->subject;
            $this->connection;
            $this->attachments = [];
            $this->mode        = 'text';
        }
    }