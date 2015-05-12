<?php
/**
 * TODO: 
 *   * Summary of 'NOT_EXIST' and others errors...
 *   * Doc public functions
 *   * ccompiler: Distinguish between errors and warnings .
 */

class Check_js{
    // ccompiler defauls
    private $_externs = '';
    private $_cc_jar = '';
    private $_mode = 'SIMPLE_OPTIMIZATIONS';
    private $_options = array(
        '--jscomp_warning', 'checkVars', 
        '--jscomp_error',   'checkRegExp',
        '--jscomp_error',   'undefinedVars'
    );
    
    // Internal status
    private $_repo_dir;
    private $_work_dir;
    private $err_count = 0;
    private $ok_count = 0;
    
    function __construct($repository_dir, $work_dir) {
        $this->_repo_dir = $this->clear_dir_name($repository_dir);
        $this->_work_dir = $this->clear_dir_name($work_dir);
        $this->empty_tree($this->_work_dir);
        echo "Check_js({$repository_dir}, {$work_dir})\n";
    }
    function __destruct() {
        $end_msg = "---- END ---\n"
            ."- - - - - - -\n"
            ."Final summary:\n"
            ."    [CC_ERRORS] = ".$this->err_count."\n"
            ."    [right_end] = ".$this->ok_count."\n";
        echo "\n".$end_msg;
        $this->_log('cc_errors', $end_msg);
        $this->_log('cc_done', $end_msg);
    }
    private function clear_dir_name($directory) {
        $directory_c = str_replace("\\", '/', $directory);
        // Clear end base dir
        if (substr($directory_c,-1) === '/') {
            $directory = substr($directory, 0, -1);
        }
        return $directory;
    }
    public function get_dir($type) {
        switch ($type) {
        case 'work':
            return $this->_work_dir;
        case 'repo':
            return $this->_repo_dir;
        }
    }
    
