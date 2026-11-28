<?php

// バージョン
if(!defined('__JP_SEND_MAIL_VERSION__')) {
    define('__JP_SEND_MAIL_VERSION__', '1.5.0');
}

/**
 * jp_send_mail()
 * ちょうどいい感じの日本語メール送信関数
 *
 * $result = jp_send_mail([
 *     'to'            => '',    // To※
 *     'from'          => '',    // From
 *     'subject'       => '',    // Subject
 *     'body'          => '',    // 本文
 *     'cc'            => '',    // CC※
 *     'bcc'           => '',    // BCC※
 *     'reply'         => '',    // Reply-To
 *     'f'             => '',    // -fで指定するメールアドレス（未指定ならfromのメールアドレス部分、設定ありならそれを使用、false指定ならf出力しない）
 *     'smtp'          => null,  // 配列で host を指定すると mail() ではなく SMTP で直接送信します。['host'=>必須, 'port'=>省略可, 'secure'=>''|'ssl'|'tls', 'user'=>'', 'pass'=>'', 'helo'=>'', 'timeout'=>10, 'verify'=>true]
 *     'encoding'      => '',    // エンコード。未指定なら ISO-2022-JP-MS
 *     'headers'       => [],    // 追加ヘッダー配列を指定可能。
 *     'files'         => [],    // 添付ファイルを配列で指定可能。ファイルパスもしくは、key=>valueでファイル名指定可能です。
 *     'phpable'       => false, // false以外だとメールアドレス・件名・本文がPHPとして実行されます。キー=>値の配列指定することで変数が使えるようになります。
 *     'startline'     => 1,     // 本文上部の改行が数を指定します。標準では1です。
 *     'force_hankana' => false, // エンコードがUTF-8以外の場合、安全のため半角カタカナを全角に変換しますが、これをtrueにすると変換処理を行いません。（非推奨）
 *     'wrap'          => false, // 折り返す半角文字数。未指定なら78。false で折返ししない
 * ]);
 * ※複数のメールアドレスを指定したい場合はカンマ区切りではなく配列でセットすること。
 */
