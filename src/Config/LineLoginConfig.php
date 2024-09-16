<?php

namespace Drupal\line_login\Config;


class LineLoginConfig
{
    public static function getEncryptionKey()
    {
        $key = \Drupal::config('line_login.settings')->get('openssl_key');
        // キーが設定されていない場合、例外を投げる
        if (!$key) {
            throw new \Exception('Encryption key is not set in the configuration.');
        }

        return $key;
    }


    public static function getEncryptionIv()
    {
        // configテーブルからIVを取得
        $iv = \Drupal::config('line_login.settings')->get('openssl_iv');

        // IVが設定されていない場合、例外を投げる
        if (!$iv) {
            throw new \Exception('Encryption IV is not set in the configuration.');
        }

        return $iv;
    }
}