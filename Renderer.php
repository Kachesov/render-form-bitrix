<?php

namespace Korus\Icecat\Admin\Form;

use \Bitrix\Main\Localization\Loc;
use \Korus\Icecat\Utils;

Loc::loadMessages(__FILE__);

class Renderer
{
	protected $formName;
	protected $actionUrl = '';
	protected $fields = [];
	protected $values = [];
	protected $validators = [];
	protected $buttons = [];
	protected $showButtons = true;

	/**
	 * Конструктор класса
	 *
	 * @param string $actionUrl
	 * @param array $fields
	 * @param \ArrayAccess $values
	 * @param array $validators
	 */
	public function __construct($formName, $actionUrl, array $fields, \ArrayAccess $values = null, $validators = [],
								$buttons = [], $showButtons = false)
	{
		$this->formName = $formName;
		$this->actionUrl = $actionUrl;
		$this->fields = $fields;
		$this->values = $values ? : [];
		$this->validators = $validators;
		$this->buttons = $buttons;
		$this->showButtons = $showButtons;
	}

	public function render(\Bitrix\Main\Result $result = null, $values = [])
	{
		$GLOBALS['APPLICATION']->SetAdditionalCss('/bitrix/panel/main/admin-public.css');

		$ret = '';

		if ($values) {
			$this->values = $values;
		}

		$ret .= '<form method="POST" action="' . $this->actionUrl . '" name="' . $this->formName . '">';
		$ret .= \bitrix_sessid_post();

		$ret .= $this->renderResultMessage($result);

		if (array_key_exists('TAB', reset($this->fields))) {
			$ret .= $this->renderFormTabs('tabControl', $this->fields, false);
		}
		else {
			$ret .= $this->renderFormFields($this->fields);
		}

		$ret .= '</form>';

		return $ret;
	}

	public function renderResultMessage(\Bitrix\Main\Result $result = null)
	{
		if (!$result) {
			return '';
		}

		if ($result->isSuccess()) {
			$type = 'OK';
			$data = $result->getData();
			$message = $data['message'] ? : Loc::getMessage('Korus_Icecat_FORM_SAVE_OK');
		}
		else {
			$type = 'ERROR';
			$message = '';
			foreach ($result->getErrorMessages() as $error) {
				$message .= $error . '<br>';
			}
		}

		$msg = new \CAdminMessage(
			[
				'MESSAGE' => $message,
				'TYPE' => $type,
				'HTML' => true,
			]
		);

		return $msg->Show();
	}

	public function renderFormTabs($name, $tabs, $sub = true)
	{
		ob_start();

		$tabs = $this->getListItems($tabs);

		$tabControl = $sub ? new \CAdminViewTabControl($name, $tabs) : new \CAdminTabControl($name, $tabs);

		$tabControl->Begin(['FORM_ACTION' => 'YY']);

		foreach ($tabs as $tabIndex => $tab) {
			$tabControl->BeginNextTab();

			print $this->renderFormFieldComment($tabIndex, $tab);
			print $this->renderFormFields($tab['CONTROLS'], $sub);
		}

		if (!$sub) {
			$tabControl->Buttons(["btnApply" => $this->showButtons, "btnSave" => $this->showButtons]);
		}

		print $this->renderButtons();

		$tabControl->End();

		return ob_get_clean();
	}

	public function renderFormFields($fields, $showWrapper = true)
	{
		$fields = $this->getListItems($fields);

		$ret = '';
		$ret .= $showWrapper ? '<table width="100%" class="adm-detail-content-table edit-table">' : '';

		foreach ($fields as $field => $data) {
			if ($data['TYPE'] == 'HIDDEN') {
				$ret = $this->renderFormFieldHidden($field, $data) . $ret;
			}
			else {
				$ret = $ret . $this->renderFormField($field, $data);
			}
		}

		$ret .= $showWrapper ? '</table>' : '';

		return $ret;
	}

	/**
	 * Отрисовывает кнопки внизу страницы
	 *
	 * @return string
	 */
	public function renderButtons()
	{
		$ret = '';

		foreach ($this->buttons as $button) {
			$ret .= '<input type="submit" id="' . $button['ID'] . '" value="' . $button['TITLE'] . '">';
		}

		return $ret;
	}

