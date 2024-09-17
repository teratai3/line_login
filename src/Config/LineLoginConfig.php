<?php

namespace Drupal\line_login\Config;

/**
 * LINEログイン設定に関連するメソッドを提供するクラス.
 */
class LineLoginConfig {

  /**
   * 設定から暗号化キーを取得する.
   *
   * @return string
   *   設定された暗号化キー.
   *
   * @throws \Exception
   *   暗号化キーが設定されていない場合にスローされる例外.
   */
  public static function getEncryptionKey() {
    $key = \Drupal::config('line_login.settings')->get('openssl_key');
    // キーが設定されていない場合、例外を投げる.
    if (!$key) {
      throw new \Exception('Encryption key is not set in the configuration.');
    }

    return $key;
  }

  /**
   * 設定から暗号化IV（初期化ベクトル）を取得する.
   *
   * @return string
   *   設定された暗号化IV.
   *
   * @throws \Exception
   *   暗号化IVが設定されていない場合にスローされる例外.
   */
  public static function getEncryptionIv() {
    $iv = \Drupal::config('line_login.settings')->get('openssl_iv');

    // IVが設定されていない場合、例外を投げる.
    if (!$iv) {
      throw new \Exception('Encryption IV is not set in the configuration.');
    }

    return $iv;
  }

}
