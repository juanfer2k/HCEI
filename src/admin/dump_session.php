<?php
// dump_session.php
if (php_sapi_name() !== 'cli') { echo "CLI only\n"; exit(1); }
$save_path = ini_get('session.save_path');
if (empty($save_path)) $save_path = sys_get_temp_dir();
echo "session.save_path = $save_path\n";
// Attempt to find a recent session file and parse it
$files = array_filter(scandir($save_path), function($f){ return preg_match('/^sess_/', $f); });
rsort($files);
if (empty($files)) { echo "No session files found in $save_path\n"; exit(0); }
$latest = $files[0];
$path = rtrim($save_path, '/\\') . DIRECTORY_SEPARATOR . $latest;
echo "Reading $path\n";
$contents = file_get_contents($path);
echo "RAW: \n". $contents ."\n\n";

// Try to parse PHP session serialized format
function session_decode_to_array($session_data) {
    $return_data = array();
    $offset = 0;
    while ($offset < strlen($session_data)) {
        if (!strstr(substr($session_data, $offset), "|")) break;
        $pos = strpos($session_data, "|", $offset);
        $num = $pos - $offset;
        $varname = substr($session_data, $offset, $num);
        $offset += $num + 1;
        $data = unserialize(substr($session_data, $offset));
        $return_data[$varname] = $data;
        $offset += strlen(serialize($data));
    }
    return $return_data;
}
$parsed = @session_decode_to_array($contents);
print_r($parsed);
