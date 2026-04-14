<?php
require_once 'base.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn = new mysqli("localhost", "root", "", "cafedash_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$restaurantId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($restaurantId <= 0) {
    header("Location: home.php");
    exit();
}

$cafeImageFolders = [
    "95 Degres Art Cafe" => "95 Degres Art Cafe",
    "Copper Pot Cafe" => "Copper Pot Cafe",
    "Feeka Coffee Roasters" => "Feeka coffee roasters",
    "Good Friends Restaurant & Cafe" => "Good Friends Restaurant & Cafe",
    "JDV Cafe" => "JDV CAFE",
    "Lil' Bird & The Big Bear Cafe" => "Lil' Bird & The Big Bear Cafe",
    "The Foxhole Bakery Cafe" => "The Foxhole Bakery Cafe",
    "The Great Cafe" => "The Great Cafe",
    "Upstairs Cafe" => "Upstairs Cafe"
];

function normalizeWebImagePath($path)
{
    $trimmed = trim((string) $path);
    if ($trimmed === '') {
        return '';
    }

    return str_replace('\\', '/', $trimmed);
}

function resolveExistingWebImagePath($webPath)
{
    $normalized = normalizeWebImagePath($webPath);
    if ($normalized === '') {
        return '';
    }

    $normalized = ltrim($normalized, '/');
    $candidates = [
        $normalized,
        ltrim($normalized, './')
    ];

    if (stripos($normalized, 'Production/') === 0) {
        $candidates[] = substr($normalized, strlen('Production/'));
    }

    $pathInfo = pathinfo($normalized);
    $baseWithoutExt = ($pathInfo['dirname'] ?? '') !== '' && ($pathInfo['dirname'] ?? '') !== '.'
        ? $pathInfo['dirname'] . '/' . ($pathInfo['filename'] ?? '')
        : ($pathInfo['filename'] ?? '');
    $ext = strtolower($pathInfo['extension'] ?? '');
    $supportedExts = ['jpg', 'jpeg', 'png', 'webp'];

    if ($baseWithoutExt !== '') {
        if ($ext === '') {
            foreach ($supportedExts as $imageExt) {
                $candidates[] = $baseWithoutExt . '.' . $imageExt;
            }
        } elseif (in_array($ext, $supportedExts, true)) {
            foreach ($supportedExts as $imageExt) {
                if ($imageExt !== $ext) {
                    $candidates[] = $baseWithoutExt . '.' . $imageExt;
                }
            }
        }
    }

    $seen = [];
    foreach ($candidates as $candidate) {
        $candidate = normalizeWebImagePath($candidate);
        if ($candidate === '' || isset($seen[$candidate])) {
            continue;
        }
        $seen[$candidate] = true;

        // Convert URL-style path to local filesystem path for existence check.
        $localPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($candidate));
        if (file_exists($localPath)) {
            return $candidate;
        }
    }

    return '';
}

function normalizeFoodType($foodType)
{
    $trimmed = trim((string) $foodType);
    return $trimmed !== '' ? $trimmed : 'Others';
}

function isDrinkCategory($category)
{
    $normalized = strtolower(trim((string) $category));
    return in_array($normalized, ['beverage', 'drink', 'drinks'], true);
}

$restaurantStmt = $conn->prepare("SELECT Restaurant_ID, Name, Address, Rating FROM Restaurant WHERE Restaurant_ID = ? LIMIT 1");
$restaurantStmt->bind_param("i", $restaurantId);
$restaurantStmt->execute();
$restaurantResult = $restaurantStmt->get_result();
$restaurant = $restaurantResult->fetch_assoc();
$restaurantStmt->close();

if (!$restaurant) {
    header("Location: home.php");
    exit();
}

$restaurantName = $restaurant['Name'];
$folderName = isset($cafeImageFolders[$restaurantName]) ? $cafeImageFolders[$restaurantName] : "images";
$folderFs = __DIR__ . DIRECTORY_SEPARATOR . "material" . DIRECTORY_SEPARATOR . $folderName;
$folderWeb = "material/" . $folderName;

$shopImage = "material/images/cafe.jpg";
$shopImageFs = $folderFs . DIRECTORY_SEPARATOR . "shop.jpg";
if (file_exists($shopImageFs)) {
    $shopImage = $folderWeb . "/shop.jpg";
}

