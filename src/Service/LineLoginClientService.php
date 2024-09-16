<?php

namespace Drupal\line_login\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\line_login\Config\LineLoginConfig;


class LineLoginClientService
{
    const LINE_EP_AUTHORIZE = "https://access.line.me/oauth2/v2.1/authorize";
    const LINE_EP_TOKEN = "https://api.line.me/oauth2/v2.1/token";
    const LINE_EP_VERIFY = "https://api.line.me/oauth2/v2.1/verify";

    protected $session;
    protected $httpClient;
    protected $configFactory;
    protected $redirect_uri;
    protected $requestStack;

    public function __construct(
        ClientInterface $httpClient,
        SessionInterface $session,
        RequestStack $requestStack,
        ConfigFactoryInterface $configFactory
    ) {
        $this->httpClient = $httpClient;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->configFactory = $configFactory;
    }


    public function setRedirectUri($redirect_uri)
    {
        $this->redirect_uri = $redirect_uri;
    }

    public function login($scope = 'profile openid email')
    {
        $state = random_int(1000, 9999);
        $this->session->set('line_state', $state);
        $this->session->save();

        $config = $this->configFactory->get('line_login.settings');
        $url = self::LINE_EP_AUTHORIZE . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $config->get('line_channel_id'),
            'redirect_uri' => $this->redirect_uri,
            'state' => $state,
            'scope' => $scope,
        ]);

        return $url;
    }


    public function is_state()
    {
        
        $storedState = $this->session->get('line_state');
        $queryState = (int)$this->requestStack->getCurrentRequest()->query->get('state');
        
        if (empty($storedState) || $storedState !== $queryState) {
            return false;
        }
        return true;
    }


    public function delete_state()
    {
        $this->session->remove('line_state');
    }


    public function get_response()
    {
        try {
            $key = LineLoginConfig::getEncryptionKey();
            $iv = LineLoginConfig::getEncryptionIv();
        } catch (\Exception $e) {
            // 例外発生時にログを記録
            \Drupal::logger('line_login')->error('Error: ' . $e->getMessage());
            return null;
        }

        $config = $this->configFactory->get('line_login.settings');
        $postData = [
            'grant_type' => 'authorization_code',
            'code' => $this->requestStack->getCurrentRequest()->query->get('code'),
            'redirect_uri' => $this->redirect_uri,
            'client_id' => $config->get('line_channel_id'),
            'client_secret' => openssl_decrypt(base64_decode($config->get('line_channel_secret')), "aes-256-cbc", $key, OPENSSL_RAW_DATA, $iv),
        ];

        try {
            $response = $this->httpClient->post(self::LINE_EP_TOKEN, [
                'form_params' => $postData,
                'connect_timeout' => 5, //　サーバーへの接続を待機時間
                'timeout' => 5, // <= レスポンス待機時間
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('LINE API failed: ' . $response->getReasonPhrase(), $response->getStatusCode());
            }
        } catch (\Exception $e) {
            \Drupal::logger('line_login')->error('エラー: ' . $e->getMessage());
            return null;
        }

        return json_decode($response->getBody());
    }


    public function get_profile(string $id_token = "")
    {
        $config = $this->configFactory->get('line_login.settings');
        $postData = [
            'id_token' => $id_token,
            'client_id' => $config->get('line_channel_id'),
        ];

        try {
            $response = $this->httpClient->post(self::LINE_EP_VERIFY, [
                'form_params' => $postData,
                'connect_timeout' => 5, //　サーバーへの接続を待機時間
                'timeout' => 5, // <= レスポンス待機時間
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('LINE API failed: ' . $response->getReasonPhrase(), $response->getStatusCode());
            }
        } catch (\Exception $e) {
            \Drupal::logger('line_login')->error('エラー: ' . $e->getMessage());
            return null;
        }

        return json_decode($response->getBody());
    }
}
