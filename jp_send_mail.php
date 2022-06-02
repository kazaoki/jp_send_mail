<?php

// バージョン
define('__JP_SEND_MAIL_VERSION__', '1.3.0');

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
 *     'f'             => '',    // -fで指定するメールアドレス（未指定ならfromのメールアドレス部分が使用されます。falseにすると無視）
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
function jp_send_mail($args)
{
    // ラベルとメールアドレスに分ける関数定義
    $func_mail_split = function($mail) {
        return preg_match('/^\s*(.*?)\s*<([^<>]+)>\s*$/', $mail, $matches) ? array($matches[1], $matches[2]) : $mail;
    };

    // メールアドレスのDNSをチェックする関数定義
    $func_is_mail = function($mail) {
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
            $strwidth = mb_strlen($line);
            if(!$strwidth) {
                $result[] = '';
                continue;
            }
            while($pos < $strwidth) {
                $result[] = mb_strimwidth($line, $pos, $width, '', $encoding);
                $pos += mb_strlen($result[@count($result)-1]);
            }
        }
        return implode($break, $result);
    };

    // 文字列をPHPとして実行する関数定義（配列のまま突っ込んでも再帰します）
    $func_phpable = function($data) use(&$func_phpable, $args) {
        if(@$args['phpable'] && is_array($args['phpable'])) extract($args['phpable']);
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

    // 送信エンコーディングの設定
    $encoding = @$args['encoding'] ?: 'ISO-2022-JP-MS';

    // オリジナルの言語環境を保管（ISO-8859-1の場合はUTF-8に強制変換）
    $original_encoding = 'ISO-8859-1'===mb_internal_encoding()
        ? 'utf-8'
        : mb_internal_encoding()
    ;

    // エンコーディングがUTF-8ではない場合、半角カタカナを全角カタカナに強制変換する
    if(!preg_match('/^utf\-?8$/i', $encoding)) {
        if(!@$args['force_hankana']) {
            foreach($args as $key=>$value) {
                if(is_array($value) || !strlen($args[$key])) continue;
                $args[$key] = mb_convert_kana($value, 'KV', $original_encoding);
            }
        }
    }

    // 変数定義
    $headers = array();
    $parameters = array();

    // メールアドレス処理
    foreach(array('to', 'from', 'cc', 'bcc', 'reply') as $key) {
        if(!isset($args[$key])) continue;
        if(@$args['phpable']) $args[$key] = $func_phpable($args[$key]);
        $addresses = $args[$key];
        if(is_array($addresses) && !count($addresses)) continue;
        if(!is_array($addresses) && !strlen($addresses)) continue;
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
            } else {
                if(!$func_is_mail($address)) return false;
                $values[] = $address;
            }
        }
        if('to'===$key) {
            $args['to'] = implode(', ', $values);
        } else {
            $label = 'reply'===$key ? 'Reply-To' : ucfirst($key);
            $headers[] = $label.': '.implode(', ', $values);
        }
    }

    // subject処理
    if(@$args['phpable']) $args['subject'] = $func_phpable($args['subject']);
    $args['subject'] = $func_unfold_base64((mb_encode_mimeheader(mb_convert_encoding($args['subject'], $encoding, $original_encoding), $encoding)));

    // body処理
    if(@$args['phpable']) $args['body'] = $func_phpable($args['body']);
    $keep_mb_regex_encoding = mb_regex_encoding();
    mb_regex_encoding(mb_internal_encoding());
    $args['body'] = mb_convert_encoding(
        mb_ereg_replace("\r\n?", "\n", // 本文の改行コードをLFに統一
            false===@$args['wrap']
                ? $args['body']
                : $func_mb_wordwrap($args['body'], (@$args['wrap']>0 ? $args['wrap'] : 78), "\r\n", $original_encoding)
        ),
        $encoding,
        $original_encoding
    );
    mb_regex_encoding($keep_mb_regex_encoding);

    // 本文の頭に改行追加（標準:1）
    $args['body'] =
        str_repeat("\n", strlen(@$args['startline']) ? $args['startline'] : 1) .
        $args['body']
    ;

    // -f 処理
    if(false!==@$args['f']) {
        if(!strlen(@$args['f'])) $args['f'] = $args['from'];
        $set = $func_mail_split($args['f']);
        if(is_array($set)) $args['f'] = $set[1];
        $parameters[] = '-f '.$args['f'];
    }

    // 追加ヘッダー処理
    if(@$args['headers'] && is_array($args['headers'])) {
        foreach($args['headers'] as $key=>$value) {
            $headers[] = $key . ': ' . $value;
        }
    }

    // MIMEヘッダー追加
    $headers[] = 'MIME-Version: 1.0';

    // X-Mailerヘッダー追加
    $headers[] = 'X-Mailer: jp_send_mail() '.__JP_SEND_MAIL_VERSION__.' (https://kantaro-cgi.com/blog/php/php-jp_send_mail.html)';

    // 添付ファイル処理
    if(@$args['files'] && is_array($args['files']) && count($args['files'])) {

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
            $filename = 'integer'===gettype($key) ? basename($value) : $key;
            $filename = mb_convert_encoding($filename, $encoding, $original_encoding);
            $filename = '=?ISO-2022-JP?B?' . base64_encode($filename) . '?=';
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
    $result = mail(
        $args['to'],
        $args['subject'],
        $args['body'],
        implode("\n", $headers),
        implode(' ', $parameters)
    );

    return $result;
}
