<?php

namespace Drupal\line_login\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\line_login\Service\LineLoginClientService;
use Drupal\user\Entity\User;

class LineLoginController extends ControllerBase
{

    protected $lineLoginClient;

    public function __construct(LineLoginClientService $lineLoginClient)
    {
        $this->lineLoginClient = $lineLoginClient;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('line_login.client')
        );
    }

    /**
     * Handles the callback from LINE login.
     */
    public function callback()
    {
        // セッションの state パラメータを検証
        if (!$this->lineLoginClient->is_state()) {
            \Drupal::messenger()->addError("Stateが古いか存在しません");
            return new RedirectResponse('/user/login');
        }

        $this->lineLoginClient->delete_state();
        $redirect_uri = \Drupal::request()->getSchemeAndHttpHost() . '/line_login/callback';
        $this->lineLoginClient->setRedirectUri($redirect_uri);
       

        // LINEからの応答を取得
        $response = $this->lineLoginClient->get_response();

        if (empty($response->id_token)) {
            \Drupal::messenger()->addError('LINEからユーザー情報が取得できませんでした。');
            return new RedirectResponse('/user/login');
        }

        // LINEユーザープロフィールを取得
        $profile = $this->lineLoginClient->get_profile($response->id_token);

        if (empty($profile->sub)) {
            \Drupal::messenger()->addError("ユーザー情報が取得できませんでした");
            return new RedirectResponse('/user/login');
        }

        // 既に他のユーザーに登録されているか確認
        $existing_user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['line_user_id' => $profile->sub]);

        if (empty($existing_user)) {
            \Drupal::messenger()->addError("このLINEアカウントは連携されていません");
            return new RedirectResponse('/user/login');
        }



        // ユーザーが見つかった場合、最初のユーザーを取得
        $user = reset($existing_user);
        if ($user instanceof User) {
            // ユーザーがブロックされていないか確認
            if ($user->isActive()) {
                user_login_finalize($user);
                return new RedirectResponse('/user/' . $user->id());
            } else {
                \Drupal::messenger()->addError("このアカウントは無効です。");
                return new RedirectResponse('/user/login');
            }
        } else {
            \Drupal::messenger()->addError("ログインに失敗しました。");
            return new RedirectResponse('/user/login');
        }
    }
}
