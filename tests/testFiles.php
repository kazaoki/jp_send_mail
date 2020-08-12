<?php

use PHPUnit\Framework\TestCase;

// 初期設定
error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');
mb_language('Japanese');
mb_internal_encoding('utf-8');

// ローダー
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../jp_send_mail.php';
require_once __DIR__.'/helper.php';

class filesTest extends TestCase
{
    /**
     * ファイル添付テスト
     */
    public function testFiles()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@kazaoki.jp',
            'from'    => 'from@kazaoki.jp',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'files'   => array(
                __DIR__.'/attach-files/ireland-1985088_1920.jpg',
                'dog2.jpg' => __DIR__.'/attach-files/dog-01.jpg',
                'いっぬ⑩.jpg' => __DIR__.'/attach-files/dog-01.jpg',
            ),
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(1000);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key, 'utf-8');
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@kazaoki.jp');
        $this->assertEquals($mailed->headers['from'], 'from@kazaoki.jp');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');

        // 本文パート
        $this->assertContains('BODY SAMPLE', $mailed->parts[0]->body);

        // 添付１パート
        $file1 = $mailed->parts[1];
        $this->assertEquals($file1->ctype_primary, 'application');
        $this->assertEquals($file1->ctype_secondary, 'octet-stream');
        $this->assertEquals($file1->ctype_parameters['name'], 'ireland-1985088_1920.jpg');
        $this->assertEquals(strlen($file1->body), 434787);

        // 添付２パート
        $file2 = $mailed->parts[2];
        $this->assertEquals($file2->ctype_primary, 'application');
        $this->assertEquals($file2->ctype_secondary, 'octet-stream');
        $this->assertEquals($file2->ctype_parameters['name'], 'dog2.jpg');
        $this->assertEquals(strlen($file2->body), 161314);

        // 添付３パート
        $file3 = $mailed->parts[3];
        $this->assertEquals($file3->ctype_primary, 'application');
        $this->assertEquals($file3->ctype_secondary, 'octet-stream');
        $this->assertEquals(mb_convert_encoding($file3->ctype_parameters['name'], 'utf-8', 'ISO-2022-JP-MS'), 'いっぬ⑩.jpg');
        $this->assertEquals(strlen($file3->body), 161314);
    }

    /**
     * ファイル添付テスト（UTF-8）
     */
    public function testFilesWithUtf8()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@kazaoki.jp',
            'from'    => 'from@kazaoki.jp',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'files'   => array(
                __DIR__.'/attach-files/ireland-1985088_1920.jpg',
                'dog2.jpg' => __DIR__.'/attach-files/dog-01.jpg',
                'いっぬ⑩.jpg' => __DIR__.'/attach-files/dog-01.jpg',
            ),
            'encoding' => 'utf-8',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(1000);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key, 'utf-8');
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@kazaoki.jp');
        $this->assertEquals($mailed->headers['from'], 'from@kazaoki.jp');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');

        // 本文パート
        $this->assertContains('BODY SAMPLE', $mailed->parts[0]->body);

        // 添付１パート
        $file1 = $mailed->parts[1];
        $this->assertEquals($file1->ctype_primary, 'application');
        $this->assertEquals($file1->ctype_secondary, 'octet-stream');
        $this->assertEquals($file1->ctype_parameters['name'], 'ireland-1985088_1920.jpg');
        $this->assertEquals(strlen($file1->body), 434787);

        // 添付２パート
        $file2 = $mailed->parts[2];
        $this->assertEquals($file2->ctype_primary, 'application');
        $this->assertEquals($file2->ctype_secondary, 'octet-stream');
        $this->assertEquals($file2->ctype_parameters['name'], 'dog2.jpg');
        $this->assertEquals(strlen($file2->body), 161314);

        // 添付３パート
        $file3 = $mailed->parts[3];
        $this->assertEquals($file3->ctype_primary, 'application');
        $this->assertEquals($file3->ctype_secondary, 'octet-stream');
        $this->assertEquals($file3->ctype_parameters['name'], 'いっぬ⑩.jpg');
        $this->assertEquals(strlen($file3->body), 161314);
    }
}
