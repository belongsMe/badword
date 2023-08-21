<?php
namespace TextFilter\Badword;


class Badword
{
    protected $dict;
    protected $dictFile;
    protected $config = [
        'host'=>'127.0.0.1',
        'port'=>'6379',
        'password'=>'',
        'select'=>0,
        'expire'=>0,
    ];

    /**
     * @param string $dictFile Dictionary file path, one sentence per line
     */
    public function __construct($config = [])
    {
        if(!empty($config['host'])){
            $this->config['host'] = $config['host'];
        }
        if(!empty($config['port'])){
            $this->config['port'] = $config['port'];
        }
        if(!empty($config['password'])){
            $this->config['password'] = $config['password'];
        }
        if(!empty($config['select'])){
            $this->config['select'] = $config['select'];
        }
        if(!empty($config['expire'])){
            $this->config['expire'] = $config['expire'];
        }

    }

    /**
     * set dictionary file
     * @param string $dictFile
     */
    public function setDictFile($dictFile = ''){
        $this->dictFile = $dictFile;
    }

    /**
     * set dictionary data from file
     * @throws \Exception
     */
    protected function setDictFromFile(){
        $this->dict = [];
        $handle = @fopen($this->dictFile, "r");
        if (!is_resource($handle)) {
            throw new \Exception("Dictionary file cannot be opened");
        }

        while (!feof($handle)) {
            $line = fgets($handle);
            if (empty($line)) {
                continue;
            }

            $this->addWords(trim($line));
        }

        fclose($handle);
    }

    /**
     * Load dictionary data from a file and construct a trie tree
     */
    public function loadDataFromFile($cache = true)
    {
        if (!file_exists($this->dictFile)) {
            $this->dictFile = __DIR__.DIRECTORY_SEPARATOR.'dict'.DIRECTORY_SEPARATOR.'dict.txt';
        }

        if($cache === false) {
            $this->setDictFromFile();
        }else{
            $redis = new \Redis();
            $redis->connect($this->config['host'], $this->config['port']);
            $redis->auth($this->config['password']);
            $redis->select($this->config['select']);
            $cacheKey = __CLASS__ . "_" . md5($this->dictFile);
            $cache_data = $redis->get($cacheKey);
            if (!empty($cache_data)) {
                $this->dict = json_decode($cache_data,true);
                return;
            }

            $this->setDictFromFile();

            $redis->set($cacheKey,json_encode($this->dict));
            if($this->config['select']){
                $redis->expire($cacheKey,$this->config['expire']);
            }
        }
    }

    /**
     * load data
     * @param array $data
     * @throws \Exception
     */
    public function loadData($data = [],$cache = true){
        if(!$data){
            throw new \Exception("Dictionary data cannot be empty");
        }

        if($cache === false) {
            $this->dict = [];
            foreach ($data as $word){
                $this->addWords(trim($word));
            }
        }else{
            $redis = new \Redis();
            $redis->connect($this->config['host'], $this->config['port']);
            $redis->auth($this->config['password']);
            $redis->select($this->config['select']);
            $cacheKey = __CLASS__ . "_dict_data";
            $cache_data = $redis->get($cacheKey);
            if (!empty($cache_data)) {
                $this->dict = json_decode($cache_data,true);
                return;
            }

            foreach ($data as $word){
                $this->addWords(trim($word));
            }

            $redis->set($cacheKey,json_encode($this->dict));
            if($this->config['select']){
                $redis->expire($cacheKey,$this->config['expire']);
            }
        }
    }

    /**
     * Split text (note that ascii occupies 1 byte, unicode...)
     * @param $str
     * @return array|false|string[]
     */
    protected function splitStr($str)
    {
        return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Add a statement to the dict tree
     * @param $words
     */
    protected function addWords($words)
    {
        $wordArr = $this->splitStr($words);
        $curNode = &$this->dict;
        foreach ($wordArr as $char) {
            if (!isset($curNode)) {
                $curNode[$char] = [];
            }

            $curNode = &$curNode[$char];
        }

        // Mark the complete path to the current node as a sensitive word
        $curNode['end'] = true;
    }

    /**
     * filter data
     * @param $str
     * @param string $replace
     * @param int $skipDistance
     * @return string
     */
    public function filter($str, $replace = '*', $skipDistance = 0)
    {
        $maxDistance = max($skipDistance, 0) + 1;
        $strArr = $this->splitStr($str);
        $length = count($strArr);
        for ($i = 0; $i < $length; $i++) {
            $char = $strArr[$i];

            if (!isset($this->dict[$char])) {
                continue;
            }

            $curNode = &$this->dict[$char];
            $dist = 0;
            $matchIndex['start'] = $i;
            $matchIndex['end'] = false;
            for ($j = $i + 1; $j < $length && $dist < $maxDistance; $j++) {
                if (!isset($curNode[$strArr[$j]])) {
                    $dist ++;
                    continue;
                }

                $curNode = &$curNode[$strArr[$j]];

                if(isset($curNode['end'])){
                    $matchIndex['end'] = $j;
                }
            }

            // 匹配
            if (!empty($matchIndex['end'])) {
                for ($m = $matchIndex['start']; $m <= $matchIndex['end']; $m ++) {
                    $strArr[$m] = $replace;
                }
                $i = $matchIndex['end'];
            }
        }
        return implode('', $strArr);
    }
    /**
     * Check for prohibited words
     * @param $str
     * @param int $skipDistance
     * @return bool
     */
    public function match_badword($str,$skipDistance = 0)
    {
        $maxDistance = max($skipDistance, 0) + 1;
        $strArr = $this->splitStr($str);
        $length = count($strArr);
        for ($i = 0; $i < $length; $i++) {
            $char = $strArr[$i];

            if (!isset($this->dict[$char])) {
                continue;
            }

            $curNode = &$this->dict[$char];
            $dist = 0;
            $matchIndex = [$i];
            for ($j = $i + 1; $j < $length && $dist < $maxDistance; $j++) {
                if (!isset($curNode[$strArr[$j]])) {
                    $dist ++;
                    continue;
                }

                $matchIndex[] = $j;
                $curNode = &$curNode[$strArr[$j]];
                if (isset($curNode['end'])) {
                    return true;
                }
            }
        }
        return false;
    }

}