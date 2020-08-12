<?php

set_include_path('/usr/share/pear/');
require_once __DIR__.'/Mail_mimeDecode-1.5.6/Mail/mimeDecode.php';

/**
 * ヘッダーに付けた判別キーをもとに maildev の emlファイルを探し出し中身を返す
 * @return string
 */
function mail_get_contents($maildev_key, $encoding='ISO-2022-JP-MS')
{
    // maildevのpidを取得（emailファイルが格納されるディレクトリ名になる）
    $eml_dir = '/tmp/maildev/'.trim(exec('ps --no-heading -C node -o pid'));

    // 該当のemlファイルを探して中身をパースして返す
    foreach(glob($eml_dir.'/*.eml') as $file){
        if(is_file($file)){
            $eml = file_get_contents($file);
            $decoder = new \Mail_mimeDecode($eml);
            $structure = $decoder->decode(array(
                'include_bodies' => true,
                'decode_bodies'  => true,
                'decode_headers' => true,
            ));
            if($maildev_key===@$structure->headers['x-maildev-key']) {
                foreach(array('to', 'from', 'cc', 'bcc', 'reply-to', 'subject') as $key) {
                    if(!@$structure->headers[$key]) continue;
                    if(strtolower(mb_internal_encoding())!==strtolower($encoding)) {
                        $structure->headers[$key] = mb_convert_encoding(mb_decode_mimeheader($structure->headers[$key]), mb_internal_encoding(), $encoding);
                    }
                }
                if(strtolower(mb_internal_encoding())!==strtolower($encoding)) {
                    if(@$structure->parts) {
                        $structure->parts[0]->body = mb_convert_encoding($structure->parts[0]->body, mb_internal_encoding(), $encoding);
                    } else {
                        $structure->body = mb_convert_encoding($structure->body, mb_internal_encoding(), $encoding);
                    }
                }
                $structure->tmpname = $file;
                return $structure;
            }
        }
    }
    return false;
}

/**
 * msecで寝る
 */
function msleep($msec)
{
    usleep($msec*1000);
}
