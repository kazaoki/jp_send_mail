<?php
/**
 * ちょうどいい感じの日本語メール送信関数
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
 *     'headers'  => [], // 追加ヘッダー配列を指定可能。
 *     'files'    => [], // 添付ファイルを配列で指定可能。ファイルパスもしくは、key=>valueでファイル名指定可能です。
 *     'phpable'  => false, // false以外だとメールアドレス・件名・本文がPHPとして実行されます。キー=>値の配列指定することで変数が使えるようになります。
 *     'startline'=> 1, // 本文上部の改行が数を指定します。標準では1です。
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
    $args['body'] = mb_convert_encoding($args['body'], $encoding, $original_encoding);

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

    // 添付ファイル処理
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

        // 添付なければ普通に Content-Type 追加（添付なしメール）
        if('ISO-2022-JP-MS'===$encoding) { # ISO-2022-JP-MS のときは ISO-2022-JP として。
            $headers[] = 'Content-Type: text/plain; charset=ISO-2022-JP';
        } else {
            $headers[] = 'Content-Type: text/plain; charset='.$encoding;
        }

        // 本文の頭に改行追加（標準:1）
        $args['body'] =
            str_repeat("\n", strlen(@$args['startline']) ? $args['startline'] : 1) .
            $args['body']
        ;
    }

    // メール送信実行
    $result = mail(
        $args['to'],
        $args['subject'],
        $args['body'],
        implode("\r\n", $headers),
        implode(' ', $parameters)
    );

    return $result;
}
