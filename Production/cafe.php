<?php
require_once 'base.php';

date_default_timezone_set('Asia/Kuala_Lumpur');
$conn = new mysqli("localhost", "root", "", "cafedash_db");

$pageTitle = 'Cafe Menu - Cafe Dash';
$extraStylesheets = ['Css/cafe.css'];
$extraScripts = ['js/cafe.js'];
require_once 'home/_home_sidebar.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$restaurantId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($restaurantId <= 0) {
    header("Location: home.php");
    exit();
}
?>

<?php
$shopAddress = '';
$shopType = '';
$shopRating = '';
$shopContact = '';
$shopEmail = '';
$shopImage = '';

$sql = "SELECT Restaurant_ID AS Id, Name, Address, Restaurant_type AS Type, Rating, Contain_number AS Contact, Email FROM Restaurant WHERE Restaurant_ID = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Failed to prepare restaurant query: " . $conn->error);
}

$stmt->bind_param("i", $restaurantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result !== false && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $shopName = $row['Name'];
    $shopAddress = $row['Address'];
    $shopType = $row['Type'];
    $shopRating = $row['Rating'];
    $shopContact = $row['Contact'];
    $shopEmail = $row['Email'];
    $shopImage = "material/" . $row['Name'] . "/shop.jpg";
} else {
    header("Location: home.php");
    exit();
}

$stmt->close();
?>

<?php
$menuItems = [];
$groupedMenuItems = [];
$categoryOrder = [];

$sql = "SELECT Food_ID AS Id, Name, detail AS Detail, Food_type AS Type, amount AS Amount FROM Food WHERE Restaurant_ID = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Failed to prepare menu query: " . $conn->error);
}

$stmt->bind_param("i", $restaurantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result !== false && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $type = trim((string) ($row['Type'] ?? 'Others'));
        if ($type === '') {
            $type = 'Others';
        }
        
        // Determine if the item is a drink based on keywords in the type
        $typeLower = strtolower($type);
        $isDrink = strpos($typeLower, 'drink') !== false
            || strpos($typeLower, 'beverage') !== false
            || strpos($typeLower, 'coffee') !== false
            || strpos($typeLower, 'tea') !== false;

        $item = [
            'id' => $row['Id'],
            'name' => $row['Name'],
            'detail' => $row['Detail'],
            'type' => $type,
            'amount' => $row['Amount'],
            'isDrink' => $isDrink,
            'image' => "material/" . $shopName . "/" . $row['Name'] . ".jpg"
        ];

        $menuItems[] = $item;

        if (!isset($groupedMenuItems[$type])) {
            $groupedMenuItems[$type] = [];
            $categoryOrder[] = $type;
        }
        $groupedMenuItems[$type][] = $item;
    }
}
$stmt->close();

?>


    <div class="main">
        <div class="main-navbar">
            <a href="home.php" class="cart"><ion-icon name="arrow-back-outline"></ion-icon></a>
            
            <div class="profile">
                <a class="user" href="profile.php"><ion-icon name="person-outline"></ion-icon></a>
            </div>
        </div>
        
        <div class="main-highlight">
            <div class="highlight-card" style="width:100%;">
                <img class="highlight-img" 
                src="<?php echo htmlspecialchars($shopImage, ENT_QUOTES, 'UTF-8'); ?>" 
                alt="<?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>" 
                style="width:120px;height:120px;">

                <div class="highlight-desc">
                    <h4><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars($shopAddress, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Rating <?php echo htmlspecialchars((string) $shopRating, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Contact: <?php echo htmlspecialchars((string) $shopContact, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Email: <?php echo htmlspecialchars((string) $shopEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>

        <div class="main-menus">
            <div class="main-detail">
                <h2 class="main-title">Food / Beverage Menu</h2>
                <?php if (count($menuItems) > 0): ?>
                    <?php foreach ($categoryOrder as $category): ?>
                        <?php if (isset($groupedMenuItems[$category])): ?>
                            <h3 class="main-title" style="margin: 12px 0; font-size: 18px;"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="detail-wrapper">
                                <?php foreach ($groupedMenuItems[$category] as $item): ?>
                                    <button type="button" class="detail-card menu-item-trigger"
                                        data-item-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-item-type="<?php echo htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-is-drink="<?php echo $item['isDrink'] ? '1' : '0'; ?>">
                                        <img class="detail-img" src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="detail-desc">
                                            <div class="detail-name">
                                                <h4><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <p class="detail-sub"><?php echo htmlspecialchars($item['detail'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="detail-price">RM <?php echo htmlspecialchars(number_format((float) $item['amount'], 2), ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="Rating"><?php echo htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No menu items found for this cafe.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="menuModalOverlay" class="menu-modal-overlay" aria-hidden="true">
        <form id="menuModalForm" class="menu-modal">
            <h3 id="modalItemTitle">Item</h3>

            <div class="modal-row">
                <label class="modal-label">Quantity</label>
                <div class="qty-box">
                    <button type="button" id="qtyMinus" class="qty-btn">-</button>
                    <input id="itemQty" class="qty-input" type="number" min="1" value="1">
                    <button type="button" id="qtyPlus" class="qty-btn">+</button>
                </div>
            </div>

            <div id="drinkOptions" class="hidden">
                <div class="modal-row">
                    <label class="modal-label">Sugar Level</label>
                    <div class="option-group">
                        <label class="option-pill"><input type="radio" name="sugar" value="Less">Less</label>
                        <label class="option-pill"><input type="radio" name="sugar" value="Normal" checked>Normal</label>
                        <label class="option-pill"><input type="radio" name="sugar" value="More">More</label>
                    </div>
                </div>

                <div class="modal-row">
                    <label class="modal-label">Ice Level</label>
                    <div class="option-group">
                        <label class="option-pill"><input type="radio" name="ice" value="Less">Less</label>
                        <label class="option-pill"><input type="radio" name="ice" value="Normal" checked>Normal</label>
                        <label class="option-pill"><input type="radio" name="ice" value="More">More</label>
                    </div>
                </div>
            </div>

            <div class="modal-row">
                <label class="modal-label" for="itemRemark">Remark</label>
                <textarea id="itemRemark" class="remark-input" placeholder="Example: No onion, spicy level 1"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" id="closeModalBtn" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add</button>
            </div>
        </form>
    </div>

<?php require_once 'home/_home_footer.php'; ?>