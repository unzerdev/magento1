<?php

/**
 *  * You are allowed to use this API in your web application.
 *
 * Copyright (C) 2018 by customweb GmbH
 *
 * This program is licenced under the customweb software licence. With the
 * purchase or the installation of the software in your application you
 * accept the licence agreement. The allowed usage is outlined in the
 * customweb software licence which can be found under
 * http://www.sellxed.com/en/software-license-agreement
 *
 * Any modification or distribution is strictly forbidden. The license
 * grants you the installation in one application. For multiuse you will need
 * to purchase further licences at http://www.sellxed.com/shop.
 *
 * See the customweb software licence agreement for more details.
 *
 *
 * @category Customweb
 * @package Customweb_UnzerCw
 *
 */
class Customweb_UnzerCw_Block_Form extends Mage_Payment_Block_Form {
	private $printJavascript = true;

	protected function _construct(){
		parent::_construct();
		$this->setTemplate('customweb/unzercw/form.phtml');
	}

	
	public function getContent(){
		$code = $this->getMethodCode();
		$method = $this->getMethod();

		$output = '';
		$output .= '<fieldset class="form-list"><ul id="payment_form_' . $code . '" class="unzercw_payment_form" style="display:none">';

		if (true) {
			$output .= $this->getPaymentForm($code, $method);
		}

		if (false) {
			$output .= '
			<input type="hidden" id="' . $code . '_authorization_method" value="failed_license" />
			<ul class="messages">
				<li class="error-msg">
					<ul>
						<li>
							<span>' .
					 Mage::helper('UnzerCw')->__(
							'We experienced a problem with your sellxed payment extension. For more information, please visit the configuration page of the plugin.') . '</span>
						</li>
					</ul>
				</li>
			</ul>';
		}

		$output .= '</ul></fieldset>';
		return $output;
	}
	
	private function getPaymentForm($code, $method){
		$form = '
			<div id="payment_description_' . $code . '" class="cw_payment_description">
				' . ($method->getPaymentMethodConfigurationValue('show_image') ? '
					<img src="' . $this->getSkinUrl('images/unzercw/' . $method->getPaymentMethodName() . '.png') . '" /><br/>
				' : '') . $this->getMethodDescription() . '
			</div>
			' . $this->getAliasSelect() . '
			<input type="hidden" id="' . $code . '_authorization_method" value="' . $this->getAuthorizationMethod() . '" />
			<div id="payment_form_fields_' . $code . '">
				' . $this->getFormFields() . '
			</div>';
		if ($this->printJavascript) {
			$form .= '

			<script type="text/javascript">
				' . $this->getFormJavaScript() . '
			</script>';
		}
		return $form;
	}

	public function getFormFields(){
		Mage::getSingleton('checkout/session')->setAliasId('new');
		return $this->getMethod()->generateVisibleFormFields(array(
			'alias_id' => 'new'
		));
	}

	public function getFormJavaScript(){
		return $this->getMethod()->generateFormJavaScript(array(
			'alias_id' => 'new'
		));
	}

	public function getProcessUrl(){
		return Mage::getUrl('UnzerCw/process/process', array(
			'_secure' => true
		));
	}

	public function getJavascriptUrl(){
		return Mage::getUrl('UnzerCw/process/ajax', array(
			'_secure' => true
		));
	}

	public function getHiddenFieldsUrl(){
		return Mage::getUrl('UnzerCw/process/getHiddenFields', array(
			'_secure' => true
		));
	}

	public function getVisibleFieldsUrl(){
		return Mage::getUrl('UnzerCw/process/getVisibleFields', array(
			'_secure' => true
		));
	}

	public function getAuthorizationMethod(){
		$adapter = $this->getMethod()->getAuthorizationAdapter(false)->getAuthorizationMethodName();
		$adapter = strtolower($adapter);
		$adapter = str_replace('authorization', '', $adapter);
		return $adapter;
	}

	public function getMethodDescription(){
		return $this->getMethod()->getPaymentMethodConfigurationValue('description', Mage::app()->getLocale()->getLocaleCode());
	}

	public function getAliasSelect(){
		$payment = $this->getMethod();
		$result = "";

		if ($payment->getPaymentMethodConfigurationValue('alias_manager') == 'active') {
			$aliasList = $payment->loadAliasForCustomer();

			if (count($aliasList)) {
				$alias = array(
					'new' => Mage::helper('UnzerCw')->__('New card')
				);

				foreach ($aliasList as $key => $value) {
					$alias[$key] = $value;
				}

				// The onchange even listener is added here, because there seems to be a bug with prototype's observe
				// on select fields. 
				$selectControl = new Customweb_UnzerCw_Model_Select("alias_select", $alias, 'new',
						"cwpm_" . $payment->getCode() . ".loadAliasData(this)");
				$aliasElement = new Customweb_Form_Element(Mage::helper('UnzerCw')->__("Saved cards:"), $selectControl,
						Mage::helper('UnzerCw')->__("You may choose one of the cards you paid before on this site."));
				$aliasElement->setRequired(false);

				$renderer = new Customweb_UnzerCw_Model_FormRenderer();
				$renderer->setNameSpacePrefix($payment->getCode());
				$result = $renderer->renderElementsWithoutJavaScript(array(
					0 => $aliasElement
				));
			}
		}

		return $result;
	}

	/**
	 * Sets printJavascript to false.
	 * The property is used to determine if javascript should be output in the getPayment
	 */
	public function disableJavascript(){
		$this->printJavascript = false;
	}
}
