# CafeDash Project — Complete Explanation

> **CafeDash** is a multi-cafe food ordering and delivery system built with PHP, MySQL/MariaDB, JavaScript, and Stripe payments. This document explains everything that has been built, how it works, and what each file does.
>
> This document uses simple English with Chinese translations for technical terms. It is written for a student who wants to understand the project.

---

## Table of Contents

1. [What Has Been Developed?](#1-what-has-been-developed)
2. [How Does the System Work?](#2-how-does-the-system-work)
3. [What Does Each File Do?](#3-what-does-each-file-do)
4. [Important Code Explained](#4-important-code-explained)
5. [Database Explanation](#5-database-explanation)
6. [Technologies Used](#6-technologies-used)
7. [Incomplete or Not Implemented](#7-incomplete-or-not-implemented)

---

## 1. What Has Been Developed?

### Overview

CafeDash is an online food ordering platform for 9 cafes in Subang Jaya, Malaysia. Customers can browse cafes, view menus, customize drinks, add items to a cart, pay with Stripe, and track their delivery in real-time on a live map.

The system has **four types of users** (用户类型):

| Role (角色) | Who they are | What they can do |
|---|---|---|
| **Customer** (顾客) | Regular users who order food | Browse cafes, customize orders, pay, track delivery, view order history, earn loyalty points |
| **Kitchen Staff** (厨房员工) | Cafe kitchen workers | View orders for their assigned cafe, accept orders, mark as preparing, mark as ready for pickup |
| **Courier** (外卖员) | Delivery riders | View assigned deliveries, update GPS location, mark orders as out for delivery, mark as delivered |
| **Manager / Super Admin** (经理/管理员) | System administrators | View dashboard, manage promotions, restaurants, members, staff, feedback, assign couriers |

### Features Implemented

#### Customer Features (顾客功能)

1. **Account Management** (账户管理)
   - Register with username, email, phone, and password
   - Login with username or email
   - Password security: minimum 8 characters, at least 1 letter and 1 number
   - Password hashing with bcrypt (密码加密)
   - Forgot password / reset via email
   - Profile dashboard with avatar upload
   - Manage multiple delivery addresses
   - Change password and contact info
   - Deactivate account (soft delete — data is kept)

2. **Cafe Browsing** (浏览咖啡馆)
   - Landing page with scroll-driven coffee animation (300 webp frames)
   - View all 9 cafes sorted by rating
   - Search cafes by name, type, or dish name
   - Filter by cafe type (Cafe, Food, Dessert)
   - View cafe details: rating, address, phone number

3. **Menu and Ordering** (菜单和点餐)
   - View menu items grouped by food type (Beverage, Pasta, Dessert, etc.)
   - Click a dish to see details and customize
   - Drink customization: sugar level (Less/Normal/More), ice level (Less/Normal/More)
   - Add notes to kitchen (e.g., "No onions, extra hot")
   - Quantity selector (1-20 items)
   - Session-based cart (cart is stored in PHP session, not database)

4. **Cart System** (购物车)
   - Slide-out cart drawer on every page
   - Add, remove, change quantity
   - Prices always re-read from database (never trust browser prices)
   - Same dish with same options merges quantity
   - Apply promo codes (voucher codes)
   - View available vouchers at checkout
   - SST 6% tax calculated automatically
   - Clear cart

5. **Payment with Stripe** (Stripe支付)
   - Stripe Checkout (hosted payment page — card details never touch CafeDash)
   - Test mode with test card 4242 4242 4242 4242
   - Currency: Malaysian Ringgit (MYR)
   - Minimum order: RM 2.00
   - Promo code discounts applied as Stripe coupons
   - Payment verification: re-reads session from Stripe (never trusts URL parameters)
   - Idempotent order recording (safe to run twice for same payment)
   - Order receipt page with items, totals, and loyalty points earned

6. **Loyalty Program** (积分系统)
   - 1 point per RM 1 spent (rounded down)
   - Four tiers: Bronze (0+), Silver (200+), Gold (500+), Platinum (1000+)
   - Points calculated from successful payment history
   - Tier progress bar in dashboard
   - Tier-specific perks displayed on landing page

7. **Order History** (订单历史)
   - View all past orders
   - Expandable receipts with item details
   - Order status badges (Paid, Pending, Failed, Refunded)
   - Track order button for successful orders
   - Statistics: total orders, total spent, total saved with promo codes

8. **Real-Time Order Tracking** (实时订单跟踪)
   - Live map using Leaflet.js + OpenStreetMap
   - Restaurant marker, courier marker, destination marker
   - Courier GPS position updates every 5 seconds (AJAX polling)
   - Status timeline showing fulfillment progress
   - ETA countdown
   - Courier contact info (name, phone, plate number)
   - Tracking PIN for easy reference

9. **Feedback** (反馈)
   - Submit feedback via contact form
   - Admin can read and mark feedback as read/unread

#### Admin Panel Features (管理面板功能)

1. **Overview Dashboard** (概览面板)
   - Revenue, orders, members, active promos, unread feedback KPIs
   - Daily revenue chart (7/14/30 days)
   - Cafe ranking by order count
   - Best sellers list

2. **Promotions Manager** (促销管理)
   - Create, edit, pause, delete promo codes
   - Set discount percentage, min spend, max redemptions, date range
   - View redemption count
   - Board view: Scheduled, Active, Expired

3. **Restaurants & Menu Manager** (餐厅和菜单管理)
   - Add, edit, delete restaurants
   - Add, edit, delete food items
   - Upload restaurant and food images
   - Set restaurant coordinates (for map)

4. **Members Manager** (会员管理)
   - View all members
   - Suspend/unsuspend accounts
   - View member order history and spending

5. **Staff Manager** (员工管理)
   - Add staff with roles (Manager, Kitchen Staff, Courier)
   - Assign Kitchen Staff to a specific restaurant
   - Suspend/activate staff
   - Reset staff passwords
   - Super Admin only access

6. **Feedback Inbox** (反馈收件箱)
   - View all feedback
   - Mark as read/unread
   - Reply to feedback

7. **Live Orders** (实时订单)
   - Manager delivery dashboard
   - View orders ready for courier assignment
   - View active deliveries
   - Assign couriers to orders
   - List available couriers

#### Tracking System Features (跟踪系统功能)

1. **Multi-Restaurant Order Tracking** (多餐厅订单跟踪)
   - Parent tracking row for overall order
   - Child tracking rows per restaurant
   - Parent status derived from child statuses
   - Kitchen staff only see their restaurant's items

2. **Kitchen Dashboard** (厨房面板)
   - Auto-refreshing order queue (every 4 seconds)
   - Orders filtered to assigned restaurant
   - Accept → Preparing → Ready for Pickup workflow
   - Customer name, phone, delivery address shown

3. **Courier Dashboard** (外卖员面板)
   - View assigned deliveries
   - Update GPS location via browser Geolocation API (every 4 seconds)
   - Mark as Out for Delivery → Delivered
   - Delivery address and restaurant info shown

4. **Manager Delivery Dashboard** (经理配送面板)
   - View orders ready for pickup
   - View active deliveries
   - Assign couriers
   - View available couriers (not currently on delivery)

---

## 2. How Does the System Work?

### System Architecture (系统架构)

```
Browser (Chrome)
    │
    ├── HTML/CSS ← assets/css/, Css/
    ├── JavaScript (AJAX) ← assets/js/, js/
    │
    ▼
Apache (XAMPP) → PHP 8.2
    │
    ├── app/bootstrap.php (core: DB, session, CSRF, auth)
    ├── app/services/ (cart, payments, promo, mailer, images)
    ├── app/layout/master.php (page template)
    ├── api/ (AJAX endpoints — JSON responses)
    ├── customer/ (customer-facing pages)
    ├── admin/ (admin panel pages)
    ├── tracking/ (order tracking system)
    ├── auth/ (login, register, logout)
    │
    ▼
MariaDB 10.4 (cafedash_db)
    │
    ├── Original tables: User, Restaurant, Food, Payment, Payment_Item, etc.
    ├── v2 tables: User_Address, Staff, Promotion, Promotion_Redemption
    └── v4 tables: Order_Tracking, Order_Tracking_Item, Order_Status_Log, Delivery_Location
```

### Complete Order and Payment Workflow (完整订单和支付流程)

Here is the step-by-step flow from browsing to delivery:

#### Step 1: Browse and Select (浏览和选择)

1. Customer visits `index.php` → sees landing page with cafe showcase
2. Clicks a cafe → goes to `customer/cafe.php?id=X`
3. Views menu items grouped by type (Beverage, Pasta, Dessert...)
4. Clicks a dish → modal opens with customization options
5. For drinks: selects sugar level and ice level
6. Optional: adds a note to the kitchen
7. Selects quantity → clicks "Add"
8. JavaScript sends AJAX POST to `api/shop/cart.php` (action=add)
9. PHP `cart_add()` function:
   - Reads the food price from database (never trusts browser price)
   - Stores item in `$_SESSION['cart']` with food ID, quantity, options
   - If same dish with same options already in cart, merges quantity

#### Step 2: Cart Review (购物车查看)

1. Customer opens cart drawer (slide-out panel)
2. JavaScript sends AJAX POST to `api/shop/cart.php` (action=get)
3. PHP `cart_summary()` function:
   - Re-reads all prices from `Food` table
   - Removes items no longer on the menu
   - Calculates subtotal, discount (if promo applied), SST 6%, total
   - Returns JSON with items, totals, promo info
4. Customer can change quantity, remove items, or apply promo code

#### Step 3: Checkout (结账)

1. Customer clicks "Checkout" → goes to `customer/checkout.php`
2. Page checks: must be logged in, cart must not be empty
3. Shows delivery address (must have at least one)
4. Shows payment info — if Stripe is configured, shows "Pay" button
5. Shows available vouchers (AJAX loads from `api/shop/cart.php` action=vouchers)
6. Shows order summary with items, subtotal, discount, SST, total
7. Shows estimated loyalty points to earn

#### Step 4: Stripe Payment (Stripe支付)

1. Customer clicks "Pay RM X.XX"
2. JavaScript sends AJAX POST to `api/shop/checkout.php` (action=session)
3. PHP `payment_create_session()` function:
   - Verifies cart is not empty and total ≥ RM 2.00
   - Verifies user has a delivery address
   - Creates Stripe Checkout Session with:
     - Line items (each food item + SST as separate line)
     - Currency: MYR (sen = RM × 100, because Stripe uses smallest currency unit)
     - Customer email, user ID in metadata
     - Success URL: `customer/checkout_return.php?session_id={CHECKOUT_SESSION_ID}`
     - Cancel URL: `customer/checkout.php?cancelled=1`
   - If promo code applied: creates a Stripe Coupon for the discount amount
   - Saves session ID + cart summary in `$_SESSION['checkout_session']`
   - Returns Stripe's payment page URL
4. Browser redirects to Stripe's hosted payment page
5. Customer enters card details on Stripe's page (CafeDash never sees card data)
6. Test card: 4242 4242 4242 4242, any future date, any CVC

#### Step 5: Payment Confirmation (支付确认)

1. After payment, Stripe redirects to `customer/checkout_return.php?session_id=cs_...`
2. PHP `payment_finalize_session()` function:
   - Validates session ID format (must start with `cs_test_` or `cs_live_`)
   - Retrieves the Checkout Session from Stripe API
   - **Security check**: verifies `metadata.user_id` matches logged-in user
   - **Security check**: verifies `payment_status === 'paid'`
   - **Idempotency check**: if `Provider_payment_id` already exists in Payment table, returns existing order (no duplicate)
   - Calls `record_paid_order()` which:
     - Begins a database transaction (数据库事务)
     - INSERTs into `Payment` table with status 'SUCCEEDED'
     - INSERTs each item into `Payment_Item` table
     - If promo used: INSERTs into `Promotion_Redemption` + increments redemption count
     - Commits transaction (all or nothing — if any step fails, everything rolls back)
   - Clears the cart
   - Redirects to `customer/order_success.php?id=PAYMENT_ID`

#### Step 6: Order Confirmation (订单确认)

1. `customer/order_success.php` shows receipt
2. Calls `track_ensure_for_payment($paymentId)` to create tracking records:
   - Reads the Payment row — only creates tracking if `Payment_status = 'SUCCEEDED'`
   - Resolves restaurants: `Payment_Item.Food_ID → Food.Restaurant_ID → Restaurant`
   - Creates one **parent** `Order_Tracking` row (status: Pending)
   - Creates one **child** `Order_Tracking_Item` row per distinct restaurant
   - Creates initial `Order_Status_Log` entry
   - All in one transaction, with `INSERT IGNORE` for idempotency
3. Shows order details, loyalty points earned, and "Track your order" button

#### Step 7: Kitchen Processing (厨房处理)

1. Kitchen staff logs in at `tracking/kitchen/login.php`
2. Goes to `tracking/kitchen/orders.php` — sees order queue for their assigned cafe
3. Page auto-refreshes every 4 seconds via AJAX
4. Kitchen clicks "Accept" → AJAX to `tracking/api/update_status.php`:
   - Validates: staff is Kitchen Staff, has Assigned_Restaurant_ID
   - Finds the child row matching their restaurant
   - Validates child transition: Pending → Accepted (allowed)
   - Updates child `Order_Tracking_Item.Item_status = 'Accepted'`
   - Derives parent status from all children:
     - If ALL children Accepted → parent becomes 'Accepted'
     - If ANY child still Pending → parent stays 'Pending'
   - Logs both child-level and parent-level status changes
5. Kitchen clicks "Preparing" → same flow, child: Accepted → Preparing
6. Kitchen clicks "Ready for Pickup" → same flow, child: Preparing → Ready for Pickup
   - If ALL children are Ready for Pickup → parent becomes 'Ready for Pickup'

#### Step 8: Courier Assignment (外卖员分配)

1. Manager views `tracking/manager/deliveries.php`
2. Sees orders with status 'Ready for Pickup'
3. Manager selects a courier and clicks "Assign"
4. AJAX to `tracking/api/update_status.php`:
   - Validates: staff is Manager/Super Admin
   - Checks `track_can_advance_parent()`: ALL children must be Ready for Pickup
   - Validates parent transition: Ready for Pickup → Assigned (allowed)
   - Verifies courier exists and has 'Courier' role
   - Updates parent: `Order_status = 'Assigned'`, sets `Courier_ID`, `Assigned_By`
   - Logs the status change

#### Step 9: Delivery (配送)

1. Courier logs in at `tracking/courier/login.php`
2. Goes to `tracking/courier/deliveries.php` — sees assigned deliveries
3. Courier clicks "Start Delivery" → AJAX to `tracking/api/update_status.php`:
   - Validates: staff is Courier, is assigned to this delivery
   - Validates parent transition: Assigned → Out for Delivery (allowed)
   - Updates parent: `Order_status = 'Out for Delivery'`
4. Courier's browser starts sending GPS every 4 seconds:
   - JavaScript uses `navigator.geolocation.getCurrentPosition()`
   - AJAX to `tracking/api/update_location.php`:
     - Validates: staff is Courier, is assigned, order is active (Assigned/Out for Delivery)
     - INSERTs into `Delivery_Location` (history row for path trail)
     - Updates `Order_Tracking.Courier_Latitude/Longitude` (latest position)
5. Courier clicks "Delivered" → AJAX to `tracking/api/update_status.php`:
   - Validates parent transition: Out for Delivery → Delivered (allowed)
   - Updates parent: `Order_status = 'Delivered'`
   - Marks ALL child rows as 'Completed'

#### Step 10: Customer Tracking (顾客跟踪)

1. Customer views `tracking/customer/track.php?id=PAYMENT_ID`
2. Page verifies: order belongs to this customer (User_ID match)
3. Shows map (Leaflet.js + OpenStreetMap) with:
   - Restaurant marker (if coordinates set)
   - Destination marker (if address has coordinates)
   - Courier marker (updates every 5 seconds via AJAX)
4. JavaScript polls `tracking/api/order_status.php` every 5 seconds:
   - Returns: order status, timeline, courier position, items, ETA
5. Shows status timeline: Pending → Accepted → Preparing → Ready → Assigned → Out for Delivery → Delivered
6. Shows courier contact info: name, phone, plate number

### How Each Role Uses the System (各角色使用方式)

#### Customer Workflow (顾客流程)
```
Landing page → Register/Login → Browse cafes → Select cafe →
View menu → Customize dish → Add to cart → Checkout →
Pay with Stripe → Order confirmed → Track order → Earn loyalty points
```

#### Kitchen Staff Workflow (厨房员工流程)
```
Kitchen login → View order queue → Click "Accept" →
Click "Preparing" → Click "Ready for Pickup" → Order leaves kitchen
```

#### Courier Workflow (外卖员流程)
```
Courier login → View assigned deliveries → Click "Start Delivery" →
Browser sends GPS every 4s → Click "Delivered" → Delivery complete
```

#### Manager Workflow (经理流程)
```
Staff login → Admin overview → View "Live Orders" →
See orders "Ready for Pickup" → Assign courier → Monitor active deliveries
```

---

## 3. What Does Each File Do?

### Folder Structure (文件夹结构)

```
D:\Assignment\Mini Project\
│
├── app/                          Core application code
│   ├── bootstrap.php             Core: DB connection, session, CSRF, auth, helpers
│   ├── loyalty.php               Loyalty points calculation
│   ├── layout/                   Page layout system
│   │   ├── master.php            Master template (header, footer, nav, cart drawer)
│   │   ├── auth_visual.php       Visual panel for auth pages
│   │   └── icons.php             SVG icon definitions
│   └── services/                 Business logic services
│       ├── cart.php              Session cart: add, remove, pricing, SST
│       ├── payments.php          Stripe Checkout: create session, finalize, record order
│       ├── promo.php             Promo code validation and listing
│       ├── mailer.php            Email sending via PHPMailer
│       └── images.php            Image paths, uploads, resizing
│
├── assets/                       Modern CSS and JavaScript
│   ├── css/
│   │   ├── core/                 Base styles: tokens, layout, base
│   │   ├── components/            Reusable UI: buttons, forms, cart, surfaces
│   │   └── pages/                Page-specific: auth, dashboard, shop, admin, landing
│   └── js/
│       ├── core/                 api.js (AJAX helper), cart.js, ui.js, motion.js
│       └── pages/                Page scripts: cafes, menu, checkout, dashboard, etc.
│
├── auth/                         Authentication pages
│   ├── login.php                 Customer login (with rate limiting)
│   ├── register.php              Customer registration
│   ├── staff_login.php           Staff login (Manager/Super Admin)
│   ├── logout.php                Session destruction
│   ├── forgot.php                Password reset request
│   └── reset.php                 Password reset form
│
├── customer/                     Customer-facing pages
│   ├── home.php                  Cafe browser (list all 9 cafes, search, filter)
│   ├── cafe.php                  Single cafe menu page
│   ├── checkout.php              Checkout page (address, payment, vouchers)
│   ├── checkout_return.php       Stripe return URL (verifies payment)
│   ├── order_success.php         Order confirmation receipt
│   ├── orders.php                Order history with expandable receipts
│   ├── dashboard.php             Profile, loyalty, addresses, recent orders
│   ├── feedback.php              Feedback form
│   └── contact.php               Contact page
│
├── admin/                        Admin panel pages
│   ├── overview.php              Dashboard with KPIs and charts
│   ├── promos.php                Promotion management
│   ├── restaurants.php           Restaurant and menu management
│   ├── members.php               Member management
│   ├── staff.php                 Staff management (Super Admin only)
│   ├── feedback.php              Feedback inbox
│   └── _admin_*.php              Partial views loaded via AJAX
│
├── api/                          AJAX API endpoints (return JSON)
│   ├── admin/                    Admin API: account, feedback, members, overview, etc.
│   ├── customer/                 Customer API: account, addresses
│   ├── profile/                  Profile API: change_password, save_profile, upload_avatar
│   ├── search/                   Search API: search_cafe
│   └── shop/                     Shop API: cart, checkout, feedback, search
│
├── tracking/                     Order tracking system
│   ├── config.php                Tracking constants, status flow, derivation logic
│   ├── bootstrap.php             Creates tracking records for confirmed payments
│   ├── api/                      Tracking AJAX endpoints
│   │   ├── order_status.php      GET tracking data for customer
│   │   ├── update_status.php     Update order status (kitchen/manager/courier)
│   │   ├── update_location.php   Update courier GPS
│   │   ├── kitchen_orders.php    Kitchen order queue
│   │   ├── courier_deliveries.php Courier delivery list
│   │   └── manager_deliveries.php Manager delivery dashboard
│   ├── customer/
│   │   └── track.php             Customer tracking page (live map + timeline)
│   ├── kitchen/
│   │   ├── login.php             Kitchen staff login
│   │   └── orders.php            Kitchen order queue dashboard
│   ├── courier/
│   │   ├── login.php             Courier login
│   │   └── deliveries.php        Courier delivery dashboard
│   ├── manager/
│   │   └── deliveries.php        Manager delivery management
│   └── assets/
│       ├── css/tracking.css      Tracking page styles
│       └── js/
│           ├── tracking-map.js   Customer map (Leaflet + AJAX polling)
│           ├── kitchen-orders.js Kitchen dashboard auto-refresh
│           ├── courier-deliveries.js Courier dashboard + GPS
│           └── manager-deliveries.js Manager dashboard
│
├── SQL/                          Database scripts
│   ├── db.sql                    Original database schema + seed data
│   ├── migration_v2.sql          Staff, User_Address, Promotion tables
│   ├── migration_v3.sql          Payment columns, feedback columns
│   ├── migration_v4_tracking.sql Tracking tables (Order_Tracking, etc.)
│   └── rollback_v4_tracking.sql  Rollback for v4 migration
│
├── config/                       Configuration files
│   └── stripe.php                Stripe API keys (in .gitignore)
│
├── vendor/                       Composer dependencies (stripe-php, phpmailer)
│
├── material/                     Food and cafe images
│   ├── [Cafe Name]/              Each cafe has a folder with shop.jpg + food images
│   ├── frame/                    300 webp frames for landing page animation
│   └── images/                   Logos, avatars, default images
│
├── includes/
│   ├── cart_modal.php            Old cart modal (legacy)
│   └── html_helpers.php          HTML helper functions
│
├── payment_cart/                 Old payment pages (legacy, replaced by customer/)
│   ├── payment.php
│   ├── cart_actions.php
│   ├── create_payment_intent.php
│   ├── finalize_order.php
│   └── Payment_success.php
│
├── sidebar/                      Old sidebar pages (legacy, replaced by customer/)
│   ├── home.php, cafe.php, bills.php, contact.php, setting.php
│
├── login/                        Old login pages (legacy, replaced by auth/)
│   ├── loginHandle.php, register.php, _member_login.php, _admin_login.php
│
├── Css/                          Old CSS files (legacy)
├── js/                           Old JavaScript files (legacy)
├── profile/                      Old profile functions (legacy)
├── contact/                      Old contact page (legacy)
├── tools/                        Utility scripts (password reset tool)
│
├── base.php                      Old bootstrap (legacy, replaced by app/bootstrap.php)
├── index.php                     Landing page (entry point)
├── composer.json                 PHP dependency declarations
├── composer.lock                 Locked dependency versions
├── composer.phar                 Composer executable
└── .gitignore                    Git ignore rules
```

### How Files Connect (文件如何连接)

The connection between files follows a clear pattern:

1. **Every page** starts with `require __DIR__ . '/../app/layout/master.php'`
2. `master.php` loads `bootstrap.php` (DB, session, CSRF, auth helpers)
3. Pages that need cart/payment also `require` the relevant service file
4. Pages render HTML using `layout_start()` ... `layout_end()`
5. JavaScript in `assets/js/` makes AJAX calls to `api/` endpoints
6. API endpoints return JSON using `json_out()` helper
7. All database queries go through `q()`, `q_one()`, `q_all()` prepared statement helpers

---

## 4. Important Code Explained

### 4.1 Database Connection and Helpers (数据库连接和辅助函数)

**File:** `app/bootstrap.php`

```php
function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli('localhost', 'root', '', 'cafedash_db');
        $conn->set_charset('utf8mb4');
        $conn->query("SET time_zone = '+08:00'");
    }
    return $conn;
}
```

**What it does:** Creates a single database connection using MySQLi (MySQL Improved extension). The `static $conn` means the connection is created only once per page load (singleton pattern). It sets UTF-8 character set (for emojis and Chinese characters) and Malaysia timezone.

```php
function q(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}
```

**What it does:** This is the most important helper function. It runs a **prepared statement** (预处理语句) which prevents SQL injection (SQL注入). Instead of putting user input directly into SQL, it uses `?` placeholders and binds parameters separately. The function automatically detects the type: `i` for integer, `d` for double/float, `s` for string.

**Example usage:**
```php
// Safe: user input goes through bind_param, not into the SQL string
$user = q_one('SELECT * FROM User WHERE User_ID = ? AND Deleted_at IS NULL', [$id]);
```

### 4.2 CSRF Protection (CSRF防护)

**File:** `app/bootstrap.php`

```php
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}
```

**What it does:** CSRF (Cross-Site Request Forgery) protection prevents other websites from submitting forms on behalf of a logged-in user. It generates a random 64-character token stored in the session. Every form includes this token as a hidden field. Every AJAX request sends it as a header. When the server receives a request, it compares the sent token with the session token using `hash_equals()` (which prevents timing attacks).

**Why it's needed:** Without CSRF protection, an attacker could create a malicious website that submits a form to CafeDash while the user is logged in. For example, the attacker could change the user's password or place an order without the user knowing.

### 4.3 Auth Guards (身份验证守卫)

```php
function require_customer(): array
{
    $user = current_user();
    if (!$user) {
        if (is_ajax()) json_out(false, null, 'Please sign in again.', 401);
        flash('info', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
    return $user;
}

function require_staff(array $roles = ADMIN_ROLES): array
{
    $staff = current_staff();
    if (!$staff) {
        if (is_ajax()) json_out(false, null, 'Please sign in again.', 401);
        redirect('auth/staff_login.php');
    }
    if (!in_array($staff['Role'], $roles, true)) {
        if (is_ajax()) json_out(false, null, 'Your role cannot do this.', 403);
        http_response_code(403);
        exit('Forbidden: your role (' . e($staff['Role']) . ') cannot open this page.');
    }
    return $staff;
}
```

**What it does:** These functions check if the current user is logged in and has the right role. If not, they redirect to login (for page requests) or return a JSON error (for AJAX requests). `require_staff()` also checks role — a Kitchen Staff cannot access Manager-only pages.

### 4.4 API Entry Point (API入口)

```php
function api_start(): string
{
    set_exception_handler(function (Throwable $ex) {
        error_log((string) $ex);
        if ($ex instanceof mysqli_sql_exception && $ex->getCode() === 1062) {
            json_out(false, null, 'That value is already in use.', 409);
        }
        json_out(false, null, 'Server error. Please try again.', 500);
    });
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, null, 'Use POST.', 405);
    if (!csrf_valid()) json_out(false, null, 'Your session expired. Refresh the page.', 419);
    return (string) ($_POST['action'] ?? '');
}
```

**What it does:** Every API endpoint calls `api_start()` at the top. It does three things:
1. Sets an exception handler that catches all errors and returns a JSON 500 response (instead of showing a PHP error page)
2. Rejects GET requests (only POST is allowed for API endpoints)
3. Validates the CSRF token

Returns the `action` parameter which tells the endpoint what operation to perform (e.g., 'add', 'get', 'session').

### 4.5 Cart System (购物车系统)

**File:** `app/services/cart.php`

```php
function cart_add(int $foodId, int $qty, string $sugar, string $ice, string $remark): void
{
    $food = q_one('SELECT f.Food_ID, f.Name, f.Food_type, f.amount, r.Restaurant_ID, r.Name AS cafe
        FROM Food f JOIN Restaurant r ON r.Restaurant_ID = f.Restaurant_ID WHERE f.Food_ID = ?', [$foodId]);
    if (!$food) throw new InvalidArgumentException('That item is no longer on the menu.');

    $drink = is_drink_type((string) $food['Food_type']);
    // ... validates sugar/ice options for drinks ...
    $items = &cart_items();
    // Same dish with same options → just increase the quantity
    foreach ($items as &$it) {
        if ((int) ($it['foodId'] ?? 0) === $foodId && ($it['sugar'] ?? '') === $sugar
            && ($it['ice'] ?? '') === $ice && ($it['remark'] ?? '') === $remark) {
            $it['quantity'] = min(MAX_QTY, (int) $it['quantity'] + $qty);
            return;
        }
    }
    $items[] = [ /* ... new item array ... */ ];
}
```

**What it does:** Adds an item to the cart stored in `$_SESSION['cart']`. Key points:
- **Reads price from database**: The function queries the `Food` table to get the current price. The browser never sends a price — it only sends food ID, quantity, and options. This prevents price manipulation.
- **Drink detection**: Checks if the food type is a drink (using regex) to determine if sugar/ice options apply.
- **Merging**: If the same dish with the exact same options is already in the cart, it just increases the quantity instead of adding a duplicate.

```php
function cart_summary(?int $userId = null): array
{
    // ... re-reads prices from DB ...
    $subtotal = round($subtotal, 2);
    // promo code re-validated every time
    $promo = null;
    $discount = 0.0;
    if (!empty($_SESSION['promo_code']) && $lines) {
        $check = promo_check($_SESSION['promo_code'], $subtotal, $userId);
        if (is_array($check)) {
            $promo = ['id' => ..., 'code' => ..., 'percent' => ...];
            $discount = round($subtotal * $promo['percent'] / 100, 2);
        }
    }
    $taxable = max(0, $subtotal - $discount);
    $sst = round($taxable * SST_RATE, 2);
    return ['items' => $lines, 'count' => $count,
            'subtotal' => $subtotal, 'discount' => $discount,
            'sst' => $sst, 'total' => round($taxable + $sst, 2),
            'promo' => $promo, 'promo_error' => $promoError];
}
```

**What it does:** Calculates the full priced cart. Every time the cart is displayed, prices are re-read from the database. If a promo code is applied, it is re-validated (could have expired since last check). SST (Sales and Service Tax) is 6% in Malaysia, calculated on the discounted amount.

### 4.6 Stripe Payment (Stripe支付)

**File:** `app/services/payments.php`

```php
function stripe_config(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $root = dirname(__DIR__, 2);
        $file = $root . '/config/stripe.php';
        $cfg = null;
        if (is_file($file) && is_file($root . '/vendor/autoload.php')) {
            $c = require $file;
            if (!empty($c['secret_key']) && !empty($c['publishable_key'])) {
                require_once $root . '/vendor/autoload.php';
                \Stripe\Stripe::setApiKey($c['secret_key']);
                $cfg = $c;
            }
        }
    }
    return $cfg;
}
```

**What it does:** Loads the Stripe configuration. It checks that two files exist:
1. `config/stripe.php` — contains the API keys
2. `vendor/autoload.php` — Composer's autoloader for the Stripe PHP SDK

If either file is missing, it returns `null`, and `stripe_ready()` returns `false`. The checkout page then shows "Card payments aren't set up on this server."

```php
function payment_create_session(array $user): string
{
    // ... validates cart not empty, total >= RM2, has address ...
    $lines = [];
    foreach ($sum['items'] as $it) {
        $lines[] = [
            'quantity'   => (int) $it['quantity'],
            'price_data' => [
                'currency'     => 'myr',
                'unit_amount'  => (int) round($it['unitAmount'] * 100),
                'product_data' => ['name' => $it['itemName'], 'description' => ...],
            ],
        ];
    }
    // SST added as a separate line item
    if ($sum['sst'] > 0) {
        $lines[] = ['quantity' => 1, 'price_data' => ['currency' => 'myr',
            'unit_amount' => (int) round($sum['sst'] * 100),
            'product_data' => ['name' => 'SST (6%)']]];
    }
    // ... discount coupon if promo applied ...
    $session = \Stripe\Checkout\Session::create($params);
    $_SESSION['checkout_session'] = ['id' => $session->id, 'summary' => $sum];
    return $session->url;
}
```

**What it does:** Creates a Stripe Checkout Session. Important details:
- **Currency**: MYR (Malaysian Ringgit)
- **unit_amount in sen**: Stripe uses the smallest currency unit. RM 8.50 = 850 sen. So the code multiplies by 100.
- **SST as separate line**: Tax is added as its own line item so Stripe shows it clearly.
- **Discount as coupon**: Stripe doesn't support negative line items, so promo discounts become a one-off Stripe Coupon.
- **Session snapshot**: The cart summary is saved in `$_SESSION` so the order can be recorded even if the cart changes while the user is on Stripe's page.

```php
function payment_finalize_session(array $user, string $sessionId): int
{
    // ... validates session ID format ...
    $session = \Stripe\Checkout\Session::retrieve($sessionId);
    if ((string) ($session->metadata['user_id'] ?? '') !== (string) $uid)
        throw new InvalidArgumentException('This payment belongs to another account.');
    if ($session->payment_status !== 'paid')
        throw new InvalidArgumentException('Stripe has not confirmed this payment yet.');

    $intentId = is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent->id;
    $existing = q_one('SELECT Payment_ID FROM Payment WHERE Provider_payment_id = ?', [$intentId]);
    if ($existing) {
        cart_clear();
        return (int) $existing['Payment_ID'];
    }
    // ... record order in database ...
}
```

**What it does:** Verifies the payment with Stripe after the customer returns. Critical security checks:
1. **Session ID format validation**: Must match `cs_test_` or `cs_live_` pattern (prevents injection)
2. **User ownership check**: `metadata.user_id` must match the logged-in user (prevents viewing other people's orders)
3. **Payment status check**: Stripe must confirm `payment_status === 'paid'`
4. **Idempotency**: If the `Provider_payment_id` (Stripe Payment Intent ID) already exists in the database, return the existing order — no duplicate orders

### 4.7 Order Recording (订单记录)

```php
function record_paid_order(int $uid, array $sum, string $intentId, int $chargedSen, string $currency): int
{
    $cafes = array_unique(array_column($sum['items'], 'restaurantId'));
    $restaurantId = count($cafes) === 1 ? (int) reset($cafes) : null;

    db()->begin_transaction();
    try {
        q('INSERT INTO Payment (...) VALUES (...)', [...]);
        $paymentId = (int) db()->insert_id;

        foreach ($sum['items'] as $it) {
            q('INSERT INTO Payment_Item (...) VALUES (...)', [...]);
        }

        if ($sum['promo']) {
            q('INSERT INTO Promotion_Redemption (...) VALUES (...)', [...]);
            q('UPDATE Promotion SET Redemption_count = Redemption_count + 1 WHERE Promo_ID = ?', [...]);
        }
        db()->commit();
        return $paymentId;
    } catch (Throwable $e) {
        db()->rollback();
        throw $e;
    }
}
```

**What it does:** Records the paid order in the database using a **transaction** (事务). A transaction means all operations succeed together or fail together — there is no partial order. If the Payment row is inserted but a Payment_Item insert fails, the entire transaction rolls back (no half-written order). For multi-cafe orders, `Restaurant_ID` is set to NULL (no single restaurant).

### 4.8 Tracking Record Creation (跟踪记录创建)

**File:** `tracking/bootstrap.php`

```php
function track_ensure_for_payment(int $paymentId): ?array
{
    $payment = q_one('SELECT p.Payment_ID, p.User_ID, p.Restaurant_ID, p.Payment_status, p.Created_at
         FROM Payment p WHERE p.Payment_ID = ?', [$paymentId]);

    if (!$payment) return null;
    // CRITICAL: Only create tracking for confirmed payments
    if ($payment['Payment_status'] !== 'SUCCEEDED') return null;

    // Resolve distinct restaurants via Payment_Item → Food → Restaurant_ID
    $restaurants = q_all(
        'SELECT DISTINCT f.Restaurant_ID
         FROM Payment_Item pi
         INNER JOIN Food f ON f.Food_ID = pi.Food_ID
         WHERE pi.Payment_ID = ? AND f.Restaurant_ID IS NOT NULL
         ORDER BY f.Restaurant_ID',
        [$paymentId]
    );

    db()->begin_transaction();
    try {
        if (!$existing) {
            // Create parent tracking row
            q('INSERT IGNORE INTO Order_Tracking (...) VALUES (...)', [...]);
            // Log initial status
            q('INSERT INTO Order_Status_Log (...) VALUES (...)', [...]);
        }
        // Create child rows (one per restaurant)
        foreach ($restaurants as $r) {
            q('INSERT IGNORE INTO Order_Tracking_Item (...) VALUES (...)', [...]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollback();
        return $existing ?: null;
    }
    return q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
}
```

**What it does:** Creates tracking records for a confirmed payment. Key design:
- **Payment gate**: Only creates tracking if `Payment_status = 'SUCCEEDED'` — this keeps payment status and tracking status separate
- **Multi-restaurant**: Resolves restaurants through the chain `Payment_Item.Food_ID → Food.Restaurant_ID`. A multi-cafe order creates N child rows.
- **Idempotent**: Uses `INSERT IGNORE` so calling this function twice doesn't create duplicates
- **Transaction**: Parent and all children created atomically

### 4.9 Parent Status Derivation (父状态推导)

**File:** `tracking/config.php`

```php
function track_derive_parent_status(array $childStatuses, string $currentParent): string
{
    // Delivery-phase statuses are protected from child override
    $parentProtected = [TRACK_STATUS_ASSIGNED, TRACK_STATUS_OUT_FOR_DELIVERY,
                        TRACK_STATUS_DELIVERED, TRACK_STATUS_CANCELLED];
    if (in_array($currentParent, $parentProtected, true)) {
        return $currentParent;
    }

    // Treat 'Completed' same as 'Ready for Pickup' for derivation
    $effective = [];
    foreach ($childStatuses as $s) {
        $effective[] = ($s === TRACK_ITEM_COMPLETED) ? TRACK_ITEM_READY : $s;
    }

    if ($allReady)     return TRACK_STATUS_READY;
    if ($anyPreparing) return TRACK_STATUS_PREPARING;
    if ($allAccepted)  return TRACK_STATUS_ACCEPTED;
    if ($anyPending)   return TRACK_STATUS_PENDING;
    return $currentParent;
}
```

**What it does:** Derives the parent order status from all child statuses. This is the core of the multi-restaurant tracking design:
- If cafe A is "Preparing" and cafe B is "Ready for Pickup", the parent becomes "Preparing" (because food is still being cooked)
- Only when ALL cafes are "Ready for Pickup" does the parent become "Ready for Pickup"
- Once a courier is assigned (parent = "Assigned"), kitchen status changes don't override the parent — delivery is in progress

**Why it's needed:** A customer who orders from 2 cafes needs to see a single status. If one cafe is still cooking, the order isn't ready yet. This function combines multiple cafe statuses into one customer-facing status.

### 4.10 Status Update API (状态更新API)

**File:** `tracking/api/update_status.php`

The role-based logic in this file:

- **Kitchen Staff**: Updates only their restaurant's child row → validates child transition → updates child → derives parent → updates parent if changed → logs both
- **Manager**: Checks all children are Ready → validates parent transition → assigns courier → updates parent → logs
- **Courier**: Validates ownership → validates parent transition → updates parent → on Delivered, marks all children as Completed

**Security checks:**
- CSRF token validated via `api_start()`
- Staff must be logged in
- Terminal state check (no changes to Delivered/Cancelled orders)
- Role-specific permission checks
- Kitchen staff can only update their assigned restaurant
- Courier can only update their assigned deliveries
- Manager can only assign when all children are Ready

### 4.11 Courier GPS Update (外卖员GPS更新)

**File:** `tracking/api/update_location.php`

```php
// Only Couriers can update GPS
if ($staff['Role'] !== 'Courier') {
    json_out(false, null, 'Only couriers can update delivery location.', 403);
}
// Verify this courier is assigned to this delivery
if ((int) $tracking['Courier_ID'] !== (int) $staff['Staff_ID']) {
    json_out(false, null, 'You are not assigned to this delivery.', 403);
}
// Only track during active delivery
if (!in_array($tracking['Order_status'], [TRACK_STATUS_ASSIGNED, TRACK_STATUS_OUT_FOR_DELIVERY])) {
    json_out(false, ['active' => false], 'Delivery is not active.', 200);
}
// Insert history row + update latest position
q('INSERT INTO Delivery_Location (...) VALUES (...)', [...]);
q('UPDATE Order_Tracking SET Courier_Latitude = ?, Courier_Longitude = ? WHERE Payment_ID = ?', [...]);
```

**What it does:** Saves the courier's GPS position. Two writes:
1. `Delivery_Location` — a history row (creates a path trail on the map)
2. `Order_Tracking.Courier_Latitude/Longitude` — the latest position (for quick lookup)

Only works when the delivery is active (Assigned or Out for Delivery).

### 4.12 JavaScript AJAX Helper (JavaScript AJAX辅助)

**File:** `assets/js/core/api.js`

```javascript
CD.api = async (endpoint, payload = {}) => {
    const body = payload instanceof FormData ? payload : new FormData();
    if (!(payload instanceof FormData)) {
        Object.entries(payload).forEach(([k, v]) => body.append(k, v ?? ''));
    }
    res = await fetch(CD.url(endpoint), {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': CD.csrf, 'X-Requested-With': 'fetch', Accept: 'application/json' },
    });
    json = await res.json();
    if (res.status === 401) setTimeout(() => location.reload(), 1200);
    if (!json.ok) {
        const err = new Error(json.error || 'Something went wrong.');
        err.fields = json.data && json.data.fields;
        throw err;
    }
    return json.data;
};
```

**What it does:** The core JavaScript function for making AJAX calls. Every JavaScript interaction with the server goes through `CD.api()`. It:
- Sends POST requests with FormData (supports file uploads)
- Includes the CSRF token as a header
- Parses JSON response
- If `ok === false`, throws an Error with the server's error message
- If 401 (unauthorized), reloads the page after 1.2 seconds (redirects to login)

### 4.13 Leaflet Map Initialization (地图初始化)

**File:** `tracking/assets/js/tracking-map.js`

```javascript
function initMap(lat, lng) {
    if (map) return;
    if (typeof L === 'undefined') {
        console.warn('Leaflet not loaded — map disabled');
        return;
    }
    map = L.map('track-map', {
        zoomControl: true,
        attributionControl: true,
    }).setView([lat || 3.0733, lng || 101.5830], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);
}
```

**What it does:** Initializes a Leaflet.js map centered on Subang Jaya, Malaysia (latitude 3.0733, longitude 101.5830). The map tiles come from OpenStreetMap (free, open-source map data). The map shows three markers:
1. Restaurant marker (where food is prepared)
2. Destination marker (customer's delivery address)
3. Courier marker (updates every 5 seconds via AJAX polling)

---

## 5. Database Explanation

### Database Overview

The database is called `cafedash_db` and runs on MariaDB 10.4 (part of XAMPP). It was built in stages:

1. **db.sql** — Original tables (User, Restaurant, Food, Payment, Payment_Item, etc.)
2. **migration_v2.sql** — Added User_Address, Staff, Promotion, Promotion_Redemption
3. **migration_v3.sql** — Added columns to Payment (Discount_amount, Promo_ID) and contact_us (Is_read, User_ID)
4. **migration_v4_tracking.sql** — Added tracking tables (Order_Tracking, Order_Tracking_Item, Order_Status_Log, Delivery_Location)

### Important Tables and Relationships (重要表格和关系)

#### Original Tables (原始表格)

```
User (用户表)
├── User_ID (PK)
├── User_Name, Email, Password, Contain_number
├── Profile_Image, Suspend, Created_at, Deleted_at
│
├── 1:N → User_Address (一个用户多个地址)
├── 1:N → Payment (一个用户多个订单)
└── 1:N → contact_us (一个用户多条反馈)

Restaurant (餐厅表)
├── Restaurant_ID (PK)
├── Name, Address, Restaurant_type, Rating
├── Contain_number, Email
├── Latitude, Longitude (v4: for map)
│
├── 1:N → Food (一个餐厅多个菜品)
└── 1:N → Payment (一个餐厅多个订单, NULL for multi-cafe)

Food (菜品表)
├── Food_ID (PK)
├── Restaurant_ID (FK → Restaurant)
├── Name, Food_type, detail, amount
│
└── 1:N → Payment_Item (一个菜品在多个订单中)

Payment (支付表)
├── Payment_ID (PK)
├── User_ID (FK → User)
├── Restaurant_ID (FK → Restaurant, NULL for multi-cafe)
├── Payment_type, Payment_amount
├── Subtotal_amount, SST_amount, Discount_amount
├── Promo_ID (FK → Promotion)
├── Currency, Payment_status (ENUM: PENDING/SUCCEEDED/FAILED/REFUNDED)
├── Provider (STRIPE), Provider_payment_id (Stripe Payment Intent ID)
├── Created_at, Paid_at
│
├── 1:N → Payment_Item (一个订单多个菜品)
└── 1:1 → Order_Tracking (一个订单一个跟踪记录)

Payment_Item (订单明细表)
├── Payment_Item_ID (PK)
├── Payment_ID (FK → Payment, ON DELETE CASCADE)
├── Food_ID (FK → Food, ON DELETE SET NULL)
├── Item_name, Item_type, Unit_amount
├── Quantity, Line_total
├── Sugar_level, Ice_level, Remark
```

#### v2 Tables (v2表格)

```
User_Address (用户地址表)
├── Address_ID (PK)
├── User_ID (FK → User)
├── Label (Home/Work/Campus/Other)
├── Recipient, Phone, Line1, Line2
├── City, Postcode, State
├── Is_default, Latitude, Longitude (v4)
│
└── Referenced by Order_Tracking.Delivery_Address_ID

Staff (员工表 — replaces Admin + Driver)
├── Staff_ID (PK)
├── Name, Email, Phone, Password
├── Role (ENUM: Super Admin, Manager, Kitchen Staff, Courier)
├── Status (ENUM: Active, Suspended, Deactivated)
├── Plate_number (for Couriers)
├── Assigned_Restaurant_ID (FK → Restaurant, v4: for Kitchen Staff)
├── Last_login_at, Created_at, Updated_at
│
├── Referenced by Order_Tracking.Courier_ID
├── Referenced by Order_Tracking.Assigned_By
└── Referenced by Order_Tracking_Item.Updated_by

Promotion (促销表)
├── Promo_ID (PK)
├── Code (UNIQUE, e.g., WELCOME15)
├── Title, Description
├── Discount_percent (1-100)
├── Max_redemptions (NULL = unlimited)
├── Redemption_count, Min_spend
├── Starts_at, Expires_at
├── Is_active (1 = active, 0 = paused)
│
└── 1:N → Promotion_Redemption

Promotion_Redemption (促销兑换记录)
├── Redemption_ID (PK)
├── Promo_ID (FK → Promotion)
├── User_ID (FK → User)
├── Payment_ID (FK → Payment)
├── Discount_amount, Redeemed_at
```

#### Tracking Tables (跟踪表格 — v4 Migration)

```
Order_Tracking (父跟踪表 — 1 row per order)
├── Tracking_ID (PK)
├── Payment_ID (FK + UNIQUE → Payment, ON DELETE RESTRICT)
├── User_ID (FK → User, ON DELETE RESTRICT)
├── Restaurant_ID (FK → Restaurant, NULL for multi-cafe, ON DELETE SET NULL)
├── Courier_ID (FK → Staff, ON DELETE SET NULL)
├── Assigned_By (FK → Staff, ON DELETE SET NULL)
├── Order_status (ENUM: Pending → Accepted → Preparing → Ready for Pickup
│          → Assigned → Out for Delivery → Delivered → Cancelled)
├── Delivery_Address_ID (FK → User_Address)
├── Dest_Latitude, Dest_Longitude (delivery destination coordinates)
├── Courier_Latitude, Courier_Longitude (latest courier position)
├── Estimated_minutes, Tracking_PIN
├── Created_at, Updated_at
│
├── 1:N → Order_Tracking_Item (one parent, N children per restaurant)
└── 1:N → Order_Status_Log (status change history)

Order_Tracking_Item (子跟踪表 — 1 row per restaurant per order)
├── Item_Tracking_ID (PK)
├── Tracking_ID (FK → Order_Tracking, ON DELETE CASCADE)
├── Payment_ID (FK → Payment, ON DELETE RESTRICT)
├── Restaurant_ID (FK → Restaurant, ON DELETE RESTRICT)
├── Item_status (ENUM: Pending → Accepted → Preparing → Ready for Pickup → Completed)
├── Updated_by (FK → Staff)
├── Updated_at, Created_at
│
└── UNIQUE KEY (Payment_ID, Restaurant_ID) — prevents duplicate children

Order_Status_Log (状态日志表)
├── Log_ID (PK)
├── Payment_ID (FK → Payment, ON DELETE RESTRICT)
├── Restaurant_ID (NULL for parent-level logs)
├── Order_status (ENUM: includes all parent + child statuses + Completed)
├── Log_Level (ENUM: 'parent' or 'item')
├── Changed_By (FK → Staff, NULL for system-generated logs)
├── Note, Created_at

Delivery_Location (外卖员GPS历史)
├── Location_ID (PK)
├── Payment_ID (FK → Payment, ON DELETE CASCADE)
├── Courier_ID (FK → Staff)
├── Latitude, Longitude, Accuracy
├── Created_at
```

### How Tracking Tables Work Together (跟踪表格如何协同工作)

```
Customer places order → Payment created (status: SUCCEEDED)
                           │
                           ▼
track_ensure_for_payment() creates:
                           │
    ┌──────────────────────┴──────────────────────┐
    │                                              │
    ▼                                              ▼
Order_Tracking (parent)              Order_Tracking_Item (children)
    Status: Pending                    Cafe A: Pending
                                       Cafe B: Pending
    │
    │ Kitchen A clicks "Accept"
    ▼
    Cafe A child: Pending → Accepted
    Parent derived: ALL children Accepted? No (Cafe B still Pending)
    Parent stays: Pending
    │
    │ Kitchen B clicks "Accept"
    ▼
    Cafe B child: Pending → Accepted
    Parent derived: ALL children Accepted? Yes!
    Parent becomes: Accepted
    │
    │ Kitchens click "Preparing"
    ▼
    Children: Cafe A = Preparing, Cafe B = Preparing
    Parent derived: ANY child Preparing? Yes!
    Parent becomes: Preparing
    │
    │ Kitchens click "Ready for Pickup"
    ▼
    Children: Cafe A = Ready for Pickup, Cafe B = Ready for Pickup
    Parent derived: ALL children Ready? Yes!
    Parent becomes: Ready for Pickup
    │
    │ Manager assigns courier
    ▼
    Parent: Ready for Pickup → Assigned (Courier_ID set)
    Children unchanged (delivery phase — protected from child override)
    │
    │ Courier starts delivery
    ▼
    Parent: Assigned → Out for Delivery
    Courier sends GPS every 4s → Delivery_Location + Order_Tracking
    │
    │ Courier delivers
    ▼
    Parent: Out for Delivery → Delivered
    ALL children marked: Completed
```

### Purpose of Migration v4 (v4迁移的目的)

Migration v4 adds the **Real-Time Order Tracking & Live Delivery Map System**. It:

1. **Adds 4 new tables**: Order_Tracking, Order_Tracking_Item, Order_Status_Log, Delivery_Location
2. **Adds columns to 3 existing tables**: Restaurant.Latitude/Longitude, User_Address.Latitude/Longitude, Staff.Assigned_Restaurant_ID
3. **Preserves all existing data**: Uses `IF NOT EXISTS` and guarded statements — safe to re-run
4. **Does not modify Payment or Payment_Item**: Tracking is completely separate from payment
5. **Foreign key rules**: RESTRICT for critical references (Payment, User), SET NULL for optional references (Courier, Restaurant, Address), CASCADE only for ephemeral GPS data

---

## 6. Technologies Used

### PHP (Hypertext Preprocessor)

**What it is:** A server-side programming language that runs on the web server.

**What it does in this project:**
- Processes form submissions (login, register, checkout)
- Connects to the database and runs SQL queries
- Implements business logic (cart pricing, promo validation, loyalty calculation)
- Serves HTML pages using the master layout template
- Handles AJAX API endpoints that return JSON
- Integrates with Stripe PHP SDK for payments
- Implements security: CSRF tokens, password hashing, prepared statements, role-based access control

**Version:** PHP 8.2.12 (loaded as Apache module via `php8apache2_4.dll`)

### MySQL / MariaDB (Database)

**What it is:** A relational database management system that stores data in tables.

**What it does in this project:**
- Stores all data: users, restaurants, food, orders, payments, tracking, promotions, staff
- Enforces data integrity with foreign keys (relationships between tables)
- Supports transactions (all-or-nothing operations for order recording)
- Provides indexes for fast lookups
- ENUM columns for controlled values (payment status, order status, roles)

**Version:** MariaDB 10.4.32 (bundled with XAMPP)

### JavaScript and AJAX

**What it is:** JavaScript is a client-side language that runs in the browser. AJAX (Asynchronous JavaScript and XML) allows the browser to communicate with the server without reloading the page.

**What it does in this project:**
- Cart drawer: add/remove items without page reload
- Checkout: create Stripe session via AJAX, redirect to payment page
- Cafe browser: search and filter cafes in real-time
- Menu page: open dish customization modal, add to cart
- Tracking map: poll server every 5 seconds for courier position
- Kitchen dashboard: auto-refresh order queue every 4 seconds
- Courier dashboard: send GPS position every 4 seconds using `navigator.geolocation`
- Admin dashboard: load KPIs, charts, and tables via AJAX
- Form validation: password strength meter, field error display
- Animations: GSAP (GreenSock Animation Platform) for scroll-driven hero animation

**Key files:** `assets/js/core/api.js` (AJAX helper), `assets/js/core/cart.js` (cart logic), `tracking/assets/js/tracking-map.js` (map)

### Stripe (Payment Gateway)

**What it is:** An online payment processing service that handles credit card transactions securely.

**What it does in this project:**
- Processes credit card payments for customer orders
- Uses Stripe Checkout (hosted payment page) — the customer enters card details on Stripe's website, not CafeDash's
- CafeDash never sees or stores credit card numbers
- Test mode: uses test keys (pk_test_... and sk_test_...), test card 4242 4242 4242 4242
- Currency: Malaysian Ringgit (MYR)
- Payment flow: Create Session → Redirect to Stripe → Customer pays → Stripe redirects back → Verify payment → Record order

**Library:** `stripe/stripe-php` v11.0.0 (installed via Composer)

### Composer (Dependency Manager)

**What it is:** A tool for managing PHP libraries (packages) that a project depends on.

**What it does in this project:**
- Installs the Stripe PHP SDK (`stripe/stripe-php`)
- Installs PHPMailer (`phpmailer/phpmailer`) for email functionality
- Generates `vendor/autoload.php` — an autoloader that automatically loads class files when needed
- `composer.json` declares dependencies, `composer.lock` locks exact versions

**Version:** Composer 2.9.7

### Leaflet.js and OpenStreetMap (Map)

**What they are:**
- **Leaflet.js** — an open-source JavaScript library for interactive maps
- **OpenStreetMap** — a free, open-source map of the world (like Google Maps but free)

**What they do in this project:**
- Display a live map on the customer tracking page (`tracking/customer/track.php`)
- Show markers for: restaurant (food preparation), destination (delivery address), courier (live position)
- Update courier marker every 5 seconds via AJAX polling
- Draw a path trail showing the courier's route history
- Pan and zoom: customers can interact with the map

**Version:** Leaflet.js 1.9.4 (loaded from CDN)

### Other Technologies

| Technology | Purpose |
|---|---|
| **Apache 2.4.58** | Web server that serves PHP pages (bundled with XAMPP) |
| **XAMPP** | All-in-one package: Apache + MariaDB + PHP + phpMyAdmin |
| **phpMyAdmin** | Web-based database management tool (used to run SQL migrations) |
| **HTML5/CSS3** | Page structure and styling |
| **GSAP** | JavaScript animation library (scroll-driven hero animation) |
| **GD Library** | PHP image processing (avatar upload resizing, security re-encoding) |
| **PHPMailer** | Email sending library (password reset, order confirmations) |
| **bcrypt** | Password hashing algorithm (via PHP's `password_hash()`) |
| **JSON** | Data format for AJAX responses (`json_out()` returns JSON) |
| **FormData** | Browser API for sending form data including files via AJAX |
| **Geolocation API** | Browser API (`navigator.geolocation`) for courier GPS |
| **WebP** | Image format used for 300 animation frames on landing page |

---

## 7. Incomplete or Not Implemented

The following items are known gaps or incomplete features:

### Not Yet Connected to Apache (Apache未配置)
- Apache's DocumentRoot is `C:/xampp/htdocs`, but the project is at `D:/Assignment/Mini Project`
- The project is not currently served by Apache — the DocumentRoot needs to be changed, or an alias/symlink created
- PHP's built-in development server (`php -S`) is not running

### Tracking System — Phase 2 Not Started (跟踪系统第二阶段未开始)
- `tracking/api/kitchen_orders.php` and `tracking/api/order_status.php` have been updated for parent/child design
- `tracking/api/update_status.php` has been updated for parent/child design
- However, `tracking/api/manager_deliveries.php` and `tracking/api/courier_deliveries.php` may need updates for the multi-restaurant child breakdown in their response data
- The JavaScript files (`kitchen-orders.js`, `manager-deliveries.js`, `courier-deliveries.js`) may need updates to display per-restaurant child status

### GPS Retention Cleanup (GPS清理未实现)
- `Delivery_Location` rows accumulate indefinitely during deliveries
- A cleanup query exists only as a comment in the migration SQL
- No cron job or scheduled event is implemented to clean old GPS data

### Migration Comment Mismatch (迁移注释不匹配)
- Line 178 of `migration_v4_tracking.sql` has a comment saying `ON DELETE SET NULL` but line 192 code says `ON DELETE RESTRICT`
- The code is correct (RESTRICT); the comment is wrong (cosmetic issue)

### Email Configuration Not Set Up (邮件未配置)
- `config/mail.config.php` does not exist (in `.gitignore`)
- `mailer.php` checks for this file and returns `null` if missing
- Password reset emails will not be sent (local dev mode shows links on screen instead)

### Restaurant and Address Coordinates (坐标未设置)
- `Restaurant.Latitude/Longitude` columns exist but are NULL (no auto-seeding)
- `User_Address.Latitude/Longitude` columns exist but are NULL
- Manager must manually enter restaurant coordinates via admin panel
- Customer must set address coordinates on the tracking page
- Map markers will not display until coordinates are set

### Old Legacy Files (旧文件未删除)
- `base.php` — old bootstrap (replaced by `app/bootstrap.php`)
- `sidebar/` — old page templates (replaced by `customer/`)
- `login/` — old login pages (replaced by `auth/`)
- `payment_cart/` — old payment pages (replaced by `customer/checkout.php` etc.)
- `Css/` — old CSS files (replaced by `assets/css/`)
- `js/` — old JavaScript files (replaced by `assets/js/`)
- `profile/` — old profile functions
- `contact/` — old contact page
- These files are preserved (not deleted) for backward compatibility

### Zip Extension Not Enabled (Zip扩展未启用)
- The `zip` PHP extension is commented out in `php.ini` (`;extension=zip`)
- This caused Composer to fall back to source installation (git clone) instead of zip extraction
- Does not affect Stripe functionality at runtime

---

## Quick Reference: Key File Paths

| Purpose | File Path |
|---|---|
| Entry point (landing page) | `index.php` |
| Core bootstrap (DB, session, CSRF) | `app/bootstrap.php` |
| Master layout template | `app/layout/master.php` |
| Cart logic | `app/services/cart.php` |
| Stripe payment logic | `app/services/payments.php` |
| Promo code logic | `app/services/promo.php` |
| Loyalty points | `app/loyalty.php` |
| Customer login | `auth/login.php` |
| Customer registration | `auth/register.php` |
| Cafe browser | `customer/home.php` |
| Cafe menu | `customer/cafe.php` |
| Checkout | `customer/checkout.php` |
| Order confirmation | `customer/order_success.php` |
| Order history | `customer/orders.php` |
| Customer tracking page | `tracking/customer/track.php` |
| Tracking config + logic | `tracking/config.php` |
| Tracking record creation | `tracking/bootstrap.php` |
| Status update API | `tracking/api/update_status.php` |
| GPS update API | `tracking/api/update_location.php` |
| Kitchen orders API | `tracking/api/kitchen_orders.php` |
| Customer tracking API | `tracking/api/order_status.php` |
| Original DB schema | `SQL/db.sql` |
| v4 tracking migration | `SQL/migration_v4_tracking.sql` |
| Stripe config (gitignored) | `config/stripe.php` |
| Composer config | `composer.json` |
| JavaScript AJAX helper | `assets/js/core/api.js` |
| Cart JavaScript | `assets/js/core/cart.js` |
| Tracking map JavaScript | `tracking/assets/js/tracking-map.js` |

---

*This document was generated by reading every important file in the project. It describes only features and code that actually exist in the project. No functionality was guessed or invented.*
