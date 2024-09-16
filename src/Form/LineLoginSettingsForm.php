<?php

namespace Drupal\line_login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\line_login\Config\LineLoginConfig;

class LineLoginSettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'line_login_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['line_login.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('line_login.settings');

        $host = \Drupal::request()->getSchemeAndHttpHost();

        $form['line_callback_description'] = [
            '#markup' => 'コールバックはLINEの開発画面に貼り付けてください。また、チャネルIDとチャネルシークレットはLINEの開発画面を参照して入力してください。',
        ];

        $form['line_callback_admin'] = [
            '#type' => 'item',
            '#title' => 'コールバックURL 管理画面連携用',
            '#markup' => $host."/admin/line_login/callback",
        ];

        $form['line_callback_front'] = [
            '#type' => 'item',
            '#title' => 'コールバックURL 自動ログイン用',
            '#markup' => $host."/line_login/callback",
        ];

        $form['line_channel_id'] = [
            '#type' => 'textfield',
            '#title' => 'チャネルID',
            '#default_value' => $config->get('line_channel_id'),
            '#required' => true,
        ];

        $form['line_channel_secret'] = [
            '#type' => 'password',
            '#title' => 'チャネルシークレット',
            '#default_value' => $config->get('line_channel_secret'),
            '#required' => true,
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        try {
            $key = LineLoginConfig::getEncryptionKey();
            $iv = LineLoginConfig::getEncryptionIv();
            $line_channel_secret = base64_encode(openssl_encrypt($form_state->getValue('line_channel_secret'), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));

            $this->config('line_login.settings')
                ->set('line_channel_id', $form_state->getValue('line_channel_id'))
                ->set('line_channel_secret', $line_channel_secret)
                ->save();
        } catch (\Exception $e) {
            \Drupal::logger('line_login')->error('Encryption error: @message', ['@message' => $e->getMessage()]);
            \Drupal::messenger()->addError($e->getMessage());
            return $form_state->setRedirect('line_login.settings');
        }

        parent::submitForm($form, $form_state);
    }
}
