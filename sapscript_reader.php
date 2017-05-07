<?php
	//	####################################################################################
	//	CONSTANTS
		define('BLANK', ' ');
		define('VERSION', '0.9.1-2017_05_07');
	//	####################################################################################
	//	(DEFAULT) LANGUAGE
		isset($_POST['language']) ? $language = $_POST['language'] : $language = 'D';
	//	####################################################################################
	//	(DEFAULT) MODE
		isset($_POST['mode']) ? $mode = $_POST['mode'] : $mode = 'standard';
	//	####################################################################################
	//	READ FILE
		$file = null;
		if(isset($_FILES['file'])) {
			if(file_exists($_FILES['file']['tmp_name'])) {
				if(($file = @fopen($_FILES['file']['tmp_name'], 'r')) === false) {
					$file = null;
				}
			}
		}
	//	####################################################################################

	function parse_file($language, $mode, $file) {
		global $languages, $includes, $headforms;

		$languages = array();
		$includes = array();
		$headforms = array();

		$exclude_rows = array('OLAND', 'ACTVSAP', 'END');
		$headform_i = null;
		$headform_window_i = 0;
		$headform_type = null;
		$headform_language = null;
		$break = false;

		if(is_null($file)) {
			return;
		}

		while(!feof($file)) {

			$row = fgets($file, 4096);
			$row = str_replace(PHP_EOL, null, $row);

			if(substr($row, 0, 1) == BLANK) {
				$row = substr($row, 1); // ATTENTION: Update row

				if(in_array(trim($row), $exclude_rows)) {
					continue; }

				if(substr($row, 0, 8) == 'HEADFORM') {

					$headform_type = substr($row, 84, 3);
					$headform_language = substr($row, 88, 1);

					is_null($headform_i) ? $headform_i = 0 : $headform_i++;

					if($headform_type == 'TXT') {
						$languages[] = $headform_language;
						$headform_language != $language ? $break = true : $break = false;
					}
				}

				if($break == true) {
					continue; }

				if(substr($row, 0, 6) == 'LINE/W') {
					$headform_window_i++;
				}

				$headforms[$headform_i]['rows'][$headform_window_i][] = $row;
				$headforms[$headform_i]['type'] = $headform_type;
				$headforms[$headform_i]['language'] = $headform_language;
			}
		}
		fclose($file);
	}

	function highlight_row($name, $line, $row) {
		global $includes, $mode, $tab_count;

		$is_include = false;
		$row = substr($row, 4); // ATTENTION: Update row
		$tab = '';

		if(substr($row, 0, 1) == '>') {
			$css = array('color' => 'green', 'backgroundColor' => '');
			$length = 1;
			if($mode == 'strict') return;
		} elseif(substr($row, 0, 1) == '*') {
			$css = array('color' => 'blue', 'backgroundColor' => '');
			$length = 1;
		} elseif(substr($row, 0, 2) == '/:') {
			if(strpos($row, 'INCLUDE')) {
				$css = array('color' => 'white', 'backgroundColor' => 'brown');
				$is_include = true;
			} else {
				$css = array('color' => 'red', 'backgroundColor' => '');
			}
			$length = 2;
			if($mode == 'strict') return;
		} elseif(substr($row, 0, 2) == '/*') {
			$css = array('color' => 'green', 'backgroundColor' => '');
			$length = 2;
			if($mode == 'strict') return;
		} elseif(substr($row, 0, 2) == '/E') {
			$css = array('color' => 'black', 'backgroundColor' => 'yellow');
			$length = 2;
			if($mode == 'strict') return;
		} elseif(substr($row, 0, 2) == '/W') {
			$css = array('color' => 'white', 'backgroundColor' => 'blueviolet');
			$length = 2;
			if($mode == 'strict') return;
		} else {
			$css = array('color' => '', 'backgroundColor' => '');
			$length = 2;
		}

		if(substr($row, $length, 5) == 'ENDIF'
		|| substr($row, $length, 7) == 'ENDCASE') {
			if(substr($row, 0, 2) != '/*') $tab_count--;
		}

		for($t = 0; $t < $tab_count; $t++) {
			$tab .= '&nbsp;&nbsp;&nbsp;';
		}

		if(substr($row, $length, 2) == 'IF'
		|| substr($row, $length, 4) == 'CASE') {
			if(substr($row, 0, 2) != '/*') $tab_count++;
		}

		$highlighted_row = '<span onclick="$(this).toggleClass(\'highlight\'); return false;" style="color:' . $css['color'] . '; background-color:' . $css['backgroundColor'] . '; white-space:nowrap;">' . $name . BLANK . sprintf("%04d", $line) . BLANK . htmlspecialchars(substr($row, 0, $length)) . BLANK . $tab . htmlspecialchars(substr($row, $length)) . '</span>' . PHP_EOL;

		if($is_include == true) { $includes[] = $highlighted_row; }
		return $highlighted_row;
	}

	//	####################################################################################
	parse_file($language, $mode, $file);
	//	####################################################################################
