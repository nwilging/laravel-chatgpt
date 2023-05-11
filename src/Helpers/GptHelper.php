<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGpt\Helpers;

class GptHelper
{
    public function utf8Encode(string $value): string
    {
        $value .= $value;
        $len = strlen($value);

        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $value[$i] < "\x80": $value[$j] = $value[$i]; break;
                case $value[$i] < "\xC0": $value[$j] = "\xC2"; $value[++$j] = $value[$i]; break;
                default: $value[$j] = "\xC3"; $value[++$j] = chr(ord($value[$i]) - 64); break;
            }
        }

        return substr($value, 0, $j);
    }

    public function dictZip(iterable $x, $y): array
    {
        $result = [];
        $count = 0;

        foreach ($x as $i) {
            if (isset($i[1]) && isset($i[0])) {
                $result[$i[0] . ',' . $i[1]] = $count;
                $count++;
            }
        }

        return $result;
    }

    public function varFilter($var): bool
    {
        return ($var !== null && $var !== false && $var !== '');
    }

    public function uniChr($c): int
    {
        if (ord($c[0]) >= 0 && ord($c[0]) <= 127) {
            return ord($c[0]);
        }

        if (ord($c[0]) >= 192 && ord($c[0]) <= 223) {
            return (ord($c[0])-192)*64 + (ord($c[1])-128);
        }

        if (ord($c[0]) >= 224 && ord($c[0]) <= 239) {
            return (ord($c[0])-224)*4096 + (ord($c[1])-128)*64 + (ord($c[2])-128);
        }

        if (ord($c[0]) >= 240 && ord($c[0]) <= 247) {
            return (ord($c[0])-240)*262144 + (ord($c[1])-128)*4096 + (ord($c[2])-128)*64 + (ord($c[3])-128);
        }

        if (ord($c[0]) >= 248 && ord($c[0]) <= 251) {
            return (ord($c[0])-248)*16777216 + (ord($c[1])-128)*262144 + (ord($c[2])-128)*4096 + (ord($c[3])-128)*64 + (ord($c[4])-128);
        }

        if (ord($c[0]) >= 252 && ord($c[0]) <= 253) {
            return (ord($c[0])-252)*1073741824 + (ord($c[1])-128)*16777216 + (ord($c[2])-128)*262144 + (ord($c[3])-128)*4096 + (ord($c[4])-128)*64 + (ord($c[5])-128);
        }

        if (ord($c[0]) >= 254 && ord($c[0]) <= 255) {
            return 0;
        }

        return 0;
    }

    public function getBpe(string $token, array $bpeRanks, array &$cache): string
    {
        if(array_key_exists($token, $cache)) {
            return $cache[$token];
        }

        $word = $this->gptSplit($token);
        $init_len = count($word);
        $pairs = $this->getPairs($word);

        if(empty($pairs)) {
            return $token;
        }

        while (true) {
            $minPairs = array();
            foreach($pairs as $pair)
            {
                if(array_key_exists($pair[0] . ','. $pair[1], $bpeRanks)) {
                    $rank = $bpeRanks[$pair[0] . ','. $pair[1]];
                    $minPairs[$rank] = $pair;
                } else {
                    $minPairs[10e10] = $pair;
                }
            }

            ksort($minPairs);
            $min_key = array_key_first($minPairs);

            foreach($minPairs as $mpi => $mp) {
                if($mpi < $min_key) {
                    $min_key = $mpi;
                }
            }

            $bigram = $minPairs[$min_key];
            if(!array_key_exists($bigram[0] . ',' . $bigram[1], $bpeRanks)) {
                break;
            }

            $first = $bigram[0];
            $second = $bigram[1];

            $new_word = array();
            $i = 0;

            while ($i < count($word))
            {
                $j = $this->indexOf($word, $first, $i);
                if ($j === -1) {
                    $new_word = array_merge($new_word, array_slice($word, $i, null, true));
                    break;
                }

                if($i > $j) {
                    $slicer = array();
                } elseif($j == 0) {
                    $slicer = array();
                } else {
                    $slicer = array_slice($word, $i, $j - $i, true);
                }

                $new_word = array_merge($new_word, $slicer);
                if(count($new_word) > $init_len) {
                    break;
                }

                $i = $j;
                if ($word[$i] === $first && $i < count($word) - 1 && $word[$i + 1] === $second) {
                    array_push($new_word, $first . $second);
                    $i = $i + 2;
                } else {
                    array_push($new_word, $word[$i]);
                    $i = $i + 1;
                }
            }

            if($word == $new_word) {
                break;
            }

            $word = $new_word;
            if (count($word) === 1) {
                break;
            } else {
                $pairs = $this->getPairs($word);
            }
        }

        $word = implode(' ', $word);
        $cache[$token] = $word;
        return $word;
    }

    public function gptSplit(string $str, int $len = 1): array
    {
        $arr = [];
        $length = mb_strlen($str, 'UTF-8');
        for ($i = 0; $i < $length; $i += $len) {
            $arr[] = mb_substr($str, $i, $len, 'UTF-8');
        }

        return $arr;
    }

    public function getPairs(array $word): array
    {
        $pairs = [];
        $prev_char = $word[0];
        for ($i = 1; $i < count($word); $i++) {
            $pairs[] = [$prev_char, $word[$i]];
            $prev_char = $word[$i];
        }

        return $pairs;
    }

    public function indexOf(array $array, $searchElement, int $fromIndex): int
    {
        $index = 0;
        foreach($array as $index => $value)
        {
            if($index < $fromIndex) {
                $index++;
                continue;
            }

            if($value == $searchElement) {
                return $index;
            }

            $index++;
        }

        return -1;
    }
}
