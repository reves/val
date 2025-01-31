<?php

namespace Val\Api;

Abstract Class Mail
{
    const LINE_MAX_LENGTH = 78;

    /**
     * Sends an email with the specified options. Returns true if the mail was 
     * successfully accepted for delivery, false otherwise.
     * 
     * Options array format:
     * 
     *  $options = [
     *      'from' => ['name' => "Company name", 'address' => "email@company.com"],
     *      'to' => ["User name" => "email@user1.com", "email@user2.com"],
     *      'cc' => ["User name" => "email@user1.com", "email@user2.com"],
     *      'bcc' => ["User name" => "email@user1.com", "email@user2.com"],
     *      'subject' => "The subject",
     *      'messageHTML' => "<p>Hello!</p>",
     *      'messagePlainText' => "Hello!"
     *  ];
     * 
     *  ...where the keys 'from' and 'to' are required.
     * 
     */
    public static function send(array $options) : bool
    {
        if (!isset($options['from']))
            throw new \InvalidArgumentException('The 
                "$options[\'from\']" option is required.');

        if (!isset($options['from']['name']))
            throw new \InvalidArgumentException('The 
                "$options[\'from\'][\'name\']" option is required.');

        if (!isset($options['from']['address']))
            throw new \InvalidArgumentException('The 
                "$options[\'from\'][\'address\']" option is required.');

        if (!isset($options['to']))
            throw new \InvalidArgumentException('The
                "$options[\'to\']" option is required.');

        if (empty($options['to']))
            throw new \InvalidArgumentException('The 
                "$options[\'to\']" option does not contain any addresses.');

        $options = [
            'from' => $options['from'],
            'to' => $options['to'],
            'cc' => $options['cc'] ?? [],
            'bcc' => $options['bcc'] ?? [],
            'subject' => $options['subject']
                ? self::encodeUTF8($options['subject'])
                : '',
            'messageHTML' => $options['messageHTML']
                ? str_replace('\n.', '\n..', trim($options['messageHTML']))
                : '',
            'messagePlainText' => $options['messagePlainText']
                ? str_replace('\n.', '\n..', trim($options['messagePlainText']))
                : ''
        ];
        
        // Prepare headers
        $uid = md5(uniqid());

        $headers = [
            'From' => self::encodeUTF8($options['from']['name']) 
                . ' <' . self::encodeUTF8($options['from']['address']) . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => "multipart/alternative; boundary{$uid}"
        ];
        if ($options['cc']) $headers['Cc'] = self::formatAddresses($options['cc']);
        if ($options['bcc']) $headers['Bcc'] = self::formatAddresses($options['bcc']);
        
        // Prepare the message
        $message = [];

        if ($options['messagePlainText']) {

            $message[] = "--{$uid}";
            $message[] = 'Content-Type: text/plain; charset=utf-8';
            $message[] = 'Content-Transfer-Encoding: quoted-printable';
            $message[] = '';
            $message[] = wordwrap(quoted_printable_encode($options['messagePlainText']), self::LINE_MAX_LENGTH);
            $message[] = '';

        }

        if ($options['messageHTML']) {

            $message[] = "--{$uid}";
            $message[] = 'Content-Type: text/html; charset=utf-8';
            $message[] = 'Content-Transfer-Encoding: quoted-printable';
            $message[] = '';
            $message[] = wordwrap(quoted_printable_encode($options['messageHTML']), self::LINE_MAX_LENGTH);
            $message[] = '';

        }

        if (!$options['messagePlainText'] && !$options['messageHTML']) {

            $message[] = "--{$uid}";
            $message[] = 'Content-Type: text/plain; charset=utf-8';
            $message[] = 'Content-Transfer-Encoding: quoted-printable';
            $message[] = '';
            $message[] = quoted_printable_encode(' ');
            $message[] = '';

        }

        $message[] = "--{$uid}";
        
        return mail(
            self::formatAddresses($options['to']),
            $options['subject'],
            implode(PHP_EOL, $message),
            $headers
        );
    }

    /**
     * Formats email addresses to a string that can be safely included in the
     * header.
     */
    protected static function formatAddresses(array $address) : string
    {
        $result = '';

        foreach ($address as $name => $email) {

            if (is_string($name) && $name) {
                $name = self::encodeUTF8($name);
                $result .= "{$name} <{$email}>, ";
                continue;
            }

            $result .= "{$email}, ";
        }

        return trim($result, ' ,');
    }

    /**
     * Removes ASCII control characters, including any carriage return, line 
     * feed or tab characters. Returns the resulting string in UTF-8 format.
     */
    protected static function encodeUTF8(string $data) : string
    {
        return '=?UTF-8?Q?' 
            . imap_8bit(trim(filter_var($data, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW)))
            . '?=';
    }

}