$menuItems = [];
$hasImagePathColumn = false;
$imageColResult = $conn->query("SHOW COLUMNS FROM Food LIKE 'Image_path'");
if ($imageColResult && $imageColResult->num_rows > 0) {
    $hasImagePathColumn = true;
}

$foodSql = $hasImagePathColumn
    ? "SELECT Name, Food_type, detail, Image_path FROM Food WHERE Restaurant_ID = ? ORDER BY COALESCE(NULLIF(TRIM(Food_type), ''), 'Others') ASC, Name ASC"
    : "SELECT Name, Food_type, detail FROM Food WHERE Restaurant_ID = ? ORDER BY COALESCE(NULLIF(TRIM(Food_type), ''), 'Others') ASC, Name ASC";

$foodStmt = $conn->prepare($foodSql);
$foodStmt->bind_param("i", $restaurantId);
$foodStmt->execute();
$foodResult = $foodStmt->get_result();

while ($foodRow = $foodResult->fetch_assoc()) {
    $foodName = trim($foodRow['Name']);
    $category = normalizeFoodType($foodRow['Food_type']);
    $dbImagePath = $hasImagePathColumn ? normalizeWebImagePath($foodRow['Image_path']) : '';
    $resolvedImage = resolveExistingWebImagePath($dbImagePath);
    if ($resolvedImage === '') {
        $resolvedImage = "material/images/card.jpg";
    }

    $menuItems[] = [
        'name' => $foodName,
        'type' => $category,
        'detail' => trim((string) $foodRow['detail']) !== '' ? $foodRow['detail'] : 'Chef recommendation',
        'image' => $resolvedImage,
        'isDrink' => isDrinkCategory($category)
    ];
}
$foodStmt->close();
$categoryOrder = [];
$groupedMenuItems = [];

foreach ($menuItems as $item) {
    $cat = $item['type'];
    if (!isset($groupedMenuItems[$cat])) {
        $groupedMenuItems[$cat] = [];
        $categoryOrder[] = $cat;
    }
    $groupedMenuItems[$cat][] = $item;
}
?>
<?php
$pageTitle = $restaurantName . ' - Cafe Dash';
$extraStylesheets = ['Css/cafe.css'];
include 'home/_home_sidebar.php';
?>
<body>
    <div class="sidebar">
        <h1 class="logo">Cafe Dash</h1>
        <div class="sidebar-menus">
            <a href="home.php"><ion-icon name="storefront-outline"></ion-icon>Home</a>
            <a href="#"><ion-icon name="receipt-outline"></ion-icon>Bills</a>
            <a href="#"><ion-icon name="wallet-outline"></ion-icon>Wallet</a>
            <a href="#"><ion-icon name="notifications-outline"></ion-icon>Notification</a>
            <a href="#"><ion-icon name="chatbubbles-outline"></ion-icon>Contact Us</a>
            <a href="#"><ion-icon name="settings-outline"></ion-icon>Setting</a>
        </div>
        <div class="sidebar-logout">
            <a href="login.php"><ion-icon name="log-out-outline"></ion-icon>Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="main-navbar">
            <a href="home.php" class="cart"><ion-icon name="arrow-back-outline"></ion-icon></a>
            <div class="search">
                <input type="text" value="<?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <button class="search-btn" type="button">Menu</button>
            </div>
            <div class="profile">
                <a class="user" href="#"><ion-icon name="person-outline"></ion-icon></a>
            </div>
        </div>

        <div class="main-highlight">
            <div class="highlight-card" style="width:100%;">
                <img class="highlight-img" src="<?php echo htmlspecialchars($shopImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?>" style="width:120px;height:120px;">
                <div class="highlight-desc">
                    <h4><?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars($restaurant['Address'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Rating <?php echo htmlspecialchars((string) $restaurant['Rating'], ENT_QUOTES, 'UTF-8'); ?></p>
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

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h4>Contact Us</h4>
                    <p>Edumetro, Persiaran Subang Permai, Usj 1, 47500 Subang Jaya, Selangor</p>
                    <p>Email: CafeDash@gmail.com</p>
                    <p>Phone: 03-8600 1777</p>
                </div>
                <div class="col-md-6">
                    <h4>Follow Us</h4>
                    <p>Stay connected with us on social media:</p>
                    <ul class="social-icons">
                        <li><a href="#"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i></a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

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
</body>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="js/cafe.js"></script>
</html>
