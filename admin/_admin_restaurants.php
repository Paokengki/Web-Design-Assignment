<?php
require '../admin/base.php';
$pageTitle = "Manage Restaurants - Admin";
include '../admin/_admin_sidebar.php'; 

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

<div class="main">
    <div class="main-navbar">
        <h1>Restaurant Management</h1>
        <a href="../admin/_admin_setting.php" style="text-decoration: none; color: inherit;">
            <div class="profile" style="cursor: pointer;">
                <span class="user"><ion-icon name="people-circle-outline"></ion-icon></span>
                <strong>Admin</strong>
            </div>
        </a>
    </div>

    <?php if (!$res): ?>
    <div class="main-highlight">
        <h3>Add New Restaurant</h3>
        <form action="../admin/base.php" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
            <input type="text" name="res_name" placeholder="Restaurant Name" required style="padding: 8px; border-radius: 5px; border: none;">
            <select name="res_type" required style="padding: 8px; border-radius: 5px; border: none;">
                <option value="">Select Cuisine Type</option>
                <option value="Food">Food</option>
                <option value="Dessert">Dessert</option>
                <option value="Cafe">Cafe</option>
            </select>
            <input type="email" name="res_email" placeholder="Email" required style="padding: 8px; border-radius: 5px; border: none;">
            <input type="text" name="res_phone" placeholder="Phone Number" required style="padding: 8px; border-radius: 5px; border: none;">
            <textarea name="res_address" placeholder="Full Address" required style="grid-column: span 2; padding: 8px; border-radius: 5px; border: none;"></textarea>
            <button type="submit" name="add_restaurant" style="background: var(--darkBrown); color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; grid-column: span 2;">
                Add Restaurant
            </button>
        </form>
    </div>
    <?php endif; ?>

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
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Change Restaurant Image:</label>
                        <input type="file" name="res_image" accept="image/*" id="res_image_input" style="display: none;">
                        <button type="button" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); color: #fff; border: none; padding: 12px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; max-width: 300px; text-align: center; transition: all 0.3s ease; font-size: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.12);" onclick="document.getElementById('res_image_input').click();" onmouseover="this.style.background='linear-gradient(135deg, #ffb300 0%, #ff9800 100%)'; this.style.boxShadow='0 6px 16px rgba(255, 152, 0, 0.3)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='linear-gradient(135deg, #ffc107 0%, #ffb300 100%)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.12)'; this.style.transform='translateY(0)';">
                            🖼️ Change Image
                        </button>
                        <small id="res_file_name" style="display: block; margin-top: 8px; color: #27ae60; word-break: break-all; font-weight: 500;"></small>
                        <script>
                        $(function() {
                            const fileInput = $('#res_image_input');
                            const fileNameDisplay = $('#res_file_name');
                            
                            fileInput.on('change', function() {
                                const fileName = this.files.length > 0 ? this.files[0].name : '';
                                fileNameDisplay.text(fileName ? '✓ ' + fileName : '');
                            });
                        });
                        </script>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="text" name="res_name" value="<?php echo htmlspecialchars($res['Name']); ?>" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <select name="res_type" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                            <option value="Food" <?php echo $res['Restaurant_type'] === 'Food' ? 'selected' : ''; ?>>Food</option>
                            <option value="Dessert" <?php echo $res['Restaurant_type'] === 'Dessert' ? 'selected' : ''; ?>>Dessert</option>
                            <option value="Cafe" <?php echo $res['Restaurant_type'] === 'Cafe' ? 'selected' : ''; ?>>Cafe</option>
                        </select>
                        <input type="email" name="res_email" value="<?php echo htmlspecialchars($res['Email']); ?>" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <input type="text" name="res_phone" value="<?php echo htmlspecialchars($res['Contain_number']); ?>" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <textarea name="res_address" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($res['Address']); ?></textarea>
                        <button type="submit" name="update_restaurant" style="background: var(--primaryColor); color: white; padding: 15px; border: none; border-radius: 8px; font-weight: bold;">Update Restaurant Info</button>
                    </div>
                </form>
                <div class="divider" style="margin: 30px 0;"></div>
                <form method="POST" action="../admin/base.php">
                    <input type="hidden" name="restaurant_id" value="<?php echo $res['Restaurant_ID']; ?>">
                    <button type="submit" name="delete_restaurant" style="background: #c0392b; color: white; border: none; padding: 12px; border-radius: 8px; width: 100%; cursor: pointer;">
                        Remove Restaurant
                    </button>
                </form>
            </div>

            <!-- FOOD MANAGEMENT SECTION -->
            <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-top: 30px;">
                <h3 style="margin-bottom: 20px;">Manage Foods for <?php echo htmlspecialchars($res['Name']); ?></h3>
                <!-- Add Food Form -->
                <form action="../admin/base.php" method="POST" enctype="multipart/form-data" style="margin-bottom: 30px; display: flex; gap: 30px; align-items: flex-start;">
                    <input type="hidden" name="restaurant_id" value="<?php echo $res['Restaurant_ID']; ?>">
                    
                    <!-- Left Column: Form Fields -->
                    <div style="flex: 0 0 300px; display: flex; flex-direction: column; gap: 15px;">
                        <input type="text" name="food_name" placeholder="Food Name" required style="padding: 12px; border-radius: 5px; border: 1px solid #ddd; font-size: 14px;">
                        
                        <input type="number" name="food_amount" id="food_price_input" placeholder="Price" step="0.10" required="" style="padding: 12px; border-radius: 5px; border: 1px solid #ddd; font-size: 14px;">
                        
                        <textarea name="food_desc" placeholder="Food Description" style="padding: 12px; border-radius: 5px; border: 1px solid #ddd; font-size: 14px; min-height: 80px; resize: vertical;"></textarea>
                        
                        <div style="position: relative;">
                            <input type="text" id="food_type_search" placeholder="Search Food Type" style="padding: 12px; border-radius: 5px; border: 1px solid #ddd; font-size: 14px; height: 47px; width: 100%;" autocomplete="off">
                            <select name="food_type" id="food_type_select" required style="padding: 12px; border-radius: 5px; border: 1px solid #ddd; font-size: 14px; height: 47px; display: none;">
                                <option value="">Select Food Type</option>
                                <option value="Rice">Rice</option>
                                <option value="Noodles">Noodles</option>
                                <option value="Pasta">Pasta</option>
                                <option value="Pizza">Pizza</option>
                                <option value="Pie">Pie</option>
                                <option value="Salad">Salad</option>
                                <option value="Soup">Soup</option>
                                <option value="Seafood">Seafood</option>
                                <option value="Chicken">Chicken</option>
                                <option value="Beef">Beef</option>
                                <option value="Breakfast">Breakfast</option>
                                <option value="Bakery">Bakery</option>
                                <option value="Western">Western</option>
                                <option value="Beverage">Beverage</option>
                                <option value="Dessert">Dessert</option>
                                <option value="Snack">Snack</option>
                                <option value="Others">Others</option>
                            </select>
                            <div id="food_type_dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; max-height: 250px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            </div>
                        </div>
                        
                        <script>
                        $(function() {
                            const foodTypes = ['Rice', 'Noodles', 'Pasta', 'Pizza', 'Pie', 'Salad', 'Soup', 'Seafood', 'Chicken', 'Beef', 'Breakfast', 'Bakery', 'Western', 'Beverage', 'Dessert', 'Snack', 'Others'];
                            const searchInput = $('#food_type_search');
                            const selectElement = $('#food_type_select');
                            const dropdown = $('#food_type_dropdown');
                            
                            function renderDropdown(filter = '') {
                                const filtered = foodTypes.filter(type => type.toLowerCase().includes(filter.toLowerCase()));
                                dropdown.empty();
                                
                                if (filtered.length === 0) {
                                    dropdown.html('<div style="padding: 12px; color: #999;">No matching types</div>');
                                } else {
                                    filtered.forEach(type => {
                                        dropdown.append(`<div style="padding: 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f0f0f0';" onmouseout="this.style.backgroundColor='white';" data-value="${type}">${type}</div>`);
                                    });
                                }
                                
                                dropdown.off('click').on('click', 'div[data-value]', function() {
                                    const value = $(this).data('value');
                                    searchInput.val(value);
                                    selectElement.val(value);
                                    dropdown.hide();
                                });
                            }
                            
                            searchInput.on('focus', function() {
                                renderDropdown($(this).val());
                                dropdown.show();
                            });
                            
                            searchInput.on('input', function() {
                                renderDropdown($(this).val());
                                dropdown.show();
                            });
                            
                            $(document).on('click', function(e) {
                                if (!$(e.target).closest('#food_type_search, #food_type_dropdown').length) {
                                    dropdown.hide();
                                }
                            });
                        });
                        </script>
                        
                        <!-- Upload Button -->
                        <div style="position: relative;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Upload Food Image</label>
                            <input type="file" name="food_image" accept="image/*" required id="food_image_input" style="display: none;">
                            <button type="button" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); color: #fff; border: none; padding: 12px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; text-align: center; transition: all 0.3s ease; font-size: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.12);" onclick="document.getElementById('food_image_input').click();" onmouseover="this.style.background='linear-gradient(135deg, #ffb300 0%, #ff9800 100%)'; this.style.boxShadow='0 6px 16px rgba(255, 152, 0, 0.3)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='linear-gradient(135deg, #ffc107 0%, #ffb300 100%)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.12)'; this.style.transform='translateY(0)';">
                                📁 Choose Image
                            </button>
                            <small id="file_name" style="display: block; margin-top: 8px; color: #27ae60; word-break: break-all; font-weight: 500;"></small>
                        </div>
                        
                        <!-- Add Food Button -->
                        <button type="submit" name="add_food" style="background: var(--primaryColor); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; box-shadow: none;" onmouseover="this.style.background='#8B4513'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.2)';" onmouseout="this.style.background='var(--primaryColor)'; this.style.boxShadow='none';">
                            ✚ Add Food
                        </button>
                    </div>
                    
                    <!-- Right Column: Image Preview -->
                    <div id="image_preview" style="flex: 1; display: none; min-width: 0; margin-top: 0; margin-right: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Preview</label>
                        <img id="preview_img" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border-radius: 8px; object-fit: cover; border: 2px solid #333;">
                    </div>
                    
                    <script>
                    $(function() {
                        const cafeName = <?php echo json_encode($res['Name']); ?>;
                        const fileInput = $('#food_image_input');
                        const fileNameDisplay = $('#file_name');
                        const previewDiv = $('#image_preview');
                        const previewImg = $('#preview_img');
                        const foodNameInput = $('input[name="food_name"]');
                        const priceInput = $('#food_price_input');

                        function updatePreview() {
                            const file = fileInput[0].files[0];
                            const foodName = foodNameInput.val().trim();

                            if (!file || !foodName) {
                                previewDiv.hide();
                                return;
                            }

                            const reader = new FileReader();
                            reader.onload = function(event) {
                                previewImg.attr('src', event.target.result);
                                previewDiv.show();
                            };
                            reader.readAsDataURL(file);
                        }
                        
                        fileInput.on('change', function() {
                            const fileName = this.files.length > 0 ? this.files[0].name : '';
                            fileNameDisplay.text(fileName ? '✓ ' + fileName : '');
                            updatePreview();
                        });
                        
                        foodNameInput.on('change input', function() {
                            if (fileInput[0].files.length > 0) {
                                updatePreview();
                            } else {
                                previewDiv.hide();
                            }
                        });
                        
                        // Format price to always show 2 decimal places
                        priceInput.on('blur', function() {
                            if ($(this).val() !== '') {
                                const price = parseFloat($(this).val());
                                if (!isNaN(price)) {
                                    $(this).val(price.toFixed(2));
                                }
                            }
                        });
                    });
                    </script>
                </form>
                
                <!-- Food List -->
                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #f5f5f5 0%, #eeeeee 100%); border-bottom: 2px solid #ddd;">
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #333;">Name</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #333;">Type</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #333;">Detail</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #333;">Price</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #333;">Actions</th>
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
                    <tr style="border-bottom: 1px solid #eee; transition: background-color 0.3s;" onmouseover="this.style.backgroundColor='#f9f9f9';" onmouseout="this.style.backgroundColor='white';">
                        <td style="padding: 14px 16px; color: #333;"><?php echo htmlspecialchars($food['Name']); ?></td>
                        <td style="padding: 14px 16px; color: #666;">
                            <span style="background: #f0f0f0; padding: 4px 12px; border-radius: 12px; font-size: 12px;"><?php echo htmlspecialchars($food['Food_type']); ?></span>
                        </td>
                        <td style="padding: 14px 16px; color: #666; max-width: 300px; word-wrap: break-word;"><?php echo htmlspecialchars($food['detail']); ?></td>
                        <td style="padding: 14px 16px; text-align: center; color: var(--primaryColor); font-weight: 600;">$<?php echo isset($food['amount']) ? number_format($food['amount'],2) : 'N/A'; ?></td>
                        <td style="padding: 14px 16px; text-align: center;">
                            <!-- Delete Food Button -->
                            <form action="../admin/base.php" method="POST" style="display:inline;">
                                <input type="hidden" name="food_id" value="<?php echo $food['Food_ID']; ?>">
                                <input type="hidden" name="restaurant_id" value="<?php echo $res['Restaurant_ID']; ?>">
                                <button type="submit" name="delete_food" style="background: #c0392b; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s;" onmouseover="this.style.background='#a93226'; this.style.boxShadow='0 2px 6px rgba(0,0,0,0.2)';" onmouseout="this.style.background='#c0392b'; this.style.boxShadow='none';">Delete</button>
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