<?php

/*
 *
 *      _    _ _
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

use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\PaletteItemDefinitions;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFINITIONS_PATH = 'generated/data/bedrock/item/PaletteItemDefinitions.php';
const ITEM_TYPE_IDS_PATH = 'src/item/ItemTypeIds.php';
const HEADER_TEMPLATE_PATH = 'build/codegen/templates/header.php';

$root = dirname(__DIR__);

$options = array_slice($argv, 1);
$write = in_array('--write', $options, true) || in_array('--update', $options, true);
if(in_array('--help', $options, true) || in_array('-h', $options, true)){
	echo <<<USAGE
Usage: php tools/audit-item-palette.php [--write]

Scans the bundled Bedrock item palette and determines which non-block items Altay cannot yet deserialize using its
dedicated implementations.

By default the tool only reports whether PaletteItemDefinitions is in sync and exits non-zero when it is stale, which
makes it suitable for use in CI.

  --write, --update   Regenerate PaletteItemDefinitions.php (and add any missing ItemTypeIds constants).

USAGE;
	exit(0);
}

/**
 * Builds a deserializer containing only Altay's dedicated item mappings, deliberately excluding the generic mappings
 * registered by PaletteItemRegistry. This is required to observe which item IDs genuinely lack an implementation.
 */
function buildDedicatedDeserializer() : ItemDeserializer{
	return new ItemDeserializer(GlobalBlockStateHandlers::getDeserializer(), false);
}

/**
 * @return list<string> the Bedrock IDs of non-block items with no dedicated deserializer, sorted
 */
function scanUnsupportedItems(ItemDeserializer $deserializer) : array{
	$blockItemIdMap = BlockItemIdMap::getInstance();
	$dedicatedMembers = VanillaItems::getAll();
	$missing = [];
	foreach((new ReflectionClass(ItemTypeNames::class))->getConstants() as $id){
		if($deserializer->getDeserializerForId($id) !== null){
			continue;
		}
		if($blockItemIdMap->lookupBlockId($id) !== null){
			continue;
		}
		if(isset($dedicatedMembers[mb_strtoupper(substr($id, 10))])){
			//a dedicated item with this registry name already exists, so we can't reuse the name for a placeholder
			continue;
		}
		$missing[] = $id;
	}
	sort($missing, SORT_STRING);
	return $missing;
}

/**
 * @param list<string> $missing
 * @return array<string, string> registry name => Bedrock ID
 */
function buildDefinitions(array $missing) : array{
	$definitions = [];
	foreach($missing as $id){
		$definitions[substr($id, 10)] = $id;
	}
	return $definitions;
}

/**
 * @param array<string, string> $definitions
 * @return array{added: list<string>, removed: list<string>, changed: list<string>, reordered: bool}
 */
function diffDefinitions(array $definitions, array $current) : array{
	$added = array_keys(array_diff_key($definitions, $current));
	$removed = array_keys(array_diff_key($current, $definitions));

	$changed = [];
	foreach($definitions as $name => $id){
		if(isset($current[$name]) && $current[$name] !== $id){
			$changed[] = $name;
		}
	}

	return [
		'added' => $added,
		'removed' => $removed,
		'changed' => $changed,
		'reordered' => array_keys($definitions) !== array_keys($current)
	];
}

/**
 * @param array<string, string> $definitions
 */
function generateDefinitionsFile(array $definitions, string $headerTemplatePath) : string{
	$content = rtrim(Filesystem::fileGetContents($headerTemplatePath), "\n") . "\n\n";
	$content .= "namespace pocketmine\\data\\bedrock\\item;\n\n";
	$content .= "/**\n";
	$content .= " * Bedrock item types which don't yet have complete dedicated implementations.\n";
	$content .= " *\n";
	$content .= " * @internal\n";
	$content .= " */\n";
	$content .= "final class PaletteItemDefinitions{\n";
	$content .= "\tprivate function __construct(){\n";
	$content .= "\t\t//NOOP\n";
	$content .= "\t}\n\n";
	$content .= "\t/**\n";
	$content .= "\t * registry name => Bedrock ID\n";
	$content .= "\t *\n";
	$content .= "\t * @var array<string, string>\n";
	$content .= "\t */\n";
	$content .= "\tpublic const ALL = [\n";
	foreach($definitions as $registryName => $id){
		$content .= "\t\t'{$registryName}' => '{$id}',\n";
	}
	$content .= "\t];\n}\n";
	return $content;
}

