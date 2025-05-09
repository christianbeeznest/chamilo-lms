<?php

/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Component\HTMLPurifier\Filter\RemoveOnAttributes;

/**
 * Class FormValidator
 * create/manipulate/validate user input.
 */
class FormValidator extends HTML_QuickForm
{
    public const LAYOUT_HORIZONTAL = 'horizontal';
    public const LAYOUT_INLINE = 'inline';
    public const LAYOUT_BOX = 'box';
    public const LAYOUT_BOX_NO_LABEL = 'box-no-label';
    public const LAYOUT_BOX_SEARCH = 'box-search';
    public const LAYOUT_GRID = 'grid';

    public $with_progress_bar = false;
    private $layout;

    /**
     * @param string      $name        Name of the form
     * @param string      $method      (optional) Method ('post' (default) or 'get')
     * @param string      $action      (optional) Action (default is $PHP_SELF)
     * @param string|null $target      (optional) Form's target defaults to '_self'
     * @param mixed       $attributes  (optional) Extra attributes for <form> tag
     * @param bool        $trackSubmit Whether to track if the form was submitted by adding a special hidden field
     */
    public function __construct(
        string $name,
        ?string $method = 'post',
        ?string $action = '',
        ?string $target = '',
        ?array $attributes = [],
        string $layout = self::LAYOUT_HORIZONTAL,
        bool $trackSubmit = true
    ) {
        if (null === $attributes) {
            $attributes = [];
        }

        if (isset($attributes['class']) && str_contains($attributes['class'], 'form-search')) {
            $layout = self::LAYOUT_INLINE;
        }

        $this->setLayout($layout);

        // Form template
        $formTemplate = $this->getFormTemplate();

        switch ($layout) {
            case self::LAYOUT_BOX_SEARCH:
            case self::LAYOUT_INLINE:
                $attributes['class'] = 'flex flex-row gap-3 items-center ';
                break;
            case self::LAYOUT_BOX:
                $attributes['class'] = 'ch flex gap-1 ';
                break;
            case self::LAYOUT_GRID:
                $attributes['class'] = 'ch form-grid';
                $formTemplate = $this->getGridFormTemplate();
                break;
        }

        parent::__construct($name, $method, $action, $target, $attributes, $trackSubmit);

        // Modify the default templates
        $renderer = &$this->defaultRenderer();
        $renderer->setFormTemplate($formTemplate);

        // Element template
        if ((isset($attributes['class']) && 'form--inline' === $attributes['class']) ||
            (self::LAYOUT_INLINE === $layout || self::LAYOUT_BOX_SEARCH === $layout)
        ) {
            $elementTemplate = ' {label}  {element} ';
            $renderer->setElementTemplate($elementTemplate);
        } elseif (isset($attributes['class']) && 'form-search' === $attributes['class']) {
            $elementTemplate = ' {label}  {element} ';
            $renderer->setElementTemplate($elementTemplate);
        } else {
            $renderer->setElementTemplate($this->getDefaultElementTemplate());

            // Display a gray div in the buttons
            $templateSimple = '<div class="form-actions">{label} {element}</div>';
            $renderer->setElementTemplate($templateSimple, 'submit_in_actions');

            //Display a gray div in the buttons + makes the button available when scrolling
            $templateBottom = '<div class="form-actions bottom_actions bg-form">{label} {element}</div>';
            $renderer->setElementTemplate($templateBottom, 'submit_fixed_in_bottom');
            $renderer->setElementTemplate($templateSimple, 'buttons_in_action');

            $templateSimpleRight = '<div class="form-actions"> <div class="pull-right">{label} {element}</div></div>';
            $renderer->setElementTemplate($templateSimpleRight, 'buttons_in_action_right');
        }

        //Set Header template
        $renderer->setHeaderTemplate(' <h1 class="text-h3 font-small text-gray-800 mb-4">{header}<hr /></h1>');

        $required = '<span class="form_required">*</span> <small>'.get_lang('Required field').'</small>';
        if ((self::LAYOUT_INLINE === $layout || self::LAYOUT_BOX_SEARCH === $layout)) {
            $required = '';
        }
        // Set required field template
        $this->setRequiredNote($required);

        if (self::LAYOUT_BOX_SEARCH !== $layout) {
            $noteTemplate = <<<EOT
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">{requiredNote}</div>
	</div>
EOT;
            $renderer->setRequiredNoteTemplate($noteTemplate);
        }
    }

    public function getFormTemplate(): string
    {
        if (self::LAYOUT_BOX_SEARCH == $this->layout) {
            return '<form {attributes}>
                    <div class="form__group form__group--inline p-inputgroup">
                        {content}
                        {hidden}
                    </div>
                </form>';
        }

        return '<form{attributes}>
                {content}
                {hidden}
            </form>';
    }

    public function getGridFormTemplate(): string
    {
        return '
        <style>
            .form_list {
                display: grid;
                grid-template-columns:  repeat(auto-fill, minmax(300px, 1fr));;
                grid-gap: 10px 30px;
                gap: 10px 30px;
            }
            .form_list .input-group {
                display:block;
            }
        </style>
        <form{attributes}>
            <div class="form_list">
                {content}
            </div>
        {hidden}
        </form>';
    }

