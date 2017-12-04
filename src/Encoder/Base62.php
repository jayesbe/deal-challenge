<?php
namespace App\Encoder;

/**
 * https://gist.github.com/ngfw/4684951
 * 
 * Base62: A class to convert a number from any base between 2-62 to any other base between 2-62
 * It doesn't use BC Math functions so works without the use of BC Math library.
 * It uses the native base_convert functions when the base is below 36 for faster execution.
 * The output number is backward compatible with the native base_convert function.
 *
 * Author : Lalit Patel
 * Website: http://www.lalit.org/lab/base62-php-convert-number-to-base-62-for-short-urls
 * License: Apache License 2.0
 *          http://www.apache.org/licenses/LICENSE-2.0
 * Version: 0.1 (08 December 2011)
 *
 * Usage:
 *       $converted_num = Base62::convert($number, $from_base, $to_base);
 */
class Base62 {
    /**
     * Converts a number/string from any base between 2-62 to any other base from 2-62
     * @param mixed $number
     * @param integer $from_base
     * @param integer $to_base
     * @return $conveted_number.
     */
    public static function  convert($number, $from_base=10, $to_base=62) {
        if($to_base > 62 || $to_base < 2) {
            trigger_error("Invalid base (".he($to_base)."). Max base can be 62. Min base can be 2.", E_USER_ERROR);
        }
        //OPTIMIZATION: no need to convert 0
        if("{$number}" === '0') {
            return 0;
        }

        //OPTIMIZATION: if to and from base are same.
        if($from_base == $to_base){
            return $number;
        }

        //OPTIMIZATION: if base is lower than 36, use PHP internal function
        if($from_base <= 36 && $to_base <= 36) {
            // for lower base, use the default PHP function for faster results
            return base_convert($number, $from_base, $to_base);
        }

        // char list starts from 0-9 and then small alphabets and then capital alphabets
        // to make it compatible with eixisting base_convert function
        $charlist = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        if($from_base < $to_base) {
            // if converstion is from lower base to higher base
            // first get the number into decimal and then convert it to higher base from decimal;

            if($from_base != 10){
                $decimal = self::convert($number, $from_base, 10);
            } else {
                $decimal = intval($number);
            }

            //get the list of valid characters
            $charlist = substr($charlist, 0, $to_base);

            if($number == 0) {
                return 0;
            }
            $converted = '';
            while($number > 0) {
                $converted = $charlist{($number % $to_base)} . $converted;
                $number = floor($number / $to_base);
            }
            return $converted;
        } else {
            // if conversion is from higher base to lower base;
            // first convert it into decimal and the convert it to lower base with help of same function.
            $number = "{$number}";
            $length = strlen($number);
            $decimal = 0;
            $i = 0;
            while($length > 0) {
                $char = $number{$length-1};
                $pos = strpos($charlist, $char);
                if($pos === false){
                    trigger_error("Invalid character in the input number: ".($char), E_USER_ERROR);
                }
                $decimal += $pos * pow($from_base, $i);
                $length --;
                $i++;
            }
            return self::convert($decimal, 10, $to_base);
        }
    }
}
