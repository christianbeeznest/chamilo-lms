<?php

/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Enums\StateIcon;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of zombie_report.
 *
 * @copyright (c) 2012 University of Geneva
 * @license GNU General Public License - http://www.gnu.org/copyleft/gpl.html
 * @author Laurent Opprecht <laurent@opprecht.info>
 */
class ZombieReport implements Countable
{
    protected $additional_parameters = [];
    protected $request;

    protected $parameters_form = null;

    public function __construct($additional_parameters = [], Request $request = null)
    {
        $this->additional_parameters = $additional_parameters;
        $this->request = $request ?? Request::createFromGlobals();
    }

    /**
     * @return ZombieReport
     */
    public static function create($additional_parameters = [])
    {
        return new self($additional_parameters);
    }

    public function get_additional_parameters()
    {
        return $this->additional_parameters;
    }

    public function get_parameters()
    {
        $result = [
            'items' => [
                [
                    'name' => 'ceiling',
                    'label' => get_lang('Latest access'),
                    'type' => 'date_picker',
                    'default' => $this->get_ceiling('Y-m-d'),
                    'rules' => [
                        [
                            'type' => 'date',
                            'message' => get_lang('Date'),
                        ],
                    ],
                ],
                [
                    'name' => 'active_only',
                    'label' => get_lang('active only'),
                    'type' => 'checkbox',
                    'default' => $this->get_active_only(),
                ],
                [
                    'name' => 'submit_button',
                    'type' => 'button',
                    'value' => get_lang('Search'),
                    'attributes' => ['class' => 'search'],
                ],
            ],
        ];

        return $result;
    }

    /**
     * @return FormValidator
     */
    public function get_parameters_form()
    {
        $form = new FormValidator(
            'zombie_report_parameters',
            'get',
            null,
            null,
            ['class' => 'well form-horizontal form-search']
        );

        $form->addDatePicker('ceiling', get_lang('Latest access'));
        $form->addCheckBox('active_only', get_lang('active only'));
        $form->addButtonSearch(get_lang('Search'));

        $params = [
            'active_only' => $this->get_active_only(),
            'ceiling' => $this->get_ceiling('Y-m-d'),
        ];
        $form->setDefaults($params);
        $additional = $this->get_additional_parameters();
        foreach ($additional as $key => $value) {
            $value = Security::remove_XSS($value);
            $form->addHidden($key, $value);
        }

        return $form;
    }

    public function display_parameters($return = false)
    {
        $form = $this->get_parameters_form();
        $result = $form->returnForm();

        if ($return) {
            return $result;
        } else {
            echo $result;
        }
    }

    public function is_valid()
    {
        $form = $this->get_parameters_form();

        return false == $form->isSubmitted() || $form->validate();
    }

    public function get_ceiling($format = null)
    {
        $result = $this->request->get('ceiling');
        $result = $result ? $result : ZombieManager::last_year();

        $result = is_array($result) && 1 == count($result) ? reset($result) : $result;
        $result = is_array($result) ? mktime(0, 0, 0, $result['F'], $result['d'], $result['Y']) : $result;
        $result = is_numeric($result) ? (int) $result : $result;
        $result = is_string($result) ? strtotime($result) : $result;
        if ($format) {
            $result = date($format, $result);
        }

        return $result;
    }

    public function get_active_only()
    {
        $result = $this->request->get('active_only', $this->request->query->get('active_only'));
        $result = 'true' === $result ? true : $result;
        $result = 'false' === $result ? false : $result;
        $result = (bool) $result;

        return $result;
    }

    public function get_action()
    {
        /**
         * todo check token.
         */
        $check = Security::check_token('post');
        Security::clear_token();
        if (!$check) {
            return 'display';
        }

        return $this->request->request->get('action', 'display');
    }