if (!function_exists('jp_send_mail')) {
    function jp_send_mail($args)
    {
        // ラベルとメールアドレスに分ける関数定義
        $func_mail_split = function($mail) {
            return preg_match('/^\s*(.*?)\s*<([^<>]+)>\s*$/', $mail, $matches) ? array($matches[1], $matches[2]) : $mail;
        };

        // メールアドレスの形式とDNSをチェックする関数定義
        // 空白や改行を含むアドレスは、ヘッダーインジェクションや sendmail の引数インジェクション（-f 経由）につながるため、DNSチェックの前に形式チェックで弾く
        $func_is_mail = function($mail) {
            if(!is_string($mail) || preg_match('/\s/', $mail)) return false;
            if(false === filter_var($mail, FILTER_VALIDATE_EMAIL)) return false;
            $host = str_replace(array('[', ']'), '', substr($mail, strpos($mail, '@') + 1));
            return (checkdnsrr($host, 'MX') || checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA'));
        };

        // マルチバイト版 wordwrap()
        $func_mb_wordwrap = function($str, $width, $break="\r\n", $encoding=null) {
            if(!$encoding) $encoding = mb_internal_encoding();
            $result = array();
            $count = 0;
            foreach(mb_split("\r\n|\r|\n", $str) as $line) {
                $pos = 0;
                $strwidth = mb_strlen((string)$line);
                if(!$strwidth) {
                    $result[] = '';
                    continue;
                }
                while($pos < $strwidth) {
                    $result[] = mb_strimwidth((string)$line, $pos, $width, '', $encoding);
                    $pos += mb_strlen((string)$result[@count((array)$result)-1]);
                }
            }
            return implode($break, $result);
        };

        // 文字列をPHPとして実行する関数定義（配列のまま突っ込んでも再帰します）
        $func_phpable = function($data) use(&$func_phpable, $args) {
            // 入力値で内部変数（$data・$args など）を上書きしない。
            // 変数名として正しいキーだけを、既にある変数を残したまま展開する。
            if(isset($args['phpable']) && is_array($args['phpable'])) {
                $vars = array();
                foreach($args['phpable'] as $k=>$v) {
                    if(!is_string($k) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $k)) continue;
                    if(in_array($k, array('data', 'args', 'func_phpable', 'new_data', 'item', 'this', 'GLOBALS', 'vars', 'k', 'v'), true)) continue;
                    $vars[$k] = $v;
                }
                extract($vars, EXTR_SKIP);
                unset($vars, $k, $v);
            }
            if(is_array($data)) {
                $new_data = array();
                foreach($data as $item) {
                    $new_data[] = $func_phpable($item);
                }
                return $new_data;
            } else {
                ob_start();
                eval ('?>'.$data);
                $data = ob_get_contents();
                ob_end_clean();
                return $data;
            }
        };

        // BASE64文字列の畳み込み解除する関数定義（EdMaxだと畳み込み途中で文字化けるため）
        $func_unfold_base64 = function($base64_str) {
            return preg_replace("/\?\=\s+\=\?ISO-2022-JP\?B\?/", '', $base64_str);
        };

        // SMTPで直接送信する関数定義（'smtp' 指定時の送信層。メッセージの組み立ては既存処理に任せ、ここはSMTPの会話だけを行う）
        $func_smtp_send = function($smtp, $from, $recipients, $message) {
            // ホスト名に空白・改行が混ざっていたら接続しない（SMTPコマンドインジェクション対策）
            if(!isset($smtp['host']) || !is_string($smtp['host']) || preg_match('/\s/', $smtp['host'])) return false;
            if(!is_string($from) || !strlen($from) || !count((array)$recipients)) return false;

            $secure  = isset($smtp['secure']) ? strtolower((string)$smtp['secure']) : '';
            if(!in_array($secure, array('', 'ssl', 'tls'), true)) return false;
            $port    = isset($smtp['port']) ? (int)$smtp['port'] : ('ssl'===$secure ? 465 : ('tls'===$secure ? 587 : 25));
            $timeout = isset($smtp['timeout']) ? (int)$smtp['timeout'] : 10;
            $helo    = (isset($smtp['helo']) && preg_match('/^[A-Za-z0-9.\-]+$/', (string)$smtp['helo']))
                ? (string)$smtp['helo']
                : ((isset($_SERVER['SERVER_NAME']) && preg_match('/^[A-Za-z0-9.\-]+$/', (string)$_SERVER['SERVER_NAME'])) ? (string)$_SERVER['SERVER_NAME'] : 'localhost');
            $verify  = isset($smtp['verify']) ? (bool)$smtp['verify'] : true;

            $context = stream_context_create(array('ssl' => array(
                'verify_peer'      => $verify,
                'verify_peer_name' => $verify,
                'peer_name'        => $smtp['host'],
                'SNI_enabled'      => true,
            )));
            $fp = @stream_socket_client(('ssl'===$secure ? 'ssl://' : 'tcp://') . $smtp['host'] . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
            if(!$fp) return false;
            stream_set_timeout($fp, $timeout);

            // 応答読み取り（「250-...」形式の複数行応答に対応）。応答行の配列を返す
            $func_read = function() use($fp) {
                $lines = array();
                while(true) {
                    $line = fgets($fp, 512);
                    if(false === $line) return false;
                    $meta = stream_get_meta_data($fp);
                    if(!empty($meta['timed_out'])) return false;
                    $lines[] = rtrim($line, "\r\n");
                    if(strlen($line) < 4 || '-' !== $line[3]) break; // 「250-」の間だけ続きを読み、「250 」「250」のみ等で終わる
                }
                return $lines;
            };
            // コマンド送信＋応答コードチェック。成功したら応答行の配列を返す
            $func_cmd = function($command, $expect) use($fp, $func_read) {
                if('' !== $command) fwrite($fp, $command . "\r\n");
                $lines = $func_read();
                if(false === $lines) return false;
                $code = (int)substr($lines[0], 0, 3);
                return in_array($code, (array)$expect, true) ? $lines : false;
            };
            $func_fail = function() use($fp) { fclose($fp); return false; };

            // 接続時の挨拶
            if(false === $func_cmd('', 220)) return $func_fail();

            // EHLO（応答から拡張機能を取り出す）
            $func_ehlo = function() use($func_cmd, $helo) {
                $lines = $func_cmd('EHLO ' . $helo, 250);
                if(false === $lines) return false;
                $caps = array();
                foreach($lines as $line) $caps[] = strtoupper(substr($line, 4));
                return $caps;
            };
            $caps = $func_ehlo();
            if(false === $caps) return $func_fail();

            // STARTTLS（'tls' 指定時。暗号化できなければ送らない）
            if('tls'===$secure) {
                if(!in_array('STARTTLS', $caps, true)) return $func_fail();
                if(false === $func_cmd('STARTTLS', 220)) return $func_fail();
                if(true !== stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $func_fail();
                $caps = $func_ehlo();
                if(false === $caps) return $func_fail();
            }

            // 認証（PLAIN優先、なければLOGIN）
            if(isset($smtp['user']) && strlen((string)$smtp['user'])) {
                $user = (string)$smtp['user'];
                $pass = isset($smtp['pass']) ? (string)$smtp['pass'] : '';
                $auth_line = '';
                foreach($caps as $cap) { if(0 === strpos($cap, 'AUTH ')) $auth_line = $cap; }
                $authed = false;
                if(false !== strpos($auth_line, 'PLAIN')) {
                    $authed = false !== $func_cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), 235);
                } elseif(false !== strpos($auth_line, 'LOGIN')) {
                    if(false !== $func_cmd('AUTH LOGIN', 334) && false !== $func_cmd(base64_encode($user), 334)) {
                        $authed = false !== $func_cmd(base64_encode($pass), 235);
                    }
                }
                if(!$authed) return $func_fail();
            }

            // エンベロープ（宛先はヘッダーではなくここで渡す。Bcc の宛先もここにだけ現れる）
            if(false === $func_cmd('MAIL FROM:<' . $from . '>', 250)) return $func_fail();
            foreach($recipients as $recipient) {
                if(false === $func_cmd('RCPT TO:<' . $recipient . '>', array(250, 251))) return $func_fail();
            }
            if(false === $func_cmd('DATA', 354)) return $func_fail();
            fwrite($fp, rtrim($message, "\r\n") . "\r\n.\r\n");
            if(false === $func_cmd('', 250)) return $func_fail();
            $func_cmd('QUIT', 221);
            fclose($fp);
            return true;
        };

        // 送信エンコーディングの設定
        $encoding = isset($args['encoding']) ? $args['encoding'] : 'ISO-2022-JP-MS';

        // オリジナルの言語環境を保管（ISO-8859-1の場合はUTF-8に強制変換）
        $original_encoding = 'ISO-8859-1'===mb_internal_encoding()
            ? 'utf-8'
            : mb_internal_encoding()
        ;

        // エンコーディングがUTF-8ではない場合、半角カタカナを全角カタカナに強制変換する
        if(!preg_match('/^utf\-?8$/i', $encoding)) {
            if(empty($args['force_hankana'])) {
                foreach($args as $key=>$value) {
                    if(is_array($value) || !strlen((string)$args[$key])) continue;
                    $args[$key] = mb_convert_kana($value, 'KV', $original_encoding);
                }
            }
        }

        // 変数定義
        $headers = array();
        $parameters = array();
        $envelope_recipients = array(); // SMTP送信用：エンベロープ宛先（To・Cc・Bcc のアドレス部分のみ）
        $envelope_from = null;          // SMTP送信用：エンベロープ送信者
        $bcc_header = null;             // Bccヘッダーは mail() 用に分けて持ち、SMTP の送信データには含めない

        // メールアドレス処理
        foreach(array('to', 'from', 'cc', 'bcc', 'reply') as $key) {
            if(!isset($args[$key])) continue;
            if(!empty($args['phpable'])) $args[$key] = $func_phpable($args[$key]);
            $addresses = $args[$key];
            // 改行・NULLを含むアドレス（表示名側も含む）はヘッダーに入れず、送信を止める
            foreach((array)$addresses as $address) {
                if(preg_match('/[\r\n\0]/', (string)$address)) return false;
            }
            if(is_array($addresses) && !count((array)$addresses)) continue;
            if(!is_array($addresses) && !strlen((string)$addresses)) continue;
            if('from'===$key && is_array($addresses)) return false;
            if('reply'===$key && is_array($addresses)) return false;
            if(!is_array($addresses)) $addresses = array($addresses);
            $values = array();
            foreach($addresses as $address) {
                $set = $func_mail_split($address);
                if(is_array($set)) {
                    if(!$func_is_mail($set[1])) return false;
                    $encoded = $func_unfold_base64(mb_encode_mimeheader(mb_convert_encoding($set[0], $encoding, $original_encoding), $encoding)).' <'.$set[1].'>';
                    $values[] = $encoded;
                    $bare = $set[1];
                } else {
                    if(!$func_is_mail($address)) return false;
                    $values[] = $address;
                    $bare = $address;
                }
                // SMTP送信用にアドレス部分だけを控える
                if(in_array($key, array('to', 'cc', 'bcc'), true)) $envelope_recipients[] = $bare;
                if('from'===$key) $envelope_from = $bare;
            }
            if('to'===$key) {
                $args['to'] = implode(', ', $values);
            } elseif('bcc'===$key) {
                $bcc_header = 'Bcc: '.implode(', ', $values);
            } else {
                $label = 'reply'===$key ? 'Reply-To' : ucfirst($key);
                $headers[] = $label.': '.implode(', ', $values);
            }
        }

        // subject処理
        if(!empty($args['phpable'])) $args['subject'] = $func_phpable($args['subject']);
        // 改行・NULLを含む件名は送信を止める（ヘッダーインジェクション対策。MIMEエンコード後は正規の折り返しで改行が入るため、エンコード前にチェック）
        if(preg_match('/[\r\n\0]/', (string)$args['subject'])) return false;
        $args['subject'] = $func_unfold_base64((mb_encode_mimeheader(mb_convert_encoding($args['subject'], $encoding, $original_encoding), $encoding)));

        // body処理
        if(!empty($args['phpable'])) $args['body'] = $func_phpable($args['body']);
        $keep_mb_regex_encoding = mb_regex_encoding();
        mb_regex_encoding(mb_internal_encoding());
        $args['body'] = mb_convert_encoding(
            mb_ereg_replace("\r\n?", "\n", // 本文の改行コードをLFに統一
                (isset($args['wrap']) && false===$args['wrap'])
                    ? $args['body']
                    : $func_mb_wordwrap($args['body'], (isset($args['wrap']) && $args['wrap'] > 0 ? $args['wrap'] : 78), "\r\n", $original_encoding)
            ),
            $encoding,
            $original_encoding
        );
        mb_regex_encoding($keep_mb_regex_encoding);

        // 本文の頭に改行追加（標準:1）
        $args['body'] =
            str_repeat("\n", (isset($args['startline']) && strlen((string)$args['startline'])) ? $args['startline'] : 1) .
            $args['body']
        ;

        // -f 処理（値はメールアドレス形式チェックを通し、シェル引数として安全に渡す。sendmail の引数インジェクション対策）
        if(isset($args['f'])) {
            if(false!==$args['f']) {
                if(strlen((string)$args['f'])) {
                    // 指定値があればそれ使う（ラベル形式ならメールアドレス部分だけを取り出す）
                    $f_set = $func_mail_split($args['f']);
                    $f_addr = is_array($f_set) ? $f_set[1] : $f_set;
                    if(!is_string($f_addr) || preg_match('/\s/', $f_addr) || false === filter_var($f_addr, FILTER_VALIDATE_EMAIL)) return false;
                    $parameters[] = '-f'.escapeshellarg($f_addr);
                    $envelope_from = $f_addr; // SMTP時は -f の値をエンベロープ送信者に使う
                }
            }
        } else {
            // 未定義の場合は from のメールアドレス部分を出力（from は上で形式チェック済み）
            $set = $func_mail_split($args['from']);
            $parameters[] = '-f'.escapeshellarg(is_array($set) ? $set[1] : $set);
        }

        // 追加ヘッダー処理
        if(isset($args['headers']) && is_array($args['headers'])) {
            foreach($args['headers'] as $key=>$value) {
                // 改行混入によるヘッダーインジェクションを防ぐ
                if(preg_match('/[\r\n]/', (string)$key) || preg_match('/[\r\n]/', (string)$value)) return false;
                $headers[] = $key . ': ' . $value;
            }
        }

        // MIMEヘッダー追加
        $headers[] = 'MIME-Version: 1.0';

        // X-Mailerヘッダー追加
        $headers[] = 'X-Mailer: jp_send_mail() '.__JP_SEND_MAIL_VERSION__.' (https://kantaro-cgi.com/blog/php/php-jp_send_mail.html)';

        // 添付ファイル処理
        if(isset($args['files']) && is_array($args['files']) && count((array)$args['files'])) {

            // ヘッダー追加
            $boundary = '----=_Boundary_' . uniqid(rand(1000,9999) . '_') . '_';
            $headers[] = 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
            $headers[] = 'Content-Transfer-Encoding: 7bit';

            // 本文追加
            $args['body'] =
                '--' . $boundary . "\n".
                (
                    ('ISO-2022-JP-MS'===$encoding)
                        ? 'Content-Type: text/plain; charset=ISO-2022-JP'
                        : 'Content-Type: text/plain; charset=' . $encoding
                ) . "\n" .
                'Content-Transfer-Encoding: 7bit' . "\n".
                "\n".
                $args['body']."\n\n"
            ;

            // ファイル展開
            foreach($args['files'] as $key=>$value) {
                // さらに下の配列も展開。同名ファイルを選択できるように。
                if(is_array($value)) {
                    $v = array_keys($value);
                    $key = $v[0];
                    $value = $value[$key];
                }
                $filename = 'integer'===gettype($key) ? basename($value) : $key;
                $filename = mb_convert_encoding($filename, $encoding, $original_encoding);
                $filename = ('ISO-2022-JP-MS'===$encoding ? '=?ISO-2022-JP?B?' : '=?'.$encoding . '?B?') . base64_encode($filename) . '?=';
                $filepath = $value;
                $args['body'] .=
                    "--{$boundary}\n".
                    "Content-Type: application/octet-stream; name=\"{$filename}\"\n".
                    "Content-Transfer-Encoding: base64\n".
                    "Content-Disposition: attachment; filename=\"{$filename}\"\n\n".
                    chunk_split(base64_encode(file_get_contents($filepath))) . "\n\n"
                ;
            }
            $args['body'] .= "--{$boundary}--\n";

        } else {

            // 添付なければ普通に Content-Type 追加（添付なしメール）
            if('ISO-2022-JP-MS'===$encoding) { # ISO-2022-JP-MS のときは ISO-2022-JP として。
                $headers[] = 'Content-Type: text/plain; charset=ISO-2022-JP';
            } else {
                $headers[] = 'Content-Type: text/plain; charset='.$encoding;
            }
        }

        // メール送信実行
        if(isset($args['smtp']) && is_array($args['smtp']) && isset($args['smtp']['host']) && strlen((string)$args['smtp']['host'])) {
            // SMTP送信（To・Subject はヘッダーとしてメッセージに含める。Bccヘッダーは含めず、宛先はエンベロープで渡す）
            $smtp_message =
                'To: ' . $args['to'] . "\r\n" .
                'Subject: ' . $args['subject'] . "\r\n" .
                implode("\r\n", $headers) . "\r\n\r\n" .
                // 改行をCRLFに統一し、行頭のドットをエスケープ（ドットスタッフィング）
                preg_replace('/^\./m', '..', preg_replace("/\r\n|\r|\n/", "\r\n", $args['body']))
            ;
            return $func_smtp_send($args['smtp'], $envelope_from, $envelope_recipients, $smtp_message);
        }

        // 通常送信（mail()。Bccヘッダーはここで合流させる）
        if(null !== $bcc_header) $headers[] = $bcc_header;
        $result = mail(
            $args['to'],
            $args['subject'],
            $args['body'],
            implode("\n", $headers),
            implode(' ', $parameters)
        );

        return $result;
    }
}
