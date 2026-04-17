<!DOCTYPE html>
<html>
<head>
	<?php
	if (!isset($pageTitle) || trim((string) $pageTitle) === '') {
		$pageTitle = 'Welcome to Cafe Dash';
	}
	if (!isset($extraStylesheets) || !is_array($extraStylesheets)) {
		$extraStylesheets = [];
	}
	?>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="Css/home style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
	<?php foreach ($extraStylesheets as $stylesheet): ?>
		<link rel="stylesheet" href="<?php echo htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8'); ?>">
	<?php endforeach; ?>
</head>