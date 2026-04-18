<?php
require '../admin/base.php';
$pageTitle = "Manage Restaurants - Admin";
include '../admin/_admin_sidebar.php'; 
?>

<div class="main">
    <div class="main-navbar">
        <h1>Restaurant Management</h1>
        <div class="profile">
            <span class="user"><ion-icon name="people-circle-outline"></ion-icon></span>
            <strong>Admin</strong>
        </div>
    </div>

    <div class="main-highlight">
        <h3>Add New Restaurant</h3>
        <form action="../admin/base.php" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
            <input type="text" name="res_name" placeholder="Restaurant Name" required style="padding: 8px; border-radius: 5px; border: none;">
            <input type="text" name="res_type" placeholder="Cuisine Type (e.g., Italian)" required style="padding: 8px; border-radius: 5px; border: none;">
            <input type="email" name="res_email" placeholder="Email" style="padding: 8px; border-radius: 5px; border: none;">
            <input type="text" name="res_phone" placeholder="Phone Number" style="padding: 8px; border-radius: 5px; border: none;">
            <textarea name="res_address" placeholder="Full Address" style="grid-column: span 2; padding: 8px; border-radius: 5px; border: none;"></textarea>
            <button type="submit" name="add_restaurant" style="background: var(--darkBrown); color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; grid-column: span 2;">
                Add Restaurant
            </button>
        </form>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <p style="color: var(--whiteColor); margin: 10px 0;"><?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></p>
    <?php endif; ?>

    <?php
    $edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
    $res = null;
    if ($edit_id) {
        $stmt = $conn->prepare("SELECT * FROM Restaurant WHERE Restaurant_ID = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $res = $result->fetch_assoc();
        $stmt->close();
    }
    ?>

    <?php if ($res): ?>
        <div class="main-menus">
            <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <form action="../admin/base.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="restaurant_id" value="<?php echo $res['Restaurant_ID']; ?>">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <?php
                        // Use the same image tracking logic as cafe.php
                        $shopImage = "../material/" . $res['Name'] . "/shop.jpg";
                        if (!file_exists($shopImage)) {
                            $shopImage = "../material/images/default_res.jpg";
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($shopImage, ENT_QUOTES, 'UTF-8'); ?>" style="width: 200px; height: 150px; object-fit: cover; border-radius: 10px; border: 2px solid #ddd;">
                        <br><br>
                        <label>Change Restaurant Image:</label><br>
                        <input type="file" name="res_image" accept="image/*">
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="text" name="res_name" value="<?php echo htmlspecialchars($res['Name']); ?>" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <input type="text" name="res_type" value="<?php echo htmlspecialchars($res['Restaurant_type']); ?>" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <input type="email" name="res_email" value="<?php echo htmlspecialchars($res['Email']); ?>" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <input type="text" name="res_phone" value="<?php echo htmlspecialchars($res['Contain_number']); ?>" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <textarea name="res_address" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($res['Address']); ?></textarea>
                        <button type="submit" name="update_restaurant" style="background: var(--primaryColor); color: white; padding: 15px; border: none; border-radius: 8px; font-weight: bold;">Update Restaurant Info</button>
                    </div>
                </form>
                <div class="divider" style="margin: 30px 0;"></div>
                <form method="POST" action="../admin/base.php" onsubmit="return confirm('Permanently delete this restaurant?');">
                    <input type="hidden" name="restaurant_id" value="<?php echo $res['Restaurant_ID']; ?>">
                    <button type="submit" name="delete_restaurant" style="background: #c0392b; color: white; border: none; padding: 12px; border-radius: 8px; width: 100%; cursor: pointer;">
                        Remove Restaurant
                    </button>
                </form>
            </div>

            <!-- FOOD MANAGEMENT SECTION -->
            <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-top: 30px;">
                <h3>Manage Foods for <?php echo htmlspecialchars($res['Name']); ?></h3>
                <!-- Add Food Form -->
                <form action="../admin/base.php" method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
                    <input type="hidden" name="restaurant_id" value="<?php echo $res['Restaurant_ID']; ?>">
                    <input type="text" name="food_name" placeholder="Food Name" required style="padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <input type="number" name="food_price" placeholder="Price" step="0.01" required style="padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <textarea name="food_desc" placeholder="Description" required style="padding:1px; margin-top:1px; border-radius: 5px; border: 1px solid #ccc;"></textarea>
                    <input type="file" name="food_image" accept="image/*" required>
                    <button type="submit" name="add_food" style="background: var(--primaryColor); color: white; border: none; padding: 10px; border-radius: 5px; margin-top: 10px;">Add Food</button>
                </form>
                <!-- Food List -->
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th>Name</th>
                            <th>Type</th>
                            <th>Detail</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM Food WHERE Restaurant_ID = ?");
                    $stmt->bind_param("i", $res['Restaurant_ID']);
                    $stmt->execute();
                    $foods = $stmt->get_result();
                    while ($food = $foods->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($food['Name']); ?></td>
                        <td><?php echo htmlspecialchars($food['Food_type']); ?></td>
                        <td><?php echo htmlspecialchars($food['detail']); ?></td>
                        <td><?php echo isset($food['amount']) ? number_format($food['amount'],2) : ''; ?></td>
                        <td>
                            <!-- Delete Food Button only -->
                            <form action="../admin/base.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this food?');">
                                <input type="hidden" name="food_id" value="<?php echo $food['Food_ID']; ?>">
                                <input type="hidden" name="restaurant_id" value="<?php echo $res['Restaurant_ID']; ?>">
                                <button type="submit" name="delete_food" style="background:#c0392b;color:white;border:none;padding:5px 10px;border-radius:4px;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; $stmt->close(); ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="main-menus">
            <div class="detail-wrapper">
                <?php
                $restaurants = getAllRestaurants($conn);
                while($row = $restaurants->fetch_assoc()):
                ?>
                    <div class="detail-card" style="cursor: pointer; padding: 15px;" onclick="window.location.href='?edit_id=<?php echo $row['Restaurant_ID']; ?>'">
                        <?php
                        // Use the same image tracking logic as cafe.php for the list
                        $shopImage = "../material/" . $row['Name'] . "/shop.jpg";
                        if (!file_exists($shopImage)) {
                            $shopImage = "../material/images/default_res.jpg";
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($shopImage, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">
                        <div class="detail-name">
                            <h4><?php echo htmlspecialchars($row['Name']); ?></h4>
                            <p class="detail-sub"><?php echo htmlspecialchars($row['Restaurant_type']); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../home/_home_footer.php'; ?>