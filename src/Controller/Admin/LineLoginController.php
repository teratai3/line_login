<?php

namespace Drupal\line_login\Controller\Admin;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\line_login\Service\LineLoginClientService;
use Drupal\Core\Session\AccountProxyInterface;


class LineLoginController extends ControllerBase
{

    protected $lineLoginClient;
    protected $currentUser;

    public function __construct(
        LineLoginClientService $lineLoginClient,
        AccountProxyInterface $currentUser
    ) {
        $this->lineLoginClient = $lineLoginClient;
        $this->currentUser = $currentUser;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('line_login.client'),
            $container->get('current_user')
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
            return new RedirectResponse('/admin/people');
        }

        $redirect_uri = \Drupal::request()->getSchemeAndHttpHost() . '/admin/line_login/callback';
        $this->lineLoginClient->setRedirectUri($redirect_uri);
        // セッションから state を削除
        $this->lineLoginClient->delete_state();

        // LINEからの応答を取得
        $response = $this->lineLoginClient->get_response();

        if (empty($response->id_token)) {
            \Drupal::messenger()->addError('LINEからユーザー情報が取得できませんでした。');
            return new RedirectResponse('/admin/people');
        }

        // LINEユーザープロフィールを取得
        $profile = $this->lineLoginClient->get_profile($response->id_token);

        if (empty($profile->sub)) {
            \Drupal::messenger()->addError("ユーザー情報が取得できませんでした");
            return new RedirectResponse('/admin/people');
        }

        // 既に他のユーザーに登録されているか確認
        $existing_user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['line_user_id' => $profile->sub]);

        if (!empty($existing_user)) {
            \Drupal::messenger()->addError("このLINEアカウントは既に他のユーザーに連携されています。");
            return new RedirectResponse('/admin/people');
        }

        // 現在のユーザーエンティティを取得
        $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser->id());

        // トランザクションを開始して、ユーザーの line_user_id を更新
        $connection = \Drupal::database();
        $transaction = $connection->startTransaction();

        try {
            // LINEユーザーIDをフィールドに保存
            $user_entity->set('line_user_id', $profile->sub);
            $user_entity->save();

            \Drupal::messenger()->addStatus('LINE連携に成功しました。');
        } catch (\Exception $e) {
            // トランザクションをロールバック
            $transaction->rollBack();
            \Drupal::logger('line_login')->error('LINE連携エラー: ' . $e->getMessage());
            \Drupal::messenger()->addError('LINE連携に失敗しました。管理者にお問い合わせください。');
            return new RedirectResponse('/admin/people');
        }
        
        return new RedirectResponse('/admin/people');
    }
}
