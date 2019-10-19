<?php
/**
 * Created by PhpStorm.
 * User: xang
 * Date: 18/03/19
 * Time: 6:02 PM
 */

namespace Drupal\register_preapproved\Form;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\Messenger;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ListForm extends FormBase {

  /**
   * @var \Drupal\Core\Database\Connection;
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $date_formatter;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, ConfigFactoryInterface $config, DateFormatter $date_formatter, Messenger $messenger) {
    $this->connection = $connection;
    $this->config = $config;
    $this->date_formatter = $date_formatter;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'register_preapproved_list_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $headers = [
      'email' => [
        'data' => $this->t('E-mail/Domain'),
        'field' => 'rp.mail',
        'sort' => 'desc'
      ],
      'timestamp' => [
        'data' => $this->t('Added'),
        'field' => 'rp.timestamp'
      ],
      'count' => ['data' => $this->t('Count')],
      'roles' => ['data' => $this->t('Roles')],
      'operations' => ['data' => $this->t('Operations')],
    ];

    // Get all prereg records and set the pager.
    $select = $this->connection->select('register_preapproved', 'rp')
      ->fields('rp', [])
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->limit(50)
      ->orderByHeader($headers);

    $results = $select->execute();

    $options = array();
    while ($result = $results->fetchAssoc()) {
      // Create the subquery to count the appropriate matching users.
      $sub_select1 = $this->connection->select('register_preapproved', 'rp');
      $sub_select1->join('users_field_data', 'u', 'u.mail = rp.mail AND rp.mail <> :mail', [':mail' => $result['mail']]);
      $sub_select1->fields('rp', ['mail']);

      // Start the primary query to count appropriate matching users.
      $select = $this->connection->select('users_field_data', 'u')
        ->fields('u')
        ->condition('u.mail', $this->connection->escapeLike($result['mail']))
        ->condition('u.mail', $sub_select1, 'NOT IN');

      // If we are only counting users who registered AFTER the preapproval was
      // created, add another filter.
      if ($this->config->get('register_preapproved_count')) {
        $select->condition('u.created', $result['timestamp'], '>');
      }

      // Run the count.
      $count = $select->countQuery()->execute()->fetchField();

      // Compile date for the table
      $options[$result['rpid']] = [
        'email' => $result['mail'],
        'timestamp' => $this->t(':date (:ago ago)', [':date' => date("M j, Y", $result['timestamp']), ':ago' => $this->date_formatter->formatInterval(time() - $result['timestamp'])]),
        'count' => $count,
        'roles' => implode(', ', unserialize($result['roles'])),
        'operations' => Link::createFromRoute($this->t('edit roles'), 'register_preapproved.edit', ['rpid' => $result['rpid']]),
      ];
    }

    //Build the tableselect.
    $form['delete'] = [
      '#type' => 'tableselect',
      '#header' => $headers,
      '#options' => $options,
      '#empty' => $this->t('No e-mail addresses or domain patterns found.'),
    ];

    $form['pager'] = ['#type' => 'pager'];
    $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Delete')];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setValue('delete', array_filter($form_state->getValue('delete')));

    if (count($form_state->getValue('delete')) == 0) {
      $form_state->setErrorByName('', $this->t('No email addresses or domain patterns selected.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $deleted = 0;
    $deletes = array_filter($form_state->getValue('delete'));
    foreach ($deletes as $rpid) {
      if ($this->connection->delete('register_preapproved')->condition('rpid', $rpid)->execute()) {
        $deleted++;
      }
    }
    $this
      ->messenger
      ->addStatus($this->t('%deleted email addresses or domain patterns successfully deleted.', ['%deleted' => $deleted]));
  }
}