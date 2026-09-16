<?php

/*
 *
 *      _    _
 *     / \  | | |_ __ _ _   _
 *    / _ \ | | __/ _` | | | |
 *   / ___ \| | || (_| | |_| |
 *  /_/   \_\_|\__\__,_|\__, |
 *                       |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Original work by the PocketMine Team.
 * https://www.pocketmine.net/
 *
 * @author Altay Team
 * @link https://github.com/altayofficial
 */

declare(strict_types=1);

use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

require dirname(__DIR__) . '/vendor/autoload.php';

$states = BlockStateDictionary::loadStatesFromPalette(Filesystem::fileGetContents(
	dirname(__DIR__) . '/vendor/altayofficial/bedrock-data/block_palette.nbt'
));
$deserializer = GlobalBlockStateHandlers::getDeserializer();

$counts = [];
$errors = [];
foreach($states as $state){
	$id = $state->getName();
	$counts[$id] = ($counts[$id] ?? 0) + 1;
	try{
		$deserializer->deserializeBlock($state);
	}catch(BlockStateDeserializeException $e){
		$errors[$id][$e->getMessage()] = ($errors[$id][$e->getMessage()] ?? 0) + 1;
	}
}

ksort($errors, SORT_STRING);
echo 'Palette states: ' . count($states) . PHP_EOL;
echo 'Palette block IDs: ' . count($counts) . PHP_EOL;
echo 'Unsupported/partial block IDs: ' . count($errors) . PHP_EOL;
echo 'Unsupported/partial states: ' . array_sum(array_map(
	static fn(array $messages) : int => array_sum($messages),
	$errors
)) . PHP_EOL;

foreach(Utils::stringifyKeys($errors) as $id => $messages){
	echo $id . ' (' . array_sum($messages) . '/' . $counts[$id] . ')' . PHP_EOL;
	foreach(Utils::stringifyKeys($messages) as $message => $count){
		echo "  {$count}x {$message}" . PHP_EOL;
	}
}

exit(count($errors) === 0 ? 0 : 1);
