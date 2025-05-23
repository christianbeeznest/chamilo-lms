<?php

/* For licensing terms, see /license.txt */

require_once __DIR__.'/../inc/global.inc.php';

api_block_anonymous_users();
GradebookUtils::block_students();

$selectCat = isset($_GET['selectcat']) ? (int) $_GET['selectcat'] : 0;

$interbreadcrumb[] = [
    'url' => Category::getUrl(),
    'name' => get_lang('Assessments'),
];
$interbreadcrumb[] = [
    'url' => Category::getUrl().'selectcat='.$selectCat,
    'name' => get_lang('Details'),
];
$interbreadcrumb[] = [
    'url' => 'gradebook_showlog_link.php?visiblelink='.Security::remove_XSS($_GET['visiblelink']).'&selectcat='.$selectCat,
    'name' => get_lang('Assessment history'),
];
$this_section = SECTION_COURSES;
Display::display_header('');
Display::page_subheader2(get_lang('Assessment history'));

$t_user = Database::get_main_table(TABLE_MAIN_USER);
$t_link_log = Database::get_main_table(TABLE_MAIN_GRADEBOOK_LINKEVAL_LOG);
$visible_link = Security::remove_XSS($_GET['visiblelink']);
$evaledit = EvalLink:: load($visible_link);
$sql = "SELECT lk.title,lk.description,lk.weight,lk.visible,lk.type,lk.created_at,us.username
        FROM ".$t_link_log." lk inner join ".$t_user." us
        ON lk.user_id_log=us.id
        WHERE lk.id_linkeval_log=".$evaledit[0]->get_id()." AND lk.type='link';";
$result = Database::query($sql);
$list_info = [];
while ($row = Database::fetch_row($result)) {
    $list_info[] = $row;
}

foreach ($list_info as $key => $info_log) {
    $list_info[$key][5] = ($info_log[5]) ? api_convert_and_format_date($info_log[5]) : 'N/A';
    $list_info[$key][3] = (1 == $info_log[3]) ? get_lang('Assessments visible') : get_lang('Assessments invisible');
}

$parameters = [
    'visiblelink' => Security::remove_XSS($_GET['visiblelink']),
    'selectcat' => $selectCat,
];

$table = new SortableTableFromArrayConfig($list_info, 1, 20, 'gradebooklink');
$table->set_additional_parameters($parameters);
$table->set_header(0, get_lang('Assessment name'));
$table->set_header(1, get_lang('Assessment description'));
$table->set_header(2, get_lang('Previous weight of resource'));
$table->set_header(3, get_lang('Assessment visibility'));
$table->set_header(4, get_lang('Category'));
$table->set_header(5, get_lang('Date'));
$table->set_header(6, get_lang('Who changed it'));
$table->display();

Display :: display_footer();