    /**
     * @todo this function should be added in the element class
     */
    public function getDefaultElementTemplate(): string
    {
        return '
            <div class="row mb-3 {error_class}">
                <label {label-for} class="col-sm-2 col-form-label {extra_label_class}" >
                    <!-- BEGIN required --><span class="form_required">*</span><!-- END required -->
                    {label}
                </label>
                <div class="col-sm-8">
                    {icon}
                    {element}
                    <!-- BEGIN label_2 -->
                        <p class="help-block">{label_2}</p>
                    <!-- END label_2 -->

                    <!-- BEGIN error -->
                        <span class="help-inline help-block">{error}</span>
                    <!-- END error -->
                </div>
                <div class="col-sm-2">
                    <!-- BEGIN label_3 -->
                        {label_3}
                    <!-- END label_3 -->
                </div>
            </div>';
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function setLayout(string $layout)
    {
        $this->layout = $layout;
    }

    /**
     * Adds a text field to the form.
     * A trim-filter is attached to the field.
     *
     * @param string       $name       The element name
     * @param string|array $label      The label for the form-element
     * @param bool         $required   (optional)    Is the form-element required (default=true)
     * @param array        $attributes (optional)    List of attributes for the form-element
     * @param bool         $createElement
     *
     * @throws Exception
     *
     * @return HTML_QuickForm_text
     */
    public function addText($name, $label, $required = true, $attributes = [], $createElement = false)
    {
        if ($createElement) {
            $element = $this->createElement('text', $name, $label, $attributes);
        } else {
            $element = $this->addElement('text', $name, $label, $attributes);
        }

        $this->applyFilter($name, 'trim');
        $this->applyFilter($name, 'html_filter');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        return $element;
    }

    /**
     * Add hidden course params.
     */
    public function addCourseHiddenParams()
    {
        $this->addHidden('cid', api_get_course_int_id());
        $this->addHidden('sid', api_get_session_id());
    }

    /**
     * The "date_range_picker" element creates 2 hidden fields
     * "elementName" + "_start"  and "elementName" + "_end"
     * For example if the name is "range", you will have 2 new fields
     * when executing $form->getSubmitValues()
     * "range_start" and "range_end".
     *
     * @param string $name
     * @param string $label
     * @param bool   $required
     * @param array  $attributes
     */
    public function addDateRangePicker($name, $label, $required = true, $attributes = [])
    {
        $this->addElement('date_range_picker', $name, $label, $attributes);
        $this->addElement('hidden', $name.'_start');
        $this->addElement('hidden', $name.'_end');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }
    }

    /**
     * @param string $name
     * @param string $label
     * @param array  $attributes
     *
     * @return mixed
     */
    public function addSelectLanguage($name, $label, $options = [], $attributes = [])
    {
        return $this->addElement('SelectLanguage', $name, $label, $options, $attributes);
    }

    public function addSelectTheme($name, $label, $options = [], $attributes = [])
    {
        return $this->addElement('SelectTheme', $name, $label, $options, $attributes);
    }

    /**
     * @param string       $name
     * @param string|array $label
     * @param array        $options
     * @param array        $attributes
     *
     * @return SelectAjax
     */
    public function addSelectAjax($name, $label, $options = [], $attributes = [])
    {
        if (!isset($attributes['url'])) {
            throw new \Exception('select_ajax needs an URL');
        }

        return $this->addElement(
            'select_ajax',
            $name,
            $label,
            $options,
            $attributes
        );
    }

    /**
     * @param string $name
     * @param string $label
     * @param array  $attributes
     *
     * @return DatePicker
     */
    public function addDatePicker($name, $label, $attributes = [])
    {
        return $this->addElement('DatePicker', $name, $label, $attributes);
    }

    /**
     * @param string       $name
     * @param string|array $label
     * @param array        $attributes
     *
     * @return DateTimePicker
     */
    public function addDateTimePicker($name, $label, $attributes = [])
    {
        return $this->addElement('DateTimePicker', $name, $label, $attributes);
    }

    /**
     * @param string       $name
     * @param string|array $label
     * @param array        $attributes
     *
     * @return DateTimeRangePicker
     */
    public function addDateTimeRangePicker($name, $label, $attributes = [])
    {
        return $this->addElement('DateTimeRangePicker', $name, $label, $attributes);
    }

    /**
     * @param string $name
     * @param string|mixed $value
     * @param array  $attributes
     */
    public function addHidden($name, $value, $attributes = [])
    {
        $this->addElement('hidden', $name, $value, $attributes);
    }

    /**
     * @param string       $name
     * @param string|array $label
     * @param array        $attributes
     * @param bool         $required
     *
     * @return HTML_QuickForm_textarea
     */
    public function addTextarea($name, $label, $attributes = [], $required = false)
    {
        $element = $this->addElement('textarea', $name, $label, $attributes);

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        return $element;
    }

    /**
     * @param string $name
     * @param string $label
     * @param string $icon          font-awesome
     * @param string $style         default|primary|success|info|warning|danger|link
     * @param string $size          large|default|small|extra-small
     * @param string $class         Example plus is transformed to icon fa fa-plus
     * @param array  $attributes
     * @param bool   $createElement
     *
     * @return HTML_QuickForm_button
     */
    public function addButton(
        $name,
        $label,
        $icon = 'check',
        $style = 'default',
        $size = 'default',
        $class = null,
        $attributes = [],
        $createElement = false
    ) {
        if ($createElement) {
            return $this->createElement(
                'button',
                $name,
                $label,
                $icon,
                $style,
                $size,
                $class,
                $attributes
            );
        }

        return $this->addElement(
            'button',
            $name,
            $label,
            $icon,
            $style,
            $size,
            $class,
            $attributes
        );
    }

