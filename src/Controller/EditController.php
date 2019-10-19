<?php


namespace Drupal\register_preapproved\Controller;


use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EditController extends ControllerBase {

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
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  public function __construct(Connection $connection, ConfigFactory $config, FormBuilder $formBuilder, Messenger $messenger) {
    $this->connection = $connection;
    $this->config = $config;
    $this->formBuilder = $formBuilder;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('form_builder'),
      $container->get('messenger')
    );
  }

  public function content($rpid = '') {
    if (is_numeric($rpid)) {
      $result = $this->connection->select('register_preapproved', 'rp')
        ->condition('rp.rpid', $rpid)
        ->fields('rp')
        ->execute();

      while ($pattern = $result->fetchAssoc()) {
        if (count(array_filter($this->config->get('register_preapproved.settings')->get('register_preapproved_roles')))) {
          return $this->formBuilder->getForm('\Drupal\register_preapproved\Form\EditForm', $pattern);
        }
        else {
          $formatted_settings_page = new FormattableMarkup(
            '<a href=":settings">register pre-approved settings</a>',
            [
              ':settings' => Url::fromRoute('register_preapproved.settings')->toString(),
            ]
          );
          $this->messenger->addError($this->t('There are no default roles defined. You can define some on the @settings page.', ['@settings' => $formatted_settings_page]));
        }
      }
    }
    else {
      $this->messenger->addError($this->t('The email address or domain record was not found.'));
    }

    $path = Url::fromRoute('register_preapproved.list_form')->toString();

    return new RedirectResponse($path);
  }

}