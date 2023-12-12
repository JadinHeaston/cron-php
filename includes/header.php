<?PHP
//Create version hashes based on last modified time.
$versionedFiles = array(
	'css/styles.css' => '',
	'js/scripts.js' => '',
);

foreach ($versionedFiles as $fileName => $hash)
{
	$versionedFiles[$fileName] = substr(md5(filemtime($fileName)), 0, 6);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>PAGE TITLE</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="icon" type="image/svg+xml" href="favicon.svg">
	<link rel="preload" as="style" href="css/styles.css?v=<?PHP echo $versionedFiles['css/styles.css']; ?>">
	<link rel="stylesheet" href="css/styles.css?v=<?PHP echo $versionedFiles['css/styles.css']; ?>">
	<script src="js/scripts.js.js?v=<?PHP echo $versionedFiles['js/scripts.js']; ?>" type="module"></script>
</head>

<body>