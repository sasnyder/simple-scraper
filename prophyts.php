<?php

libxml_use_internal_errors(true);

include './excel/PHPExcel.php';

class Prophyts {

	const UPLOADS	= '/uploads/';
	const ENHANCED	= '/enhanced/';

	private $original;
	private $phpexcel;
	private $enhanced;
	private $error;

	public function __construct($upload) {
		// Directory and Upload is valid and tmp was moved
		if ($this->valid($upload) and $this->move($upload)) {
			// Enhance the file
			$this->process();
		}
	}

	public function error() {
		return $this->error;
	}

	public function enhanced() {
		return $this->enhanced;
	}

	private function valid($upload) {
		// Check upload file exists
		if (!file_exists($upload['tmp_name']) or !is_uploaded_file($upload['tmp_name'])) {
			$this->error = 'No file uploaded';
			return false;
		}

		// Check file upload error
		if (!empty($upload['error'])) {
			$this->error = 'File upload error: '.$upload['error'];
			return false;
		}

		// Check upload file extension
		if (pathinfo($upload['name'], PATHINFO_EXTENSION) != 'xlsx') {
			$this->error = 'Invalid file type';
			return false;
		}

		return true;
	}

	private function move($upload) {
		// Check for upload directory or attempt to create
		if (!is_dir(__DIR__.self::UPLOADS) and !mkdir(__DIR__.self::UPLOADS, 0755)) {
			$this->error = 'Failed to create upload directory';
			return false;
		}

		// Destination of upload file
		$this->original = __DIR__.self::UPLOADS.$upload['name'];

		// Attempt to move uploaded temporary file
		if (!move_uploaded_file($upload['tmp_name'], $this->original)) {
			$this->error = 'Failed to move uploaded file';
			return false;
		}

		// Check for enhanced directory or attempt to create
		if (!is_dir(__DIR__.self::ENHANCED) and !mkdir(__DIR__.self::ENHANCED, 0755)) {
			$this->error = 'Failed to create enhanced directory';
			return false;
		}

		return true;
	}

	private function process() {
		// Open excel file
		$this->phpexcel = PHPExcel_IOFactory::load($this->original);
		$worksheet = $this->phpexcel->getActiveSheet();

		// Check for data rows
		if (!$worksheet->getHighestRow() > 1) {
			$this->error = 'File missing data to process';
			return false;
		}

		// Create row iterator
		$ri = $worksheet->getRowIterator();

		// Loop thru each row
		foreach ($ri as $i => $row) {
			// Header row
			if ($i == 1) {
				$column = $this->header($row);
			}
			// Data row
			else {
				// If row has company name
				if ($company_name = $worksheet->getCell($column['COMPANY_NAME'].$i)->getValue()) {
					$cell = [
						$worksheet->getCell($column['DESCRIPTION_1'].$i),
						$worksheet->getCell($column['DESCRIPTION_2'].$i),
					];
					$this->description($company_name, $cell);
				}

				// If row has domain name
				if ($domain_name = $worksheet->getCell($column['DOMAIN_NAME'].$i)->getValue()) {
					$cell = $worksheet->getCell($column['WEBSITE'].$i);
					$this->website($domain_name, $cell);
				}
			}

			// Processing error occured
			if (!empty($this->error)) {
				return false;
			}
		}

		$this->enhanced = __DIR__.self::ENHANCED.'ENHANCED_'.basename($this->original);

		$writer = PHPExcel_IOFactory::createWriter($this->phpexcel, "Excel2007");
		$writer->save($this->enhanced);
	}

	private function header($row) {
		// Column indexes
		$column = [];
		// Create cell iterator
		$ci = $row->getCellIterator();
		// Get all cells even if empty
		$ci->setIterateOnlyExistingCells(false);

		// Loop thru header cells
		foreach ($ci as $i => $cell) {
			$value = $cell->getValue();

			// Find relevant columns
			if (in_array($value, ['COMPANY_NAME', 'DOMAIN_NAME'])) {
				$column[$value] = $i;
			}
		}

		if (empty($column)) {
			$this->error = 'Missing COMPANY_NAME and DOMAIN_NAME columns';
			return false;
		}

		// Add enhanced columns
		foreach (['DESCRIPTION_1', 'DESCRIPTION_2', 'WEBSITE'] as $enhanced) {
			$column[$enhanced] = ++$i;
			$this->phpexcel->getActiveSheet()->setCellValue($i.'1', $enhanced);
		}

		return $column;
	}

	private function description($company_name, $cell) {
		// Sleep between 1s-2s
		usleep(mt_rand(1000,2000)*1000);

		// Count descriptions
		$count = 0;

		// Search query
		$url = 'https://www.google.com/search?q='.urlencode($company_name.' Bloomberg');

		// Search Google
		if (!$response = file_get_contents($url)) {
			$this->error = error_get_last()['message'];
			return false;
		}

		// Load HTML response
		$html = new DomDocument;
		$html->loadHtml($response);

		// Loop thru spans tags
		foreach ($html->getElementsByTagName('span') as $i => $span) {

			// Loop thru attributes
			foreach ($span->attributes as $attr) {

				// Look for class st
				if ($attr->name == 'class' and $attr->value == 'st') {
					// Write to description cell
					$cell[$count++]->setValue(str_replace("\n", '', $span->nodeValue));
					break;
				}
			}

			// Stop after all cells written
			if ($count == count($cell)) {
				break;
			}
		}

		return true;
	}

	private function website($domain_name, $cell) {
		// Sleep between 1s-2s
		usleep(mt_rand(1000,2000)*1000);

		// URL key
		$key = '/url?q=http';
		// Strip this off URL
		$strip = '/url?q=';

		// Banned domains
		$banned = [
			'webcache.googleusercontent.com',
			'wikipedia.com',
			'yelp.com',
			'facebook.com',
			'amazon.com',
			'twitter.com'
		];

		// Search query
		$url = 'https://www.google.com/search?q='.urlencode($domain_name);

		// Search Google
		if (!$response = file_get_contents($url)) {
			$this->error = error_get_last()['message'];
			return false;
		}

		// Load HTML response
		$html = new DomDocument;
		$html->loadHtml($response);

		// Loop thru a tags
		foreach ($html->getElementsByTagName('a') as $link) {

			// Loop thru attributes
			foreach ($link->attributes as $attr) {

				// Look for href starting with key
				if ($attr->name == 'href' and substr($attr->value, 0, strlen($key)) == $key) {

					// Find all slashes
					preg_match_all('~/~', $attr->value, $slash, PREG_OFFSET_CAPTURE);
					// From end of key to 4rd slash
					$website = substr($attr->value, strlen($strip), $slash[0][3][1]-strlen($strip));
					// Search for banned domains
					str_replace($banned, '', $website, $found);

					// URL was not found
					if (!$found) {
						// Write to website cell
						$cell->setValue($website);
						break 2;
					}
				}
			}
		}

		return true;
	}


}
