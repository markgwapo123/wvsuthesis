<?php
session_start();
require_once __DIR__ . '/../../db_connect.php'; 

// Authenticate session configuration cleanly
if (!isset($_SESSION['role'])) {
    header("Content-Type: application/json");
    echo json_encode(['response' => 'Session expired. Please log in again to continue.']);
    exit();
}

// Dedicated internal structured knowledge base array 
$faqs = [
    'login' => 'To log in to SOIMS, go to the login landing screen, enter your assigned corporate username or password credentials, and select the login button.',
    
    'add_item' => 'To add items or supplies, open the "Inventory Management" dropdown menu on your sidebar layout, select "Manage Inventory", and choose the "Add Item" interface control option.',
    
    'check_stock' => 'To monitor stock levels, expand the "Reports" option group section inside your navigation sidebar. Click on either "Inventory Summary Report" or "Stock Levels" to review complete quantity data charts.',
    
    'low_stock' => 'When your stocks fall to critical boundaries, an automated alert flag triggers right inside the system notifications center. Admins see these explicitly highlighted in red layout styling rules within the Control Center dashboard.',
    
    'issue_item' => 'To issue items out to department employees, click on "Inventory Management" inside the sidebar links, select "Order Management", select the item, provide your quantities, and finalize the issue task.',
    
    'suppliers' => 'To adjust or configure vendors, expand the inventory tools layout, click "Suppliers List", and utilize the custom management pipeline options available there.',
    
    'disposal' => 'To dispose of assets, an End-User goes to "My Workspace" (receive_item.php) and hits "Request Disposal" on their row balance. Once confirmed, this issues alerts directly to the DAA and Admin notification hub records persistently.',
    
    'restock' => 'To restock inventory elements, navigate to the inventory panel controls and trigger a procurement communication request flag. Once suppliers fulfill tracking logistics, utilize the "Stock In Report" panel to safely update total volume metrics.',
    
    'password_forgot' => 'If you are locked out of your account, locate the "Forgot Password" link on the landing page wrapper to request an automated recovery link via your registered Gmail account profile entry.',
    
    'password_reset' => 'If you are currently logged in and want to reset your password: Go to your profile menu, enter your verification password context, and select "Send OTP". This triggers an automated system email to your registered Gmail address. Copy that OTP key value, paste it into the verification input form field framework, click "Submit OTP", and define your modified password configuration string.'
];

function getAnswer($user_message, $faqs) {
    // Standardize input string safely
    $msg = strtolower(trim($user_message));

    // 1. DISPOSAL / SCRAP / BROKEN PIPELINES
    if (strpos($msg, 'dispos') !== false || strpos($msg, 'scrap') !== false || strpos($msg, 'broken') !== false || strpos($msg, 'junk') !== false) {
        return $faqs['disposal'];
    }

    // 2. ISSUING QUANTITIES
    if (strpos($msg, 'issue') !== false || strpos($msg, 'send item') !== false || strpos($msg, 'handout') !== false) {
        return $faqs['issue_item'];
    }

    // 3. REPLENISHMENTS / RESTOCKING
    if (strpos($msg, 'restock') !== false || strpos($msg, 'procure') !== false || strpos($msg, 'buy') !== false) {
        return $faqs['restock'];
    }

    // 4. ADD NEW RECORDS
    if (strpos($msg, 'add') !== false || strpos($msg, 'create') !== false || strpos($msg, 'insert') !== false) {
        return $faqs['add_item'];
    }

    // 5. STOCK CHECKS & ALERT LEVELS
    if (strpos($msg, 'check') !== false || strpos($msg, 'view stock') !== false || strpos($msg, 'report') !== false) {
        return $faqs['check_stock'];
    }
    if (strpos($msg, 'low') !== false || strpos($msg, 'out of stock') !== false || strpos($msg, 'alert') !== false || strpos($msg, 'warn') !== false) {
        return $faqs['low_stock'];
    }

    // 6. DETAILED OTP / ENCRYPTED PROFILE PASSWORD MODIFICATIONS
    if (strpos($msg, 'reset') !== false || strpos($msg, 'change password') !== false || strpos($msg, 'otp') !== false || strpos($msg, 'gmail') !== false) {
        return $faqs['password_reset'];
    }
    if (strpos($msg, 'forgot') !== false || strpos($msg, 'login page') !== false) {
        return $faqs['password_forgot'];
    }

    // 7. ACCOUNT ACCESS AUTHENTICATIONS
    if (strpos($msg, 'login') !== false || strpos($msg, 'sign in') !== false) {
        return $faqs['login'];
    }
    if (strpos($msg, 'supplier') !== false || strpos($msg, 'vendor') !== false) {
        return $faqs['suppliers'];
    }

    // Safe System Fallback Match Statement
    return "I couldn't locate a precise step-by-step match for your question text phrasing. Could you specify if you are trying to manage inventory balances, request asset disposals, adjust account credentials settings, or pull dynamic system reports metrics?";
}