    // COMPILE JS FROM HTML //
    public function minimize_js($files, $output_file = null) {
        $this->ccompile($files, null, $output_file);
        return $this;
    }
    public function check_extract_js($file_name) {
        $this->extract_js_and_check($file_name, 0);
        return $this;
    }
    public function check_mixed_js($file_name) {
        $this->extract_js_and_check($file_name, 2);
        return $this;
    }
    public function check_js_dir($includes, $excludes = null) {
        $files = $this->filter_dir($includes, $excludes);
        foreach ($files as $f) {
            $this->ccompile(
                $f,
                $this->_repo_dir,
                'temp/'.$this->get_flat_name($f).'.min.js',
                $this->_work_dir
            );
        }
        return $this;
    }
    public function check_extract_js_dir($includes, $excludes = null) {
        $files = $this->filter_dir($includes, $excludes);
        foreach ($files as $f) {
            $this->extract_js_and_check($f, 0);
        }
        return $this;
    }
    public function check_mixed_js_dir($includes, $excludes = null) {
        $files = $this->filter_dir($includes, $excludes);
        foreach ($files as $f) {
            $this->extract_js_and_check($f, 2);
        }
        return $this;
    }
    // Extract tools
    private function extract_js_and_check($file_name, $start_type_code) {
        try {
            $r = $this->extract_js_write($file_name, $start_type_code);
        } catch (Exception $e) {
            $this->_log_error('EXTRACT_FAILURE', $file_name);
            return;
        }
        if ($r) { 
            $this->ccompile(
                "{$r['temp_folder']}/{$r['file_name']}", 
                $this->_work_dir,
                'temp/'.$this->get_flat_name($r['file_name']).'.min.js'
            );
        }
    }
    private function get_flat_name($file_name) {
        return str_replace(
            array('/',"\\"), array(' ',' '),
            $file_name
        );
    }
    private function extract_js_write($file_name, $start_type_code) {
        $this->_log('logs/extract_js_write', $file_name);
        $f = $this->extract_js_code(
            $this->_repo_dir.'/'.$file_name,
            $start_type_code
        );
        if (!$f || count($f) === 0) { return null; }
        
        $code = '';
        $line = 1;
        foreach ($f as $item) {
             "{$item['start']}-{$item['end']}.js";
            if ($item['start']-$line > 0) { 
            // this should be true, but if negative jump in order to write code.
                $code .= str_repeat("\n", $item['start']-$line);
            }
            $line = $item['end'];
            $code .= 
                " /* extract_js->Lines: {$item['start']}-{$item['end']} */ "
                .$item['text']
                ." /* extract_js->End lines: {$item['start']}-{$item['end']} */ ";
        }
        $file_name .='.js';
        $temp_folder ='temp/_extract_js';
        $f_name ="/{}";
        $this->write_file(
            "{$this->_work_dir}/{$temp_folder}/{$file_name}",
            $code
        );
        return array(
            'temp_folder' => $temp_folder,
            'file_name' => $file_name            
        );
    } 
    private function extract_js_code($file_name, $start_type_code) {
        if (!file_exists($file_name)) {
            $this->_log_error('NOT_EXIST', $file_name);
            return null;
        }
        $handle = @fopen($file_name, "r");
        $codes = array();
        $C_START = '<script';
        $C_END = '</script>';
        $line = 0;
        $type_code = $start_type_code; // 0=no, 1=label, 2=code, 3=internal PHP
        if ($start_type_code === 2) {
            $code_start_line = 1;
            $js_text = array();
        } else {
            $code_start_line = 0;
        }
        $s_pos = false;
        $code_pendig = '';
        $lf_pendig = 0;
        if ($handle) {
            while (true) {
                if ($s_pos === false) {
                    $buffer = fgets($handle, 4096);
                    if ($buffer === false) {
                        break;
                    }
                    $s_pos = 0;
                    $s_pos_label = 0;
                    $s_pos_code = 0;
                    $s_pos_PHP = 0;
                    $line++;
                }
                switch ($type_code) {
                case 0:
                    $s_pos = stripos($buffer, $C_START, $s_pos);
                    if ($s_pos !== false) {
                        $type_code = 1;
                        $s_pos_label = $s_pos + strlen($C_START);
                    }
                    break;
                case 1:
                    $s_pos = stripos($buffer, '>', $s_pos_label);
                    if ($s_pos !== false) {
                        // check if <script src="<?=... ></script>"
                        $s_pos_x = stripos($buffer, '?>', $s_pos_label);
                        if ($s_pos_x !== false) {
                            if ($s_pos_x + 1 === $s_pos) {
                                $s_pos_label = $s_pos + 1;
                                break;
                            }
                        }
                        $type_code = 2;
                        $s_pos_code = $s_pos + 1;
                        $code_start_line = $line;
                        $js_text = array();
                    }
                    break;
                case 2:
                    $s_pos = stripos($buffer, $C_END, $s_pos_code);
                    $s_pos_PHP = stripos($buffer, '<?', $s_pos_code);
                    if ($s_pos_PHP !== false && 
                                ($s_pos === false || $s_pos_PHP < $s_pos) ) {
                        $code_pendig .= substr(
                            $buffer, $s_pos_code, $s_pos_PHP-$s_pos_code
                        ).'_PHP_REMOVED_';
                        $type_code = 3;
                        $s_pos_PHP = $s_pos_PHP + 2;
                        $s_pos = $s_pos_PHP;
                        break;
                    }
                    if ($s_pos === false) {
                        // end js line
                        $line_text = $code_pendig.str_replace(
                                array("\r","\n"), array('',''),
                                substr($buffer, $s_pos_code)
                            ).str_repeat("\n", $lf_pendig);
                        $code_pendig = '';
                        $lf_pendig = 0;
                    } else {
                        $line_text = substr(
                                $buffer, $s_pos_code, $s_pos-$s_pos_code);
                        // end code
                        $s_pos += strlen($C_END);
                        $type_code = 0;                    
                    }
                    array_push($js_text, $line_text);
                    if ($type_code !== 2) {
                        if (count($js_text) > 1 || $js_text[0] !== '') {
                            array_push($codes, array(
                                'text' => implode("\n", $js_text),
                                'start' => $code_start_line,
                                'end' => $line
                            ));
                        }
                    }
                    break;
                case 3:
                    $s_pos = stripos($buffer, '?>', $s_pos_PHP);                    
                    if ($s_pos === false) {
                        // end line PHP into js
                        $lf_pendig++;
                    } else {
                        // end PHP
                        $s_pos += 2;
                        $type_code = 2;
                        $s_pos_code = $s_pos;
                    }
                    break;
                }
            }
            if (!feof($handle)) {
                throw new Exception(
                    "Error: fgets() is not false at the end of ::split() reading file: \"{$file_name}\".");
                exit; 
            } elseif ($type_code !== $start_type_code) {
                throw new Exception(
                    "Error: `script` is not closed at end of file: \"{$file_name}\". Code_type={$type_code}.");
                exit;
            }
            if ($start_type_code === 2) {
                if (count($js_text) > 1 || $js_text[0] !== '') {
                    array_push($codes, array(
                        'text' => implode("\n", $js_text),
                        'start' => $code_start_line,
                        'end' => $line
                    ));
                }
            }
            fclose($handle);
            return $codes;
        }
    }
    
