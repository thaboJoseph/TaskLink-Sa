<?php
session_start();
require_once 'includes/db.php';

// Enforce active login check
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];

// --- SECTION 1: PROCESSING INCOMING NEW MESSAGES (WITH ATTACHMENTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'])) {
    $receiver_id  = intval($_POST['receiver_id']);
    $message_text = trim($_POST['message_text'] ?? '');

    // Handle attachment upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];

        if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/uploads/messages/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newName = 'msg_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                $attachment_path = 'uploads/messages/' . $newName;
            }
        }
    }

    if (!empty($message_text) || $attachment_path !== null) {
        $send_stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text, attachment) 
            VALUES (?, ?, ?, ?)
        ");
        $send_stmt->execute([$current_user_id, $receiver_id, $message_text, $attachment_path]);
        
        header("Location: messages.php?chat_with=" . $receiver_id);
        exit();
    }
}

// --- SECTION 2: FETCH ACTIVE CONTACT CHAT LIST ---
$chat_with_id = isset($_GET['chat_with']) ? intval($_GET['chat_with']) : 0;

// Back link logic
if ($current_role === 'provider') {
    $back_href = '/provider/dashboard.php';
} elseif ($current_role === 'admin') {
    $back_href = '/admin/dashboard.php';
} else {
    $back_href = '/my-bookings.php';
}

$contacts = [];

