<?php require 'base.php'; ?>

<body>
    <div class="main">
        <div class="main-navbar">
            <div class="main-title">
                <h1>Admin Dashboard</h1>
            </div>
            <div class="profile">
            <span class="user"><ion-icon name="people-circle-outline"></ion-icon></span>
            <strong>Admin</strong>
        </div>
        </div>

        <div class="main-highlight">
            <div class="main-header">
                <div class="main-title">
                    <h2>User Feedback Management</h2>
                    <p>Review and delete messages sent from the Contact Us page.</p>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div style="background-color: var(--whiteColor); color: var(--darkBrown); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid var(--primaryColor);">
                <strong>System Notification:</strong> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
            </div>
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

                            <form method="POST" onsubmit="return confirm('Confirm deletion of this feedback?');">
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