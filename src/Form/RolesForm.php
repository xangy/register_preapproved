<?php
/**
 * Created by PhpStorm.
 * User: xang
 * Date: 18/03/19
 * Time: 5:52 PM
 */

namespace Drupal\register_preapproved\Form;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RolesForm extends ConfigFormBase {

  // @todo Do we really require constructor and create function over here?
  /**
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
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
    return 'register_preapproved_roles_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $register_preapproved_roles = user_role_names(TRUE);
    unset($register_preapproved_roles[RoleInterface::AUTHENTICATED_ID]);
    // Removing administrator role as this was not being provided earlier as an
    // option.
    unset($register_preapproved_roles['administrator']);

    if (count($register_preapproved_roles)) {
      $form['register_preapproved_roles'] = array(
        '#type' => 'checkboxes',
        '#title' => $this->t('Pre-approved roles'),
        '#default_value' => $this->config('register_preapproved.settings')->get('register_preapproved_roles'),
        '#options' => $register_preapproved_roles,
        '#description' => $this->t('Select the custom roles available for assignment when adding email addresses and domain patterns.'),
      );
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo To change a configuration you will need to get an instance of \Drupal\Core\Config\Config (Mutable config object) by making a call to getEditable() on the config factory.
    // https://www.drupal.org/docs/8/api/configuration-api/simple-configuration-api
    $this->config('register_preapproved.settings')
      ->set('register_preapproved_roles', $form_state->getValue('register_preapproved_roles'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}