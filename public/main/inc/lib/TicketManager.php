<?php

/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Entity\Ticket;
use Chamilo\CoreBundle\Entity\TicketMessage;
use Chamilo\CoreBundle\Entity\TicketMessageAttachment;
use Chamilo\CoreBundle\Entity\TicketPriority;
use Chamilo\CoreBundle\Entity\TicketProject;
use Chamilo\CoreBundle\Entity\TicketRelUser;
use Chamilo\CoreBundle\Entity\TicketStatus;
use Chamilo\CoreBundle\Entity\User;
use Chamilo\CoreBundle\Entity\ValidationToken;
use Chamilo\CoreBundle\Enums\ObjectIcon;
use Chamilo\CoreBundle\Enums\StateIcon;
use Chamilo\CoreBundle\Framework\Container;
use Chamilo\CoreBundle\Helpers\ValidationTokenHelper;
use Chamilo\CourseBundle\Entity\CLp;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class TicketManager.
 */
class TicketManager
{
    public const PRIORITY_NORMAL = 'NRM';
    public const PRIORITY_HIGH = 'HGH';
    public const PRIORITY_LOW = 'LOW';

    public const SOURCE_EMAIL = 'MAI';
    public const SOURCE_PHONE = 'TEL';
    public const SOURCE_PLATFORM = 'PLA';
    public const SOURCE_PRESENTIAL = 'PRE';

    public const STATUS_NEW = 'NAT';
    public const STATUS_PENDING = 'PND';
    public const STATUS_UNCONFIRMED = 'XCF';
    public const STATUS_CLOSE = 'CLS';
    public const STATUS_FORWARDED = 'REE';

    public function __construct()
    {
    }

    /**
     * Get categories of tickets.
     *
     * @param int    $projectId
     * @param string $order
     *
     * @return array
     */
    public static function get_all_tickets_categories($projectId, $order = '')
    {
        $table_support_category = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $table_support_project = Database::get_main_table(TABLE_TICKET_PROJECT);

        $order = empty($order) ? 'category.total_tickets DESC' : $order;
        $order = Database::escape_string($order);
        $projectId = (int) $projectId;
        $accessUrlId = Container::getAccessUrlUtil()->getCurrent()->getId();

        $sql = "SELECT
                    category.*,
                    category.id category_id,
                    project.other_area,
                    project.email
                FROM
                $table_support_category category
                INNER JOIN $table_support_project project
                ON project.id = category.project_id
                WHERE project.id = $projectId AND project.access_url_id = $accessUrlId
                ORDER BY $order";
        $result = Database::query($sql);
        $types = [];
        while ($row = Database::fetch_assoc($result)) {
            $types[] = $row;
        }

        return $types;
    }

    /**
     * @param $from
     * @param $numberItems
     * @param $column
     * @param $direction
     *
     * @return array
     */
    public static function getCategories($from, $numberItems, $column, $direction)
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $sql = "SELECT id, title, description, total_tickets
                FROM $table";

        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $column = (int) $column;
        $from = (int) $from;
        $numberItems = (int) $numberItems;

        //$sql .= " ORDER BY col$column $direction ";
        $sql .= " LIMIT $from,$numberItems";

        $result = Database::query($sql);
        $types = [];
        while ($row = Database::fetch_array($result)) {
            $types[] = $row;
        }

