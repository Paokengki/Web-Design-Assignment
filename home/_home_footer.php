			<footer class="footer">
				<div class="container">
					<div class="row">
						<div class="col-md-6">
							<h4>Contact Us</h4>
							<p> Edumetro, Persiaran Subang Permai, Usj 1, 47500 Subang Jaya, Selangor</p>
							<p>Email: CafeDash@gmail.com</p>
							<p>Phone:  03-8600 1777</p>
						</div>
						<div class="col-md-6">
							<h4>Follow Us</h4>
							<p>Stay connected with us on social media:</p>
							<ul class="social-icons">
								<li><a href="https://www.facebook.com/share/1BEvvsF4YG/?mibextid=wwXIfr"><i class="fab fa-facebook-f"></i></a></li>
								<li><a href="https://x.com/CafeDash44362"><i class="fab fa-twitter"></i></a></li>
								<li><a href="https://www.instagram.com/cafedashoffical?igsh=MXQxY204dDJ1eGpu&utm_source=qr"><i class="fab fa-instagram"></i></a></li>
							</ul>
						</div>
					</div>
				</div>
			</footer>
</body>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<!-- adding javascript -->
<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="../js/app.js"></script>
<?php
if (!isset($extraScripts) || !is_array($extraScripts)) {
	$extraScripts = [];
}
foreach ($extraScripts as $scriptPath):
?>
<script src="<?php echo htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>
</html>