    /**
     * Returns a button with the primary color and a check mark.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     * @param array  $attributes
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonSave($label, $name = 'submit', $createElement = false, $attributes = [])
    {
        return $this->addButton(
            $name,
            $label,
            'check',
            'primary',
            null,
            null,
            $attributes,
            $createElement
        );
    }

    /**
     * Returns a cancel button.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonCancel($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'close',
            'danger',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a button with the primary color and a "plus" icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     * @param array  $attributes    Additional attributes
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonCreate($label, $name = 'submit', $createElement = false, $attributes = [])
    {
        return $this->addButton(
            $name,
            $label,
            'plus',
            'primary',
            null,
            null,
            $attributes,
            $createElement
        );
    }

    /**
     * Returns a button with the primary color and a pencil icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonUpdate($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'pencil',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a button with the danger color and a trash icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonDelete($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'delete',
            'danger',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a move style button.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonMove($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'arrow-right-bold-circle',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a button with the primary color and a paper-plane icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     * @param array  $attributes
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonSend($label, $name = 'submit', $createElement = false, $attributes = [])
    {
        return $this->addButton(
            $name,
            $label,
            'send',
            'primary',
            null,
            null,
            $attributes,
            $createElement
        );
    }

    /**
     * Returns a button with the default (grey?) color and a magnifier icon.
     *
     * @param string $label Text appearing on the button
     * @param string $name  Element name (for form treatment purposes)
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonSearch($label = null, $name = 'submit')
    {
        if (empty($label)) {
            $label = get_lang('Search');
        }

        return $this->addButton($name, $label, 'magnify', 'primary');
    }

    /**
     * Returns a button with the primary color and a right-pointing arrow icon.
     *
     * @param string $label      Text appearing on the button
     * @param string $name       Element name (for form treatment purposes)
     * @param array  $attributes Additional attributes
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonNext($label, $name = 'submit', $attributes = [])
    {
        return $this->addButton(
            $name,
            $label,
            'arrow-right',
            'primary',
            null,
            null,
            $attributes
        );
    }

    /**
     * Returns a button with the primary color and a check mark icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonImport($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'check',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a button with the primary color and a check-mark icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonExport($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'check',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Shortcut to filter button.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonFilter($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'filter',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Shortcut to reset button.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonReset($label, $name = 'reset', $createElement = false)
    {
        $icon = 'eraser';
        $style = 'default';
        $size = 'default';
        $class = null;
        $attributes = [];

        if ($createElement) {
            return $this->createElement(
                'reset',
                $name,
                $label,
                $icon,
                $style,
                $size,
                $class,
                $attributes
            );
        }

        return $this->addElement(
            'reset',
            $name,
            $label,
            $icon,
            $style,
            $size,
            $class,
            $attributes
        );
    }

    /**
     * Returns a button with the primary color and an upload icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonUpload($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'upload',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a button with the primary color and a download icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonDownload($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'download',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a button with the primary color and a magnifier icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonPreview($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'magnify',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * Returns a button with the primary color and a copy (double sheet) icon.
     *
     * @param string $label         Text appearing on the button
     * @param string $name          Element name (for form treatment purposes)
     * @param bool   $createElement Whether to use the create or add method
     *
     * @return HTML_QuickForm_button
     */
    public function addButtonCopy($label, $name = 'submit', $createElement = false)
    {
        return $this->addButton(
            $name,
            $label,
            'copy',
            'primary',
            null,
            null,
            [],
            $createElement
        );
    }

    /**
     * @param string $name
     * @param string $label
     * @param string $text
     * @param array  $attributes
     *
     * @return HTML_QuickForm_checkbox
     */
    public function addCheckBox($name, $label, $text = '', $attributes = [])
    {
        return $this->addElement('checkbox', $name, $label, $text, $attributes);
    }

    /**
     * @param string $name
     * @param string $label
     * @param array  $options
     * @param array  $attributes
     *
     * @return HTML_QuickForm_group
     */
    public function addCheckBoxGroup($name, $label, $options = [], $attributes = [])
    {
        $group = [];
        foreach ($options as $value => $text) {
            $attributes['value'] = $value;
            $group[] = $this->createElement(
                'checkbox',
                $value,
                null,
                $text,
                $attributes
            );
        }

        return $this->addGroup($group, $name, $label);
    }

    /**
     * @param string $name
     * @param string $label
     * @param array  $options
     * @param array  $attributes
     *
     * @return HTML_QuickForm_group
     */
    public function addRadio($name, $label, $options = [], $attributes = [])
    {
        $group = [];
        foreach ($options as $key => $value) {
            $group[] = $this->createElement('radio', null, null, $value, $key, $attributes);
        }

        return $this->addGroup($group, $name, $label);
    }

    /**
     * @param string|array $label
     *
     * @return HTML_QuickForm_select
     */
    public function addSelect(string $name, $label, ?array $options = [], array $attributes = [])
    {
        return $this->addElement('select', $name, $label, $options, $attributes);
    }

    /**
     * @param $name
     * @param $label
     * @param $collection
     * @param array  $attributes
     * @param bool   $addNoneOption
     * @param string $textCallable  set a function getStringValue() by default __toString()
     *
     * @return HTML_QuickForm_element
     */
    public function addSelectFromCollection(
        $name,
        $label,
        $collection,
        $attributes = [],
        $addNoneOption = false,
        $textCallable = ''
    ) {
        $options = [];

        if ($addNoneOption) {
            $options[0] = get_lang('None');
        }

        if (!empty($collection)) {
            foreach ($collection as $item) {
                $text = $item;
                if (!empty($textCallable)) {
                    $text = $item->$textCallable();
                }
                $options[$item->getId()] = $text;
            }
        }

        return $this->addElement('select', $name, $label, $options, $attributes);
    }

    public function addMultiSelect(string $name, $label, array $options, array $attributes = [])
    {
        $this->addElement('advmultiselect', $name, $label, $options, $attributes);
    }

    /**
     * @param string $label
     * @param string $text
     * @param bool   $createElement
     *
     * @return HTML_QuickForm_Element
     */
    public function addLabel($label, $text, $createElement = false)
    {
        if ($createElement) {
            return $this->createElement(
                'label',
                $label,
                $text
            );
        }

        return $this->addElement('label', $label, $text);
    }

    /**
     * @param string $text
     */
    public function addHeader($text)
    {
        if (!empty($text)) {
            $this->addElement('header', $text);
        }
    }

