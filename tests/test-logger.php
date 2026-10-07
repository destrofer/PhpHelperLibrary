<?php

use Destrofer\Debugging\Logger;

include __DIR__ . "/../vendor/autoload.php";

$logger = new Logger(__DIR__ . "/test-data/test.log", true, Logger::LEVEL_WARNING);

// Run level limit test
$logger->verbose("{:GRAY:}Verbose{:RESET:} message (shouldn't be visible)");
$logger->debug("{:B-CYAN:}Debug{:RESET:} message (shouldn't be visible)");
$logger->notice("{:B-WHITE:}Notice{:RESET:} message (shouldn't be visible)");
$logger->warning("{:B-YELLOW:}Warning{:RESET:} message (should be visible)");
$logger->error("{:B-RED:}Error{:RESET:} message (should be visible)");

// Run visual effect test (support depends on terminal, where it is run)
$logger->log(100, "{:BOLD:}Bold{:RESET:}");
$logger->log(100, "{:DIM:}Dim{:RESET:}");
$logger->log(100, "{:ITALIC:}Italic{:RESET:}");
$logger->log(100, "{:UNDERLINE:}Underline{:RESET:}");
$logger->log(100, "{:BLINK:}Blink{:RESET:}");
$logger->log(100, "{:INVERT:}Reverse{:RESET:}");
$logger->log(100, "{:STRIKE:}Strikethrough{:RESET:}");
$logger->log(100, "{:BG-YELLOW:}{:BLACK:}Yellow bg with black text{:RESET:}");
$logger->log(100, "{:BG-B-CYAN:}{:RED:}{:BLINK:}Bright cyan bg with blinking red text{:RESET:}");
