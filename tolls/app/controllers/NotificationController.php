<?php
class NotificationController {
    private $db;
    public function __construct() {
        Security::requireStrictAdmin();
        $this->db = Database::getInstance();
    }
    public function index(): void {
        $notifications = $this->db->fetchAll(
            "SELECT n.*, u.full_name, u.email FROM notifications n
             LEFT JOIN users u ON n.user_id=u.id
             ORDER BY n.created_at DESC LIMIT 100"
        );
        $users = $this->db->fetchAll("SELECT id, full_name, email, role FROM users WHERE status='active' ORDER BY full_name");
        $unread = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE is_read=0")['c'];
        Response::view('admin/notifications', compact('notifications','users','unread'), 'admin');
    }
    public function send(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) Response::error('Invalid CSRF',403);
        $target  = Security::sanitize($_POST['target']??'all');
        $subject = Security::sanitize($_POST['subject']??'');
        $message = Security::sanitize($_POST['message']??'');
        $type    = Security::sanitize($_POST['type']??'info');
        if (!$subject || !$message) Response::json(array('error'=>'Subject and message are required'),400);
        if ($target === 'all') {
            $users = $this->db->fetchAll("SELECT id FROM users WHERE status='active'");
        } elseif ($target === 'low_balance') {
            $threshold = (float)($this->db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key='low_balance_alert'")['setting_value']??50);
            $users = $this->db->fetchAll("SELECT id FROM users WHERE wallet_balance < ? AND status='active' AND role='user'", array($threshold));
        } else {
            $users = array(array('id'=>(int)$target));
        }
        $count = 0;
        foreach ($users as $u) {
            $this->db->execute(
                "INSERT INTO notifications (user_id, type, subject, message, created_at) VALUES (?,?,?,?,NOW())",
                array($u['id'], $type, $subject, $message)
            );
            $count++;
        }
        Response::json(array('success'=>true,'message'=>"Notification sent to {$count} user(s)"));
    }
    public function markRead(): void {
        if (!Security::verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) Response::error('Invalid CSRF',403);
        $id = (int)($_POST['id']??0);
        if ($id) $this->db->execute("UPDATE notifications SET is_read=1 WHERE id=?", array($id));
        else     $this->db->execute("UPDATE notifications SET is_read=1");
        Response::json(array('success'=>true,'message'=>'Marked as read'));
    }
}