    // COMPILER OPTIONS //
    public function set_cc_jar($file_name) {
        $this->check_exists('set_cc_jar()', $file_name);
        $this->_cc_jar = $file_name;
        return $this;
    }
    public function set_externs($files = null, $base_dir = null) {
        if ($base_dir === null) {
            $base_dir = $this->_repo_dir;
        }
        $this->_externs = '';
        if ($files) {
            if (!is_array($files)) {
                $files = array($files);
            }
            foreach ($files as $file) {
                $file_final = $base_dir !== '' ? $base_dir.'/'.$file : $file;
                $this->check_exists('set_externs()', $file_final);
                $this->_externs .= ' --externs "'.realpath($file_final).'"';
            }
        }
        return $this;
    }
    public function set_options($options = null) {
        if ($options) {
            $this->_options = $options;
        } else {
            $this->_options = array();
        }
        return $this;
    }

    // THE COMPILER //
    private function ccompile(
                $files, $base_dir = null, 
                $output_file = null, $output_dir = null) {
        // Check ccompiler jar is set.
        if (!$this->_cc_jar) {
            $this->_cancel('Check_js()', '->set_cc_jar() is not set.');
        }
        
        // Check `files` parameter
        if ($files) {
            if (!is_array($files)) {
                $files = array($files);
            }
        } else {
            return null;
        }
        if (count($files) < 1) { return; }
        
        // Parse parameters
        if (!$base_dir) {
            $base_dir = $this->_repo_dir;
        }
        if (!$output_dir) {
            $output_dir = $base_dir;
        }
        $js_cmd = 'java -jar '.$this->_cc_jar;
        $js_cmd .= ' --compilation_level '.$this->_mode;
        foreach ($files as $file) {
            $file_final = $base_dir.'/'.$file;
            if (!file_exists($file_final)) {
                $this->_log_error('NOT_EXIST', "Source \"{$file_final}\" does not exist.");
                return;
            }
            $js_cmd .= ' --js "'.realpath($file_final).'"';
        }
        
        // externs
        $js_cmd .= $this->_externs;
        // output_file
        if (!$output_file) {
            $path = pathinfo($files[count($files)-1]);
            $output_file = $path['dirname'].'/'.$path['filename'].'.min.js';
        }
        $output_file_final = $output_dir.'/'.$output_file;
        $path = pathinfo($output_file_final);
        if ( !file_exists($path['dirname']) ) {
            mkdir($path['dirname'], null, true);
        }
        $js_cmd .= ' --js_output_file "'.$output_file_final.'"';
        // options
        if (count($this->_options) > 0) {
            $js_cmd .= ' '.implode(' ', $this->_options);
        }
        
        // Run ccompiler.
        $result = $this->_exec($js_cmd);
        
        // Write results
        if ($result['err'] !== '') {
            $this->err_count++;
            $this->write_file(
                $this->_work_dir.'/cc_errors/'.
                        $this->get_flat_name($output_file).'.log',
                $result['err']
            );
            $this->_log_error('CC_ERRORS', $output_file);
        } else {
            $this->ok_count++;
            $this->_log('cc_done', 'compile OK; '.$output_file);
        }
        if ($result['out'] !== '') {
            $this->write_file(
                $this->_work_dir.'/logs/cc_out/'.
                        $this->get_flat_name($output_file).'.log',
                $result['out']
            );
        }
    }