    /**
     * @param string       $name
     * @param string|array $label
     * @param array        $attributes
     *
     * @throws Exception if the file doesn't have an id
     *
     * @return HTML_QuickForm_file
     */
    public function addFile($name, $label, $attributes = [])
    {
        try {
            $element = $this->addElement('file', $name, $label, $attributes);
            if (isset($attributes['crop_image'])) {
                $id = $element->getAttribute('id');
                if (empty($id)) {
                    throw new Exception('If you use the crop functionality the element must have an id');
                }
                $this->addHtml(
                    '
                <div class="form-group row" id="'.$id.'-form-group" style="display: none;">
                    <div class="offset-md-2 col-sm-8">
                        <div class="card-cropper">
                            <div id="'.$id.'_crop_image" class="cropCanvas">
                                <img id="'.$id.'_preview_image">
                            </div>
                            <button class="btn btn--primary" type="button" name="cropButton" id="'.$id.'_crop_button">
                                <em class="fa fa-crop"></em> '.get_lang('Crop your picture').'
                            </button>
                        </div>
                    </div>
                </div>'
                );
                $this->addHidden($id.'_crop_result', '');
                $this->addHidden($id.'_crop_result_for_resource', '');
                $this->addHidden($id.'_crop_image_base_64', '');
            }
        } catch (HTML_Quick | Form_Error $e) {
            var_dump($e->getMessage());
        }

        return $element;
    }

    /**
     * @param string $snippet
     */
    public function addHtml($snippet)
    {
        if (empty($snippet)) {
            return false;
        }
        $this->addElement('html', $snippet);

        return true;
    }

