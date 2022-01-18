<?php
/**
 * TODO: 
 *   * Summary of 'NOT_EXIST' and others errors...
 *   * Doc public functions
 *   * ccompiler: Distinguish between errors and warnings .
 */

class CheckCode{
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
    private $_repo_dir = null;
    private $_work_dir;
    private $err_count = 0;
    private $ok_count = 0;
    
    public static $PHP_CODE = 1;
    public static $JS_CODE = 2;
    
    function __construct($repository_dir, $work_dir) {
        $this->_repo_dir = $this->clear_dir_name($repository_dir);
        $this->_work_dir = $this->clear_dir_name($work_dir);
        $this->empty_tree($this->_work_dir);
        
        echo "CheckCode({$repository_dir}, {$work_dir})\n\n";
        if (is_dir($this->_repo_dir)) {
            $startText = "Start:\n" .
                "  from:    {$this->_repo_dir}/\n" .
                "  working: {$this->_work_dir}/\n";
        } else {
            $startText = "ERROR: Directory '{$this->_repo_dir}/' does not exist!\n";
            $this->_repo_dir = null;
            $this->_cancel('__construct()', $startText);
        }
        echo $startText . "\n";
        $this->_log('_errors', $startText);
        $this->_log('_done', $startText);
        
    }
    
    function __destruct() {
        $end_msg = "\n---- END ----\n\n"
            . "Final summary:\n"
            . "    [ERRORS] = ".$this->err_count."\n"
            . "    [right_end] = ".$this->ok_count;
        echo "\n" . $end_msg . "\nEnd\n";
        $this->_log('_errors', $end_msg);
        $this->_log('_done', $end_msg);
    }
    
    public function php_check($options) {
        if ($this->_repo_dir && is_array($options)) {
            $this->mapFiles('_php_check', $options);
        }
        return $this;
    }
    
    // Verify js sintax using cc
    public function cc_checkJs($options) {
        if ($this->_repo_dir && is_array($options)) {
            $this->mapFiles('_cc_checkJs', $options);
        }
        return $this;
    }
    
    // Ccompile JS from HTML //
    public function cc_extractJs($options) {
        if ($this->_repo_dir && is_array($options)) {
            $this->mapFiles('_cc_extractJs', $options);
        }
        return $this;
    }
    
    public function cc_minimizeJs($options) {
        if ($this->_repo_dir && is_array($options)) {
            $this->_log('_done', "\n------\nMethod: cc_minimizeJs()");
            if (isset($options['files'])) {
                $files = (array)$options['files'];
                $outputFile = isset($options['output-file']) ? $options['output-file'] : null;
                $this->ccompile($files, null, $outputFile);
            }
        }
        return $this;
    }

