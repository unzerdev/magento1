<?php
/**
 * You are allowed to use this API in your web application.
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
 * @category	Customweb
 * @package		Customweb_UnzerCw
 *
 */

class Customweb_UnzerCw_Model_BackendFormRenderer extends Customweb_Form_Renderer
{
	private $formId = null;

	private $showScope = true;

	private $showAllElements = false;

	public function __construct()
	{
		$this->setControlCssClass('input-box');
		$this->setControlCssClassResolver(Mage::getModel('unzercw/controlCssClassResolver'));

		if (!function_exists('cw_resource_loader')) {
			function cw_resource_loader($relativeClassName, $resourceFile){
				$path = Mage::getBaseDir('lib') . '/' . str_replace('_', '/', $relativeClassName) . '/' . $resourceFile;
				if (file_exists($path)) {
					return file_get_contents($path);
				}
			}
			Customweb_Core_Util_Class::registerResourceResolver('cw_resource_loader');
		}
	}

	public function setShowScope($flag) {
		$this->showScope = (boolean) $flag;
		return $this;
	}

	public function renderElementPrefix(Customweb_Form_IElement $element)
	{
		if ($element instanceof Customweb_Form_HiddenElement) {
			return '';
		}

		$classes = $this->getCssClassPrefix() . $this->getElementCssClass();
		$classes .= ' ' . $this->getCssClassPrefix() . $element->getElementIntention()
			->getCssClass();

		$errorMessage = $element->getErrorMessage();
		if (!empty($errorMessage)) {
			$classes .= ' ' . $this->getCssClassPrefix() . $this->getElementErrorCssClass();
		}

		$output = '<tr class="' . $classes . '" id="' . $element->getElementId() . '">';
		if ($element instanceof Customweb_Form_WideElement) {
			$output .= '<td class="value" colspan="2">';
		}
		return $output;
	}

	public function renderElementPostfix(Customweb_Form_IElement $element)
	{
		if ($element instanceof Customweb_Form_HiddenElement) {
			return '';
		}
		$output = '';
		if ($this->showScope) {
			$output .= '</td><td class="scope-label">';
			if ($element->getControl() instanceof Customweb_Form_Control_IEditableControl) {
				$output .= '[' . ($element->isGlobalScope() ? 'GLOBAL' : 'STORE VIEW') . ']';
			}
		}
		$output .= '</td><td></td></tr>';
		return $output;
	}

	public function renderElementLabel(Customweb_Form_IElement $element) {
		return parent::renderElementLabel($element) . '<td class="value">';
	}

	protected function renderLabel($referenceTo, $label, $class)
	{
		return '<td class="label">' . parent::renderLabel($referenceTo, $label, $class) . '</td>';
	}

	public function renderElementAdditional(Customweb_Form_IElement $element)
	{
		$output = '';

		$errorMessage = $element->getErrorMessage();
		if (!empty($errorMessage)) {
			$output .= $this->renderElementErrorMessage($element);
		}

		$description = $element->getDescription();
		if (!empty($description)) {
			$output .= $this->renderElementDescription($element);
		}

		if (!$element->isGlobalScope()) {
			$output .= $this->renderElementScope($element);
		}

		return $output;
	}

	protected function renderElementDescription(Customweb_Form_IElement $element)
	{
		return '<p class="note ' . $this->getCssClassPrefix() . $this->getDescriptionCssClass() . '"><span>' . $element->getDescription() . '</span></p>';
	}

	protected function renderElementScope(Customweb_Form_IElement $element)
	{
		if (Mage::getModel('unzercw/configurationAdapter')->getStoreHierarchy() == null) {
			return '';
		}
		$output = '</td><td class="use-default">';
		if ($element->getControl() instanceof Customweb_Form_Control_IEditableControl) {
			$output .= $this->renderElementScopeControl($element);
		}
		return $output;
	}

	/**
	 * @param Customweb_Form_IElement $element
	 * @return string
	 */
	protected function renderElementScopeControl(Customweb_Form_IElement $element)
	{
		$scopeControlId = $element->getControl()->getControlId() . '-scope';
		$scopeControlName = implode('_', $element->getControl()->getControlNameAsArray());
		$output = '';
		$output .= '<input class="use-default-checkbox"
			type="checkbox" ' . ($element->isInherited() ? 'checked="checked"' : '') . '
			name="default[' . $scopeControlName . ']"
			id="' . $scopeControlId . '"
			value="default"
			' . ($this->isAddJs() ? 'onclick="toggleValueElements(this, Element.previous(this.parentNode))"' : '') . ' />';
		$output .= '<label for="' . $scopeControlId . '">' . Mage::helper('UnzerCw')->__('Use Default') . '</label>';
		return $output;
	}

	public function renderRawElements(array $elements)
	{
		$result = '';
		foreach($this->getVisibleElements($elements) as $element) {
			if ($this->getNamespacePrefix() !== NULL) {
				$element->applyNamespacePrefix($this->getNamespacePrefix());
			}

			if ($this->getControlCssClassResolver() !== NULL) {
				$element->applyControlCssResolver($this->getControlCssClassResolver());
			}
			$result .= $element->render($this);
		}
		return $result;
	}

