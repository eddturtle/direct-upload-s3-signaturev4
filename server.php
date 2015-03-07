<!DOCTYPE html>
<html>
<head>
	<title>Server</title>
</head>
<body>

	<h1>Save Filename on Server here</h1>

	<p>Original Filename &amp; S3 Filename: <?php echo htmlentities($_POST['upload_original_name']); ?></p>
	<p>Custom User Filename: <?php echo htmlentities($_POST['upload_custom_name']); ?></p>

</body>
</html>