// Localized interceptor processing logic for AJAX Requests 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $user_message = isset($_POST['user_message']) ? trim($_POST['user_message']) : '';
    
    if ($user_message === '') {
        echo json_encode(['response' => 'Please type a message query context step description before submitting.']);
        exit();
    }
    
    $response = getAnswer($user_message, $faqs);
    echo json_encode(['response' => $response]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOIMS Support Hub Help Desk Chat</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 0; }
        .chat-container { width: 420px; height: 550px; background: white; position: fixed; bottom: 20px; right: 20px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; overflow: hidden; border: 1px solid #e2e8f0; }
        .chat-header { background: #0f172a; color: white; padding: 18px; text-align: center; font-weight: bold; font-size: 16px; border-bottom: 2px solid #1e293b; }
        .chat-body { padding: 15px; flex-grow: 1; overflow-y: auto; background: #f8fafc; }
        .chat-input { display: flex; padding: 12px; background: #ffffff; border-top: 1px solid #e2e8f0; gap: 8px; }
        .chat-input input { flex-grow: 1; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
        .chat-input input:focus { border-color: #3b82f6; }
        .chat-input button { padding: 10px 16px; background: #3b82f6; border: none; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px; transition: background 0.15s; }
        .chat-input button:hover { background: #2563eb; }
        .chat-message { margin-bottom: 12px; line-height: 1.4; font-size: 13.5px; padding: 10px 14px; border-radius: 8px; max-width: 85%; }
        .user-message { text-align: left; background: #eff6ff; color: #1e40af; font-weight: 600; margin-left: auto; border-bottom-right-radius: 1px; }
        .bot-message { text-align: left; background: #e2e8f0; color: #1e293b; margin-right: auto; border-bottom-left-radius: 1px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <div class="chat-container">
        <div class="chat-header">
            🛡️ SOIMS System Assistant
        </div>
        <div class="chat-body" id="chat-body">
            <div class="chat-message bot-message">
                Hello! I am your SOIMS support assistant. Ask me questions like:
                <br><br>
                • <em>"How do I reset my password?"</em><br>
                • <em>"How to request an item disposal?"</em><br>
                • <em>"Where can I look at low stock levels?"</em>
            </div>
        </div>
        <div class="chat-input">
            <input type="text" id="user-message" placeholder="Type system workflow question..." autocomplete="off" />
            <button id="send-message">Send</button>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            function processChatInput() {
                var msgText = $('#user-message').val().trim();
                if (msgText !== "") {
                    // Append User UI elements Bubble string
                    appendBubble(msgText, 'user');
                    $('#user-message').val(''); // Clear the input field text content

                    // Execute targeted post communication pipeline loop 
                    $.ajax({
                        url: 'support_ajax.php', // Explicit target route pointing safely directly to itself
                        type: 'POST',
                        data: { user_message: msgText },
                        dataType: 'json',
                        success: function(data) {
                            appendBubble(data.response, 'bot');
                        },
                        error: function() {
                            appendBubble("An unexpected interface connectivity communication error intercepted the query workflow trace requests.", 'bot');
                        }
                    });
                }
            }

            function appendBubble(text, sender) {
                var body = $('#chat-body');
                var bubble = $('<div></div>').addClass('chat-message');
                
                if (sender === 'user') {
                    bubble.addClass('user-message').text(text);
                } else {
                    // Bot responses may contain bold lists text layout structures safely 
                    bubble.addClass('bot-message').html(text);
                }
                
                body.append(bubble);
                body.scrollTop(body[0].scrollHeight);
            }

            // Click listener execution branch binding tree
            $('#send-message').on('click', function() {
                processChatInput();
            });

            // Enter keyboard stroke parsing action engine
            $('#user-message').on('keypress', function(e) {
                if (e.which === 13) {
                    processChatInput();
                }
            });
        });
    </script>
</body>
</html>