    // Closure COMPILER OPTIONS //
    public function set_cc_jar($fileName) {
        $this->check_exists('set_cc_jar()', $fileName);
        $this->_cc_jar = $fileName;
        return $this;
    }
    public function set_cc_externs($files = null, $base_dir = null) {
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
                $this->check_exists('set_cc_externs()', $file_final);
                $this->_externs .= ' --externs "'.realpath($file_final).'"';
            }
        }
        return $this;
    }
    public function set_cc_options($options = null) {
        if ($options) {
            $this->_options = $options;
        } else {
            $this->_options = array();
        }
        return $this;
    }
    
    // File Tools
    protected function mapFiles($functionName, $options = null) 
    {
        // Default options
        $options = array_merge($options, array('from-code' => self::$PHP_CODE));
        $this->_log('_done', "\n------\nMethod: " . substr($functionName, 1) . '()');
        if (isset($options['files'])) {
            $files = (array)$options['files'];
            foreach ($files as $f) {
                $this->_log('_done', "\n  File: {$f}");
                call_user_func_array(array($this, $functionName), array($f, $options));
            }
        }
        if (isset($options['file-pattern'])) {
            $pattern = $options['file-pattern'];
            $excluding = isset($options['excluded-patterns']) ? 
                (array)$options['excluded-patterns'] :
                '';
            $this->_log('_done',
                "\n  Pattern of files: '{$pattern}'" . (
                    $excluding ? 
                        ",\n  excluding files:\n    '" .
                        implode("',\n    '", $excluding) . "'." :
                        ''
                )
            );
            $files = $this->filter_dir($pattern, $excluding);
            foreach ($files as $f) {
                call_user_func_array(array($this, $functionName), array($f, $options));
            }
        }
    }

    private function clear_dir_name($directory) {
        $directory_c = str_replace("\\", '/', $directory);
        // Clear end base dir
        if (substr($directory_c, -1) === '/') {
            $directory = substr($directory, 0, -1);
        }
        return $directory;
    }
    
    private function get_flat_name($fileName) {
        return str_replace(
            array('/',"\\"), array(' ',' '),
            $fileName
        );
    }
    
    private function check_exists($procedure_name, $file) {
        if (!file_exists($file)) {
            $msg = "\"{$file}\" not exist.";
            $this->_cancel($procedure_name, "\"{$file}\" not exist.");
            exit;
        }
    }
    
    // Lint PHP //
    private function _php_check($file)
    {
        $fileName = $this->_repo_dir . '/' . $file;
        if (!file_exists($fileName)) {
            $this->_log_error('NOT_EXIST', $fileName);
            echo 'f';
            return;
        }
        $cmd = 'php -l ' . $fileName;
        $result = $this->_exec($cmd, $file);
        // Write results
        if ($result['code']) {
            echo 'e';
            $this->_log_error('PHP_ERRORS', $file);
            $this->write_file(
                $this->_work_dir. '/PHP_errors/' .
                        $this->get_flat_name($file) . '.log',
                $result['out']
            );
        } else {
            $this->ok_count++;
            $this->_log('_done', 'PHP Lint Ok; ' . $file);
        }
    }
    
    // Extract js from HTML    
    private function _cc_checkJs($fileName) {
        $this->ccompile(
            $fileName,
            null, 
            'logs/_check_js_min/' . $this->get_flat_name($fileName). '.min.js',
            $this->_work_dir
        );
    }
    
    private function _cc_extractJs($fileName, $options) {
        $startCode = $options['from-code'] ? 2 : 0;
        try {
            $r = $this->extract_js_write($fileName, $startCode);
        } catch (Exception $e) {
            $this->_log_error('EXTRACT_FAILURE', $fileName);
            echo 'f';
            return;
        }
        if ($r) { 
            $this->ccompile(
                "{$r['temp_folder']}/{$r['fileName']}", 
                $this->_work_dir,
                'logs/_extract_js_min/' . $this->get_flat_name($r['fileName']).'.min.js'
            );
        }
    }
    
    private function extract_js_write($fileName, $startCode)
    {
        $this->_log('logs/_extract_js', $fileName);
        $f = $this->extractJsCode(
            $this->_repo_dir.'/'.$fileName,
            $startCode
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
        $fileName .='.js';
        $temp_folder = 'logs/_extract_js';
        $f_name ="/{}";
        $this->write_file(
            "{$this->_work_dir}/{$temp_folder}/{$fileName}",
            $code
        );
        return array(
            'temp_folder' => $temp_folder,
            'fileName' => $fileName            
        );
    }
    
    private function extractJsCode($fileName, $startCode) {
        if (!file_exists($fileName)) {
            $this->_log_error('NOT_EXIST', $fileName);
            return null;
        }
        $handle = @fopen($fileName, "r");
        $codes = array();
        $C_START = '<script';
        $C_END = '</script>';
        $line = 0;
        $type_code = $startCode; // 0=no, 1=label, 2=code, 3=internal PHP
        if ($startCode === 2) {
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
                case -1: // php
                    $s_pos = stripos($buffer, '?>', $s_pos);
                    if ($s_pos !== false) { // end PHP
                        $s_pos += 2;
                        $type_code = 0;
                    }
                    break;
                case 0: // html
                    $s_pos_AUX = stripos($buffer, '<?', $s_pos);
                    $s_pos = stripos($buffer, $C_START, $s_pos);
                    if ($s_pos_AUX !== false && 
                                ($s_pos === false || $s_pos_AUX < $s_pos) ) {
                        $type_code = -1;
                        $s_pos = $s_pos_AUX + 2;
                        break;
                    }
                    if ($s_pos !== false) {
                        $type_code = 1;
                        $s_pos_label = $s_pos + strlen($C_START);
                    }
                    break;
                case 1: // <script ...
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
                case 2: // js
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
                case 3: // php into js
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
                    "Error: fgets() is not false at the end of ::split() reading file: \"{$fileName}\".");
                exit; 
            } elseif ($type_code !== $startCode) {
                throw new Exception(
                    "Error: `script` is not closed at end of file: \"{$fileName}\". Code_type={$type_code}.");
                exit;
            }
            if ($startCode === 2) {
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
    
    // Closure Compiler //
    private function ccompile(
        $files, 
        $base_dir = null, 
        $output_file = null,
        $output_dir = null
    ) {
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
                echo 'f';
                return;
            }
            $js_cmd .= ' --js "' . realpath($file_final).'"';
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
            mkdir($path['dirname'], 0777, true);
        }
        $js_cmd .= ' --js_output_file "'.$output_file_final.'"';
        // options
        if (count($this->_options) > 0) {
            $js_cmd .= ' '.implode(' ', $this->_options);
        }
        
        // Run js ccompiler.
        $result = $this->_exec($js_cmd, $output_file);
        
        // Write results
        if ($result['err'] !== '') {
            echo 'e';
            $this->_log_error('CC_ERRORS', $output_file);
            $this->write_file(
                $this->_work_dir . '/logs/cc_errors/' .
                        $this->get_flat_name($output_file) . '.log',
                $result['err']
            );
        } else {
            $this->ok_count++;
            $this->_log('_done', 'cc-js Ok; '.$output_file);
        }
        if ($result['out'] !== '') {
            $this->write_file(
                $this->_work_dir . '/logs/cc_out/' . 
                    $this->get_flat_name($output_file) . '.log',
                $result['out']
            );
        }
    }

    // Execution utilities
    private function _exec($cmd, $output_file) {
        echo '.'; // So see is working.
        $this->_log('logs/_executions', $cmd);
        $process = proc_open(
            $cmd, 
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            ),
            $pipes
        ); 
        $result = array();
        $result['err'] = stream_get_contents($pipes[2]); // Important, first read err
        // See: https://stackoverflow.com/questions/31194152/proc-open-hangs-when-trying-to-read-from-a-stream
        $result['out'] = stream_get_contents($pipes[1]);
        
        fclose($pipes[0]); 
        fclose($pipes[1]); 
        fclose($pipes[2]);
        $result['code'] = proc_close($process);
        return $result;
    }
    
    // Log and write utilities
    private function _cancel($procedure_name, $msg) {
        $this->err_count++;
        $msg = "Error on \"->{$procedure_name}\": \n\t".$msg;
        $can_msg = $msg."\n** Process canceled! **";
        echo "\n".$can_msg."\n\n";
        $this->_log('_errors', $can_msg);
        $this->_log('_done', $can_msg);
        //throw new Exception($msg);
        exit(1);
    }
    
    private function _log_error($type_error, $step) {
        $this->err_count++;
        $this->_log('_errors', " {$type_error}; {$step}");
        $this->_log('_done', " {$type_error}; {$step}");
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
            mkdir($path['dirname'], 0777, true);
        }
        file_put_contents($file_path, $content, FILE_APPEND | LOCK_EX);
    }
    
    private function empty_tree($dir) {
        if (!is_dir($dir)) {
            // Create if not exist 
            mkdir($dir, 0777, true);
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
    
    // Directory utilities
    private function filter_dir($pattern, $excluding = null) {
        /*
        $directory = $this->clear_dir_name($subdir);
        $directory = ($directory !=='') ? $this->_repo_dir.'/'.$directory :
                                          $this->_repo_dir; */
                                          
        if (!is_array($pattern)) {
            $pattern = array($pattern);
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
            if ($excluding) {
                $excluded = false;
                foreach ((array)$excluding as $excl) {
                    if (preg_match($excl, $filename_c)) {
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded)  { continue; }
            }
            
            // Include
            $included = false;
            foreach ($pattern as $inc) {
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
    
}
