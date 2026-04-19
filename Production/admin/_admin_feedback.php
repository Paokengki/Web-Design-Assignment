<?php require 'base.php'; ?>

<body>
    <div class="main">
        <div class="main-navbar">
            <div class="main-title">
                <h1>Admin Dashboard</h1>
            </div>
            <a href="../admin/_admin_setting.php" style="text-decoration: none; color: inherit;">
                <div class="profile" style="cursor: pointer;">
                    <span class="user"><ion-icon name="people-circle-outline"></ion-icon></span>
                    <strong>Admin</strong>
             </div>
            </a>
        </div>

        <div class="main-highlight">
            <div class="main-header">
                <div class="main-title">
                    <h2>User Feedback Management</h2>
                    <p>Review and delete messages sent from the Contact Us page.</p>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): 
            $msg = $_SESSION['msg'];
            $isError = stripos($msg, 'error') !== false ? true : false;
            unset($_SESSION['msg']);
        ?>
            <div id="notification-bar" style="position: fixed; top: 20px; right: 20px; max-width: 400px; padding: 16px 20px; border-radius: 8px; background: <?php echo $isError ? '#e74c3c' : '#27ae60'; ?>; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; z-index: 9999; animation: slideIn 0.3s ease-out, slideOut 3s ease-out 2.7s forwards;">
                <div style="font-size: 20px;">
                    <?php echo $isError ? '❌' : '✓'; ?>
                </div>
                <div style="flex: 1; font-weight: 500;">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
                <button onclick="document.getElementById('notification-bar').style.display='none';" style="background: transparent; border: none; color: white; font-size: 18px; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">✕</button>
            </div>
            <style>
                @keyframes slideIn {
                    from {
                        transform: translateX(420px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(420px);
                        opacity: 0;
                    }
                }
            </style>
        <?php endif; ?>

        <div class="main-menus">
            <div class="main-detail">
                <div class="detail-wrapper">
                    <?php
                    $result = getAllFeedback($conn);
                    if ($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()):
                    ?>
                        <div class="detail-card" style="padding: 20px; height: auto; cursor: default;">
                            <div class="detail-name">
                                <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                                <p class="detail-sub"><?php echo htmlspecialchars($row['email']); ?></p>
                            </div>
                            
                            <div class="divider"></div>
                            
                            <div style="font-size: 13px; color: var(--blackColor); margin-bottom: 15px;">
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($row['phone']); ?></p>
                                <p style="margin-top: 10px; font-style: italic;">
                                    "<?php echo htmlspecialchars($row['message']); ?>"
                                </p>
                                <p style="font-size: 11px; color: gray; margin-top: 10px;">
                                    Received: <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                </p>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="feedback_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="delete_feedback" 
                                        style="background-color: var(--primaryColor); color: white; border: none; padding: 8px 15px; border-radius: 20px; cursor: pointer; width: 100%;">
                                    Delete Message
                                </button>
                            </form>
                        </div>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 50px;">
                            <p>No feedback messages found in the database.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>