	public function renderButton(Customweb_Form_IButton $button, $jsFunctionPostfix = '') {

		$postfix = $jsFunctionPostfix;
		if($this->getNamespacePrefix() != null) {
			$postfix = $this->getNamespacePrefix().$postfix;
		}

		if($this->isAddJs()){
			$clickFunction = '$(\'' . $this->formId . '-button\').value = \'' . $button->getMachineName() . '\'; $(\'' . $this->formId . '\').submit();';
			if($button->isJSValidationExecuted()){
				$clickFunction = 'if(typeof cwValidateFields'.$postfix.' !== \'undefined\') {cwValidateFields'.$postfix.'(function(valid){
						'.$clickFunction.'},
						function(errors, valid){alert(errors[Object.keys(errors)[0]]);}
					)}
					else{
						'.$clickFunction.'
					}
					return false;';
			}

		}
		return '<button id="' . $button->getId() . '" title="' . $button->getTitle()  . '" type="button"
			class="' . $this->getButtonClasses($button) . ' scalable ' . ($button->getType() == Customweb_Form_IButton::TYPE_CANCEL ? 'cancel' : 'save') . ' unzercw-button"
			onclick="' . $clickFunction.'">
			<span><span><span>' . $button->getTitle()  . '</span></span></span></button>';
	}

	public function renderElementGroupPrefix(Customweb_Form_IElementGroup $elementGroup)
	{
		return '';
	}

	public function renderElementGroupPostfix(Customweb_Form_IElementGroup $elementGroup)
	{
		$output = '';
		$output .= '</tbody>';
		$output .= '</table>';
		$output .= '</fieldset>';
		$output .= '</fieldset>';
		return $output;
	}

	public function renderElementGroupTitle(Customweb_Form_IElementGroup $elementGroup)
	{
		$output = '';
		$title = $elementGroup->getTitle();
		if (! empty($title)) {
			$cssClass = $this->getCssClassPrefix() . $this->getElementGroupTitleCssClass();
			$output .= '<div class="entry-edit-head ' . $cssClass . '"><strong>' . $title . '</strong></div>';
		}
		$output .= '<fieldset>';
		$output .= '<table cellspacing="0" class="form-list">';
		$output .= '<colgroup class="label"></colgroup>';
		$output .= '<colgroup class="value"></colgroup>';
		$output .= '<colgroup class="use-default"></colgroup>';
		$output .= '<colgroup></colgroup>';
		$output .= '<tbody>';
		return $output;
	}

	public function renderForm(Customweb_IForm $form)
	{
		$this->formId = $form->getId();

		$output = '<div class="content-header"><table cellspacing="0"><tbody><tr><td><h3>' . $form->getTitle() . '</h3></td>';
		$output .= '<td class="form-buttons">';
		$output .= $this->renderButtons($form->getButtons());
		$output .= '</td>';
		$output .= '</tr></tbody></table></div>';

		$output .= '<form class="' . $this->getFormCssClass() . '" action="' . $form->getTargetUrl() . '" method="' . $form->getRequestMethod() . '"
				target="' . $form->getTargetWindow() . '" id="' . $form->getId() . '" name="' . $form->getMachineName() . '">';

		$output .= '<div class="entry-edit">';
		$output .= $this->renderElementGroups($form->getElementGroups());
		$output .= '</div>';

		$output .= '<input type="hidden" name="form_key" value="'. Mage::getSingleton('core/session')->getFormKey() . '" />';
		$output .= '<input type="hidden" name="button" id="' . $form->getId() . '-button" value="" />';

		$output .= '</form>';

		if ($this->isAddJs()) {
			$output .= '<script type="text/javascript">' . "\n";
			$output .= '$$(\'.use-default-checkbox\').each(function(element){ toggleValueElements(element, Element.previous(element.parentNode)); });' . "\n";
			$output .= $this->renderElementsJavaScript($form->getElements());
			$output .= "\n</script>";
		}
		return $output;
	}

	public function renderElementsJavaScript(array $elements, $jFunctionPostfix = '')
	{
		$visibleElements = $this->getVisibleElements($elements);

		$js = '';
		foreach ($visibleElements as $element) {
			$js .= $element->getJavaScript() . "\n";
		}
		$js .= "\n";
		$js .= $this->renderValidatorCallbacks($visibleElements, $jFunctionPostfix);
		$js .= $this->renderOnLoadJs(array('cwRegisterValidatorCallbacks'), $jFunctionPostfix);
		return $js;
	}

	protected function getVisibleElements(array $elements)
	{
		$storeHierarchy = Mage::getModel('unzercw/configurationAdapter')->getStoreHierarchy();
		$visibleElements = array();
		foreach ($elements as $index => $element) {
			if ($storeHierarchy == null || !$element->isGlobalScope() || $this->showAllElements) {
				$visibleElements[$index] = $element;
			}
		}
		return $visibleElements;
	}

	public function setShowAllElements($show) {
		$this->showAllElements = $show;
		return $this;
	}
}