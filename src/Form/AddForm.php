<?php

namespace Drupal\register_preapproved\Form;


use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Render\Element\Email;
use Egulias\EmailValidator\EmailValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AddForm extends FormBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * @var \Egulias\EmailValidator\EmailValidator
   */
  protected $email_validator;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, ConfigFactory $config, EmailValidator $email_validator, Messenger $messenger) {
    $this->connection = $connection;
    $this->config = $config;
    $this->email_validator = $email_validator;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('email.validator'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'register_preapproved_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['register_preapproved']['emails'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pre-approved email addresses and domains'),
      '#required' => TRUE,
      '#description' => $this->t('Enter a list of email addresses and domain patterns, one entry per line. Valid patterns are full email addresses or domains beginning with the @ symbol. Ex. @domain.com'),
    ];

    $register_preapproved_roles = $this->config->get('register_preapproved.settings')->get('register_preapproved_roles');

    if (count($register_preapproved_roles)) {
      $options = [];
      // Retrieve user roles excluding anonymous
      $user_roles = user_role_names(TRUE);
      // Create options from default role selections
      foreach ($register_preapproved_roles as $rid) {
        // Make sure pre-approved role exists
        if (isset($user_roles[$rid])) {
          $options[$rid] = $user_roles[$rid];
        }
      }
      $form['register_preapproved']['roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Pre-approved roles'),
        '#options' => $options,
        '#description' => $this->t('Select the custom roles automatically assigned to these pre-approved users during registration.'),
      ];
    }

    $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Add')];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $errors = [];

    // preg_split more reliable than split to eliminate empty array elements
    $emails = preg_split('/[\n\r]+/', $form_state->getValue('emails'));

    foreach ($emails as $email) {
      if ($email = trim($email)) {
        $args = ['%email' => $email];

        // Portion of validation logic copied from _user_edit_validate()
        if (!$this->email_validator->isValid($email) && !valid_domain($email)) {
          $errors[] = $this->t('The e-mail address or domain %email is not valid.', $args);
        }
        elseif ($this->connection->select('users_field_data', 'u')->condition('u.mail', $email)->fields('u')->countQuery()->execute()->fetchField()) {
          $this->messenger->addWarning($this->t('The e-mail address %email is already registered.', $args));
          $form_state->setValue('emails', str_replace($email, '', $form_state->getValue('emails')));
        }
        elseif ($this->connection->select('register_preapproved', 'rp')->condition('rp.mail', $email)->fields('rp')->countQuery()->execute()->fetchField()) {
          $this->messenger->addWarning(t('The e-mail address or domain %email is already pre-approved.', $args));
          $form_state->setValue('emails', str_replace($email, '', $form_state->getValue('emails')));
        }
        else if (mb_strlen($email) > Email::EMAIL_MAX_LENGTH) {
          $formatted_maxlength = new FormattableMarkup('@maxlength',
            [
              '@maxlength' => Email::EMAIL_MAX_LENGTH,
            ]);
          $errors[] = $this->t('The e-mail address or domain %email cannot not exceed @maxlength characters.', array('%email' => $email, '@maxlength' => $formatted_maxlength));
        }
      }
    }

    if (count($errors)) {
      array_unshift($errors, $this->t('The following problems occurred while preparing to add the email addresses and/or domain patterns and must be corrected before continuing:') . '<br />');
      $form_state->setErrorByName('emails', implode('<br />', $errors));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $added = 0;

    // @todo use [] short array syntax.
    $register_preapproved_roles = array();
    $emails = preg_split('/[\n\r]+/', $form_state->getValue('emails'));
    $original = count($emails);

    // Remove duplicate entries, if any
    $emails = array_unique($emails);

    if ($form_state->getValue('roles')) {
      // retrieve user roles excluding anonymous
      $user_roles = user_role_names(TRUE);
      $register_preapproved_roles = array_filter($form_state->getValue('roles'));

      // create pattern default role selections
      foreach ($register_preapproved_roles as $rid) {
        $register_preapproved_roles[$rid] = $user_roles[$rid];
      }
    }

    foreach ($emails as $email) {
      if ($email = trim($email)) {
        $record = [
          'roles' => serialize($register_preapproved_roles),
          'timestamp' => time()
        ];

        $saved = $this->connection->merge('register_preapproved')->key('mail', $email)->fields($record)->execute();

        switch ($saved) {
          case SAVED_NEW:
          case SAVED_UPDATED:
            $added++;
        }
      }
    }

    $this->messenger->addStatus($this->t('%added pre-approved email addresses or domain patterns successfully added.', ['%added' => $added]));

    if ($original != $added) {
      // alert admin of duplicate entries
      $adjusted = $original - $added;
      $this->messenger->addWarning($this->t('%adjusted duplicate email addresses or domain patterns were detected and automatically excluded.', ['%adjusted' => $adjusted]));
    }

    $form_state->setRedirect('register_preapproved.list_form');
  }
}