	/**
	 * Отрисовывает один филд
	 *
	 * @param  string $field
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormField($field, $data)
	{
		$ret = '';
		$showCaption = !in_array($data['TYPE'], ['TABS', 'HEADER', 'COMMENT']) && $data['SHOW_CAPTION'] != 'N';

		$ret .= '<tr class="' . ($data['TYPE'] == 'HEADER' ? 'heading' : 'adm-detail-content-cell-r') . '">';

		if ($showCaption) {
			$ret .= '<td width="40%" class="adm-detail-content-cell-l">' .
				($data['HELP'] ? $this->renderFormFieldHelp($field, $data) : '') .
				($data['TITLE'] && $this->hasRequiredField($field) ? '<span class="star_required required">*</span>'
					: '') .
				($data['TITLE']) .
				($data['TITLE'] ? ':' : '') .
				'</td>';
		}

		$ret .= '<td' .
			($showCaption ? '' : ' colspan="2"') .
			' class="' .
			($data['TYPE'] == 'HEADER' ? 'heading' : 'adm-detail-content-cell-r') .
			'">';

		$ret .= $this->doRenderFormField($field, $data);

		$ret .= '</td>';

		$ret .= '</tr>';

		return $ret;
	}

	/**
	 * Отрисовывает непосредственный контрол
	 *
	 * @param  $field
	 * @param  $data
	 *
	 * @return string
	 */
	public function doRenderFormField($field, $data)
	{
		if (is_callable($data['TYPE']) && !is_string($data['TYPE'])) {
			$cb = $data['TYPE'];
		}
		else {
			$method = 'renderFormField' . Utils::underScoreToCamelCase($data['TYPE'], true);
			$method = method_exists($this, $method) ? $method : 'renderFormFieldInput';
			$cb = [$this, $method];
		}

		$ret = '';

		$ret .= call_user_func($cb, $field, $data);
		$ret .= $data['COMMENT'] ? '<br><small>' . $data['COMMENT'] . '</small>' : '';

		return $ret;
	}

	/**
	 * Отрисовывает вложенные табы
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldTabs($name, $data)
	{
		return $this->renderFormTabs($name, $data['ITEMS'], true);
	}

	/**
	 * Отрисовывает подзаголовок
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldHeader($name, $data)
	{
		return $data['TITLE'];
	}

	/**
	 * Отрисовывает комментарий
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldComment($name, $data)
	{
		if (!$data['HELP']) {
			return '';
		}

		return '<div id="' .
			$this->getFormFieldId($name) .
			'" class="adm-info-message" style="width: 100%; box-sizing: border-box;">' .
			$data['HELP'] .
			'</div>';
	}

	/**
	 * Отрисовывет просто значение
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldNote($name, $data)
	{
		return '<span id="' . $this->getFormFieldId($name) . '">' . $this->getValue($name, $data) . '</span>';
	}

	/**
	 * Отрисовывает группу контролов
	 *
	 * @param  $name
	 * @param  $data
	 *
	 * @return string
	 */
	public function renderFormFieldControlGroup($name, $data)
	{
		$ret = '';

		$items = $this->getListItems($data['ITEMS']);
		foreach ($items as $name => $subData) {
			$subData['ATTRS'] = array_merge((array)$subData['ATTRS'], (array)$data['ATTRS']);
			$ret .= $this->doRenderFormField($name, $subData) . $data['SPLIT'];
		}

		return rtrim($ret, $data['SPLIT']);
	}

	/**
	 * Отрисовывает input или texarea
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldInput($name, $data)
	{
		if ($data['MULTILINE']) {
			return '<textarea ' .
				$this->getFormFieldAttrs($name, $data) .
				'>' .
				$this->getValue($name, $data) .
				'</textarea>';
		}

		return '<input ' .
			$this->getFormFieldAttrs($name, $data, ['value' => $this->getValue($name, $data), 'type' => 'text']) .
			'>' .
			$data['PREFIX'];
	}

	/**
	 * Отрисовывает hidden input
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldHidden($name, $data)
	{
		return '<input ' .
			$this->getFormFieldAttrs($name, $data, ['value' => $this->getValue($name, $data), 'type' => 'hidden']) .
			'>';
	}

	/**
	 * Отрисовывает checkbox
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldCheckbox($name, $data, $checkValue = 1, $unCheckValue = 0)
	{
		$checkValue = $data['VALUE'] ? : $checkValue;
		$unCheckValue = $data['UNCHECK_VALUE'] ? : $unCheckValue;

		return '' . '<input ' . $this->getFormFieldAttrs(
				$name,
				$data,
				[
					'type' => 'hidden',
					'value' => $unCheckValue,
					'id' => '',
				]
			) . '>'

			. '<input ' . $this->getFormFieldAttrs(
				$name,
				$data,
				[
					'type' => 'checkbox',
					'checked' => $this->getValue($name, $data) == $checkValue,
					'value' => $checkValue,
				]
			) . '>';
	}

	/**
	 * Отрисовывает select
	 *
	 * @param  string $name
	 * @param  array $data
	 *
	 * @return string
	 */
	public function renderFormFieldSelect($name, $data)
	{
		$items = $this->getListItems($data['ITEMS']);

		if (isset($data['NULL'])) {
			$items = ['' => $data['NULL']] + $items;
		}

		$options = '';
		foreach ($items as $value => $title) {
			if (is_array($title)) {
				$value = $title['ID'];
				$title = $title['NAME'] ? : $title['TITLE'];
			}

			$attrs = [
				'value' => $value,
				'selected' => in_array($value, (array)$this->getValue($name, $data)),
			];

			$options .= '<option ' . $this->getFormFieldAttrs(null, null, $attrs) . '>' . $title . '</option>';
		}

		$attrs = [
			'multiple' => ($multiple = (bool)$data['MULTIPLE']),
			'name' => $multiple ? $this->getFormFieldName($name) . '[]' : $this->getFormFieldName($name),
		];

		return '<select ' . $this->getFormFieldAttrs($name, $data, $attrs) . '>' . $options . '</select>';
	}