    public function perform_action()
    {
        $ids = $this->request->request->get('id');
        if (empty($ids)) {
            return $ids;
        }

        $action = $this->get_action();
        switch ($action) {
            case 'activate':
                return UserManager::activate_users($ids);
                break;
            case 'deactivate':
                return UserManager::deactivate_users($ids);
                break;
            case 'delete':
                return UserManager::delete_users($ids);
        }

        return false;
    }

    public function count()
    {
        $ceiling = $this->get_ceiling();
        $active_only = $this->get_active_only();
        $items = ZombieManager::listZombies($ceiling, $active_only, null, null);

        return count($items);
    }

    public function get_data($from, $count, $column, $direction)
    {
        $ceiling = $this->get_ceiling();
        $active_only = $this->get_active_only();
        $items = ZombieManager::listZombies($ceiling, $active_only, $from, $count, $column, $direction);
        $result = [];
        foreach ($items as $item) {
            $row = [];
            $row[] = $item['id'];
            $row[] = $item['official_code'];
            $row[] = $item['firstname'];
            $row[] = $item['lastname'];
            $row[] = $item['username'];
            $row[] = $item['email'];
            $row[] = $item['status'];
            $row[] = $item['auth_sources'];
            $row[] = api_format_date($item['created_at'], DATE_FORMAT_SHORT);
            $row[] = api_format_date($item['login_date'], DATE_FORMAT_SHORT);
            $row[] = $item['active'];
            $result[] = $row;
        }

        return $result;
    }

    public function display_data($return = false)
    {
        $count = [$this, 'count'];
        $data = [$this, 'get_data'];

        $parameters = [];
        $parameters['sec_token'] = Security::get_token();
        $parameters['ceiling'] = $this->get_ceiling();
        $parameters['active_only'] = $this->get_active_only() ? 'true' : 'false';
        $additional_parameters = $this->get_additional_parameters();
        $parameters = array_merge($additional_parameters, $parameters);

        $table = new SortableTable('zombie_users', $count, $data, 1, 50);
        $table->set_additional_parameters($parameters);

        $col = 0;
        $table->set_header($col++, '', false);
        $table->set_header($col++, get_lang('Code'));
        $table->set_header($col++, get_lang('First name'));
        $table->set_header($col++, get_lang('Last name'));
        $table->set_header($col++, get_lang('Login'));
        $table->set_header($col++, get_lang('e-mail'));
        $table->set_header($col++, get_lang('Profile'));
        $table->set_header($col++, get_lang('Authentication source'), false);
        $table->set_header($col++, get_lang('Registered date'));
        $table->set_header($col++, get_lang('Latest access'), false);
        $table->set_header($col, get_lang('active'), false);

        $table->set_column_filter(5, [$this, 'format_email']);
        $table->set_column_filter(6, [$this, 'format_status']);
        $table->set_column_filter(10, [$this, 'format_active']);

        $table->set_form_actions([
            'activate' => get_lang('Activate'),
            'deactivate' => get_lang('Deactivate'),
            'delete' => get_lang('Delete'),
        ]);

        if ($return) {
            return $table->return_table();
        } else {
            echo $table->return_table();
        }
    }

    /**
     * Table formatter for the active column.
     *
     * @param string $active
     *
     * @return string
     */
    public function format_active($active)
    {
        $active = '1' == $active;
        if ($active) {
            $image = StateIcon::COMPLETE;
            $text = get_lang('Yes');
        } else {
            $image = StateIcon::INCOMPLETE;
            $text = get_lang('No');
        }

        return Display::getMdiIcon($image, 'ch-tool-icon', null, ICON_SIZE_SMALL, $text);
    }

    public function format_status($status)
    {
        $statusname = api_get_status_langvars();

        return $statusname[$status];
    }

    public function format_email($email)
    {
        return Display::encrypted_mailto_link($email, $email);
    }

    public function display($return = false)
    {
        $result = $this->display_parameters($return);
        $valid = $this->perform_action();

        if ($valid) {
            echo Display::return_message(get_lang('Update successful'), 'confirmation');
        }

        $result .= $this->display_data($return);

        if ($return) {
            return $result;
        }
    }
}
