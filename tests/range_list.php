<?php
// Test RangeList class

use Collections\RangeList;

require_once "../autoload.php";

$priorityCheck = function($a, $b) {
	return ($a[3] >= $b[3]) ? 0 : 1;
};

$list = new RangeList();
$list->add([0, 10, 'foo 1', 1], true, $priorityCheck);
$list->add([3, 6, 'bar 1', 3], true, $priorityCheck);
$list->add([4, 9, 'baz 1', 2], true, $priorityCheck);
echo "List 1\n";
print_r($list->ranges);

$list->sort();
echo "\nList 1 sorted by default function\n";
print_r($list->ranges);

$list->sort(function($a, $b) {
	return strcmp($a[2], $b[2]);
});
echo "\nList 1 sorted by custom function\n";
print_r($list->ranges);

echo "\nFound entries in list 1 (search range from 5 to 8)\n";
print_r($list->find(5, 8));

$list2 = new RangeList();
$list2->add([4, 7, 'foo 2']);
$list2->add([9, 12, 'bar 2']);
echo "\nList 2\n";
print_r($list2->ranges);

echo "\nIntersections between lists 1 and 2\n";
print_r($list->findIntersections($list2));
