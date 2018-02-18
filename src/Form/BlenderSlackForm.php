<?php

namespace Drupal\blender\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BlenderSlackForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'blenderslack_form';
  }

    /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form constructor.
    $form = parent::buildForm($form, $form_state);
    // Default settings.
    $config = $this->config('blender-slack.settings');

    // Enabled field.
    $form['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Slack integration enabled?'),
      '#default_value' => $config->get('blender-slack.enabled'),
      '#description' => $this->t('Enabling Slack integration will allow the system to post comments, votes, and recommendations to a Slack workspace.'),
    );

    $form['workspace-token'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Workspace token:'),
      '#default_value' => $config->get('blender-slack.workspace-token'),
      '#description' => $this->t('Workspace token. This allows the app to post comments and votes to a channel you select.'),
    );

    $form['channel'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Channel ID:'),
      '#default_value' => $config->get('blender-slack.channel'),
      '#description' => $this->t('Slack Channel ID where comments and votes will be posted.'),
    );

    $form['bot-token'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Bot token:'),
      '#default_value' => $config->get('blender-slack.bot-token'),
      '#description' => $this->t('Bot token. This allows the app to send direct messages to inform users of new article recommendations.'),
    );

    $form['aggregate'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Aggregate?'),
      '#default_value' => $config->get('blender-slack.aggregate'),
      '#description' => $this->t('If checked, new comments and votes will only be posted to Slack when cron runs, not immediately.'),
    );



    return $form;
  }

    /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('blender-slack.settings');
    $config->set('blender-slack.enabled', $form_state->getValue('enabled'));
    $config->set('blender-slack.workspace-token', $form_state->getValue('workspace-token'));
    $config->set('blender-slack.channel', $form_state->getValue('channel'));
    $config->set('blender-slack.bot-token', $form_state->getValue('bot-token'));
    $config->set('blender-slack.aggregate', $form_state->getValue('aggregate'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'blender-slack.settings',
    ];
  }

}