    public function addStartPanel(string $id, string $title, bool $open = false, $icon = null): void
    {
        // Same code as in Display::panelCollapse
        $parent = null;
        $javascript = '
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const buttons = document.querySelectorAll("#card_'.$id.' a");
                const menus = document.querySelectorAll("#collapse_'.$id.'");

                buttons.forEach((button, index) => {
                    button.addEventListener("click", function() {
                        menus.forEach((menu, menuIndex) => {
                            if (index === menuIndex) {
                                button.setAttribute("aria-expanded", "true" === button.getAttribute("aria-expanded") ? "false" : "true")
                                button.classList.toggle("mdi-chevron-down")
                                button.classList.toggle("mdi-chevron-up")
                                menu.classList.toggle("active");
                            } else {
                                menu.classList.remove("active");
                            }
                        });
                    });
                });
            });
        </script>';

        $this->addHtml($javascript);

        $htmlIcon = '';
        if ($icon) {
            $htmlIcon = Display::getMdiIcon($icon, 'ch-tool-icon', 'float:left;', ICON_SIZE_SMALL);
        }
        $html = '
        <div class="display-panel-collapse field">
            <div class="display-panel-collapse__header" id="card_'.$id.'">
                <a role="button"
                    class="mdi mdi-chevron-down"
                    data-toggle="collapse"
                    data-target="#collapse_'.$id.'"
                    aria-expanded="'.(($open) ? 'true' : 'false').'"
                    aria-controls="collapse_'.$id.'"
                >
                    '.$htmlIcon.'&nbsp;'.$title.'
                </a>
            </div>
            <div
                id="collapse_'.$id.'"
                class="display-panel-collapse__collapsible '.(($open) ? 'active' : '').'"
            >
                <div id="collapse_contant_'.$id.'"  class="card-body ">';

        $this->addHtml($html);
    }

    public function addEndPanel(): void
    {
        $this->addHtml('</div></div></div>');
    }

    /**
     * Draws a panel of options see the course_info/infocours.php page.
     *
     * @param string $name      internal name
     * @param string $title     visible title
     * @param array  $groupList list of group or elements
     */
    public function addPanelOption($name, $title, $groupList, $icon, $open)
    {
        $this->addStartPanel($name, $title, $open, $icon);

        foreach ($groupList as $groupName => $group) {
            // Add group array
            if (!empty($groupName) && is_array($group)) {
                $this->addGroup($group, '', $groupName);
            }
            // Add element
            if ($group instanceof HTML_QuickForm_element) {
                $this->addElement($group);
            }
        }

        $this->addEndPanel();
    }

    /**
     * Adds a HTML-editor to the form.
     *
     * @param string       $name
     * @param string|array $label      The label for the form-element
     * @param bool         $required   (optional) Is the form-element required (default=true)
     * @param bool         $fullPage   (optional) When it is true, the editor loads completed html code for a full page
     * @param array        $config     (optional) Configuration settings for the online editor
     * @param array        $attributes
     *
     * @throws Exception
     * @throws HTML_QuickForm_Error
     */
    public function addHtmlEditor(
        $name,
        $label,
        $required = true,
        $fullPage = false,
        $config = [],
        $attributes = []
    ) {
        $attributes['rows'] = $config['rows'] ?? 15;
        $attributes['cols'] = $config['cols'] ?? 80;
        $attributes['cols-size'] = $config['cols-size'] ?? [];
        $attributes['class'] = $config['class'] ?? [];
        $cleanName = str_replace(['[', ']', '#'], '', $name);

        if (empty($attributes['id'])) {
            $attributes['id'] = $cleanName;
        }

        //$attributes['id'] = $config['id'] ?? 'editor_'.$cleanName;

        $this->addElement('html_editor', $name, $label, $attributes, $config);
        $this->applyFilter($name, 'trim');
        $this->applyFilter($name, 'attr_on_filter');
        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        /** @var HtmlEditor $element */
        $element = $this->getElement($name);
        $config['style'] = $config['style'] ?? false;
        if ($fullPage) {
            $config['fullPage'] = true;
            // Adds editor_content.css in ckEditor
            $config['style'] = true;
        }

        if ($element->editor) {
            $element->editor->processConfig($config);
        }
    }

    /**
     * Prevent execution of event handlers in HTML elements.
     *
     * @param string $html
     * @return string
     */
    function attr_on_filter($html) {
        $pattern = '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';
        return preg_replace($pattern, '', $html);
    }

    /**
     * Adds a Google Maps Geolocalization field to the form.
     *
     * @param      $name
     * @param      $label
     * @param bool $hideGeoLocalizationDetails
     */
    public function addGeoLocationMapField($name, $label, $dataValue, $hideGeoLocalizationDetails = false)
    {
        $gMapsPlugin = GoogleMapsPlugin::create();
        $geolocalization = 'true' === $gMapsPlugin->get('enable_api');

        if ($geolocalization && false === $gMapsPlugin->javascriptIncluded) {
            $gmapsApiKey = $gMapsPlugin->get('api_key');
            $url = '//maps.googleapis.com/maps/api/js?key='.$gmapsApiKey;
            $this->addHtml('<script type="text/javascript" src="'.$url.'" ></script>');
            $gMapsPlugin->javascriptIncluded = true;
        }

        $this->addElement(
            'text',
            $name,
            $label,
            ['id' => $name]
        );

        $this->addHidden(
            $name.'_coordinates',
            '',
            ['id' => $name.'_coordinates']
        );

        $this->applyFilter($name, 'stripslashes');
        $this->applyFilter($name, 'trim');

        $this->addHtml(Extrafield::getLocalizationJavascript($name, $dataValue));

        if ($hideGeoLocalizationDetails) {
            $this->addHtml('<div style="display:none">');
        }

        $this->addHtml(
            Extrafield::getLocalizationInput($name, $label)
        );

        if ($hideGeoLocalizationDetails) {
            $this->addHtml('</div>');
        }
    }

    /**
     * @param string       $name
     * @param string|array $label
     *
     * @return mixed
     */
    public function addButtonAdvancedSettings($name, $label = null)
    {
        $label = !empty($label) ? $label : get_lang('Advanced settings');

        return $this->addElement('advanced_settings', $name, $label);
    }

    /**
     * Adds a progress loading image to the form.
     */
    public function addProgress($delay = 2, $label = '')
    {
        if (empty($label)) {
            $label = get_lang('Please stand by...');
        }
        $this->with_progress_bar = true;
        $id = $this->getAttribute('id');

        $this->updateAttributes("onsubmit=\"javascript: addProgress('".$id."')\"");
        $this->addHtml('<script language="javascript" src="'.api_get_path(WEB_LIBRARY_PATH).'javascript/upload.js" type="text/javascript"></script>');
    }

    /**
     * This function has been created for avoiding changes directly within QuickForm class.
     * When we use it, the element is threated as 'required' to be dealt during validation.
     *
     * @param array  $elements The array of elements
     * @param string $message  The message displayed
     */
    public function add_multiple_required_rule($elements, $message)
    {
        $this->_required[] = $elements[0];
        $this->addRule($elements, $message, 'multiple_required');
    }

    /**
     * Displays the form.
     * If an element in the form didn't validate, an error message is showed
     * asking the user to complete the form.
     */
    public function display()
    {
        echo $this->returnForm();
    }

    /**
     * Returns the HTML code of the form.
     *
     * @return string $return_value HTML code of the form
     */
    public function returnForm()
    {
        $returnValue = '';
        /** @var HTML_QuickForm_element $element */
        foreach ($this->_elements as &$element) {
            $element->setLayout($this->getLayout());
            $elementError = parent::getElementError($element->getName());
            if (!is_null($elementError)) {
                $returnValue .= Display::return_message($elementError, 'warning').'<br />';
                break;
            }
        }

        $returnValue .= parent::toHtml();
        // Add div-element which is to hold the progress bar
        $id = $this->getAttribute('id');
        if (isset($this->with_progress_bar) && $this->with_progress_bar) {
            // @todo improve UI
            $returnValue .= '<br />
            <div id="loading_div_'.$id.'" class="loading_div" style="display:none;margin-left:40%; margin-top:10px; height:50px;">
                <div class="wobblebar-loader"></div>
            </div>
            ';
        }

        return $returnValue;
    }

    /**
     * Returns the HTML code of the form.
     * If an element in the form didn't validate, an error message is showed
     * asking the user to complete the form.
     *
     * @return string $return_value HTML code of the form
     *
     * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University, august 2006
     * @author Julio Montoya
     *
     * @deprecated use returnForm()
     */
    public function return_form()
    {
        return $this->returnForm();
    }

    /**
     * @return HTML_QuickForm_Renderer_Default
     */
    public static function getDefaultRenderer()
    {
        return
            isset($GLOBALS['_HTML_QuickForm_default_renderer']) ?
                $GLOBALS['_HTML_QuickForm_default_renderer'] : null;
    }

    /**
     * Adds a input of type url to the form.
     *
     * @param string $name       The label for the form-element
     * @param string $label      The element name
     * @param bool   $required   Optional. Is the form-element required (default=true)
     * @param array  $attributes Optional. List of attributes for the form-element
     */
    public function addUrl($name, $label, $required = true, $attributes = [])
    {
        $this->addElement('url', $name, $label, $attributes);
        $this->applyFilter($name, 'trim');
        $this->addRule($name, get_lang('Insert a valid URL'), 'url');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }
    }

    /**
     * Adds a text field for letters to the form.
     * A trim-filter is attached to the field.
     *
     * @param string $name       The element name
     * @param string $label      The label for the form-element
     * @param bool   $required   Optional. Is the form-element required (default=true)
     * @param array  $attributes Optional. List of attributes for the form-element
     */
    public function addTextLettersOnly(
        $name,
        $label,
        $required = false,
        $attributes = []
    ) {
        $attributes = array_merge(
            $attributes,
            [
                'pattern' => '[a-zA-ZñÑ]+',
                'title' => get_lang('Only letters'),
            ]
        );

        $this->addElement(
            'text',
            $name,
            [
                $label,
                get_lang('Only letters'),
            ],
            $attributes
        );

        $this->applyFilter($name, 'trim');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        $this->addRule(
            $name,
            get_lang('Only letters'),
            'regex',
            '/^[a-zA-ZñÑ]+$/'
        );
    }

    /**
     * @param string $name
     * @param string $label
     * @param array  $attributes
     * @param bool   $required
     *
     * @return HTML_QuickForm_element
     */
    public function addNumeric($name, $label, $attributes = [], $required = false)
    {
        $element = $this->addElement('Number', $name, $label, $attributes);

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        return $element;
    }

    /**
     * Adds a text field for alphanumeric characters to the form.
     * A trim-filter is attached to the field.
     *
     * @param string $name       The element name
     * @param string $label      The label for the form-element
     * @param bool   $required   Optional. Is the form-element required (default=true)
     * @param array  $attributes Optional. List of attributes for the form-element
     */
    public function addTextAlphanumeric(
        $name,
        $label,
        $required = false,
        $attributes = []
    ) {
        $attributes = array_merge(
            $attributes,
            [
                'pattern' => '[a-zA-Z0-9ñÑ]+',
                'title' => get_lang('Only lettersAndNumbers'),
            ]
        );

        $this->addElement(
            'text',
            $name,
            [
                $label,
                get_lang('Only lettersAndNumbers'),
            ],
            $attributes
        );

        $this->applyFilter($name, 'trim');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        $this->addRule(
            $name,
            get_lang('Only lettersAndNumbers'),
            'regex',
            '/^[a-zA-Z0-9ÑÑ]+$/'
        );
    }

    /**
     * @param string $name
     * @param $label
     * @param bool  $required
     * @param array $attributes
     * @param bool  $allowNegative
     * @param int   $minValue
     * @param null  $maxValue
     */
    public function addFloat(
        $name,
        $label,
        $required = false,
        $attributes = [],
        $allowNegative = false,
        $minValue = null,
        $maxValue = null
    ) {
        $this->addElement(
            'FloatNumber',
            $name,
            $label,
            $attributes
        );

        $this->applyFilter($name, 'trim');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        // Rule allows "," and "."
        /*$this->addRule(
            $name,
            get_lang('Only numbers'),
            'regex',
            '/(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)|(^-?\d\d*\,\d*$)|(^-?\,\d\d*$)/'
        );*/

        if (false == $allowNegative) {
            $this->addRule(
                $name,
                get_lang('Negative value'),
                'compare',
                '>=',
                'server',
                false,
                false,
                0
            );
        }

        if (!is_null($minValue)) {
            $this->addRule(
                $name,
                get_lang('Under the minimum.'),
                'compare',
                '>=',
                'server',
                false,
                false,
                $minValue
            );
        }

        if (!is_null($maxValue)) {
            $this->addRule(
                $name,
                get_lang('Value exceeds score.'),
                'compare',
                '<=',
                'server',
                false,
                false,
                $maxValue
            );
        }
    }

    /**
     * Adds a text field for letters and spaces to the form.
     * A trim-filter is attached to the field.
     *
     * @param string $name       The element name
     * @param string $label      The label for the form-element
     * @param bool   $required   Optional. Is the form-element required (default=true)
     * @param array  $attributes Optional. List of attributes for the form-element
     */
    public function addTextLettersAndSpaces(
        $name,
        $label,
        $required = false,
        $attributes = []
    ) {
        $attributes = array_merge(
            $attributes,
            [
                'pattern' => '[a-zA-ZñÑ\s]+',
                'title' => get_lang('Only lettersAndSpaces'),
            ]
        );

        $this->addElement(
            'text',
            $name,
            [
                $label,
                get_lang('Only lettersAndSpaces'),
            ],
            $attributes
        );

        $this->applyFilter($name, 'trim');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        $this->addRule(
            $name,
            get_lang('Only lettersAndSpaces'),
            'regex',
            '/^[a-zA-ZñÑ\s]+$/'
        );
    }

    /**
     * Adds a text field for alphanumeric and spaces characters to the form.
     * A trim-filter is attached to the field.
     *
     * @param string $name       The element name
     * @param string $label      The label for the form-element
     * @param bool   $required   Optional. Is the form-element required (default=true)
     * @param array  $attributes Optional. List of attributes for the form-element
     */
    public function addTextAlphanumericAndSpaces(
        $name,
        $label,
        $required = false,
        $attributes = []
    ) {
        $attributes = array_merge(
            $attributes,
            [
                'pattern' => '[a-zA-Z0-9ñÑ\s]+',
                'title' => get_lang('Only lettersAndNumbersAndSpaces'),
            ]
        );

        $this->addElement(
            'text',
            $name,
            [
                $label,
                get_lang('Only lettersAndNumbersAndSpaces'),
            ],
            $attributes
        );

        $this->applyFilter($name, 'trim');

        if ($required) {
            $this->addRule($name, get_lang('Required field'), 'required');
        }

        $this->addRule(
            $name,
            get_lang('Only lettersAndNumbersAndSpaces'),
            'regex',
            '/^[a-zA-Z0-9ñÑ\s]+$/'
        );
    }

    /**
     * @param string $url
     * @param string $urlToRedirect after upload redirect to this page
     */
    public function addMultipleUpload($url, $urlToRedirect = '')
    {
        $inputName = 'input_file_upload';
        $this->addMultipleUploadJavascript($url, $inputName, $urlToRedirect);

        $this->addHtml('
            <div class="description-upload">
            '.get_lang('Click on the box below to select files from your computer (you can use CTRL + clic to select various files at a time), or drag and drop some files from your desktop directly over the box below. The system will handle the rest!').'
            </div>
            <span class="btn btn--success fileinput-button">
                <i class="glyphicon glyphicon-plus"></i>
                <span>'.get_lang('Add files').'</span>
                <!-- The file input field used as target for the file upload widget -->
                <input id="'.$inputName.'" type="file" name="files[]" multiple>
            </span>
            <div id="dropzone">
                <div class="button-load">
                '.get_lang('Click or drag and drop files here to upload them').'
                </div>
            </div>
            <br />
            <!-- The global progress bar -->
            <div id="progress" class="progress">
                <div class="progress-bar progress-bar-success"></div>
            </div>
            <div id="files" class="files"></div>
        ');
    }

    /**
     * @param string $elementName
     * @param string $groupName   if element is inside a group
     *
     * @throws Exception
     */
    public function addPasswordRule($elementName, $groupName = '')
    {
        if ('true' == api_get_setting('security.check_password')) {
            $message = get_lang('this password  is too simple. Use a pass like this').': '.api_generate_password();

            if (!empty($groupName)) {
                $groupObj = $this->getElement($groupName);

                if ($groupObj instanceof HTML_QuickForm_group) {
                    $elementName = $groupObj->getElementName($elementName);

                    if (false === $elementName) {
                        throw new Exception("The $groupName doesn't have the element $elementName");
                    }

                    $this->_rules[$elementName][] = [
                        'type' => 'callback',
                        'format' => 'api_check_password',
                        'message' => $message,
                        'validation' => '',
                        'reset' => false,
                        'group' => $groupName,
                    ];
                }
            } else {
                $this->addRule(
                    $elementName,
                    $message,
                    'callback',
                    'api_check_password'
                );
            }
        }
    }

    /**
     * Add an element with user ID and avatar to the form.
     * It needs a Chamilo\CoreBundle\Entity\User as value. The exported value is the Chamilo\CoreBundle\Entity\User ID.
     *
     * @see \UserAvatar
     *
     * @param string $name
     * @param string $label
     * @param string $imageSize Optional. Small, medium or large image
     * @param string $subtitle  Optional. The subtitle for the field
     *
     * @return \UserAvatar
     */
    public function addUserAvatar($name, $label, $imageSize = 'small', $subtitle = '')
    {
        return $this->addElement('UserAvatar', $name, $label, ['image_size' => $imageSize, 'sub_title' => $subtitle]);
    }

    /**
     * @param array $typeList
     */
    public function addEmailTemplate($typeList)
    {
        $mailManager = new MailTemplateManager();
        foreach ($typeList as $type) {
            $list = $mailManager->get_all(
                ['where' => ['type = ? AND url_id = ?' => [$type, api_get_current_access_url_id()]]]
            );

            $options = [get_lang('Select')];
            $name = $type;
            $defaultId = '';
            foreach ($list as $item) {
                $options[$item['id']] = $item['name'];
                $name = $item['name'];
                if (empty($defaultId)) {
                    $defaultId = 1 == $item['default_template'] ? $item['id'] : '';
                }
            }

            $url = api_get_path(WEB_AJAX_PATH).'mail.ajax.php?a=select_option';
            $typeNoDots = 'email_template_option_'.str_replace('.tpl', '', $type);
            $this->addSelect(
                'email_template_option['.$type.']',
                $name,
                $options,
                ['id' => $typeNoDots]
            );

            $templateNoDots = 'email_template_'.str_replace('.tpl', '', $type);
            $templateNoDotsBlock = 'email_template_block_'.str_replace('.tpl', '', $type);
            $this->addHtml('<div id="'.$templateNoDotsBlock.'" style="display:none">');
            $this->addTextarea(
                $templateNoDots,
                get_lang('Preview'),
                ['disabled' => 'disabled ', 'id' => $templateNoDots, 'rows' => '5']
            );
            $this->addHtml('</div>');

            $this->addHtml("<script>
            $(function() {
                var defaultValue = '$defaultId';
                $('#$typeNoDots').val(defaultValue);
                //$('#$typeNoDots').selectpicker('render');
                if (defaultValue != '') {
                    var selected = $('#$typeNoDots option:selected').val();
                    $.ajax({
                        url: '$url' + '&id=' + selected+ '&template_name=$type',
                        success: function (data) {
                            $('#$templateNoDots').html(data);
                            $('#$templateNoDotsBlock').show();
                            return;
                        },
                    });
                }

                $('#$typeNoDots').on('change', function(){
                    var selected = $('#$typeNoDots option:selected').val();
                    $.ajax({
                        url: '$url' + '&id=' + selected,
                        success: function (data) {
                            $('#$templateNoDots').html(data);
                            $('#$templateNoDotsBlock').show();
                            return;
                        },
                    });
                });
            });
            </script>");
        }
    }

    /**
     * Add email rule for an element.
     */
    public function addEmailRule(string $element)
    {
        $this->addRule(
            $element,
            get_lang('The email address is not complete or contains some invalid characters'),
            'email'
        );
    }

    /**
     * @param string $url           page that will handle the upload
     * @param string $inputName
     * @param string $urlToRedirect
     */
    private function addMultipleUploadJavascript($url, $inputName, $urlToRedirect = '')
    {
        $redirectCondition = '';
        if (!empty($urlToRedirect)) {
            $redirectCondition = "window.location.replace('$urlToRedirect'); ";
        }
        $icon = Display::getMdiIcon('text-box-outline', 'ch-tool-icon', null, ICON_SIZE_SMALL);
        $this->addHtml("
        <script>
        $(function () {
            'use strict';
            $('#".$this->getAttribute('id')."').submit(function() {
                return false;
            });

            $('#dropzone').on('click', function() {
                $('#".$inputName."').click();
            });

            var url = '".$url."';
            var uploadButton = $('<button/>')
                .addClass('btn btn--primary')
                .prop('disabled', true)
                .text('".addslashes(get_lang('Loading'))."')
                .on('click', function () {
                    var \$this = $(this),
                    data = \$this.data();
                    \$this
                        .off('click')
                        .text('".addslashes(get_lang('Cancel'))."')
                        .on('click', function () {
                            \$this.remove();
                            data.abort();
                        });
                    data.submit().always(function () {
                        \$this.remove();
                    });
                });

            $('#".$inputName."').fileupload({
                url: url,
                dataType: 'json',
                // Enable image resizing, except for Android and Opera,
                // which actually support image resizing, but fail to
                // send Blob objects via XHR requests:
                disableImageResize: /Android(?!.*Chrome)|Opera/.test(window.navigator.userAgent),
                previewMaxWidth: 300,
                previewMaxHeight: 169,
                previewCrop: true,
                dropzone: $('#dropzone'),
            }).on('fileuploadadd', function (e, data) {
                data.context = $('<div class=\"row\" />').appendTo('#files');
                $.each(data.files, function (index, file) {
                    var node = $('<div class=\"col-sm-5 file_name\">').text(file.name);
                    node.appendTo(data.context);
                });
            }).on('fileuploadprocessalways', function (e, data) {
                var index = data.index,
                    file = data.files[index],
                    node = $(data.context.children()[index]);
                if (file.preview) {
                    data.context.prepend($('<div class=\"col-sm-4\">').html(file.preview));
                } else {
                    data.context.prepend($('<div class=\"col-sm-4\">').html('".$icon."'));
                }
                if (index + 1 === data.files.length) {
                    data.context.find('button')
                        .text('Upload')
                        .prop('disabled', !!data.files.error);
                }
            }).on('fileuploadprogressall', function (e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                $('#progress .progress-bar').css(
                    'width',
                    progress + '%'
                );
            }).on('fileuploaddone', function (e, data) {
                $.each(data.result.files, function (index, file) {
                    if (file.error) {
                        var link = $('<div>')
                            .attr({class : 'panel-image'})                            ;
                        $(data.context.children()[index]).parent().wrap(link);
                        // Update file name with new one from Chamilo
                        $(data.context.children()[index]).parent().find('.file_name').html(file.name);
                        var message = $('<div class=\"col-sm-3\">').html(
                            $('<span class=\"message-image-danger\"/>').text(file.error)
                        );
                        $(data.context.children()[index]).parent().append(message);

                        return;
                    }
                    if (file.url) {
                        var link = $('<a>')
                            .attr({target: '_blank', class : 'panel-image'})
                            .prop('href', file.url);
                        $(data.context.children()[index]).parent().wrap(link);
                    }
                    // Update file name with new one from Chamilo
                    $(data.context.children()[index]).parent().find('.file_name').html(file.name);
                    var message = $('<div class=\"col-sm-3\">').html(
                        $('<span class=\"alert alert-success\"/>').text('".addslashes(get_lang('File upload succeeded!'))."')
                    );
                    $(data.context.children()[index]).parent().append(message);
                });
                $('#dropzone').removeClass('hover');
                ".$redirectCondition."
            }).on('fileuploadfail', function (e, data) {
                $.each(data.files, function (index) {
                    var failedMessage = '".addslashes(get_lang('The file upload has failed.'))."';
                    var error = $('<div class=\"col-sm-3\">').html(
                        $('<span class=\"alert alert-danger\"/>').text(failedMessage)
                    );
                    $(data.context.children()[index]).parent().append(error);
                });
                $('#dropzone').removeClass('hover');
            }).prop('disabled', !$.support.fileInput).parent().addClass($.support.fileInput ? undefined : 'disabled');

            $('#dropzone').on('dragover', function (e) {
                // dragleave callback implementation
                $('#dropzone').addClass('hover');
            });

            $('#dropzone').on('dragleave', function (e) {
                $('#dropzone').removeClass('hover');
            });
            $('.fileinput-button').hide();
        });
        </script>");
    }
}

/**
 * Cleans HTML text filter.
 *
 * @param string $html HTML to clean
 * @param int    $mode (optional)
 *
 * @return string The cleaned HTML
 */
function html_filter($html, $mode = NO_HTML)
{
    $allowed_tags = HTML_QuickForm_Rule_HTML::get_allowed_tags($mode);
    $cleaned_html = kses($html, $allowed_tags);

    return $cleaned_html;
}

function html_filter_teacher($html)
{
    return html_filter($html, TEACHER_HTML);
}

function html_filter_student($html)
{
    return html_filter($html, STUDENT_HTML);
}

function html_filter_teacher_fullpage($html)
{
    return html_filter($html, TEACHER_HTML_FULLPAGE);
}

function html_filter_student_fullpage($html)
{
    return html_filter($html, STUDENT_HTML_FULLPAGE);
}

/**
 * Cleans mobile phone number text.
 *
 * @param string $mobilePhoneNumber Mobile phone number to clean
 *
 * @return string The cleaned mobile phone number
 */
function mobile_phone_number_filter($mobilePhoneNumber)
{
    $mobilePhoneNumber = str_replace(['+', '(', ')'], '', $mobilePhoneNumber);

    return ltrim($mobilePhoneNumber, '0');
}

/**
 * Cleans JS from a URL.
 *
 * @param string $html URL to clean
 * @param int    $mode (optional)
 *
 * @return string The cleaned URL
 */
function plain_url_filter($html, $mode = NO_HTML)
{
    $allowed_tags = HTML_QuickForm_Rule_HTML::get_allowed_tags($mode);
    $html = kses_no_null($html);
    $html = kses_js_entities($html);
    $allowed_html_fixed = kses_array_lc($allowed_tags);

    return kses_split($html, $allowed_html_fixed, ['http', 'https']);
}

/**
 * Prevent execution of event handlers in HTML elements.
 */
function attr_on_filter(string $html): string
{
    return RemoveOnAttributes::filter($html);
}