        return $types;
    }

    /**
     * @param int $id
     *
     * @return array|mixed
     */
    public static function getCategory($id)
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $id = (int) $id;
        $sql = "SELECT id, title, title as name, description, total_tickets
                FROM $table WHERE id = $id";

        $result = Database::query($sql);
        $category = Database::fetch_array($result);

        return $category;
    }

    /**
     * @return int
     */
    public static function getCategoriesCount()
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY);

        $sql = "SELECT count(id) count
                FROM $table ";

        $result = Database::query($sql);
        $category = Database::fetch_array($result);

        return $category['count'];
    }

    /**
     * @param int   $id
     * @param array $params
     */
    public static function updateCategory($id, $params)
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $id = (int) $id;
        Database::update($table, $params, ['id = ?' => $id]);
    }

    /**
     * @param array $params
     */
    public static function addCategory($params)
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY);
        Database::insert($table, $params);
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public static function deleteCategory($id)
    {
        $id = (int) $id;
        if (empty($id)) {
            return false;
        }

        $table = Database::get_main_table(TABLE_TICKET_TICKET);
        $sql = "UPDATE $table SET category_id = NULL WHERE category_id = $id";
        Database::query($sql);

        $table = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $sql = "DELETE FROM $table WHERE id = $id";
        Database::query($sql);

        return true;
    }

    /**
     * @param int   $categoryId
     * @param array $users
     *
     * @return bool
     */
    public static function addUsersToCategory($categoryId, $users)
    {
        if (empty($users) || empty($categoryId)) {
            return false;
        }

        $table = Database::get_main_table(TABLE_TICKET_CATEGORY_REL_USER);
        foreach ($users as $userId) {
            if (false === self::userIsAssignedToCategory($userId, $categoryId)) {
                $params = [
                    'category_id' => $categoryId,
                    'user_id' => $userId,
                ];
                Database::insert($table, $params);
            }
        }

        return true;
    }

    /**
     * @param int $userId
     * @param int $categoryId
     *
     * @return bool
     */
    public static function userIsAssignedToCategory($userId, $categoryId)
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY_REL_USER);
        $userId = (int) $userId;
        $categoryId = (int) $categoryId;
        $sql = "SELECT * FROM $table
                WHERE category_id = $categoryId AND user_id = $userId";
        $result = Database::query($sql);

        return Database::num_rows($result) > 0;
    }

    /**
     * @param int $categoryId
     *
     * @return array
     */
    public static function getUsersInCategory($categoryId)
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY_REL_USER);
        $categoryId = (int) $categoryId;
        $sql = "SELECT * FROM $table WHERE category_id = $categoryId";
        $result = Database::query($sql);

        return Database::store_result($result);
    }

    /**
     * @param int $categoryId
     */
    public static function deleteAllUserInCategory($categoryId)
    {
        $table = Database::get_main_table(TABLE_TICKET_CATEGORY_REL_USER);
        $categoryId = (int) $categoryId;
        $sql = "DELETE FROM $table WHERE category_id = $categoryId";
        Database::query($sql);
    }

    /**
     * Get all possible tickets statuses.
     *
     * @return array
     */
    public static function get_all_tickets_status()
    {
        $table = Database::get_main_table(TABLE_TICKET_STATUS);
        $sql = "SELECT * FROM $table";
        $result = Database::query($sql);
        $types = [];
        while ($row = Database::fetch_assoc($result)) {
            $types[] = $row;
        }

        return $types;
    }

    /**
     * Inserts a new ticket in the corresponding tables.
     *
     * @param int      $category_id
     * @param int      $course_id
     * @param int      $sessionId
     * @param int      $project_id
     * @param string   $other_area
     * @param string   $subject
     * @param string   $content
     * @param string   $personalEmail
     * @param array    $fileAttachments
     * @param string   $source
     * @param string   $priority
     * @param string   $status
     * @param int|null $assignedUserId
     * @param int      $exerciseId
     * @param int      $lpId
     *
     * @return bool
     */
    public static function add(
        $category_id,
        $course_id,
        $sessionId,
        $project_id,
        $other_area,
        $subject,
        $content,
        $personalEmail = '',
        $fileAttachments = [],
        $source = '',
        $priority = '',
        $status = '',
        $assignedUserId = null,
        $exerciseId = null,
        $lpId = null
    ) {
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $table_support_category = Database::get_main_table(TABLE_TICKET_CATEGORY);

        if (empty($category_id)) {
            return false;
        }

        $currentUserId = api_get_user_id();
        $currentUserInfo = api_get_user_info();
        $now = api_get_utc_datetime();
        $course_id = (int) $course_id;
        $category_id = (int) $category_id;
        $project_id = (int) $project_id;
        $priority = empty($priority) ? self::PRIORITY_NORMAL : (int) $priority;

        if ('' === $status) {
            $status = self::STATUS_NEW;
            if ($other_area > 0) {
                $status = self::STATUS_FORWARDED;
            }
        }

        if (empty($assignedUserId)) {
            $usersInCategory = self::getUsersInCategory($category_id);
            if (!empty($usersInCategory) && count($usersInCategory) > 0) {
                $userCategoryInfo = $usersInCategory[0];
                if (isset($userCategoryInfo['user_id'])) {
                    $assignedUserId = $userCategoryInfo['user_id'];
                }
            }
        }

        $assignedUserInfo = [];
        if (!empty($assignedUserId)) {
            $assignedUserInfo = api_get_user_info($assignedUserId);
            if (empty($assignedUserInfo)) {
                return false;
            }
        }

        // insert_ticket
        $params = [
            'project_id' => $project_id,
            'category_id' => $category_id,
            'priority_id' => $priority,
            'personal_email' => $personalEmail,
            'status_id' => $status,
            'start_date' => $now,
            'sys_insert_user_id' => $currentUserId,
            'sys_insert_datetime' => $now,
            'sys_lastedit_user_id' => $currentUserId,
            'sys_lastedit_datetime' => $now,
            'source' => $source,
            'assigned_last_user' => $assignedUserId,
            'subject' => $subject,
            'message' => $content,
            'code' => '',
            'total_messages' => 0,
            'access_url_id' => Container::getAccessUrlUtil()->getCurrent()->getId(),
        ];

        if (!empty($exerciseId)) {
            $params['exercise_id'] = $exerciseId;
        }

        if (!empty($lpId)) {
            $params['lp_id'] = $lpId;
        }
        if (!empty($course_id)) {
            $params['course_id'] = $course_id;
        }

        if (!empty($sessionId)) {
            $params['session_id'] = $sessionId;
        }
        $ticketId = Database::insert($table_support_tickets, $params);

        if ($ticketId) {
            self::subscribeUserToTicket($ticketId, $currentUserId);
            $ticket_code = 'A'.str_pad($ticketId, 11, '0', STR_PAD_LEFT);
            $titleCreated = sprintf(
                get_lang('Ticket %s created'),
                $ticket_code
            );

            Display::addFlash(Display::return_message(
                $titleCreated,
                'normal',
                false
            ));

            if (0 != $assignedUserId) {
                self::assignTicketToUser(
                    $ticketId,
                    $assignedUserId
                );

                Display::addFlash(Display::return_message(
                    sprintf(
                        get_lang('Ticket <b>#%s</b> assigned to user <b>%s</b>'),
                        $ticket_code,
                        $assignedUserInfo['complete_name']
                    ),
                    'normal',
                    false
                ));
            }

            if (!empty($fileAttachments)) {
                $attachmentCount = 0;
                foreach ($fileAttachments as $attach) {
                    if (!empty($attach['tmp_name'])) {
                        $attachmentCount++;
                    }
                }
                if ($attachmentCount > 0) {
                    self::insertMessage(
                        $ticketId,
                        '',
                        '',
                        $fileAttachments,
                        $currentUserId
                    );
                }
            }

            // Update code
            $sql = "UPDATE $table_support_tickets
                    SET code = '$ticket_code'
                    WHERE id = '$ticketId'";
            Database::query($sql);

            // Update total
            $sql = "UPDATE $table_support_category
                    SET total_tickets = total_tickets + 1
                    WHERE id = $category_id";
            Database::query($sql);

            $helpDeskMessage =
                '<table>
                        <tr>
                            <td width="100px"><b>'.get_lang('User').'</b></td>
                            <td width="400px">'.$currentUserInfo['complete_name'].'</td>
                        </tr>
                        <tr>
                            <td width="100px"><b>'.get_lang('Username').'</b></td>
                            <td width="400px">'.$currentUserInfo['username'].'</td>
                        </tr>
                        <tr>
                            <td width="100px"><b>'.get_lang('Email').'</b></td>
                            <td width="400px">'.$currentUserInfo['email'].'</td>
                        </tr>
                        <tr>
                            <td width="100px"><b>'.get_lang('Phone').'</b></td>
                            <td width="400px">'.$currentUserInfo['phone'].'</td>
                        </tr>
                        <tr>
                            <td width="100px"><b>'.get_lang('Date').'</b></td>
                            <td width="400px">'.api_convert_and_format_date($now, DATE_TIME_FORMAT_LONG).'</td>
                        </tr>
                        <tr>
                            <td width="100px"><b>'.get_lang('Title').'</b></td>
                            <td width="400px">'.Security::remove_XSS($subject).'</td>
                        </tr>
                        <tr>
                            <td width="100px"><b>'.get_lang('Description').'</b></td>
                            <td width="400px">'.Security::remove_XSS($content).'</td>
                        </tr>
                    </table>';

            if (0 != $assignedUserId) {
                $href = api_get_path(WEB_CODE_PATH).'ticket/ticket_details.php?ticket_id='.$ticketId;
                $helpDeskMessage .= sprintf(
                    get_lang("Ticket assigned to %s. Follow-up at <a href='%s'>#%s</a>."),
                    $assignedUserInfo['complete_name'],
                    $href,
                    $ticketId
                );
            }

            if (empty($category_id)) {
                if ('true' === api_get_setting('ticket_send_warning_to_all_admins')) {
                    $warningSubject = sprintf(
                        get_lang('Ticket %s was created without a category'),
                        $ticket_code
                    );
                    Display::addFlash(Display::return_message($warningSubject));

                    $admins = UserManager::get_all_administrators();
                    foreach ($admins as $userId => $data) {
                        if ($data['active']) {
                            MessageManager::send_message_simple(
                                $userId,
                                $warningSubject,
                                $helpDeskMessage
                            );
                        }
                    }
                }
            } else {
                $categoryInfo = self::getCategory($category_id);
                $usersInCategory = self::getUsersInCategory($category_id);
                $message = '<h2>'.get_lang('Ticket info').'</h2><br />'.$helpDeskMessage;

                if ('true' === api_get_setting('ticket_warn_admin_no_user_in_category')) {
                    $usersInCategory = self::getUsersInCategory($category_id);
                    if (empty($usersInCategory)) {
                        $subject = sprintf(
                            get_lang('Warning: No one has been assigned to category %s'),
                            $categoryInfo['title']
                        );

                        if ('true' === api_get_setting('ticket_send_warning_to_all_admins')) {
                            Display::addFlash(Display::return_message(
                                sprintf(
                                    get_lang(
                                        'A notification was sent to the administrators to report this category has no user assigned'
                                    ),
                                    $categoryInfo['title']
                                ),
                                null,
                                false
                            ));

                            $admins = UserManager::get_all_administrators();
                            foreach ($admins as $userId => $data) {
                                if ($data['active']) {
                                    self::sendNotification(
                                        $ticketId,
                                        $subject,
                                        $message,
                                        $userId
                                    );
                                }
                            }
                        } else {
                            Display::addFlash(Display::return_message($subject));
                        }
                    }
                }

                // Send notification to all users
                if (!empty($usersInCategory)) {
                    foreach ($usersInCategory as $data) {
                        if ($data['user_id'] && $data['user_id'] !== $currentUserId) {
                            self::sendNotification(
                                $ticketId,
                                $titleCreated,
                                $helpDeskMessage,
                                $data['user_id']
                            );
                        }
                    }
                }
            }

            if (!empty($personalEmail)) {
                api_mail_html(
                    get_lang('Virtual support'),
                    $personalEmail,
                    get_lang('The incident has been sent to the virtual support team again'),
                    $helpDeskMessage
                );
            }

            self::sendNotification(
                $ticketId,
                $titleCreated,
                $helpDeskMessage
            );

            return true;
        }

        return false;
    }

    /**
     * Assign ticket to admin.
     *
     * @param int $ticketId
     * @param int $userId
     *
     * @return bool
     */
    public static function assignTicketToUser(
        $ticketId,
        $userId
    ) {
        $ticketId = (int) $ticketId;
        $userId = (int) $userId;

        if (empty($ticketId)) {
            return false;
        }

        $ticket = self::get_ticket_detail_by_id($ticketId);

        if ($ticket) {
            $table = Database::get_main_table(TABLE_TICKET_TICKET);
            $sql = "UPDATE $table
                    SET assigned_last_user = $userId
                    WHERE id = $ticketId";
            Database::query($sql);

            $table = Database::get_main_table(TABLE_TICKET_ASSIGNED_LOG);
            $params = [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'sys_insert_user_id' => api_get_user_id(),
                'assigned_date' => api_get_utc_datetime(),
            ];
            Database::insert($table, $params);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Insert message between Users and Admins.
     *
     * @param int    $ticketId
     * @param string $subject
     * @param string $content
     * @param array  $fileAttachments
     * @param int    $userId
     * @param string $status
     * @param bool   $sendConfirmation
     *
     * @return bool
     */
    public static function insertMessage(
        $ticketId,
        $subject,
        $content,
        $fileAttachments,
        $userId,
        $status = 'NOL',
        $sendConfirmation = false
    ) {
        $ticketId = (int) $ticketId;
        $userId = (int) $userId;
        $table_support_messages = Database::get_main_table(TABLE_TICKET_MESSAGE);
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        if ($sendConfirmation) {
            $form =
                '<form action="ticket_details.php?ticket_id='.$ticketId.'" id="confirmticket" method="POST" >
                     <p>'.get_lang('Was this answer satisfactory?').'</p>
                     <button class="btn btn--primary responseyes" name="response" id="responseyes" value="1">'.
                get_lang('Yes').'</button>
                     <button class="btn btn--danger responseno" name="response" id="responseno" value="0">'.
                get_lang('No').'</button>
                 </form>';
            $content .= $form;
        }

        $now = api_get_utc_datetime();

        $params = [
            'ticket_id' => $ticketId,
            'subject' => $subject,
            'message' => $content,
            'ip_address' => api_get_real_ip(),
            'sys_insert_user_id' => $userId,
            'sys_insert_datetime' => $now,
            'sys_lastedit_user_id' => $userId,
            'sys_lastedit_datetime' => $now,
            'status' => $status,
        ];
        $messageId = Database::insert($table_support_messages, $params);
        if ($messageId) {
            // update_total_message
            $sql = "UPDATE $table_support_tickets
                    SET
                        sys_lastedit_user_id = $userId,
                        sys_lastedit_datetime = '$now',
                        total_messages = (
                            SELECT COUNT(*) as total_messages
                            FROM $table_support_messages
                            WHERE ticket_id = $ticketId
                        )
                    WHERE id = $ticketId ";
            Database::query($sql);

            if (is_array($fileAttachments)) {
                foreach ($fileAttachments as $file_attach) {
                    if (0 == $file_attach['error']) {
                        self::saveMessageAttachmentFile(
                            $file_attach,
                            $ticketId,
                            $messageId
                        );
                    } else {
                        if (UPLOAD_ERR_NO_FILE != $file_attach['error']) {
                            return false;
                        }
                    }
                }
            }

            if (!self::isUserSubscribedToTicket($ticketId, $userId)) {
                self::subscribeUserToTicket($ticketId, $userId);
            }
        }

        return true;
    }

    /**
     * Attachment files when a message is sent.
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public static function saveMessageAttachmentFile(
        $fileAttach,
        $ticketId,
        $messageId
    ): bool {
        if (!is_array($fileAttach) || UPLOAD_ERR_OK != $fileAttach['error']) {
            return false;
        }

        $em = Database::getManager();

        $ticket = $em->find(Ticket::class, $ticketId);
        $message = $em->find(TicketMessage::class, $messageId);

        $newFileName = add_ext_on_mime(
            stripslashes($fileAttach['name']),
            $fileAttach['type']
        );

        $fileName = $fileAttach['name'];

        if (!filter_extension($newFileName)) {
            Display::addFlash(
                Display::return_message(
                    get_lang('File upload failed: this file extension or file type is prohibited'),
                    'error'
                )
            );

            return false;
        }

        $currentUser = api_get_user_entity();

        $repo = Container::getTicketMessageAttachmentRepository();
        $attachment = (new TicketMessageAttachment())
            ->setFilename($fileName)
            ->setPath(uniqid('ticket_message', true))
            ->setMessage($message)
            ->setSize((int) $fileAttach['size'])
            ->setTicket($ticket)
            ->setInsertUserId($currentUser->getId())
            ->setInsertDateTime(api_get_utc_datetime(null, false, true))
            ->setParent($currentUser)
        ;

        if (null !== $ticket->getAssignedLastUser()) {
            $attachment->addUserLink($ticket->getAssignedLastUser());
        }

        $em->persist($attachment);
        $em->flush();

        $file = new UploadedFile($fileAttach['tmp_name'], $fileAttach['name'], $fileAttach['type'], $fileAttach['error']);

        $repo->addFile($attachment, $file);

        return true;
    }

    /**
     * Get tickets by userId.
     *
     * @param int $from
     * @param int $number_of_items
     * @param $column
     * @param $direction
     *
     * @return array
     */
    public static function getTicketsByCurrentUser($from, $number_of_items, $column, $direction)
    {
        $table_support_category = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $table_support_priority = Database::get_main_table(TABLE_TICKET_PRIORITY);
        $table_support_status = Database::get_main_table(TABLE_TICKET_STATUS);
        $direction = !empty($direction) ? $direction : 'DESC';
        $userId = api_get_user_id();
        $userInfo = api_get_user_info($userId);
        $accessUrlId = Container::getAccessUrlUtil()->getCurrent()->getId();

        if (empty($userInfo)) {
            return [];
        }
        $isAdmin = UserManager::is_admin($userId);

        if (!isset($_GET['project_id'])) {
            return [];
        }

        switch ($column) {
            case 0:
                $column = 'ticket_id';
                break;
            case 1:
                $column = 'status_title';
                break;
            case 2:
                $column = 'start_date';
                break;
            case 3:
                $column = 'sys_lastedit_datetime';
                break;
            case 4:
                $column = 'category_title';
                break;
            case 5:
                $column = 'sys_insert_user_id';
                break;
            case 6:
                $column = 'assigned_last_user';
                break;
            case 7:
                $column = 'total_messages';
                break;
            case 8:
                $column = 'subject';
                break;
            default:
                $column = 'ticket_id';
        }

        $sql = "SELECT DISTINCT
                ticket.*,
                ticket.id ticket_id,
                status.title AS status_title,
                ticket.start_date,
                ticket.sys_lastedit_datetime,
                cat.title AS category_title,
                priority.title AS priority_title,
                ticket.total_messages AS total_messages,
                ticket.message AS message,
                ticket.subject AS subject,
                ticket.assigned_last_user
            FROM $table_support_tickets ticket
            INNER JOIN $table_support_category cat
            ON (cat.id = ticket.category_id)
            INNER JOIN $table_support_priority priority
            ON (ticket.priority_id = priority.id)
            INNER JOIN $table_support_status status
            ON (ticket.status_id = status.id)
            WHERE 1=1
        ";
        $sql .= " AND ticket.access_url_id = $accessUrlId ";

        $projectId = (int) $_GET['project_id'];
        $userIsAllowInProject = self::userIsAllowInProject($projectId);

        // Check if a role was set to the project
        if (false == $userIsAllowInProject) {
            $sql .= " AND (ticket.assigned_last_user = $userId OR ticket.sys_insert_user_id = $userId )";
        }

        // Search simple
        if (isset($_GET['submit_simple']) && '' != $_GET['keyword']) {
            $keyword = Database::escape_string(trim($_GET['keyword']));
            $sql .= " AND (
                      ticket.id LIKE '%$keyword%' OR
                      ticket.code LIKE '%$keyword%' OR
                      ticket.subject LIKE '%$keyword%' OR
                      ticket.message LIKE '%$keyword%' OR
                      ticket.keyword LIKE '%$keyword%' OR
                      ticket.source LIKE '%$keyword%' OR
                      cat.title LIKE '%$keyword%' OR
                      status.title LIKE '%$keyword%' OR
                      priority.title LIKE '%$keyword%' OR
                      ticket.personal_email LIKE '%$keyword%'
            )";
        }

        $keywords = [
            'project_id' => 'ticket.project_id',
            'keyword_category' => 'ticket.category_id',
            'keyword_assigned_to' => 'ticket.assigned_last_user',
            'keyword_source' => 'ticket.source ',
            'keyword_status' => 'ticket.status_id',
            'keyword_priority' => 'ticket.priority_id',
        ];

        foreach ($keywords as $keyword => $label) {
            if (isset($_GET[$keyword])) {
                $data = Database::escape_string(trim($_GET[$keyword]));
                if (!empty($data)) {
                    $sql .= " AND $label = '$data' ";
                }
            }
        }

        // Search advanced
        $keyword_start_date_start = isset($_GET['keyword_start_date_start']) ? Database::escape_string(trim($_GET['keyword_start_date_start'])) : '';
        $keyword_start_date_end = isset($_GET['keyword_start_date_end']) ? Database::escape_string(trim($_GET['keyword_start_date_end'])) : '';
        $keyword_course = isset($_GET['keyword_course']) ? Database::escape_string(trim($_GET['keyword_course'])) : '';
        $keyword_range = !empty($keyword_start_date_start) && !empty($keyword_start_date_end);

        if (false == $keyword_range && '' != $keyword_start_date_start) {
            $sql .= " AND DATE_FORMAT(ticket.start_date,'%d/%m/%Y') >= '$keyword_start_date_start' ";
        }
        if ($keyword_range && '' != $keyword_start_date_start && '' != $keyword_start_date_end) {
            $sql .= " AND DATE_FORMAT(ticket.start_date,'%d/%m/%Y') >= '$keyword_start_date_start'
                      AND DATE_FORMAT(ticket.start_date,'%d/%m/%Y') <= '$keyword_start_date_end'";
        }

        if ('' != $keyword_course) {
            $course_table = Database::get_main_table(TABLE_MAIN_COURSE);
            $sql .= " AND ticket.course_id IN (
                     SELECT id FROM $course_table
                     WHERE (
                        title LIKE '%$keyword_course%' OR
                        code LIKE '%$keyword_course%' OR
                        visual_code LIKE '%$keyword_course%'
                     )
            )";
        }
        $sql .= " ORDER BY `$column` $direction";
        $sql .= " LIMIT $from, $number_of_items";

        $result = Database::query($sql);
        $tickets = [];
        $webPath = api_get_path(WEB_PATH);
        while ($row = Database::fetch_assoc($result)) {
            $userInfo = api_get_user_info($row['sys_insert_user_id']);
            $hrefUser = $webPath.'main/admin/user_information.php?user_id='.$userInfo['user_id'];
            $name = "<a href='$hrefUser'> {$userInfo['complete_name_with_username']} </a>";
            if (0 != $row['assigned_last_user']) {
                $assignedUserInfo = api_get_user_info($row['assigned_last_user']);
                if (!empty($assignedUserInfo)) {
                    $hrefResp = $webPath.'main/admin/user_information.php?user_id='.$assignedUserInfo['user_id'];
                    $row['assigned_last_user'] = "<a href='$hrefResp'> {$assignedUserInfo['complete_name_with_username']} </a>";
                } else {
                    $row['assigned_last_user'] = get_lang('Unknown user');
                }
            } else {
                if (self::STATUS_FORWARDED !== $row['status_id']) {
                    $row['assigned_last_user'] = '<span style="color:#ff0000;">'.get_lang('To be assigned').'</span>';
                } else {
                    $row['assigned_last_user'] = '<span style="color:#00ff00;">'.get_lang('Message resent').'</span>';
                }
            }

            switch ($row['source']) {
                case self::SOURCE_PRESENTIAL:
                    $img_source = ObjectIcon::USER;
                    break;
                case self::SOURCE_EMAIL:
                    $img_source = ObjectIcon::EMAIL;
                    break;
                case self::SOURCE_PHONE:
                    $img_source = ObjectIcon::PHONE;
                    break;
                default:
                    $img_source = ObjectIcon::TICKET;
                    break;
            }

            $row['start_date'] = Display::dateToStringAgoAndLongDate($row['start_date']);
            $row['sys_lastedit_datetime'] = Display::dateToStringAgoAndLongDate($row['sys_lastedit_datetime']);

            $icon = Display::getMdiIcon(
                $img_source,
                'ch-tool-icon',
                'margin-right: 10px; float: left;',
                ICON_SIZE_SMALL,
                get_lang('Information'),
            );

            $icon .= '<a href="ticket_details.php?ticket_id='.$row['id'].'">'.$row['code'].'</a>';

            if ($isAdmin) {
                $ticket = [
                    $icon.' '.Security::remove_XSS($row['subject']),
                    $row['status_title'],
                    $row['start_date'],
                    $row['sys_lastedit_datetime'],
                    $row['category_title'],
                    $name,
                    $row['assigned_last_user'],
                    $row['total_messages'],
                ];
            } else {
                $ticket = [
                    $icon.' '.Security::remove_XSS($row['subject']),
                    $row['status_title'],
                    $row['start_date'],
                    $row['sys_lastedit_datetime'],
                    $row['category_title'],
                ];
            }
            if ($isAdmin) {
                $ticket['0'] .= '&nbsp;&nbsp;<a
                href="javascript:void(0)"
                onclick="load_history_ticket(\'div_'.$row['ticket_id'].'\','.$row['ticket_id'].')">
                    <a
                        onclick="load_course_list(\'div_'.$row['ticket_id'].'\','.$row['ticket_id'].')"
					    onmouseover="clear_course_list (\'div_'.$row['ticket_id'].'\')"
					    title="'.get_lang('History').'"
					    alt="'.get_lang('History').'"
                    >
                    '.Display::getMdiIcon('history').'
                    </a>

					<div class="blackboard_hide" id="div_'.$row['ticket_id'].'">&nbsp;&nbsp;</div>
					</a>&nbsp;&nbsp;';
            }
            $tickets[] = $ticket;
        }

        return $tickets;
    }

    /**
     * @return int
     */
    public static function getTotalTicketsCurrentUser()
    {
        $table_support_category = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $table_support_priority = Database::get_main_table(TABLE_TICKET_PRIORITY);
        $table_support_status = Database::get_main_table(TABLE_TICKET_STATUS);

        $userInfo = api_get_user_info();
        if (empty($userInfo)) {
            return 0;
        }
        $userId = $userInfo['id'];

        if (!isset($_GET['project_id'])) {
            return 0;
        }

        $accessUrlId = Container::getAccessUrlUtil()->getCurrent()->getId();

        $sql = "SELECT COUNT(ticket.id) AS total
                FROM $table_support_tickets ticket
                INNER JOIN $table_support_category cat
                ON (cat.id = ticket.category_id)
                INNER JOIN $table_support_priority priority
                ON (ticket.priority_id = priority.id)
                INNER JOIN $table_support_status status
                ON (ticket.status_id = status.id)
	            WHERE 1 = 1";

        $sql .= " AND ticket.access_url_id = $accessUrlId ";

        $projectId = (int) $_GET['project_id'];
        $allowRoleList = self::getAllowedRolesFromProject($projectId);

        // Check if a role was set to the project
        if (!empty($allowRoleList) && is_array($allowRoleList)) {
            $allowed = self::userIsAllowInProject($projectId);
            if (!$allowed) {
                $sql .= " AND (ticket.assigned_last_user = $userId OR ticket.sys_insert_user_id = $userId )";
            }
        } else {
            if (!api_is_platform_admin()) {
                $sql .= " AND (ticket.assigned_last_user = $userId OR ticket.sys_insert_user_id = $userId )";
            }
        }

        // Search simple
        if (isset($_GET['submit_simple'])) {
            if ('' != $_GET['keyword']) {
                $keyword = Database::escape_string(trim($_GET['keyword']));
                $sql .= " AND (
                          ticket.code LIKE '%$keyword%' OR
                          ticket.subject LIKE '%$keyword%' OR
                          ticket.message LIKE '%$keyword%' OR
                          ticket.keyword LIKE '%$keyword%' OR
                          ticket.personal_email LIKE '%$keyword%' OR
                          ticket.source LIKE '%$keyword%'
                )";
            }
        }

        $keywords = [
            'project_id' => 'ticket.project_id',
            'keyword_category' => 'ticket.category_id',
            'keyword_assigned_to' => 'ticket.assigned_last_user',
            'keyword_source' => 'ticket.source',
            'keyword_status' => 'ticket.status_id',
            'keyword_priority' => 'ticket.priority_id',
        ];

        foreach ($keywords as $keyword => $sqlLabel) {
            if (isset($_GET[$keyword])) {
                $data = Database::escape_string(trim($_GET[$keyword]));
                $sql .= " AND $sqlLabel = '$data' ";
            }
        }

        // Search advanced
        $keyword_start_date_start = isset($_GET['keyword_start_date_start']) ? Database::escape_string(trim($_GET['keyword_start_date_start'])) : '';
        $keyword_start_date_end = isset($_GET['keyword_start_date_end']) ? Database::escape_string(trim($_GET['keyword_start_date_end'])) : '';
        $keyword_range = isset($_GET['keyword_dates']) ? Database::escape_string(trim($_GET['keyword_dates'])) : '';
        $keyword_course = isset($_GET['keyword_course']) ? Database::escape_string(trim($_GET['keyword_course'])) : '';

        if (false == $keyword_range && '' != $keyword_start_date_start) {
            $sql .= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') = '$keyword_start_date_start' ";
        }
        if ($keyword_range && '' != $keyword_start_date_start && '' != $keyword_start_date_end) {
            $sql .= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') >= '$keyword_start_date_start'
                      AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') <= '$keyword_start_date_end'";
        }
        if ('' != $keyword_course) {
            $course_table = Database::get_main_table(TABLE_MAIN_COURSE);
            $sql .= " AND ticket.course_id IN (
                        SELECT id
                        FROM $course_table
                        WHERE (
                            title LIKE '%$keyword_course%' OR
                            code LIKE '%$keyword_course%' OR
                            visual_code LIKE '%$keyword_course%'
                        )
                   ) ";
        }

        $res = Database::query($sql);
        $obj = Database::fetch_object($res);

        return (int) $obj->total;
    }

    /**
     * @param int $id
     *
     * @return false|TicketMessageAttachment
     */
    public static function getTicketMessageAttachment($id)
    {
        $id = (int) $id;
        $em = Database::getManager();
        $item = $em->getRepository(TicketMessageAttachment::class)->find($id);
        if ($item) {
            return $item;
        }

        return false;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public static function getTicketMessageAttachmentsByTicketId($id)
    {
        $id = (int) $id;
        $em = Database::getManager();
        $items = $em->getRepository(TicketMessageAttachment::class)->findBy(['ticket' => $id]);
        if ($items) {
            return $items;
        }

        return false;
    }

    /**
     * @param int $ticketId
     *
     * @return array
     */
    public static function get_ticket_detail_by_id($ticketId)
    {
        $attachmentRepo = Container::getTicketMessageAttachmentRepository();

        $ticketId = (int) $ticketId;
        $table_support_category = Database::get_main_table(TABLE_TICKET_CATEGORY);
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $table_support_priority = Database::get_main_table(TABLE_TICKET_PRIORITY);
        $table_support_status = Database::get_main_table(TABLE_TICKET_STATUS);
        $table_support_messages = Database::get_main_table(TABLE_TICKET_MESSAGE);
        $table_main_user = Database::get_main_table(TABLE_MAIN_USER);

        $sql = "SELECT
                    ticket.*,
                    cat.title,
                    status.title as status,
                    priority.title priority
                FROM $table_support_tickets ticket
                INNER JOIN $table_support_category cat
                ON (cat.id = ticket.category_id)
                INNER JOIN $table_support_priority priority
                ON (priority.id = ticket.priority_id)
                INNER JOIN $table_support_status status
                ON (status.id = ticket.status_id)
		        WHERE
                    ticket.id = $ticketId ";
        $result = Database::query($sql);
        $ticket = [];

        $repo = Container::getLpRepository();
        if (Database::num_rows($result) > 0) {
            while ($row = Database::fetch_assoc($result)) {
                $row['course'] = null;
                $row['start_date_from_db'] = $row['start_date'];
                $row['start_date'] = api_convert_and_format_date(
                    api_get_local_time($row['start_date']),
                    DATE_TIME_FORMAT_LONG,
                    api_get_timezone()
                );
                $row['end_date_from_db'] = $row['end_date'];
                $row['end_date'] = api_convert_and_format_date(
                    api_get_local_time($row['end_date']),
                    DATE_TIME_FORMAT_LONG,
                    api_get_timezone()
                );
                $row['sys_lastedit_datetime_from_db'] = $row['sys_lastedit_datetime'];
                $row['sys_lastedit_datetime'] = api_convert_and_format_date(
                    api_get_local_time($row['sys_lastedit_datetime']),
                    DATE_TIME_FORMAT_LONG,
                    api_get_timezone()
                );
                $row['course_url'] = null;
                if (0 != $row['course_id']) {
                    $course = api_get_course_info_by_id($row['course_id']);
                    $sessionId = 0;
                    if ($row['session_id']) {
                        $sessionId = $row['session_id'];
                    }
                    if ($course) {
                        $row['course_url'] = '<a href="'.$course['course_public_url'].'?id_session='.$sessionId.'">'.$course['name'].'</a>';
                    }
                    $row['exercise_url'] = null;

                    if (!empty($row['exercise_id'])) {
                        $exerciseTitle = ExerciseLib::getExerciseTitleById($row['exercise_id']);
                        $dataExercise = [
                            'cidReq' => $course['code'],
                            'id_session' => $sessionId,
                            'exerciseId' => $row['exercise_id'],
                        ];
                        $urlParamsExercise = http_build_query($dataExercise);

                        $row['exercise_url'] = '<a href="'.api_get_path(WEB_CODE_PATH).'exercise/overview.php?'.$urlParamsExercise.'">'.$exerciseTitle.'</a>';
                    }

                    $row['lp_url'] = null;

                    if (!empty($row['lp_id'])) {
                        /** @var CLp $lp */
                        $lp = $repo->find($row['lp_id']);
                        $dataLp = [
                            'cidReq' => $course['code'],
                            'id_session' => $sessionId,
                            'lp_id' => $row['lp_id'],
                            'action' => 'view',
                        ];
                        $urlParamsLp = http_build_query($dataLp);

                        $row['lp_url'] = '<a
                            href="'.api_get_path(WEB_CODE_PATH).'lp/lp_controller.php?'.$urlParamsLp.'">'.
                            $lp->getTitle().
                        '</a>';
                    }
                }

                $userInfo = api_get_user_info($row['sys_insert_user_id']);
                $row['user_url'] = '<a href="'.api_get_path(WEB_PATH).'main/admin/user_information.php?user_id='.$userInfo['user_id'].'">
                '.$userInfo['complete_name'].'</a>';
                $ticket['user'] = $userInfo;
                $ticket['ticket'] = $row;
            }

            $sql = "SELECT *, message.id as message_id, user.id AS user_id
                    FROM $table_support_messages message
                    INNER JOIN $table_main_user user
                    ON (message.sys_insert_user_id = user.id)
                    WHERE user.active <> ".USER_SOFT_DELETED." AND
                        message.ticket_id = '$ticketId' ";
            $result = Database::query($sql);
            $ticket['messages'] = [];
            $attach_icon = Display::getMdiIcon(ObjectIcon::ATTACHMENT, 'ch-tool-icon', null, ICON_SIZE_SMALL);

            while ($row = Database::fetch_assoc($result)) {
                $message = $row;
                $message['admin'] = UserManager::is_admin($message['user_id']);
                $message['user_info'] = api_get_user_info($message['user_id']);

                $messageAttachments = $attachmentRepo->findBy(['ticket' => $ticketId, 'message' => $row['message_id']]);

                /** @var TicketMessageAttachment $messageAttachment */
                foreach ($messageAttachments as $messageAttachment) {
                    $archiveURL = $attachmentRepo->getResourceFileDownloadUrl($messageAttachment);
                    $link = Display::url(
                        sprintf("%s (%d)", $messageAttachment->getFilename(), $messageAttachment->getSize()),
                        $archiveURL
                    );

                    $message['attachments'][] = $attach_icon.PHP_EOL.$link;
                }
                $ticket['messages'][] = $message;
            }
        }

        return $ticket;
    }

    /**
     * @param int $ticketId
     * @param int $userId
     *
     * @return bool
     */
    public static function update_message_status($ticketId, $userId)
    {
        $ticketId = (int) $ticketId;
        $userId = (int) $userId;
        $table_support_messages = Database::get_main_table(TABLE_TICKET_MESSAGE);
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $now = api_get_utc_datetime();
        $sql = "UPDATE $table_support_messages
                SET
                    status = 'LEI',
                    sys_lastedit_user_id ='".api_get_user_id()."',
                    sys_lastedit_datetime ='".$now."'
                WHERE ticket_id ='$ticketId' ";

        if (api_is_platform_admin()) {
            $sql .= " AND sys_insert_user_id = '$userId'";
        } else {
            $sql .= " AND sys_insert_user_id != '$userId'";
        }
        $result = Database::query($sql);
        if (Database::affected_rows($result) > 0) {
            Database::query(
                "UPDATE $table_support_tickets SET
                    status_id = '".self::STATUS_PENDING."'
                 WHERE id ='$ticketId' AND status_id = '".self::STATUS_NEW."'"
            );

            return true;
        }

        return false;
    }

    /**
     * Send notification to a user through the internal messaging system.
     */
    public static function sendNotification($ticketId, $title, $message, $onlyToUserId = 0, $debug = false)
    {
        $ticketInfo = self::get_ticket_detail_by_id($ticketId);

        if (empty($ticketInfo)) {
            return false;
        }

        $assignedUserInfo = api_get_user_info($ticketInfo['ticket']['assigned_last_user']);
        $requestUserInfo = $ticketInfo['user'];
        $ticketCode = $ticketInfo['ticket']['code'];
        $status = $ticketInfo['ticket']['status'];
        $priority = $ticketInfo['ticket']['priority'];
        $creatorId = $ticketInfo['ticket']['sys_insert_user_id'];

        // Subject
        $titleEmail = "[$ticketCode] ".Security::remove_XSS($title);

        // Content
        $href = api_get_path(WEB_CODE_PATH) . 'ticket/ticket_details.php?ticket_id=' . $ticketId;
        $ticketUrl = Display::url($ticketCode, $href);
        $messageEmailBase = get_lang('Ticket number') . ": $ticketUrl <br />";
        $messageEmailBase .= get_lang('Status') . ": $status <br />";
        $messageEmailBase .= get_lang('Priority') . ": $priority <br />";
        $messageEmailBase .= '<hr /><br />';
        $messageEmailBase .= $message;

        $currentUserId = api_get_user_id();
        $recipients = [];

        if (!empty($onlyToUserId) && $currentUserId != $onlyToUserId) {
            $recipients[$onlyToUserId] = $onlyToUserId;
        } else {
            if (
                $requestUserInfo &&
                $currentUserId != $requestUserInfo['id'] &&
                self::isUserSubscribedToTicket($ticketId, $requestUserInfo['id'])
            ) {
                $recipients[$requestUserInfo['id']] = $requestUserInfo['complete_name_with_username'];
            }

            if ($assignedUserInfo && $currentUserId != $assignedUserInfo['id']) {
                $recipients[$assignedUserInfo['id']] = $assignedUserInfo['complete_name_with_username'];
            }

            $followers = self::getFollowers($ticketId);
            /* @var User $follower */
            foreach ($followers as $follower) {
                if (
                    $follower->getId() !== $currentUserId &&
                    (
                        $follower->getId() !== $creatorId ||
                        self::isUserSubscribedToTicket($ticketId, $follower->getId())
                    )
                ) {
                    $recipients[$follower->getId()] = $follower->getFullname();
                }
            }
        }

        if ($debug) {
            echo "<pre>";
            echo "Title: $titleEmail\n";
            echo "Message Preview:\n\n";

            foreach ($recipients as $recipientId => $recipientName) {
                $unsubscribeLink = self::generateUnsubscribeLink($ticketId, $recipientId);
                $finalMessageEmail = $messageEmailBase;
                $finalMessageEmail .= '<br /><hr /><br />';
                $finalMessageEmail .= '<small>' . get_lang('To unsubscribe from notifications, click here') . ': ';
                $finalMessageEmail .= '<a href="' . $unsubscribeLink . '">' . $unsubscribeLink . '</a></small>';

                echo "------------------------------------\n";
                echo "Recipient: $recipientName (User ID: $recipientId)\n";
                echo "Message:\n$finalMessageEmail\n";
                echo "------------------------------------\n\n";
            }

            echo "</pre>";
            exit;
        }

        foreach ($recipients as $recipientId => $recipientName) {
            $unsubscribeLink = self::generateUnsubscribeLink($ticketId, $recipientId);

            $finalMessageEmail = $messageEmailBase;
            $finalMessageEmail .= '<br /><hr /><br />';
            $finalMessageEmail .= '<small>' . get_lang('To unsubscribe from notifications, click here') . ': ';
            $finalMessageEmail .= '<a href="' . $unsubscribeLink . '">' . $unsubscribeLink . '</a></small>';

            MessageManager::send_message_simple(
                $recipientId,
                $titleEmail,
                $finalMessageEmail,
                0,
                false,
                false,
                false
            );
        }

        return true;
    }

    /**
     * @param array $params
     * @param int   $ticketId
     * @param int   $userId
     *
     * @return bool
     */
    public static function updateTicket(
        $params,
        $ticketId,
        $userId
    ) {
        $now = api_get_utc_datetime();
        $table = Database::get_main_table(TABLE_TICKET_TICKET);
        $newParams = [
            'priority_id' => isset($params['priority_id']) ? (int) $params['priority_id'] : '',
            'status_id' => isset($params['status_id']) ? (int) $params['status_id'] : '',
            'sys_lastedit_user_id' => (int) $userId,
            'sys_lastedit_datetime' => $now,
        ];
        Database::update($table, $newParams, ['id = ? ' => $ticketId]);

        return true;
    }

    /**
     * @param int $status_id
     * @param int $ticketId
     * @param int $userId
     *
     * @return bool
     */
    public static function update_ticket_status(
        $status_id,
        $ticketId,
        $userId
    ) {
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);

        $ticketId = (int) $ticketId;
        $status_id = (int) $status_id;
        $userId = (int) $userId;
        $now = api_get_utc_datetime();

        $sql = "UPDATE $table_support_tickets
                SET
                    status_id = '$status_id',
                    sys_lastedit_user_id ='$userId',
                    sys_lastedit_datetime ='".$now."'
                WHERE id ='$ticketId'";
        $result = Database::query($sql);

        if (Database::affected_rows($result) > 0) {
            self::sendNotification(
                $ticketId,
                get_lang('Ticket updated'),
                get_lang('Ticket updated')
            );

            return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public static function getNumberOfMessages()
    {
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $table_support_messages = Database::get_main_table(TABLE_TICKET_MESSAGE);
        $table_main_user = Database::get_main_table(TABLE_MAIN_USER);
        $table_main_admin = Database::get_main_table(TABLE_MAIN_ADMIN);
        $user_info = api_get_user_info();
        $userId = $user_info['user_id'];
        $sql = "SELECT COUNT(DISTINCT ticket.id) AS unread
                FROM $table_support_tickets ticket,
                $table_support_messages message ,
                $table_main_user user
                WHERE
                    ticket.id = message.ticket_id AND
                    message.status = 'NOL' AND
                    user.user_id = message.sys_insert_user_id ";
        if (!api_is_platform_admin()) {
            $sql .= " AND ticket.request_user = '$userId'
                      AND user_id IN (SELECT user_id FROM $table_main_admin)  ";
        } else {
            $sql .= " AND user_id NOT IN (SELECT user_id FROM $table_main_admin)
                      AND ticket.status_id != '".self::STATUS_FORWARDED."'";
        }
        $sql .= ' AND ticket.access_url_id = '.(int) Container::getAccessUrlUtil()->getCurrent()->getId();
        $sql .= "  AND ticket.project_id != '' ";
        $res = Database::query($sql);
        $obj = Database::fetch_object($res);

        return $obj->unread;
    }

    /**
     * @param int $ticketId
     * @param int $userId
     */
    public static function send_alert($ticketId, $userId)
    {
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $now = api_get_utc_datetime();

        $ticketId = (int) $ticketId;
        $userId = (int) $userId;

        $sql = "UPDATE $table_support_tickets SET
                  priority_id = '".self::PRIORITY_HIGH."',
                  sys_lastedit_user_id = $userId,
                  sys_lastedit_datetime = '$now'
                WHERE id = $ticketId";
        Database::query($sql);
    }

    /**
     * @param int $ticketId
     * @param int $userId
     */
    public static function close_ticket($ticketId, $userId)
    {
        $ticketId = (int) $ticketId;
        $userId = (int) $userId;

        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $now = api_get_utc_datetime();
        $sql = "UPDATE $table_support_tickets SET
                    status_id = '".self::STATUS_CLOSE."',
                    sys_lastedit_user_id ='$userId',
                    sys_lastedit_datetime ='".$now."',
                    end_date ='$now'
                WHERE id ='$ticketId'";
        Database::query($sql);

        self::sendNotification(
            $ticketId,
            get_lang('Ticket closed'),
            get_lang('Ticket closed')
        );
    }

    /**
     * Close old tickets.
     */
    public static function close_old_tickets()
    {
        $table = Database::get_main_table(TABLE_TICKET_TICKET);
        $now = api_get_utc_datetime();
        $userId = api_get_user_id();
        $accessUrlId = (int) Container::getAccessUrlUtil()->getCurrent()->getId();

        $sql = "UPDATE $table
            SET
                status_id = '".self::STATUS_CLOSE."',
                sys_lastedit_user_id ='$userId',
                sys_lastedit_datetime ='$now',
                end_date = '$now'
            WHERE
                DATEDIFF('$now', sys_lastedit_datetime) > 7 AND
                status_id != '".self::STATUS_CLOSE."' AND
                status_id != '".self::STATUS_NEW."' AND
                status_id != '".self::STATUS_FORWARDED."' AND
                access_url_id = $accessUrlId";

        Database::query($sql);
    }

    /**
     * @param int $ticketId
     *
     * @return array
     */
    public static function get_assign_log($ticketId)
    {
        $table = Database::get_main_table(TABLE_TICKET_ASSIGNED_LOG);
        $ticketId = (int) $ticketId;

        $sql = "SELECT * FROM $table
                WHERE ticket_id = $ticketId
                ORDER BY assigned_date DESC";
        $result = Database::query($sql);
        $history = [];
        $webpath = api_get_path(WEB_PATH);
        while ($row = Database::fetch_assoc($result)) {
            if (0 != $row['user_id']) {
                $assignuser = api_get_user_info($row['user_id']);
                $row['assignuser'] = '<a href="'.$webpath.'main/admin/user_information.php?user_id='.$row['user_id'].'"  target="_blank">'.
                $assignuser['username'].'</a>';
            } else {
                $row['assignuser'] = get_lang('Unassign');
            }
            $row['assigned_date'] = Display::dateToStringAgoAndLongDate($row['assigned_date']);
            $insertuser = api_get_user_info($row['sys_insert_user_id']);
            $row['insertuser'] = '<a href="'.$webpath.'main/admin/user_information.php?user_id='.$row['sys_insert_user_id'].'"  target="_blank">'.
                $insertuser['username'].'</a>';
            $history[] = $row;
        }

        return $history;
    }

    /**
     * @param $from
     * @param $number_of_items
     * @param $column
     * @param $direction
     * @param null $userId
     *
     * @return array
     */
    public static function export_tickets_by_user_id(
        $from,
        $number_of_items,
        $column,
        $direction,
        $userId = null
    ) {
        $from = (int) $from;
        $number_of_items = (int) $number_of_items;
        $table_support_category = Database::get_main_table(
            TABLE_TICKET_CATEGORY
        );
        $table_support_tickets = Database::get_main_table(TABLE_TICKET_TICKET);
        $table_support_priority = Database::get_main_table(TABLE_TICKET_PRIORITY);
        $table_support_status = Database::get_main_table(TABLE_TICKET_STATUS);
        $table_support_messages = Database::get_main_table(TABLE_TICKET_MESSAGE);
        $table_main_user = Database::get_main_table(TABLE_MAIN_USER);

        if (is_null($direction)) {
            $direction = 'DESC';
        }
        if (is_null($userId) || 0 == $userId) {
            $userId = api_get_user_id();
        }

        $sql = "SELECT
                    ticket.code,
                    ticket.sys_insert_datetime,
                    ticket.sys_lastedit_datetime,
                    cat.title as category,
                    CONCAT(user.lastname,' ', user.firstname) AS fullname,
                    status.title as status,
                    ticket.total_messages as messages,
                    ticket.assigned_last_user as responsable
                FROM $table_support_tickets ticket,
                $table_support_category cat ,
                $table_support_priority priority,
                $table_support_status status ,
                $table_main_user user
                WHERE
                    cat.id = ticket.category_id
                    AND ticket.priority_id = priority.id
                    AND ticket.status_id = status.id
                    AND user.user_id = ticket.request_user ";
        $sql .= ' AND ticket.access_url_id = '.(int) Container::getAccessUrlUtil()->getCurrent()->getId();

        // Search simple
        if (isset($_GET['submit_simple'])) {
            if ('' !== $_GET['keyword']) {
                $keyword = Database::escape_string(trim($_GET['keyword']));
                $sql .= " AND (ticket.code = '$keyword'
                          OR user.firstname LIKE '%$keyword%'
                          OR user.lastname LIKE '%$keyword%'
                          OR concat(user.firstname,' ',user.lastname) LIKE '%$keyword%'
                          OR concat(user.lastname,' ',user.firstname) LIKE '%$keyword%'
                          OR user.username LIKE '%$keyword%')  ";
            }
        }
        // Search advanced
        if (isset($_GET['submit_advanced'])) {
            $keyword_category = Database::escape_string(
                trim($_GET['keyword_category'])
            );
            $keyword_request_user = Database::escape_string(
                trim($_GET['keyword_request_user'])
            );
            $keywordAssignedTo = (int) $_GET['keyword_assigned_to'];
            $keyword_start_date_start = Database::escape_string(
                trim($_GET['keyword_start_date_start'])
            );
            $keyword_start_date_end = Database::escape_string(
                trim($_GET['keyword_start_date_end'])
            );
            $keyword_status = Database::escape_string(
                trim($_GET['keyword_status'])
            );
            $keyword_source = Database::escape_string(
                trim($_GET['keyword_source'])
            );
            $keyword_priority = Database::escape_string(
                trim($_GET['keyword_priority'])
            );
            $keyword_range = Database::escape_string(
                trim($_GET['keyword_dates'])
            );
            $keyword_unread = Database::escape_string(
                trim($_GET['keyword_unread'])
            );
            $keyword_course = Database::escape_string(
                trim($_GET['keyword_course'])
            );

            if ('' != $keyword_category) {
                $sql .= " AND ticket.category_id = '$keyword_category'  ";
            }
            if ('' != $keyword_request_user) {
                $sql .= " AND (ticket.request_user = '$keyword_request_user'
                          OR user.firstname LIKE '%$keyword_request_user%'
                          OR user.official_code LIKE '%$keyword_request_user%'
                          OR user.lastname LIKE '%$keyword_request_user%'
                          OR concat(user.firstname,' ',user.lastname) LIKE '%$keyword_request_user%'
                          OR concat(user.lastname,' ',user.firstname) LIKE '%$keyword_request_user%'
                          OR user.username LIKE '%$keyword_request_user%') ";
            }
            if (!empty($keywordAssignedTo)) {
                $sql .= " AND ticket.assigned_last_user = $keywordAssignedTo ";
            }
            if ('' != $keyword_status) {
                $sql .= " AND ticket.status_id = '$keyword_status'  ";
            }
            if ('' == $keyword_range && '' != $keyword_start_date_start) {
                $sql .= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') = '$keyword_start_date_start' ";
            }
            if ('1' == $keyword_range && '' != $keyword_start_date_start && '' != $keyword_start_date_end) {
                $sql .= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') >= '$keyword_start_date_start'
                          AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') <= '$keyword_start_date_end'";
            }
            if ('' != $keyword_priority) {
                $sql .= " AND ticket.priority_id = '$keyword_priority'  ";
            }
            if ('' != $keyword_source) {
                $sql .= " AND ticket.source = '$keyword_source' ";
            }
            if ('' != $keyword_priority) {
                $sql .= " AND ticket.priority_id = '$keyword_priority' ";
            }
            if ('' != $keyword_course) {
                $course_table = Database::get_main_table(TABLE_MAIN_COURSE);
                $sql .= " AND ticket.course_id IN ( ";
                $sql .= "SELECT id
                         FROM $course_table
                         WHERE (title LIKE '%$keyword_course%'
                         OR code LIKE '%$keyword_course%'
                         OR visual_code LIKE '%$keyword_course%' )) ";
            }
            if ('yes' == $keyword_unread) {
                $sql .= " AND ticket.id IN (
                          SELECT ticket.id
                          FROM $table_support_tickets ticket,
                          $table_support_messages message,
                          $table_main_user user
                          WHERE ticket.id = message.ticket_id
                          AND message.status = 'NOL'
                          AND message.sys_insert_user_id = user.user_id
                          AND user.status != 1   AND ticket.status_id != '".self::STATUS_FORWARDED."'
                          GROUP BY ticket.id)";
            } else {
                if ('no' == $keyword_unread) {
                    $sql .= " AND ticket.id NOT IN (
                              SELECT ticket.id
                              FROM  $table_support_tickets ticket,
                              $table_support_messages message,
                              $table_main_user user
                              WHERE ticket.id = message.ticket_id
                              AND message.status = 'NOL'
                              AND message.sys_insert_user_id = user.user_id
                              AND user.status != 1
                              AND ticket.status_id != '".self::STATUS_FORWARDED."'
                             GROUP BY ticket.id)";
                }
            }
        }

        $sql .= !str_contains($sql, 'WHERE') ? ' WHERE user.active <> '.USER_SOFT_DELETED : ' AND user.active <> '.USER_SOFT_DELETED;
        $sql .= " LIMIT $from,$number_of_items";

        $result = Database::query($sql);
        $tickets[0] = [
            utf8_decode('Ticket#'),
            utf8_decode('Fecha'),
            utf8_decode('Fecha Edicion'),
            utf8_decode('Categoria'),
            utf8_decode('Usuario'),
            utf8_decode('Estado'),
            utf8_decode('Mensajes'),
            utf8_decode('Responsable'),
            utf8_decode('Programa'),
        ];

        while ($row = Database::fetch_assoc($result)) {
            if (0 != $row['responsable']) {
                $row['responsable'] = api_get_user_info($row['responsable']);
                $row['responsable'] = $row['responsable']['firstname'].' '.$row['responsable']['lastname'];
            }
            $row['sys_insert_datetime'] = api_format_date(
                $row['sys_insert_datetime'],
                '%d/%m/%y - %I:%M:%S %p'
            );
            $row['sys_lastedit_datetime'] = api_format_date(
                $row['sys_lastedit_datetime'],
                '%d/%m/%y - %I:%M:%S %p'
            );
            $row['category'] = utf8_decode($row['category']);
            $row['programa'] = utf8_decode($row['fullname']);
            $row['fullname'] = utf8_decode($row['fullname']);
            $row['responsable'] = utf8_decode($row['responsable']);
            $tickets[] = $row;
        }

        return $tickets;
    }

    /**
     * @param string $url
     * @param int    $projectId
     *
     * @return FormValidator
     */
    public static function getCategoryForm($url, $projectId)
    {
        $form = new FormValidator('category', 'post', $url);
        $form->addText('name', get_lang('Name'));
        $form->addHtmlEditor('description', get_lang('Description'));
        $form->addHidden('project_id', $projectId);
        $form->addButtonUpdate(get_lang('Save'));

        return $form;
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        $accessUrl = Container::getAccessUrlUtil()->getCurrent();
        $items = Database::getManager()
            ->getRepository(TicketStatus::class)
            ->findBy(['accessUrl' => $accessUrl]);

        $list = [];
        /** @var TicketStatus $row */
        foreach ($items as $row) {
            $list[$row->getId()] = $row->getTitle();
        }

        return $list;
    }

    /**
     * @param array $criteria
     *
     * @return array
     */
    public static function getTicketsFromCriteria($criteria)
    {
        $items = Database::getManager()->getRepository(Ticket::class)->findBy($criteria);
        $list = [];
        /** @var Ticket $row */
        foreach ($items as $row) {
            $list[$row->getId()] = $row->getCode();
        }

        return $list;
    }

    /**
     * @param string $code
     *
     * @return int
     */
    public static function getStatusIdFromCode($code)
    {
        $item = Database::getManager()
            ->getRepository(TicketStatus::class)
            ->findOneBy(['code' => $code])
        ;

        if ($item) {
            return $item->getId();
        }

        return 0;
    }

    /**
     * @return array
     */
    public static function getPriorityList()
    {
        $accessUrl = Container::getAccessUrlUtil()->getCurrent();
        $priorities = Database::getManager()
            ->getRepository(TicketPriority::class)
            ->findBy(['accessUrl' => $accessUrl]);

        $list = [];
        /** @var TicketPriority $row */
        foreach ($priorities as $row) {
            $list[$row->getId()] = $row->getTitle();
        }

        return $list;
    }

    /**
     * @return array
     */
    public static function getProjects()
    {
        $accessUrl = Container::getAccessUrlUtil()->getCurrent();
        $projects = Database::getManager()
            ->getRepository(TicketProject::class)
            ->findBy(['accessUrl' => $accessUrl]);

        $list = [];
        /** @var TicketProject $row */
        foreach ($projects as $row) {
            $list[] = [
                'id' => $row->getId(),
                '0' => $row->getId(),
                '1' => $row->getTitle(),
                '2' => $row->getDescription(),
                '3' => $row->getId(),
            ];
        }

        return $list;
    }

    /**
     * @return array
     */
    public static function getProjectsSimple()
    {
        $accessUrl = Container::getAccessUrlUtil()->getCurrent();
        $projects = Database::getManager()
            ->getRepository(TicketProject::class)
            ->findBy(['accessUrl' => $accessUrl]);

        $list = [];
        /** @var TicketProject $row */
        foreach ($projects as $row) {
            $list[] = [
                'id' => $row->getId(),
                '0' => $row->getId(),
                '1' => Display::url(
                    $row->getTitle(),
                    api_get_path(WEB_CODE_PATH).'ticket/tickets.php?project_id='.$row->getId()
                ),
                '2' => $row->getDescription(),
            ];
        }

        return $list;
    }

    /**
     * @return int
     */
    public static function getProjectsCount()
    {
        return Database::getManager()->getRepository(TicketProject::class)->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array $params
     */
    public static function addProject($params)
    {
        $project = new TicketProject();
        $project->setTitle($params['title']);
        $project->setDescription($params['description']);
        $project->setInsertUserId(api_get_user_id());
        $project->setAccessUrl(Container::getAccessUrlUtil()->getCurrent());

        Database::getManager()->persist($project);
        Database::getManager()->flush();
    }

    /**
     * @param int $id
     *
     * @return TicketProject
     */
    public static function getProject($id)
    {
        return Database::getManager()->getRepository(TicketProject::class)->find($id);
    }

    /**
     * @param int   $id
     * @param array $params
     */
    public static function updateProject($id, $params)
    {
        $project = self::getProject($id);
        $project->setTitle($params['title']);
        $project->setDescription($params['description']);
        $project->setLastEditDateTime(new DateTime($params['sys_lastedit_datetime']));
        $project->setLastEditUserId($params['sys_lastedit_user_id']);
        $project->setAccessUrl(Container::getAccessUrlUtil()->getCurrent());

        Database::getManager()->persist($project);
        Database::getManager()->flush();
    }

    /**
     * @param int $id
     */
    public static function deleteProject($id)
    {
        $project = self::getProject($id);
        if ($project) {
            Database::getManager()->remove($project);
            Database::getManager()->flush();
        }
    }

    /**
     * @param string $url
     *
     * @return FormValidator
     */
    public static function getProjectForm($url)
    {
        $form = new FormValidator('project', 'post', $url);
        $form->addText('name', get_lang('Name'));
        $form->addHtmlEditor('description', get_lang('Description'));
        $form->addButtonUpdate(get_lang('Save'));

        return $form;
    }

    /**
     * @return array
     */
    public static function getStatusAdminList()
    {
        $accessUrl = Container::getAccessUrlUtil()->getCurrent();
        $items = Database::getManager()
            ->getRepository(TicketStatus::class)
            ->findBy(['accessUrl' => $accessUrl]);

        $list = [];
        /** @var TicketStatus $row */
        foreach ($items as $row) {
            $list[] = [
                'id' => $row->getId(),
                'code' => $row->getCode(),
                '0' => $row->getId(),
                '1' => $row->getTitle(),
                '2' => $row->getDescription(),
                '3' => $row->getId(),
            ];
        }

        return $list;
    }

    /**
     * @return array
     */
    /*public static function getStatusSimple()
    {
        $projects = Database::getManager()->getRepository(TicketStatus::class)->findAll();
        $list = [];
        // @var TicketProject $row
        foreach ($projects as $row) {
            $list[] = [
                'id' => $row->getId(),
                '0' => $row->getId(),
                '1' => Display::url($row->getName()),
                '2' => $row->getDescription(),
            ];
        }

        return $list;
    }*/

    /**
     * @return int
     */
    public static function getStatusCount()
    {
        return Database::getManager()->getRepository(TicketStatus::class)->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array $params
     */
    public static function addStatus($params)
    {
        $item = new TicketStatus();
        $item->setCode(URLify::filter($params['title']));
        $item->setTitle($params['title']);
        $item->setDescription($params['description']);
        $item->setAccessUrl(Container::getAccessUrlUtil()->getCurrent());

        Database::getManager()->persist($item);
        Database::getManager()->flush();
    }

    /**
     * @param $id
     *
     * @return TicketProject
     */
    public static function getStatus($id)
    {
        return Database::getManager()->getRepository(TicketStatus::class)->find($id);
    }

    /**
     * @param int   $id
     * @param array $params
     */
    public static function updateStatus($id, $params)
    {
        $item = self::getStatus($id);
        $item->setTitle($params['title']);
        $item->setDescription($params['description']);
        $item->setAccessUrl(Container::getAccessUrlUtil()->getCurrent());

        Database::getManager()->persist($item);
        Database::getManager()->flush();
    }

    /**
     * @param int $id
     */
    public static function deleteStatus($id)
    {
        $item = self::getStatus($id);
        if ($item) {
            Database::getManager()->remove($item);
            Database::getManager()->flush();
        }
    }

    /**
     * @param string $url
     *
     * @return FormValidator
     */
    public static function getStatusForm($url)
    {
        $form = new FormValidator('status', 'post', $url);
        $form->addText('name', get_lang('Name'));
        $form->addHtmlEditor('description', get_lang('Description'));
        $form->addButtonUpdate(get_lang('Save'));

        return $form;
    }

    /**
     * @return array
     */
    public static function getPriorityAdminList(): array
    {
        $accessUrl = Container::getAccessUrlUtil()->getCurrent();
        $items = Database::getManager()
            ->getRepository(TicketPriority::class)
            ->findBy(['accessUrl' => $accessUrl]);

        $list = [];
        /** @var TicketPriority $row */
        foreach ($items as $row) {
            $list[] = [
                'id' => $row->getId(),
                'code' => $row->getCode(),
                '0' => $row->getId(),
                '1' => $row->getTitle(),
                '2' => $row->getDescription(),
                '3' => $row->getId(),
            ];
        }

        return $list;
    }

    /**
     * @return int
     */
    public static function getPriorityCount()
    {
        return Database::getManager()->getRepository(TicketPriority::class)->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array $params
     */
    public static function addPriority($params)
    {
        $item = new TicketPriority();
        $item
            ->setCode(URLify::filter($params['title']))
            ->setTitle($params['title'])
            ->setDescription($params['description'])
            ->setColor('')
            ->setInsertUserId(api_get_user_id())
            ->setUrgency('')
            ->setAccessUrl(Container::getAccessUrlUtil()->getCurrent());

        Database::getManager()->persist($item);
        Database::getManager()->flush();
    }

    /**
     * @param $id
     *
     * @return TicketPriority
     */
    public static function getPriority($id)
    {
        return Database::getManager()->getRepository(TicketPriority::class)->find($id);
    }

    /**
     * @param int   $id
     * @param array $params
     */
    public static function updatePriority($id, $params)
    {
        $item = self::getPriority($id);
        $item->setTitle($params['title']);
        $item->setDescription($params['description']);
        $item->setAccessUrl(Container::getAccessUrlUtil()->getCurrent());

        Database::getManager()->persist($item);
        Database::getManager()->flush();
    }

    /**
     * @param int $id
     */
    public static function deletePriority($id)
    {
        $item = self::getPriority($id);
        if ($item) {
            Database::getManager()->remove($item);
            Database::getManager()->flush();
        }
    }

    /**
     * @param string $url
     *
     * @return FormValidator
     */
    public static function getPriorityForm($url)
    {
        $form = new FormValidator('priority', 'post', $url);
        $form->addText('name', get_lang('Name'));
        $form->addHtmlEditor('description', get_lang('Description'));
        $form->addButtonUpdate(get_lang('Save'));

        return $form;
    }

    /**
     * Returns a list of menu elements for the tickets system's configuration.
     *
     * @param string $exclude The element to exclude from the list
     *
     * @return array
     */
    public static function getSettingsMenuItems($exclude = null)
    {
        $project = [
            'icon' => ObjectIcon::PROJECT,
            'url' => 'projects.php',
            'content' => get_lang('Projects'),
        ];
        $status = [
            'icon' => StateIcon::COMPLETE,
            'url' => 'status.php',
            'content' => get_lang('Status'),
        ];
        $priority = [
            'icon' => StateIcon::EXPIRED,
            'url' => 'priorities.php',
            'content' => get_lang('Priority'),
        ];
        switch ($exclude) {
            case 'project':
                $items = [$status, $priority];
                break;
            case 'status':
                $items = [$project, $priority];
                break;
            case 'priority':
                $items = [$project, $status];
                break;
            default:
                $items = [$project, $status, $priority];
                break;
        }

        return $items;
    }

    /**
     * Returns a list of strings representing the default statuses.
     *
     * @return array
     */
    public static function getDefaultStatusList()
    {
        return [
            self::STATUS_NEW,
            self::STATUS_PENDING,
            self::STATUS_UNCONFIRMED,
            self::STATUS_CLOSE,
            self::STATUS_FORWARDED,
        ];
    }

    /**
     * @return array
     */
    public static function getDefaultPriorityList()
    {
        return [
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_LOW,
            self::STATUS_CLOSE,
            self::STATUS_FORWARDED,
        ];
    }

    /**
     * Deletes the user from all the ticket system.
     *
     * @param int $userId
     */
    public static function deleteUserFromTicketSystem($userId)
    {
        $userId = (int) $userId;
        $schema = Database::getManager()->getConnection()->createSchemaManager();

        if ($schema->tablesExist('ticket_assigned_log')) {
            $sql = "UPDATE ticket_assigned_log SET user_id = NULL WHERE user_id = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_assigned_log SET sys_insert_user_id = NULL WHERE sys_insert_user_id = $userId";
            Database::query($sql);
        }

        if ($schema->tablesExist('ticket_ticket')) {
            $sql = "UPDATE ticket_ticket SET assigned_last_user = NULL WHERE assigned_last_user = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_ticket SET sys_insert_user_id = NULL WHERE sys_insert_user_id = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_ticket SET sys_lastedit_user_id = NULL WHERE sys_lastedit_user_id = $userId";
            Database::query($sql);
        }

        if ($schema->tablesExist('ticket_category')) {
            $sql = "UPDATE ticket_category SET sys_insert_user_id = NULL WHERE sys_insert_user_id = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_category SET sys_lastedit_user_id = NULL WHERE sys_lastedit_user_id = $userId";
            Database::query($sql);
        }

        if ($schema->tablesExist('ticket_category_rel_user')) {
            $sql = "DELETE FROM ticket_category_rel_user WHERE user_id = $userId";
            Database::query($sql);
        }

        if ($schema->tablesExist('ticket_message')) {
            $sql = "UPDATE ticket_message SET sys_insert_user_id = NULL WHERE sys_insert_user_id = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_message SET sys_lastedit_user_id = NULL WHERE sys_lastedit_user_id = $userId";
            Database::query($sql);
        }

        if ($schema->tablesExist('ticket_message_attachments')) {
            $sql = "UPDATE ticket_message_attachments SET sys_insert_user_id = NULL WHERE sys_insert_user_id = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_message_attachments SET sys_lastedit_user_id = NULL WHERE sys_lastedit_user_id = $userId";
            Database::query($sql);
        }

        if ($schema->tablesExist('ticket_priority')) {
            $sql = "UPDATE ticket_priority SET sys_insert_user_id = NULL WHERE sys_insert_user_id = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_priority SET sys_lastedit_user_id = NULL WHERE sys_lastedit_user_id = $userId";
            Database::query($sql);
        }

        if ($schema->tablesExist('ticket_project')) {
            $sql = "UPDATE ticket_project SET sys_insert_user_id = NULL WHERE sys_insert_user_id = $userId";
            Database::query($sql);

            $sql = "UPDATE ticket_project SET sys_lastedit_user_id = NULL WHERE sys_lastedit_user_id = $userId";
            Database::query($sql);
        }
    }

    /**
     * @deprecated Use TicketProjectHelper::userIsAllowInProject instead
     */
    public static function userIsAllowInProject(int $projectId): bool
    {
        $authorizationChecked = Container::getAuthorizationChecker();

        if ($authorizationChecked->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $allowRoleList = self::getAllowedRolesFromProject($projectId);

        // Check if a role was set to the project.
        // Project 1 is considered the default and is accessible to all users
        if (!empty($allowRoleList)) {
            $result = false;
            foreach ($allowRoleList as $role) {
                if ($authorizationChecked->isGranted($role)) {
                    $result = true;
                    break;
                }
            }

            return $result;
        }

        return false;
    }

    /**
     * @deprecated Use TicketProjectHelper::getAllowedRolesFromProject instead
     */
    public static function getAllowedRolesFromProject(int $projectId): array
    {
        // Define a mapping from role IDs to role names
        $roleMap = [
            1 => 'ROLE_TEACHER',
            17 => 'ROLE_STUDENT_BOSS',
            4 => 'ROLE_HR',
            3 => 'ROLE_SESSION_MANAGER',
            // ... other mappings can be added as needed
        ];

        $jsonString = Container::getSettingsManager()->getSetting('ticket.ticket_project_user_roles');

        if (empty($jsonString)) {
            return [];
        }

        $data = json_decode($jsonString, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            // Invalid JSON
            return [];
        }

        if (!isset($data['permissions'][$projectId])) {
            // No permissions for the given projectId
            return [];
        }

        $roleIds = $data['permissions'][$projectId];

        // Transform role IDs into role names using the defined mapping
        return array_map(function ($roleId) use ($roleMap) {
            return $roleMap[$roleId] ?? "$roleId";
        }, $roleIds);
    }

    /**
     * Subscribes a user to a ticket.
     */
    public static function subscribeUserToTicket(int $ticketId, int $userId): void
    {
        $em = Database::getManager();
        $ticket = $em->getRepository(Ticket::class)->find($ticketId);
        $user = $em->getRepository(User::class)->find($userId);

        if ($ticket && $user) {
            $repository = $em->getRepository(TicketRelUser::class);
            $repository->subscribeUserToTicket($user, $ticket);

            Event::addEvent(
                'ticket_subscribe',
                'ticket_event',
                ['user_id' => $userId, 'ticket_id' => $ticketId, 'action' => 'subscribe']
            );
        }
    }

    /**
     * Unsubscribes a user from a ticket.
     */
    public static function unsubscribeUserFromTicket(int $ticketId, int $userId): void
    {
        $em = Database::getManager();
        $ticket = $em->getRepository(Ticket::class)->find($ticketId);
        $user = $em->getRepository(User::class)->find($userId);

        if ($ticket && $user) {
            $repository = $em->getRepository(TicketRelUser::class);
            $repository->unsubscribeUserFromTicket($user, $ticket);

            Event::addEvent(
                'ticket_unsubscribe',
                'ticket_event',
                ['user_id' => $userId, 'ticket_id' => $ticketId, 'action' => 'unsubscribe']
            );
        }
    }

    /**
     * Checks if a user is subscribed to a ticket.
     */
    public static function isUserSubscribedToTicket(int $ticketId, int $userId): bool
    {
        $em = Database::getManager();
        $ticket = $em->getRepository(Ticket::class)->find($ticketId);
        $user = $em->getRepository(User::class)->find($userId);

        if ($ticket && $user) {
            $repository = $em->getRepository(TicketRelUser::class);
            return $repository->isUserSubscribedToTicket($user, $ticket);
        }

        return false;
    }

    /**
     * Retrieves the followers of a ticket.
     */
    public static function getFollowers($ticketId): array
    {
        $em = Database::getManager();
        $repository = $em->getRepository(TicketRelUser::class);
        $ticket = $em->getRepository(Ticket::class)->find($ticketId);

        $followers = $repository->findBy(['ticket' => $ticket]);

        $users = [];
        foreach ($followers as $follower) {
            $users[] = $follower->getUser();
        }

        return $users;
    }

    /**
     * Generates an unsubscribe link for a ticket.
     */
    public static function generateUnsubscribeLink($ticketId, $userId): string
    {
        $token = new ValidationToken(ValidationTokenHelper::TYPE_TICKET, $ticketId);
        Database::getManager()->persist($token);
        Database::getManager()->flush();

        return api_get_path(WEB_PATH).'validate/ticket/'.$token->getHash().'?user_id='.$userId;
    }
}