	public function renderFormFieldHelp($name, $data)
	{
		$ret = '';

		if ($data['HELP']) {
			$ret .= '<img src="/bitrix/js/main/core/images/hint.gif" title="' .
				$data['HELP'] .
				'" style="margin: 0 5px">';
		}

		return $ret;
	}

	public function renderFormFieldDate($name, $data)
	{
		if ($data['ATTRS']['disabled']) {
			return $this->renderFormFieldInput($name, $data);
		}

		ob_start();

		$GLOBALS['APPLICATION']->IncludeComponent(
			'bitrix:main.calendar',
			'.default',
			[
				'SHOW_INPUT' => 'Y',
				'FORM_NAME' => '',
				'INPUT_NAME' => $this->getFormFieldName($name),
				'INPUT_VALUE' => $this->getValue($name, $data),
				'SHOW_TIME' => $data['SHOW_TIME'],
				'HIDE_TIMEBAR' => $data['HIDE_TIMEBAR'],
			]
		);

		return ob_get_clean();
	}

	public function renderFormFieldButton($name, $data)
	{
		$attrs = [
			'type' => 'button',
			'title' => $data['TITLE'],
		];

		return '<input ' . $this->getFormFieldAttrs($name, $data, $attrs) . '>';
	}

	public function renderFormFieldLink($name, $data)
	{
		$value = $this->getValue($name, $data);

		if (empty($value)) {
			return '(пусто)';
		}

		$attrs = [
			'href' => $value,
			'target' => '_blank',
		];

		return '<a ' .
			$this->getFormFieldAttrs(null, $data, $attrs) .
			'>' .
			($data['CAPTION'] ? : Loc::getMessage('Korus_Icecat_FORM_LINK_LABEL')) .
			'</a>';
	}

	protected function getListItems($items)
	{
		return is_callable($items) ? call_user_func_array($items, [$this->values]) : $items;
	}

	protected function getFormFieldId($fieldName)
	{
		return $this->formName . '_' . $fieldName;
	}

	protected function getFormFieldName($fieldName)
	{
		return $this->formName . '[' . $fieldName . ']';
	}

	protected function getFormFieldAttrs($name, $data)
	{
		$attrs = array_merge(
			[
				[
					'id' => $name ? $this->getFormFieldId($name) : false,
					'name' => $name ? $this->getFormFieldName($name) : false,
				]
			],

			$data['ATTRS'] ? [$data['ATTRS']] : [],

			array_slice(func_get_args(), 2)
		);

		$attrs = call_user_func_array('array_merge', $attrs);

		$ret = '';
		foreach ($attrs as $name => $value) {
			if ($value === false) {
				continue;
			}

			if ($value === true) {
				$value = $name;
			}

			$ret .= $name . '="' . htmlspecialcharsbx($value) . '" ';
		}

		return trim($ret);
	}

	protected function getValue($name, $data)
	{
		if (isset($this->values[$name])) {
			return $this->values[$name];
		}

		return $data['DEFAULT'] ? : '';
	}

	/**
	 * Возвращает является ли поле обязательным для заполнения
	 *
	 * @param  string $fieldName
	 *
	 * @return boolean
	 */
	protected function hasRequiredField($fieldName)
	{
		if (!isset($this->validators[$fieldName])) {
			return false;
		}

		return array_key_exists('required', $this->validators[$fieldName]);
	}
}