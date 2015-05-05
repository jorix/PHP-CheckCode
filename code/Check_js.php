<?php
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
    }
    function __destruct() {
        $this->_log('cc_done',
            "---- END ---\n"
            ."- - - - - - -\n"
            ."Final totals:\n"
            ."    [CC_ERRORS] = ".$this->err_count."\n"
            ."    [right_end] = ".$this->ok_count."\n"
        );
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
    
    public function filter_dir($subdir, $include, $exclude) {
        $directory = $this->clear_dir_name($subdir);
        $directory = ($directory !=='') ? $this->_repo_dir.'/'.$directory :
                                          $this->_repo_dir;
        $files = array();
        $iterator = new RecursiveDirectoryIterator($directory);
        foreach (new RecursiveIteratorIterator($iterator) as
                        $filename=>$fileinfo) {
            if (!$fileinfo->isFile()) { continue; }
            $filename_c = str_replace("\\", '/', $filename);
            // Exclude dir
            $excluded = false;
            foreach ($exclude as $excl) {
                if (stripos($filename_c, $excl) !== false) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded)  { continue; }
            // Extension
            $included = false;
            foreach ($include as $ext) {
                if (stripos($filename, '.'.$ext) !== false) {
                    $included = true;
                    break;
                }
            }
            if (!$included) { continue; }
            // Push
            $p_folder = strlen($this->_repo_dir)+1;
            $p_name = strrpos($filename_c, '/')+1;
            array_push($files, array(
                'folder' => $this->clear_dir_name(
                    substr($filename, $p_folder, $p_name-$p_folder)
                ),
                'file' => substr($filename,$p_name)
            ));
        }
        return $files;
    }
    
    // COMPILE JS FROM HTML //
    private function get_flat_name($file_name) {
        return str_replace(
                    array('.','/',"\\"), array('_','-','-'),
                    $file_name
        );
    }
    public function ccompile_extract_js($file_name) {
        $this->ccompile(
            $this->extract_js_write($file_name), 
            $this->_work_dir,
            'temp/_min/'.$this->get_flat_name($file_name).'.min.js'
        );
    }
    private function extract_js_write($file_name) {
        $f = $this->extract_js_code($this->_repo_dir.'/'.$file_name);
        if (!$f || count($f) === 0) { return null; }
        
        $this->_log('logs/extract_js_write', $file_name);
        $code = '';
        $line = 1;
        foreach ($f as $item) {
             "{$item['start']}-{$item['end']}.js";
            $code .= str_repeat("\n", $item['start']-$line);
            $line = $item['end'];
            $code .= 
                " /* extract_js->Lines: {$item['start']}-{$item['end']} */ "
                .$item['text']
                ." /* extract_js->End lines: {$item['start']}-{$item['end']} */ ";
        }
        $f_name ="temp/{$file_name}.js";
        $this->write_file($this->_work_dir.'/'.$f_name, $code);
        return array($f_name);
        /*
        $folder = "temp/{$file_name}";
        $files = array();
        foreach ($f as $item) {
            $f_name = "{$item['start']}-{$item['end']}.js";
            $this->write_file(
                    $this->_work_dir.'/'.$folder.'/'.$f_name, $item['text']);
            array_push($files, $folder.'/'.$f_name);
        }
        return $files;
        */
    } 
    private function extract_js_code($file_name) {
        if (!file_exists($file_name)) {
            $this->_log_error('NOT_EXIST', $file_name);
            return null;
        }
        $handle = @fopen($file_name, "r");
        $codes = array();
        $C_START = '<script';
        $C_END = '</script>';
        $line = 0;
        $is_code = 0; // 0=no, 1=label, 2=code
        $code_start_line = 0;
        $s_pos = false;
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
                    $line++;
                }
                switch ($is_code) {
                case 0:
                    $s_pos = stripos($buffer, $C_START, $s_pos);
                    if ($s_pos !== false) {
                        $is_code = 1;
                        $s_pos_label = $s_pos + strlen($C_START);
                    }
                    break;
                case 1:
                    $s_pos = stripos($buffer, '>', $s_pos_label);
                    if ($s_pos !== false) {
                        $is_code = 2;
                        $s_pos_code = $s_pos + 1;
                        $code_start_line = $line;
                        $js_text = array();
                    }
                    break;
                case 2:
                    $s_pos = stripos($buffer, $C_END, $s_pos_code);                    
                    if ($s_pos === false) {
                        // end line
                        $line_text = substr($buffer, $s_pos_code);
                    } else {
                        $line_text = substr(
                                $buffer, $s_pos_code, $s_pos-$s_pos_code);
                        // end code
                        $s_pos += strlen($C_END);
                        $is_code = 0;                    
                    }
                    array_push($js_text, 
                        str_replace(array("\r","\n"),array('',''), $line_text));
                    if ($is_code !== 2) {
                        if (count($js_text) > 1 || $js_text[0] !== '') {
                            array_push($codes, array(
                                'text' => implode("\n", $js_text),
                                'start' => $code_start_line,
                                'end' => $line
                            ));
                        }
                    }
                    break;
                }
            }
            if (!feof($handle)) {
                throw new Exception(
                    "Error: fgets() is not false at the end of ::split() reading file: \"{$file_name}\".");
                exit; 
            } elseif ($is_code === 2) {
                throw new Exception(
                    "Error: `script` is not closed at end of file: \"{$file_name}\".");
                exit;
            }
            fclose($handle);
            return $codes;
        }
    }
    
    // COMPILER OPTIONS //
    public function set_cc_jar($file_name) {
        $this->check_exists('set_cc_jar()', $file_name);
        $this->_cc_jar = $file_name;
    }
    public function set_externs($files, $base_dir = null) {
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
                $this->_externs .= " --externs ".realpath($file_final);
            }
        }
        return $this;
    }
    public function set_options($options) {
        $this->_options = $options;
        return $this;
    }

    // THE COMPILER //
    public function ccompile(
                $files, $base_dir = null, 
                $output_file = null, $output_dir = null) {
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
            $js_cmd .= " --js ".realpath($base_dir.'/'.$file);
        }
        
        // externs
        $js_cmd .= $this->_externs;
        // output_file
        if (!$output_file) {
            $path = pathinfo($files[0]);
            $output_file = $path['dirname'].'/'.$path['filename'].'.min.js';
        }
        $output_file_final = $output_dir.'/'.$output_file;
        $path = pathinfo($output_file_final);
        if ( !file_exists($path['dirname']) ) {
            mkdir($path['dirname'], null, true);
        }
        $js_cmd .= ' --js_output_file '.$output_file_final;
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
}
?>

