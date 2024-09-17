<?php

namespace Drupal\line_login\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\line_login\Config\LineLoginConfig;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * LINEログインのクライアントサービスを提供するサービスクラス.
 */
class LineLoginClientService {
  const LINE_EP_AUTHORIZE = "https://access.line.me/oauth2/v2.1/authorize";
  const LINE_EP_TOKEN = "https://api.line.me/oauth2/v2.1/token";
  const LINE_EP_VERIFY = "https://api.line.me/oauth2/v2.1/verify";

  /**
   * Session service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * ConfigFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The redirect URI for the LINE login.
   *
   * @var string
   */
  protected $redirectUri;

  /**
   * Request service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  public function __construct(
    ClientInterface $httpClient,
    SessionInterface $session,
    RequestStack $requestStack,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->httpClient = $httpClient;
    $this->session = $session;
    $this->requestStack = $requestStack;
    $this->configFactory = $configFactory;
  }

  /**
   * リダイレクトURIを設定する.
   */
  public function setRedirectUri($redirectUri) {
    $this->redirectUri = $redirectUri;
  }

  /**
   * LINEログインURLを生成し返す.
   */
  public function login($scope = 'profile openid email') {
    $state = random_int(1000, 9999);
    $this->session->set('line_state', $state);
    $this->session->save();

    $config = $this->configFactory->get('line_login.settings');
    $url = self::LINE_EP_AUTHORIZE . '?' . http_build_query([
      'response_type' => 'code',
      'client_id' => $config->get('line_channel_id'),
      'redirect_uri' => $this->redirectUri,
      'state' => $state,
      'scope' => $scope,
    ]);

    return $url;
  }

  /**
   * セッションに保存されたstateとクエリのstateが一致するか確認する.
   */
  public function isState() {
    $storedState = $this->session->get('line_state');
    $queryState = (int) $this->requestStack->getCurrentRequest()->query->get('state');

    if (empty($storedState) || $storedState !== $queryState) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * セッションからstateを削除する.
   */
  public function deleteState() {
    $this->session->remove('line_state');
  }

  /**
   * LINE APIに対するトークンリクエストを送信し、応答を取得する.
   *
   * @return mixed
   *   失敗した場合はNULL.
   */
  public function getResponse() {
    try {
      $key = LineLoginConfig::getEncryptionKey();
      $iv = LineLoginConfig::getEncryptionIv();
    }
    catch (\Exception $e) {
      // 例外発生時にログを記録.
      \Drupal::logger('line_login')->error('Error: ' . $e->getMessage());
      return NULL;
    }

    $config = $this->configFactory->get('line_login.settings');
    $postData = [
      'grant_type' => 'authorization_code',
      'code' => $this->requestStack->getCurrentRequest()->query->get('code'),
      'redirect_uri' => $this->redirectUri,
      'client_id' => $config->get('line_channel_id'),
      'client_secret' => openssl_decrypt(base64_decode($config->get('line_channel_secret')), "aes-256-cbc", $key, OPENSSL_RAW_DATA, $iv),
    ];

    try {
      $response = $this->httpClient->post(self::LINE_EP_TOKEN, [
        'form_params' => $postData,
        'connect_timeout' => 5,
        'timeout' => 5,
      ]);

      if ($response->getStatusCode() !== 200) {
        throw new \Exception('LINE API failed: ' . $response->getReasonPhrase(), $response->getStatusCode());
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('line_login')->error('エラー: ' . $e->getMessage());
      return NULL;
    }

    return json_decode($response->getBody());
  }

  /**
   * IDトークンを検証し、LINEプロフィール情報を取得する.
   */
  public function getProfile(string $id_token = "") {
    $config = $this->configFactory->get('line_login.settings');
    $postData = [
      'id_token' => $id_token,
      'client_id' => $config->get('line_channel_id'),
    ];

    try {
      $response = $this->httpClient->post(self::LINE_EP_VERIFY, [
        'form_params' => $postData,
        'connect_timeout' => 5,
        'timeout' => 5,
      ]);

      if ($response->getStatusCode() !== 200) {
        throw new \Exception('LINE API failed: ' . $response->getReasonPhrase(), $response->getStatusCode());
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('line_login')->error('エラー: ' . $e->getMessage());
      return NULL;
    }

    return json_decode($response->getBody());
  }

}
