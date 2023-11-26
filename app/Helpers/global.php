<?php
    // format add thousand separator and define 
    function format_amount($number) {
        return number_format($number, config('setting.decimal_digit'),".",",");
    }