    // UTILITIES //
    private function check_exists($procedure_name, $file) {
        if (!file_exists($file)) {
            $msg = "\"{$file}\" not exist.";
            $this->_cancel($procedure_name, "\"{$file}\" not exist.");
            exit;
        }
    }
    private function _exec($cmd) {
        echo '.'; // So see is working.
        $this->_log('logs/cmd_exec', $cmd);
        $process = proc_open(
            $cmd, 
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            ),
            $pipes
        ); 
        $responses = array(
            'out' => stream_get_contents($pipes[1]),
            'err' => stream_get_contents($pipes[2])
        );
        fclose($pipes[0]); 
        fclose($pipes[1]); 
        fclose($pipes[2]); 
        proc_close($process);
        return $responses;
    }
    private function _cancel($procedure_name, $msg) {
        $msg = "Error on \"->{$procedure_name}\": \n\t".$msg;
        $can_msg = $msg."\n** Process canceled! **";
        echo "\n".$can_msg."\n\n";
        $this->_log('cc_errors', $can_msg);
        $this->_log('cc_done', $can_msg);
        //throw new Exception($msg);
        exit(1);
    }
    private function _log_error($type_error, $step) {
        $this->_log('cc_errors', " {$type_error}; {$step}");
        $this->_log('cc_done', " {$type_error}; {$step}");
    }
    private function _log($log_name, $step) {
        $datetime = new DateTime();
        $this->write_file(
            $this->_work_dir."/{$log_name}.log", 
            $datetime->format('Y-m-d H:i:s').'->; '.$step."\n");
    }
    private function write_file($file_path, $content) {
        $path = pathinfo($file_path);
        if (!file_exists($path['dirname'])) {
            mkdir($path['dirname'], null, true);
        }
        file_put_contents($file_path, $content, FILE_APPEND | LOCK_EX);
    }
    
    // DIR UTILITIES //
    private function filter_dir($includes, $excludes = null) {
        /*
        $directory = $this->clear_dir_name($subdir);
        $directory = ($directory !=='') ? $this->_repo_dir.'/'.$directory :
                                          $this->_repo_dir; */
                                          
        if (!is_array($includes)) {
            $includes = array($includes);
        }
        
        $directory =$this->_repo_dir;
        $files = array();
        $p_folder = strlen($this->_repo_dir)+1;
        $iterator = new RecursiveDirectoryIterator($directory);
        foreach (new RecursiveIteratorIterator($iterator) as
                        $filename=>$fileinfo) {
            if (!$fileinfo->isFile()) { continue; }
            $filename_c = substr(str_replace("\\", '/', $filename), $p_folder);
            // Exclude
            if ($excludes) {
                if (!is_array($excludes)) {
                    $excludes = array($excludes);
                }
                $excluded = false;
                foreach ($excludes as $excl) {
                    if (preg_match($excl, $filename_c)) {
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded)  { continue; }
            }
            
            // Include
            $included = false;
            foreach ($includes as $inc) {
                if (preg_match($inc, $filename_c)) {
                    $included = true;
                    break;
                }
            }
            if (!$included) { continue; }
            
            // Push
            array_push($files, substr($filename, $p_folder));
        }
        return $files;
    }
    private function empty_tree($dir) {
        if (!is_dir($dir)) {
            // Create if not exist 
            mkdir($dir, null, true);
            return;
        }
        if ($dir === '.' || $dir === '..') {
            throw new Exception("Error: \"{$dir}\" can't bee deleted.");
            exit;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (substr($file, 0, 1) === '.') {
                throw new Exception(
                    "Error on delete \"{$dir}/{$file}\": As a precaution unnamed files or dirs can't bee deleted.");
                exit;
            }
            if (is_dir("{$dir}/{$file}")) {
                $this->empty_tree("{$dir}/{$file}");
                try {
                    rmdir("{$dir}/{$file}");
                } catch (Exception $e) {}
            } else {
                unlink("{$dir}/{$file}");
            }
        }
    }
}
?>

