<?

abstract class LogLevel {
    const ALL = 9;
    const TEST = 8;
    const TRACE = 7;
    const COMMUNICATION = 6;
    const DEBUG = 5;
    const INFO = 4;
    const WARN = 3;
    const ERROR = 2;
    const FATAL = 1;
}

abstract class VARIABLE {
    const TYPE_BOOLEAN = 0;
    const TYPE_INTEGER = 1;
    const TYPE_FLOAT = 2;
    const TYPE_STRING = 3;
}


trait TRONITY_COMMON {


    protected function CalcDuration_ms(float $timeStart) {
        $duration =  microtime(true)- $timeStart;
        return round($duration*1000,2);
    }	

}


?>