?>
<!DOCTYPE html>
<html lang="de">
	<head>
		<meta charset="utf-8">
		<!-- <meta http-equiv="X-UA-Compatible" content="IE=edge"> -->
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>SAPscript Reader v<?php echo VERSION; ?></title>
		<link href="./includes/bootstrap-3.3.7/css/bootstrap.min.css" rel="stylesheet">
		<script src="./includes/jquery-3.2.1/jquery-3.2.1.min.js"></script>
		<script src="./includes/bootstrap-3.3.7/js/bootstrap.min.js"></script>

		<style>
			body {
				background-color:#FFFFF0;
				padding:10px;
			}
			.highlight {
				color:black !important;
				background-color:cyan !important;
			}
		</style>
	</head>
	<body>
		<?php
			if($mode == 'strict') {
				echo '<div class="alert alert-danger" role="alert"><strong>STRICT LAYOUT ACTIVE (EXPERIMENTAL)</strong></div>';
			}
		?>
		<div class="panel panel-primary">
			<div class="panel-heading">Configuration</div>
			<div class="panel-body">
				<form enctype="multipart/form-data" action="?" method="post">
					<div class="form-group">
						<label for="file">File</label>
						<input type="file" id="file" name="file">
					</div>
					<?php if(!is_null($file)) { ?>
						<div class="form-group">
							<label for="language">Language</label>
							<select class="form-control" id="language" name="language">
								<?php
									foreach($languages as $value) {
										echo '<option value="' . $value . '"'; if($value == $language) { echo ' selected'; } echo '>' . $value . '</option>';
									}
								?>
							</select>
						</div>
						<div class="form-group">
							<label for="mode">Mode</label>
							<select class="form-control" id="mode" name="mode">
								<option value="standard"<?php if($mode == 'standard') { echo ' selected'; } ?>>Standard</option>
								<option value="strict"<?php  if($mode == 'strict') { echo ' selected'; } ?>>Strict</option>
							</select>
						</div>
					<?php } ?>
					<button type="submit" class="btn btn-success">Go!</button>
				</form>
			</div>
		</div>
		<?php
			if(!is_null($file)) {
				foreach($headforms as $key => $headform) {

					echo '<div class="panel panel-primary"><div class="panel-heading"><a href="#" onclick="$(\'#headform_' . $headform['type'] . '_' . $headform['language'] . '\').toggleClass(\'hide\'); return false;"><code>Type: ' . $headform['type'] . ' // Language: ' . $headform['language'] . '</code></a></div>';
					echo '<div class="panel-body hide" id="headform_' . $headform['type'] . '_' . $headform['language'] . '">';

					foreach($headform['rows'] as $key => $window) {

						$key == 0 ? $name = '<em>METADATA</em>' : $name = substr($window[0], 6);

						echo '<div class="panel panel-'; if($key == 0) { echo 'success'; } else { echo 'default'; } echo '"><div class="panel-heading"><a href="#" onclick="$(\'#headform_' . $headform['type'] . '_' . $headform['language'] . '_' . $key . '\').toggleClass(\'hide\'); return false;"><mark>' . $name . ' (' . (count($window) - 1) . ')</mark></a></div>';
						echo '<div class="panel-body hide" id="headform_' . $headform['type'] . '_' . $headform['language'] . '_' . $key . '"><pre>';

						foreach($window as $key => $row) {

							echo highlight_row($name, $key, $row);
						}
						echo '</pre></div></div>';
					}
					echo '</div></div>';
				}
				if(count($includes)) {
					echo '<div class="panel panel-primary">';
						echo '<div class="panel-heading"><a href="#" onclick="$(\'#includes\').toggleClass(\'hide\'); return false;"><code>Includes</code></a></div>';
						echo '<div class="panel-body hide" id="includes"><pre>';
							foreach($includes as $include) {
								echo $include;
							}
						echo '</pre></div>';
					echo '</div>';
				}
			}
		?>
	</body>
</html>