<?php


namespace Drupal\register_preapproved\Form;


use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EditForm extends FormBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, ConfigFactory $config, Messenger $messenger) {
    $this->connection = $connection;
    $this->config = $config;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'register_preapproved_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $pattern = []) {
    $register_preapproved_roles = array_filter($this->config->get('register_preapproved.settings')->get('register_preapproved_roles'));
    if (count($register_preapproved_roles)) {
      // Retrieve user roles excluding anonymous.
      $user_roles = user_role_names(TRUE);
      foreach ($register_preapproved_roles as $rid) {
        // Make sure pre-approved role exists.
        if (isset($user_roles[$rid])) {
          $options[$rid] = $user_roles[$rid];
        }
      }
      $form['register_preapproved']['roles'] = [
        '#type' => 'checkboxes',
        '#title' => t('Pre-approved roles for %title', ['%title' => $pattern['mail']]),
        '#default_value' => array_keys(unserialize($pattern['roles'])),
        '#options' => $options,
        '#description' => t('Select the custom roles automatically assigned for this pattern during registration.'),
      ];
    }

    $form['rpid'] = ['#type' => 'value', '#value' => $pattern['rpid']];
    $form['mail'] = ['#type' => 'value', '#value' => $pattern['mail']];
    $form['submit'] = ['#type' => 'submit', '#value' => t('Update')];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('roles')) {
      // Retrieve user roles excluding anonymous.
      $user_roles = user_role_names(TRUE);
      $register_preapproved_roles = array_filter($form_state->getValue('roles'));

      // Create pattern default role selections.
      foreach ($register_preapproved_roles as $rid) {
        $register_preapproved_roles[$rid] = $user_roles[$rid];
      }

      $record['roles'] = serialize($register_preapproved_roles);
      $rpid = $form_state->getValue('rpid');

      if ($this->connection->update('register_preapproved')->fields($record)->condition('rpid', $rpid)->execute()) {
        $this->messenger->addStatus($this->t('The custom role selections for :email were successfully updated.', [':email' => $form_state->getValue('mail')]));
      }
    }

    $form_state->setRedirect('register_preapproved.list_form');
  }
}
