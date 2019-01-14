<?php
ini_set('display_errors',1);
function import_from_zip($request)
{
	wlog('Start - ' . date('Y/m/d H:i:s'));
	$zips = glob(WOO_IMPORT_UPLOAD_ZIP_SOURCE . '*.zip');
	if (empty($zips)) {
        return new WP_Error( 'error', 'Source file not found. Please upload ZIP file to import folder', ['status' => 400]);
    }
	
	_unzip_file($zips);
	
	$files = glob(WOO_IMPORT_UPLOAD_ZIP_IMPORT . '*');
	$count = 0;
	$total = 0;
	foreach ($files as $file) {
		$rs = import_from_file($file);
		$count += $rs['count'];
		$total += $rs['total'];
	}
	$message['import_count'] = "{$count} / {$total}";
	wlog(" ----- {$count} / {$total}");
	wlog('End - ' . date('Y/m/d H:i:s'));
	return rest_ensure_response(['data' => $message]);
}

function _unzip_file($zips, $wp_filesystem = null) 
{
	foreach ($zips as $zip) {
		try {
			exec("unzip {$zip} -d " . WOO_IMPORT_UPLOAD_ZIP_IMPORT);
			exec("mv {$zip} " . WOO_IMPORT_UPLOAD_ZIP_ARCHIVE);
		} catch (Exception $e) {
			wlog($e->getMessage());
		}
	}
	
	return;
}

function import_from_file ($file_path) 
{
	$file = new SplFileObject($file_path);
	$count = 0;
	$total = 0;
	while (!$file->eof()) {
		$json = $file->fgets();
		$product_object = json_decode($json);
		$total++;
		$utils = new WooUtils();
		
		try {
			$rs = $utils->create_product($product_object);
			if($rs){
				$count++;
			}
		} catch (Exception $e) {
			wlog($e->getMessage());
		}
		
	}
	unlink($file_path);
	
	return ['count' => $count, 'total' => $total];
}

function batch_import($request)
{
    $sources = $request['sources'];
    register_process($sources);

    return rest_ensure_response(['data' => ['message' => 'Added process to queue']]);
}

function register_process ($sources)
{
    $wp_filesystem = new WP_Filesystem_Direct([]);
    $sources = is_array($sources) ? $sources : [$sources];
    $source_json = json_encode($sources);

    $process_name = date('YmdHis') . '.process';
    $file_path = WOO_IMPORT_PROCESS . process_name;

    $wp_filesystem->put_contents($file_path, $source_json);

    return;
}

/**
 * Import from list source
 *
 * @param object $request
 *
 * @return json
 */
function import_from_source_urls($request)
{
    if (!isset($request['sources'])) {
        return new WP_Error( 'error', 'Invalid source', ['status' => 400]);
    }

    $sources = $request['sources'];
    $sources = is_array($sources) ? $sources : [$sources];
    $total = count($sources);
    $count = 0;
    foreach ($sources as $source) {
        $json = parse_json($source);

        $utils = new WooUtils();

        if ($utils->create_product($json)) {
            $count++;
        }
    }

    $message['import_count'] = "{$count} / {$total}";

    return rest_ensure_response(['data' => $message]);
}

function wlog($mes, $log_file = WOO_IMPORT . 'message.log')
{
    $prefix = date("Y/m/d h:i:s");
    file_put_contents($log_file, $prefix . ' - ' . $mes . PHP_EOL, FILE_APPEND);
}


