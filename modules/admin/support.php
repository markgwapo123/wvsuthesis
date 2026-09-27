<?php
session_start();
require_once __DIR__ . '/../../db_connect.php'; // Ensure your database connection is available

// Check if the user is authenticated
if (!isset($_SESSION['role'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
        }

        .main-content {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            max-width: 1500px;
            margin: 20px auto;
            box-shadow: 0 0 8px rgba(0, 0, 0, .1);
        }

        h1 {
            margin: 0 0 20px;
            color: #f8f9faff;
        }

        /* Chat Container */
        .chat-container {
            flex-grow: 1;
            background: white;
            margin-left: 250px;
            /* To accommodate sidebar width */
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .chat-header {
            background: #0a77ebff;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            border-bottom: 2px solid #ddd;
        }

        .chat-body {
            padding: 15px;
            height: calc(100% - 150px);
            /* Adjust height for body */
            overflow-y: auto;
            border-bottom: 2px solid #ddd;
            flex-grow: 1;
        }

        .chat-input {
            display: flex;
            padding: 15px;
            background: #f9f9f9;
            border-top: 2px solid #ddd;
        }

        .chat-input input {
            width: 85%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .chat-input button {
            width: 12%;
            padding: 10px;
            margin-left: 5px;
            background: #007bff;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        .chat-input button:hover {
            background: #0056b3;
        }

        .chat-message {
            margin-bottom: 15px;
            word-wrap: break-word;
        }

        .user-message {
            text-align: right;
            font-weight: bold;
            color: #007bff;
        }

        .bot-message {
            text-align: left;
            color: #333;
        }

        /* Styling for the scrollbar */
        .chat-body::-webkit-scrollbar {
            width: 8px;
        }

        .chat-body::-webkit-scrollbar-thumb {
            background-color: #0026ffe5;
            border-radius: 4px;
        }

        .chat-body::-webkit-scrollbar-track {
            background-color: #f1f1f1;
        }
    </style>
</head>

<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
    <div class="dashboard">
        <?php include __DIR__ . '/../../sidebar.php'; ?>

        <main class="main-content">


                <div class="chat-header">
                    <h4>SOIMS Support Chat</h4>
                </div>
                <div class="chat-body" id="chat-body">
                    <!-- Messages will be dynamically loaded here -->
                </div>
                <div class="chat-input">
                    <input type="text" id="user-message" placeholder="Ask me anything about SOIMS..." />
                    <button id="send-message">Send</button>
                </div>
            





        </main>

    </div>
    <script>
        // When the "Send" button is clicked
        document.getElementById('send-message').addEventListener('click', function() {
            var userMessage = document.getElementById('user-message').value;
            if (userMessage.trim() !== "") {
                // Add user message to the chat
                addMessageToChat(userMessage, 'user');
                document.getElementById('user-message').value = ''; // Clear input field

                // Send AJAX request to the server
                var formData = new FormData();
                formData.append('user_message', userMessage);

                fetch('support_ajax.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        var botResponse = data.response;
                        addMessageToChat(botResponse, 'bot');
                    })
                    .catch(error => console.error('Error:', error));
            }
        });

        // Function to add messages to the chat
        function addMessageToChat(message, sender) {
            var chatBody = document.getElementById('chat-body');
            var messageDiv = document.createElement('div');
            messageDiv.classList.add('chat-message');

            if (sender === 'user') {
                messageDiv.classList.add('user-message');
                messageDiv.textContent = message;
            } else {
                messageDiv.classList.add('bot-message');
                messageDiv.textContent = message;
            }

            chatBody.appendChild(messageDiv);
            chatBody.scrollTop = chatBody.scrollHeight; // Scroll to the bottom
        }
    </script>

    <script src="chart.umd.min.js"></script>
    <script src="admindashboard.js" defer></script>

</body>


</html>
