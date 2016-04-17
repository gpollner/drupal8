<?php

/**
 * @file
 * Contains \Drupal\gigya\Form\GigyaKeysForm.
 */

namespace Drupal\gigya\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use gigya\GigyaApiHelper;
use gigya\sdk\GigyaApiRequest;

class GigyaKeysForm extends ConfigFormBase {

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'gigya.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form constructor
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('gigya.settings');

    $form['gigya_api_key'] = array('#type' => 'textfield', '#title' => $this->t('Gigya API Key'),
      '#description' => $this->t('Specify the Gigya API Key for this domain'),
      '#default_value' => $config->get('gigya.gigya_api_key'), '#required' => TRUE);


    $form['gigya_application_key'] = array('#type' => 'textfield', '#title' => $this->t('Gigya Application Key'),
                                  '#description' => $this->t('Specify the Gigya Application key for this domain'),
                                  '#default_value' => $config->get('gigya.gigya_application_key'), '#required' => TRUE);

    $form['gigya_application_secret_key'] = array('#type' => 'textfield', '#title' => $this->t('Gigya Application Secret Key'),
      '#description' => $this->t('Specify the Gigya Application Secret (Base64 encoded) key for this domain'),
      '#default_value' => $config->get('gigya.gigya_application_key'), '#required' => TRUE);


    $data_centers = array('us1.gigya.com' => 'US', 'eu1.gigya.com' => 'EU', 'au1.gigya.com' => 'AU', 'other' => "Other");
    $form['gigya_data_center'] = array(
      '#type' => 'select',
      '#title' => $this->t('Data Center'),
      '#description' => $this->t('Please select the Gigya data center in which your site is defined. To verify your site location contact your Gigya implementation manager.'),
      '#options' => $data_centers,
      '#default_value' => array_key_exists($config->get('gigya.gigya_data_center'), $data_centers) ? $config->get('gigya.gigya_data_center') : 'other'
    );

    $form['gigya_other_data_center'] = array(
      "#type" => "textfield",
      "#default_value" => '',
      "#attributes" => array("id" => "gigya-other-data-center"),
      '#states' => array(
        'visible' => array(
         ':input[name="gigya_data_center"]' => array('value' => 'other'),
        ),
      ),
    );

    return $form;

  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gigya_admin_keys';
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    //Default values string used to check if the secrets keys changed.
    //Since we output garbage to the text field we use this string to check if the user change the key
    $config = $this->config('gigya.settings');

    $_validate = FALSE;
    // API key was changed ?
    if ($form_state->getValue('gigya_api_key') != $config->get('gigya_api_key')) {
      $_gigya_api_key = $form_state->getValue('gigya_api_key');
      $_validate = TRUE;
    }
    else {
      $_gigya_api_key = $config->get('gigya_api_key');
    }

    // APP key was changed ?

    if ($form_state->getValue('gigya_application_key') != $config->get('gigya_application_key')) {
      $_gigya_application_key = $form_state->getValue('gigya_application_key');
      $_validate = TRUE;
    }
    else {
      $_gigya_application_key = $config->get('gigya_application_key');
    }

    // APP secret key was changed ?
    if ($form_state->getValue('gigya_application_secret_key') != $config->get('gigya_application_secret_key')) {
      $_gigya_application_secret_key = $form_state->getValue('gigya_application_secret_key');
      $_validate = TRUE;
    }
    else {
      $_gigya_application_secret_key = $config->get('gigya_application_secret_key');
    }

    // Data Center was changed ?
    if ($form_state->getValue('gigya_data_center') != $config->get('gigya_data_center')) {
      $_gigya_data_center = $form_state->getValue('gigya_data_center');
      $_validate = TRUE;
    }
    else {
      $_gigya_data_center = $config->get('gigya_data_center');
    }

    if ($_validate && !$form_state->getErrors()) {

      $valid = $this->gigya_validate($_gigya_api_key, $_gigya_application_key, $_gigya_application_secret_key, $_gigya_data_center);
      if ($valid !== TRUE) {
        if (is_object($valid)) {
          $code = $valid->getErrorCode();
          $msg = $valid->getErrorMessage();
          $form_state->setErrorByName('gigya_api_key', $this->t("Gigya API error: {$code} - {$msg}.") .
          "For more information please refer to <a href=http://developers.gigya.com/037_API_reference/zz_Response_Codes_and_Errors target=_blank>Response_Codes_and_Errors page</a>");
//          watchdog('gigya', 'Error setting API key, error code: @code - @msg', array('@code' => $code, '@msg' => $msg));
        }
        else {
          $form_state->setErrorByName('gigya_api_key', $this->t("Your API key or Secret key could not be validated. Please try again"));
        }
      }
    }
  }


  /**
   * Validates the Gigya session keys.
   *
   * We use the site 'admin' username to find out the status. If it shows the
   * user logged out, that's good, if it returns an error then our keys are
   * most likely bad.
   */
  private function gigya_validate($api_key, $app_key, $app_secret, $data_center) {
    $request = new GigyaApiRequest($api_key, $app_secret, 'shortenURL', NULL ,$data_center, TRUE, $app_key);
    $request->setParam('url', 'http://gigya.com');
    ini_set('arg_separator.output', '&');
    $response = $request->send();
    ini_restore('arg_separator.output');
    $error = $response->getErrorCode();
    if ($error == 0) {
//        global $user;
//        $account = clone $user;
//        $datestr = Drupal::service('date.formatter')->format(time(), 'custom', 'Y-m-d H:i:s');
//        watchdog('gigya', 'secret key has changed by @name date @date', array('@name' => $account->name, "@date" => $datestr), WATCHDOG_DEBUG);

      drupal_set_message($this->t('Gigya validated properly. This site is authorized to use Gigya services'));
      return TRUE;
    }
    else {
      return $response;
    }
    return TRUE;
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('gigya.settings');
    $config->set('gigya.gigya_application_key', $form_state->getValue('gigya_application_key'));
    $config->set('gigya.gigya_api_key', $form_state->getValue('gigya_api_key'));
    $config->set('gigya.gigya_application_secret_key', $form_state->getValue('gigya_application_secret_key'));
    $config->set('gigya.gigya_data_center', $form_state->getValue('gigya_data_center'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }
}