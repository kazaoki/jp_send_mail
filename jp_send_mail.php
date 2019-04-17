<?php
/**
 * ちゃんと使える日本語メール送信関数
 *
 * $result = jp_send_mail([
 *     'to'       => '', // To※
 *     'from'     => '', // From
 *     'subject'  => '', // Subject
 *     'body'     => '', // 本文
 *     'cc'       => '', // CC※
 *     'bcc'      => '', // BCC※
 *     'reply'    => '', // Reply-To
 *     'f'        => '', // -fで指定するメールアドレス（未指定ならfromのメールアドレス部分が使用されます。falseにすると無視）
 *     'encoding' => '', // エンコード。未指定なら ISO-2022-JP-MS
 *     'headers'  => '', // 追加ヘッダー配列を指定します。
 *     'files'    => '', // 配列で添付ファイルを指定してください。key=>value指定でファイル名指定可能です。
 * ]);
 * ※複数のメールアドレスを指定したい場合はカンマ区切りではなく配列でセットすること。
 */
function jp_send_mail($args)
{
    // ラベルとメールアドレスに分ける関数定義
    $func_mail_split = function($mail) {
        return preg_match('/^\s*(.*?)\s*<([^<>]+)>\s*$/', $mail, $matches) ? array($matches[1], $matches[2]) : $mail;
    };

    // メールアドレスのDNSチェック
    $func_is_mail = function($mail) {
        $host = str_replace(array('[', ']'), '', substr($mail, strpos($mail, '@') + 1));
        return (checkdnsrr($host, 'MX') || checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA'));
    };

    // エンコーディングの設定
    $encoding = @$args['encoding'] ?: 'ISO-2022-JP-MS';

    // オリジナルの言語環境を保管
    $original_encoding = mb_internal_encoding();

    // 変数定義
    $headers = array();
    $parameters = array();

    // メールアドレス処理
    foreach(array('to', 'from', 'cc', 'bcc', 'reply') as $key) {
        if(!isset($args[$key])) continue;
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
                $encoded = mb_encode_mimeheader(mb_convert_encoding($set[0], $encoding, $original_encoding), $encoding).' <'.$set[1].'>';
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
    $args['subject'] = mb_encode_mimeheader(mb_convert_encoding($args['subject'], $encoding, $original_encoding), $encoding);

    // body処理
    $args['body'] = mb_convert_encoding($args['body'], $encoding, $original_encoding);

    // -f 処理
    if(false!==@$args['f']) {
        if(!strlen(@$args['f'])) $args['f'] = $args['from'];
        $set = $func_mail_split($args['f']);
        if(is_array($set)) $args['f'] = $set[1];
        $parameters[] = '-f '.$args['f'];
    }

    // 追加ヘッダー
    if(@$args['headers'] && is_array($args['headers'])) {
        foreach($args['headers'] as $key=>$value) {
            $headers[] = $key . ': ' . $value;
        }
    }

    // 添付ファイル
    if(@$args['files'] && is_array($args['files']) && count($args['files'])) {

        // ヘッダー追加
        $boundary = '----=_Boundary_' . uniqid(rand(1000,9999) . '_') . '_';
        $headers[] = 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
        $headers[] = 'MIME-Version: 1.0';
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

        // Content-Type追加（添付なしメール）
        if('ISO-2022-JP-MS'===$encoding) { # ISO-2022-JP-MS のときは ISO-2022-JP として。
            $headers[] = 'Content-Type: text/plain; charset=ISO-2022-JP';
        } else {
            $headers[] = 'Content-Type: text/plain; charset='.$encoding;
        }

        // 本文の頭に改行１つ追加（見やすくするためだけ）
        $args['body'] = "\n" . $args['body'];
    }

    // メール配信実行
    $result = mail(
        $args['to'],
        $args['subject'],
        $args['body'],
        implode("\r\n", $headers),
        implode(' ', $parameters)
    );

    return $result;
}
