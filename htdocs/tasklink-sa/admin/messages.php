<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];

// --- Handle sending message (with attachment support) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'])) {
    $receiver_id  = intval($_POST['receiver_id']);
    $message_text = trim($_POST['message_text'] ?? '');

    // Handle attachment
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];

        if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../uploads/messages/';
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
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text, attachment) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$current_user_id, $receiver_id, $message_text, $attachment_path]);
        header("Location: messages.php?chat_with=" . $receiver_id);
        exit();
    }
}

$chat_with_id = isset($_GET['chat_with']) ? intval($_GET['chat_with']) : 0;

// Get all users + providers (admin can message anyone)
$contacts = $pdo->query("
    SELECT user_id, full_name, role 
    FROM users 
    WHERE role IN ('client', 'provider')
    ORDER BY full_name ASC
")->fetchAll();

// Get chat history
$chat_history = [];
$active_contact_name = '';

if ($chat_with_id > 0) {
    $user_stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $user_stmt->execute([$chat_with_id]);
    $active_contact_name = $user_stmt->fetchColumn() ?: 'User';

    $log_stmt = $pdo->prepare("
        SELECT m.*, u.full_name as sender_name 
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
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
    <title>Admin Messages – TaskLink SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #F8FAFC; margin:0; padding:0; }
        .navbar { background: white; padding: 16px 40px; border-bottom: 1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; }
        .chat-container { display: grid; grid-template-columns: 320px 1fr; height: calc(100vh - 64px); }
        .sidebar { background: #F8FAFC; border-right: 1px solid #E2E8F0; overflow-y: auto; }
        .sidebar-header { padding: 20px; font-weight: 700; border-bottom: 1px solid #E2E8F0; }
        .contact-item { padding: 14px 20px; border-bottom: 1px solid #EDF2F7; text-decoration: none; color: inherit; display:block; }
        .contact-item:hover { background: #EDF2F7; }
        .contact-item.active { background: #E8F5EE; border-left: 4px solid #1B6B3A; }
        .chat-window { display: flex; flex-direction: column; background: white; }
        .chat-header { padding: 18px 24px; border-bottom: 1px solid #E2E8F0; font-weight: 700; }
        .message-stream { flex: 1; padding: 24px; overflow-y: auto; background: #F1F5F9; display:flex; flex-direction:column; gap:14px; }
        .bubble { max-width: 65%; padding: 12px 16px; border-radius: 12px; font-size:14px; }
        .bubble.outgoing { background: #1B6B3A; color: white; align-self: flex-end; }
        .bubble.incoming { background: white; border: 1px solid #E2E8F0; align-self: flex-start; }
        .chat-input-bar { padding: 20px; border-top: 1px solid #E2E8F0; background: white; }
        .chat-form { display: flex; gap: 12px; align-items: center; }
        .input-box { flex: 1; padding: 14px 16px; border: 1px solid #CBD5E0; border-radius: 8px; font-size: 14px; }
        .send-btn { background: #1B6B3A; color: white; border: none; padding: 0 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        
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
        
        .empty-state { flex:1; display:flex; align-items:center; justify-content:center; color:#A0AEC0; flex-direction:column; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="/admin/dashboard.php" style="font-weight:700; color:#1B6B3A; text-decoration:none;">TaskLink SA — Admin Messages</a>
    <a href="/admin/dashboard.php" style="color:#1B6B3A; font-weight:600; text-decoration:none;">← Back to Dashboard</a>
</nav>

<div class="chat-container">
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">All Users & Providers</div>
        <?php foreach ($contacts as $contact): ?>
            <a href="?chat_with=<?php echo $contact['user_id']; ?>" 
               class="contact-item <?php echo ($chat_with_id == $contact['user_id']) ? 'active' : ''; ?>">
                <strong><?php echo htmlspecialchars($contact['full_name']); ?></strong><br>
                <small style="color:#718096;"><?php echo ucfirst($contact['role']); ?></small>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Chat Window -->
    <div class="chat-window">
        <?php if ($chat_with_id > 0): ?>
            <div class="chat-header">
                Chatting with: <?php echo htmlspecialchars($active_contact_name); ?>
            </div>

            <div class="message-stream" id="messageStream">
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
                        
                        <span style="font-size:10px; opacity:0.7; display:block; margin-top:4px; text-align:right;">
                            <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-input-bar">
                <form method="POST" class="chat-form" enctype="multipart/form-data">
                    <input type="hidden" name="receiver_id" value="<?php echo $chat_with_id; ?>">
                    
                    <div style="flex: 1; display: flex; gap: 8px; align-items: center;">
                        <input type="text" name="message_text" class="input-box" 
                               placeholder="Type your message..." autocomplete="off" autofocus style="flex:1;">
                        
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
                var stream = document.getElementById("messageStream");
                if(stream) stream.scrollTop = stream.scrollHeight;
                
                const attachInput = document.getElementById('attachment-input');
                if (attachInput) {
                    attachInput.addEventListener('change', function() {
                        if (this.files.length > 0) {
                            console.log('Selected: ' + this.files[0].name);
                        }
                    });
                }
            </script>

        <?php else: ?>
            <div class="empty-state">
                <h3>Select a user or provider</h3>
                <p>Choose someone from the left to start messaging.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>