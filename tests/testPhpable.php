<?php

use PHPUnit\Framework\TestCase;

// 初期設定
error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');
mb_language('Japanese');
mb_internal_encoding('utf-8');

// ローダー
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/helper.php';

class phpableTest extends TestCase
{
    /**
     * phpableテスト（ちゃんとPHP実行できるか）
     */
    public function testPhpable()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@<?php echo "example.com" ?>',
            'from'    => 'from@<?php echo "example.com" ?>',
            'cc'      => array(
                'cc@<?php echo "example.com" ?>',
                'cc2@<?php echo "example.com" ?>',
            ),
            'bcc'     => 'bcc@<?php echo "example.com" ?>',
            'reply'   => 'reply@<?php echo "example.com" ?>',
            'subject' => 'SUBJECT <?php echo "SAMPLE" ?>',
            'body'    => "㈱サンプル本文①\n12*34=<?php echo 12*34 ?>",
            'phpable' => true,
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['cc'], 'cc@example.com, cc2@example.com');
        $this->assertEquals($mailed->headers['reply-to'], 'reply@example.com');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('㈱サンプル本文①', $mailed->body);
        $this->assertContains('12*34=408', $mailed->body);
        $this->assertNotContains('php', $mailed->body);
    }

    /**
     * phpableテスト（phpable無効の場合PHP実行しないよう）
     */
    public function testPhpableNoRun()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@<?php echo "example.com" ?>',
            'from'    => 'from@<?php echo "example.com" ?>',
            'cc'      => array(
                'cc@<?php echo "example.com" ?>',
                'cc2@<?php echo "example.com" ?>',
            ),
            'bcc'     => 'bcc@<?php echo "example.com" ?>',
            'reply'   => 'reply@<?php echo "example.com" ?>',
            'subject' => 'SUBJECT <?php echo "SAMPLE" ?>',
            'body'    => "㈱サンプル本文①\n12*34=<?php echo 12*34 ?>",
            'phpable' => false,
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertFalse($result);
    }
}
