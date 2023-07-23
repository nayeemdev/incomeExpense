<?php
// format add thousand separator and define 
function format_amount($number) {
    return number_format($number, config('setting.decimal_digit'),".",",");
}

//return array of period for the use of dropdown,
//formated as yyyy-mm
function list_period($until) {
    $month = date("m")*1;
    $year = date("Y")*1;

    //TODO: get the earliest entry. Assign to $last_year & $last_month
    //      or, assign $last_year & $last_month as param
    $last_year = substr($until,0,4)*1;
    $last_month = substr($until,4,2)*1;

    do {
        $period = "$year-".str_pad($month,2,'0',STR_PAD_LEFT);
        $result[$period] = date("Y M", strtotime($period.'-01'));
        $month--;
        if($month==0) {
            $month = 12;
            $year--;
        }
    }while($year>=$last_year && $month>=$last_month);

    return $result;
}