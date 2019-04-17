<?php
/**
 * 文字コード「ISO-2022-JP-MS」を使ったメール送信関数
 *
 * $args['to']       ... To※
 * $args['from']     ... From※
 * $args['subject']  ... Subject
 * $args['body']     ... 本文
 * $args['cc']       ... CC※
 * $args['bcc']      ... BCC※
 * $args['reply']    ... Reply-To
 * $args['f']        ... -fで指定するメールアドレス（未指定ならfromのメールアドレス部分が使用されます。falseにすると無視）
 * $args['encoding'] ... エンコード。未指定なら ISO-2022-JP-MS
 * $args['headers']  ... 追加ヘッダー配列を指定します。自動的に設定されたものを上書きしてしまいますので注意。
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
		$pair = explode('@', $mail);
        $host = str_replace(array('[', ']'), '', $pair[1]);
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
        if(!isset($args[$key])) {
            continue;
        }
        $addresses = $args[$key];
        if(is_array($addresses) && !count($addresses)) {
            continue;
        }
        if(!is_array($addresses) && !strlen($addresses)) {
            continue;
        }
        if('from'===$key && is_array($addresses)) {
            return false;
        }
        if('reply'===$key && is_array($addresses)) {
            return false;
        }
        if(!is_array($addresses)) {
            $addresses = array($addresses);
        }
        $values = array();
        foreach($addresses as $address) {
            $set = $func_mail_split($address);
            if(is_array($set)) {
                if(!$func_is_mail($set[1])) {
                    return false;
                }
                $encoded = mb_encode_mimeheader(mb_convert_encoding($set[0], $encoding, $original_encoding), $encoding).' <'.$set[1].'>';
                $values[] = $encoded;
            } else {
                if(!$func_is_mail($address)) {
                    return false;
                }
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
        if(!strlen(@$args['f'])) {
            $args['f'] = $args['from'];
        }
        $set = $func_mail_split($args['f']);
        if(is_array($set)) {
            $args['f'] = $set[1];
        }
        $parameters[] = '-f '.$args['f'];
    }

    // Content-Type追加
    if('ISO-2022-JP-MS'===$encoding) { # ISO-2022-JP-MS のときは ISO-2022-JP として。
        $headers[] = 'Content-Type: text/plain; charset=ISO-2022-JP';
    } else {
        $headers[] = 'Content-Type: text/plain; charset='.$encoding;
    }

    // 追加ヘッダー
    if(@$args['headers'] && is_array($args['headers'])) {
        foreach($args['headers'] as $key=>$value) {
            $headers[] = $key . ': ' . $value;
        }
    }

    // メール配信実行
    $result = mail(
        $args['to'],
        $args['subject'],
        "\n".$args['body'],
        implode("\r\n", $headers),
        implode(' ', $parameters)
    );

    return $result;
}
