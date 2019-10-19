<?php

namespace Drupal\register_preapproved\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase {

  // @todo Do we really require constructor and create function over here?

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['register_preapproved.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'register_preapproved_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $register_preapproved_count = $this->config('register_preapproved.settings')->get('register_preapproved_count');
    $register_preapproved_message = $this->config('register_preapproved.settings')->get('register_preapproved_message');
    $register_preapproved_restrict = $this->config('register_preapproved.settings')->get('register_preapproved_restrict');

    $form['register_preapproved_count'] = [
      '#type' => 'radios',
      '#title' => $this->t('Include pre-existing account email addresses in list page counts'),
      '#default_value' => $register_preapproved_count,
      '#options' => [$this->t('Exclude'), $this->t('Include')],
      '#description' => $this->t('Choose to exclude/include pre-existing account email addresses created prior to the corresponding pattern creation timestamp.'),
    ];

    $form['register_preapproved_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pre-approved message'),
      '#default_value' => $register_preapproved_message,
      '#size' => 100,
      '#maxlength' => 250,
      '#description' => $this->t('This message will appear to pre-approved users after registration. Available variable: %site_name'),
    ];

    $form['register_preapproved_restrict'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Retrict registration to only pre-approved email addresses.'),
      '#default_value' => $register_preapproved_restrict,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo To change a configuration you will need to get an instance of \Drupal\Core\Config\Config (Mutable config object) by making a call to getEditable() on the config factory.
    // https://www.drupal.org/docs/8/api/configuration-api/simple-configuration-api
    $this->config('register_preapproved.settings')
      ->set('register_preapproved_count', $form_state->getValue('register_preapproved_count'))
      ->save();

    $this->config('register_preapproved.settings')
      ->set('register_preapproved_message', $form_state->getValue('register_preapproved_message'))
      ->save();
    $this->config('register_preapproved.settings')
      ->set('register_preapproved_restrict', $form_state->getValue('register_preapproved_restrict'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}