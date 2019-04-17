<?php

use PHPUnit\Framework\TestCase;

error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');

require __DIR__.'/helper.php';
require __DIR__.'/../jp_send_mail.php';

class MessageTest extends TestCase
{
    /**
     * toにメールアドレスのみセットした場合のテスト
     */
    public function testToNormal()
    {
        $result = jp_send_mail(array(
            'to'      => 'to@kazaoki.jp',
            'from'    => 'from@kazaoki.jp',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>md5(uniqid(rand(),1))),
        ));
        $this->assertNotFalse($result);

        $cont = file_get_contents('http://localhost:9981');
        echo $cont;

    }
}