/**
 * @param list<string> $registryNames
 * @return list<string>
 */
function findMissingItemTypeIds(array $registryNames) : array{
	$reflect = new ReflectionClass(ItemTypeIds::class);
	$missing = [];
	foreach($registryNames as $registryName){
		if(!is_int($reflect->getConstant(mb_strtoupper($registryName)))){
			$missing[] = mb_strtoupper($registryName);
		}
	}
	return $missing;
}

/**
 * Inserts the given constants (in order) immediately before FIRST_UNUSED_ITEM_ID, assigning sequential IDs from the
 * current value and bumping FIRST_UNUSED_ITEM_ID accordingly.
 *
 * @param list<string> $missingNames
 */
function addItemTypeIds(string $path, array $missingNames) : void{
	$content = Filesystem::fileGetContents($path);
	if(preg_match('/public const FIRST_UNUSED_ITEM_ID = (\d+);/', $content, $matches) !== 1){
		throw new \RuntimeException("Could not find FIRST_UNUSED_ITEM_ID in $path");
	}

	$nextId = (int) $matches[1];
	$insert = "";
	foreach($missingNames as $name){
		$insert .= "\tpublic const {$name} = {$nextId};\n";
		$nextId++;
	}

	$original = "\tpublic const FIRST_UNUSED_ITEM_ID = {$matches[1]};\n";
	$replacement = $insert . "\n\tpublic const FIRST_UNUSED_ITEM_ID = {$nextId};\n";
	$updated = str_replace($original, $replacement, $content);
	if($updated === $content){
		throw new \RuntimeException("Failed to insert new constants into $path");
	}

	file_put_contents($path, $updated);
}

$deserializer = buildDedicatedDeserializer();
$missing = scanUnsupportedItems($deserializer);
$definitions = buildDefinitions($missing);
$current = PaletteItemDefinitions::ALL;

$diff = diffDefinitions($definitions, $current);
$missingTypeIds = findMissingItemTypeIds(array_keys($definitions));

$isInSync = ($diff['added'] === [] && $diff['removed'] === [] && $diff['changed'] === [] && !$diff['reordered'] && $missingTypeIds === []);

echo 'Unsupported non-block items: ' . count($definitions) . PHP_EOL;

if($isInSync){
	echo 'PaletteItemDefinitions is up to date.' . PHP_EOL;
}else{
	echo PHP_EOL;
	foreach($diff['added'] as $name){
		echo "+ {$name} => '{$definitions[$name]}'" . PHP_EOL;
	}
	foreach($diff['changed'] as $name){
		echo "~ {$name}: '{$current[$name]}' => '{$definitions[$name]}'" . PHP_EOL;
	}
	foreach($diff['removed'] as $name){
		echo "- {$name}" . PHP_EOL;
	}
	if($diff['reordered']){
		echo "! definition order changed" . PHP_EOL;
	}
	foreach($missingTypeIds as $name){
		echo "? missing ItemTypeIds::{$name}" . PHP_EOL;
	}
}

if(!$write){
	if(!$isInSync){
		echo PHP_EOL . 'PaletteItemDefinitions is out of date. Re-run with --write to regenerate it.' . PHP_EOL;
		exit(1);
	}
	exit(0);
}

if($isInSync){
	echo PHP_EOL;
}

file_put_contents($root . '/' . DEFINITIONS_PATH, generateDefinitionsFile($definitions, $root . '/' . HEADER_TEMPLATE_PATH));
echo 'Wrote ' . DEFINITIONS_PATH . PHP_EOL;

if($missingTypeIds !== []){
	addItemTypeIds($root . '/' . ITEM_TYPE_IDS_PATH, $missingTypeIds);
	echo 'Added ' . count($missingTypeIds) . ' constant(s) to ' . ITEM_TYPE_IDS_PATH . PHP_EOL;
}
exit(0);
