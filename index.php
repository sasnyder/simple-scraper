<?php
	//error_reporting(E_ALL & ~E_NOTICE);
	//ini_set('display_errors', 1);
	error_reporting(0);
	ini_set('display_errors', 0);

	include './prophyts.php';

	if ($file = $_FILES['upload']) {
		$prophyts = new Prophyts($file);
		if (!$error = $prophyts->error()) {
			$enhanced = $prophyts->enhanced();
			header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="'.basename($enhanced).'"');
			readfile($enhanced);
			exit;
		}
	}
 ?>

<html>
<head>
	<title>Prophyts Enhancer</title>
	<style type="text/css">
		input { clear: left; float: left; margin-bottom: 25px; }
		#upload { width:400px; }
		#error { color: red; }
		#working { color:green; float:left; clear:left; }
		.hide { display:none; }
	</style>
	<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function(e) {
			document.getElementById('enhancer').addEventListener('submit', function(e) {
				document.getElementById('submit').disabled = true;
				document.getElementById('working').classList.remove('hide');
			});
			document.getElementById('upload').addEventListener('change', function(e) {
				document.getElementById('error').innerHTML = '';
			});
		});
	</script>
</head>
<body>
	<h1>Prophyts Enhancer</h1>
	<form id="enhancer" method="post" action="/" enctype="multipart/form-data">
		<input type="file" id="upload" name="upload" required="required" />
		<span id="error"><?php echo $error; ?></span>
		<input type="submit" id="submit" value="Enhance!" />
		<span id="working" class="hide">Working...</span>
	</form>
</body>
</html>