if ($current_role === 'admin') {
    // Admin sees everyone
    $contact_stmt = $pdo->query("
        SELECT user_id, full_name, role 
        FROM users 
        WHERE role IN ('client', 'provider')
        ORDER BY full_name ASC
    ");
    $contacts = $contact_stmt->fetchAll();
} else {
    // Original logic for clients & providers
    $admin_stmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' ORDER BY user_id ASC LIMIT 1");
    $admin_id = $admin_stmt->fetchColumn() ?: 0;

    $query_params = [$current_user_id, $current_user_id, $current_user_id, $current_user_id];
    $extra_condition = "";

    if ($chat_with_id > 0) {
        $extra_condition = " OR u.user_id = ? ";
        $query_params[] = $chat_with_id;
    }

    $query_params[] = $current_user_id;

    $contact_stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.full_name, u.role 
        FROM users u
        WHERE (
            u.user_id IN (
                SELECT client_id FROM bookings WHERE provider_id = ?
                UNION
                SELECT provider_id FROM bookings WHERE client_id = ?
                UNION
                SELECT receiver_id FROM messages WHERE sender_id = ?
                UNION
                SELECT sender_id FROM messages WHERE receiver_id = ?
            ) $extra_condition
            OR u.user_id = ?   -- Always show Admin
        ) AND u.user_id != ?
    ");
    $query_params[] = $admin_id;
    $contact_stmt->execute($query_params);
    $contacts = $contact_stmt->fetchAll();

    // === RELIABLE ADMIN VISIBILITY FIX ===
    // This guarantees the Admin always appears for clients and providers
    if ($current_role !== 'admin') {
        $admin_check = $pdo->query("SELECT user_id, full_name, role FROM users WHERE role = 'admin' ORDER BY user_id ASC LIMIT 1");
        if ($admin = $admin_check->fetch()) {
            $admin_exists = false;
            foreach ($contacts as $c) {
                if ((int)$c['user_id'] === (int)$admin['user_id']) {
                    $admin_exists = true;
                    break;
                }
            }
            if (!$admin_exists) {
                $contacts[] = $admin;
            }
        }
    }
}

// --- SECTION 3: FETCH LIVE CHAT THREAD ---
$chat_history = [];
$active_contact_name = '';

if ($chat_with_id > 0) {
    $user_stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $user_stmt->execute([$chat_with_id]);
    $active_contact_name = $user_stmt->fetchColumn() ?: 'User';

    $log_stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $log_stmt->execute([$current_user_id, $chat_with_id, $chat_with_id, $current_user_id]);
    $chat_history = $log_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Inbox – TaskLink SA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background-color: #F8FAFC; color: #1A202C; height: 100vh; display: flex; flex-direction: column; }
        
        .navbar { background: #FFFFFF; border-bottom: 1px solid #E2E8F0; padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { font-size: 20px; font-weight: 700; color: #1B6B3A; text-decoration: none; }
        .back-link { font-size: 14px; color: #1B6B3A; text-decoration: none; font-weight: 600; }

        .chat-container { display: grid; grid-template-columns: 320px 1fr; flex: 1; overflow: hidden; background: #FFFFFF; }
        
        .sidebar-contacts { border-right: 1px solid #E2E8F0; background: #F8FAFC; overflow-y: auto; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; font-size: 16px; font-weight: 700; border-bottom: 1px solid #E2E8F0; color: #2D3748; }
        .contact-item { padding: 16px 20px; display: flex; flex-direction: column; border-bottom: 1px solid #EDF2F7; text-decoration: none; color: inherit; transition: background 0.2s; }
        .contact-item:hover { background: #EDF2F7; }
        .contact-item.active { background: #E8F5EE; border-left: 4px solid #1B6B3A; }
        .contact-name { font-size: 14px; font-weight: 600; color: #1A202C; }
        .contact-role { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #718096; margin-top: 2px; }

        .chat-window { display: flex; flex-direction: column; background: #FFFFFF; height: 100%; position: relative; }
        .chat-header { padding: 18px 24px; border-bottom: 1px solid #E2E8F0; font-weight: 700; font-size: 16px; background: #FFFFFF; color: #2D3748; }
        
        .message-stream { flex: 1; padding: 24px; overflow-y: auto; background: #F1F5F9; display: flex; flex-direction: column; gap: 14px; }
        
        .bubble { max-width: 60%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; word-wrap: break-word; }
        .bubble.outgoing { background: #1B6B3A; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        .bubble.incoming { background: #FFFFFF; color: #1A202C; align-self: flex-start; border-bottom-left-radius: 2px; border: 1px solid #E2E8F0; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .timestamp { font-size: 10px; opacity: 0.75; margin-top: 4px; text-align: right; display: block; }
        .bubble.incoming .timestamp { color: #718096; }

        .chat-input-bar { padding: 20px 24px; background: #FFFFFF; border-top: 1px solid #E2E8F0; }
        .chat-form { display: flex; gap: 12px; align-items: center; }
        .input-box { flex: 1; padding: 14px 16px; border: 1px solid #CBD5E0; border-radius: 8px; font-size: 14px; outline: none; }
        .input-box:focus { border-color: #1B6B3A; box-shadow: 0 0 0 2px rgba(27,107,58,0.15); }
        .send-btn { background: #1B6B3A; color: white; border: none; padding: 0 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .send-btn:hover { background: #14522B; }

        .attach-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            color: #1B6B3A;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .attach-btn:hover {
            background: #E8F5EE;
            border-color: #1B6B3A;
        }

        .empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #A0AEC0; padding: 40px; text-align: center; background: #F8FAFC; }
        
        .attachment-preview img {
            max-width: 220px;
            max-height: 180px;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            cursor: pointer;
            display: block;
        }
        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F1F5F9;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            color: #1B6B3A;
            text-decoration: none;
            margin-top: 8px;
        }
        .attachment-link:hover {
            background: #E8F5EE;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="#" class="nav-brand">TaskLink SA — Communication Hub</a>
        <a href="<?php echo $back_href; ?>" class="back-link">
            &larr; Exit Chat Workspace
        </a>
    </nav>

    <div class="chat-container">
        
        <div class="sidebar-contacts">
            <div class="sidebar-header">Conversations</div>
            <?php if (empty($contacts)): ?>
                <div style="padding: 30px; font-size:13px; color:#A0AEC0; text-align:center;">
                    No operational connections available to chat yet.
                </div>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <a href="?chat_with=<?php echo $contact['user_id']; ?>" 
                       class="contact-item <?php echo ($chat_with_id === $contact['user_id']) ? 'active' : ''; ?>">
                        <span class="contact-name"><?php echo htmlspecialchars($contact['full_name']); ?></span>
                        <span class="contact-role"><?php echo htmlspecialchars($contact['role']); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-window">
            <?php if ($chat_with_id > 0): ?>
                <div class="chat-header">
                    Chatting with: <?php echo htmlspecialchars($active_contact_name); ?>
                </div>
                
                <div class="message-stream" id="messageStream">
                    <?php if (empty($chat_history)): ?>
                        <div style="text-align:center; color:#A0AEC0; font-size:13px; margin:auto 0;">
                            Send a secure chat message below to coordinate scheduling instructions.
                        </div>
                    <?php else: ?>
                        <?php foreach ($chat_history as $msg): 
                            $is_outbound = ($msg['sender_id'] == $current_user_id);
                        ?>
                            <div class="bubble <?php echo $is_outbound ? 'outgoing' : 'incoming'; ?>">
                                <?php if (!empty($msg['message_text'])): ?>
                                    <div><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($msg['attachment'])): 
                                    $att = htmlspecialchars($msg['attachment']);
                                    $ext = strtolower(pathinfo($att, PATHINFO_EXTENSION));
                                    $is_image = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                                ?>
                                    <div class="attachment-preview" style="margin-top:8px;">
                                        <?php if ($is_image): ?>
                                            <a href="/<?php echo $att; ?>" target="_blank">
                                                <img src="/<?php echo $att; ?>" alt="Attachment">
                                            </a>
                                        <?php else: ?>
                                            <a href="/<?php echo $att; ?>" download class="attachment-link">
                                                📎 Download attachment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <span class="timestamp"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="chat-input-bar">
                    <form method="POST" action="" class="chat-form" enctype="multipart/form-data">
                        <input type="hidden" name="receiver_id" value="<?php echo $chat_with_id; ?>">
                        
                        <div style="flex: 1; display: flex; gap: 8px; align-items: center;">
                            <input type="text" name="message_text" class="input-box" 
                                   placeholder="Type your message here..." autocomplete="off" autofocus
                                   style="flex: 1;">
                            
                            <label for="attachment-input" class="attach-btn" title="Attach image or document">
                                📎
                            </label>
                            <input type="file" id="attachment-input" name="attachment" 
                                   style="display: none;" accept="image/*,.pdf,.doc,.docx">
                        </div>
                        
                        <button type="submit" class="send-btn">Send</button>
                    </form>
                </div>
                
                <script>
                    var objDiv = document.getElementById("messageStream");
                    if(objDiv) { objDiv.scrollTop = objDiv.scrollHeight; }
                    
                    const attachInput = document.getElementById('attachment-input');
                    if (attachInput) {
                        attachInput.addEventListener('change', function() {
                            if (this.files.length > 0) {
                                console.log('Selected file: ' + this.files[0].name);
                            }
                        });
                    }
                </script>

            <?php else: ?>
                <div class="empty-state">
                    <span style="font-size: 50px; margin-bottom: 16px;">💬</span>
                    <h3>Select a Conversation</h3>
                    <p style="font-size:14px; margin-top:4px; max-width:320px;">Pick an active contact entry on the left column list to review communication timelines.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>