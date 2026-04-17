<?php
require_once 'base.php';
require_once 'includes/html_helpers.php';

$pageTitle = 'Contact Us - Cafe Dash';
$extraStylesheets = ['Css/contact_us.css'];
$extraScripts = ['js/contact_us.js'];
$bodyClass = 'contact-page';

require_once 'home/_home_sidebar.php';

$successMessage = '';
$errors = [];
$formData = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    foreach ($formData as $field => $value) {
        $formData[$field] = trim($_POST[$field] ?? '');
    }

    if ($formData['name'] === '') {
        $errors[] = 'Please enter your name.';
    }
    if ($formData['phone'] === '') {
        $errors[] = 'Please enter your phone number.';
    }
    if ($formData['email'] === '') {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($formData['message'] === '') {
        $errors[] = 'Please enter your message.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('INSERT INTO contact_us (name, phone, email, message) VALUES (?, ?, ?, ?)');

        if ($stmt === false) {
            $errors[] = 'Unable to prepare the database query. Please try again later.';
        } else {
            $stmt->bind_param('ssss', $formData['name'], $formData['phone'], $formData['email'], $formData['message']);
            if ($stmt->execute()) {
                $successMessage = 'Message sent successfully. We will get back to you shortly.';
                $formData = array_fill_keys(array_keys($formData), '');
            } else {
                $errors[] = 'Unable to save your message. Please try again later.';
            }
            $stmt->close();
        }
    }
}


?>


    <div class="contact-content">
    <div class="main-navbar contact-navbar">
        <a href="javascript:history.back()" class="cart" aria-label="Go back"><ion-icon name="arrow-back-outline"></ion-icon></a>
        <div></div>
    </div>
    <div class="contact-container">
        <div class="header-section">
            <h1>CONTACT US</h1>
            <p>If you have any questions, please feel free to get in touch with us via phone, text, email, the form below, or even on social media!</p>
        </div>

        <?php echo renderStatusMessage($successMessage, $errors); ?>

        <div class="main-layout">
            <div class="form-card">
                <h3>GET IN TOUCH</h3>
                <form id="contactForm" action="" method="post" novalidate>
                    <div class="form-row">
                        <?php echo renderTextInput('name', 'NAME', $formData['name'], ['placeholder' => 'Enter your name*', 'required' => 'required']); ?>
                        <?php echo renderTextInput('phone', 'PHONE NUMBER', $formData['phone'], ['placeholder' => 'Enter your phone number*', 'required' => 'required']); ?>
                    </div>
                    <?php echo renderEmailInput('email', 'EMAIL', $formData['email'], ['placeholder' => 'Enter your email*', 'required' => 'required']); ?>
                    <?php echo renderTextareaInput('message', 'YOUR MESSAGE', $formData['message'], ['placeholder' => 'Type your message here...', 'required' => 'required']); ?>
                    <?php echo renderSubmitButton('SEND MESSAGE', 'submit_contact', ['class' => 'send-btn']); ?>
                </form>

                <div class="info-section">
                    <div class="info-card">
                        <h3>CONTACT INFORMATION</h3>
                        <div class="info-item"><strong>PHONE:</strong> 012-34567889</div>
                        <div class="info-item"><strong>ADDRESS:</strong> Tarumt Arena</div>
                        <div class="info-item"><strong>EMAIL:</strong> IDk@gmail.com</div>
                    </div>
                    <div class="info-card">
                        <h3>BUSINESS HOURS</h3>
                        <div class="hours-grid">
                            <div><strong>MON - FRI</strong><br>9:00 am - 8:00 pm</div>
                            <div><strong>SATURDAY</strong><br>9:00 am - 6:00 pm</div>
                            <div><strong>SUNDAY</strong><br>9:00 am - 5:00 pm</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="map-section">
            <iframe src="https://www.google.com/maps/embed?pb=!3m2!1sen!2sus!4v1774802584519!5m2!1sen!2sus!6m8!1m7!1s03a2Paa1_eoNLnDHt0j4RA!2m2!1d3.213426454237041!2d101.730402997966!3f164.46545407772578!4f-8.614787379248725!5f0.7820865974627469" width="940" height="600" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
    </div>

<?php require_once 'home/_home_footer.php'; ?>
