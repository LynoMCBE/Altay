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

use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\convert\BlockObjectToStateSerializer;
use pocketmine\data\bedrock\block\convert\BlockSerializerDeserializerRegistrar;
use pocketmine\data\bedrock\block\convert\BlockStateToObjectDeserializer;
use pocketmine\data\bedrock\block\convert\VanillaBlockMappings;
use pocketmine\data\bedrock\PaletteBlockDefinitions;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\utils\Filesystem;

require dirname(__DIR__) . '/vendor/autoload.php';

const PALETTE_PATH = 'vendor/altayofficial/bedrock-data/block_palette.nbt';
const DEFINITIONS_PATH = 'generated/data/bedrock/PaletteBlockDefinitions.php';
const BLOCK_TYPE_IDS_PATH = 'src/block/BlockTypeIds.php';
const HEADER_TEMPLATE_PATH = 'build/codegen/templates/header.php';

$root = dirname(__DIR__);

$options = array_slice($argv, 1);
$write = in_array('--write', $options, true) || in_array('--update', $options, true);
if(in_array('--help', $options, true) || in_array('-h', $options, true)){
	echo <<<USAGE
Usage: php tools/audit-block-palette.php [--write]

Scans the bundled Bedrock block palette and determines which block states Altay cannot yet deserialize using its
dedicated (non-palette) implementations.

By default the tool only reports whether PaletteBlockDefinitions is in sync and exits non-zero when it is stale, which
makes it suitable for use in CI.

  --write, --update   Regenerate PaletteBlockDefinitions.php (and add any missing BlockTypeIds constants).
                      After running this you must run `composer run update-codegen` to regenerate VanillaBlocks.

USAGE;
	exit(0);
}

/**
 * Builds a deserializer containing only Altay's dedicated block mappings, deliberately excluding the palette mappings
 * registered by PaletteBlockStateRegistry. This is required to observe which states genuinely lack an implementation.
 */
function buildDedicatedDeserializer() : BlockStateToObjectDeserializer{
	$deserializer = new BlockStateToObjectDeserializer();
	$registrar = new BlockSerializerDeserializerRegistrar($deserializer, new BlockObjectToStateSerializer());
	VanillaBlockMappings::init($registrar, false);
	return $deserializer;
}

/**
 * @return array<string, int> palette block ID => number of states that failed to deserialize
 */
function scanUnsupportedStates(BlockStateToObjectDeserializer $deserializer, string $palettePath) : array{
	$unsupported = [];
	foreach(BlockStateDictionary::loadStatesFromPalette(Filesystem::fileGetContents($palettePath)) as $state){
		try{
			$deserializer->deserializeBlock($state);
		}catch(BlockStateDeserializeException){
			$id = $state->getName();
			$unsupported[$id] = ($unsupported[$id] ?? 0) + 1;
		}
	}
	return $unsupported;
}

/**
 * Computes the expected contents of PaletteBlockDefinitions, ordered by Bedrock block ID.
 *
 * Blocks which still have a dedicated deserializer (partial support) are registered under a `<name>_palette_states`
 * alias to avoid clashing with the existing dedicated block.
 *
 * @param array<string, int> $unsupported
 * @return array<string, array{string, int, bool}> registry name => [Bedrock ID, state count, fully unsupported]
 */
function buildDefinitions(BlockStateToObjectDeserializer $deserializer, array $unsupported) : array{
	$byId = [];
	foreach($unsupported as $id => $count){
		$fullyUnsupported = $deserializer->getDeserializerForId($id) === null;
		$suffix = str_contains($id, ':') ? substr($id, strpos($id, ':') + 1) : $id;
		$byId[$id] = [$fullyUnsupported ? $suffix : $suffix . '_palette_states', $count, $fullyUnsupported];
	}
	ksort($byId, SORT_STRING);

	$definitions = [];
	foreach($byId as $id => [$registryName, $count, $fullyUnsupported]){
		$definitions[$registryName] = [$id, $count, $fullyUnsupported];
	}
	return $definitions;
}

/**
 * @param array<string, array{string, int, bool}> $definitions
 * @return array{added: list<string>, removed: list<string>, changed: list<string>, reordered: bool}
 */
function diffDefinitions(array $definitions, array $current) : array{
	$added = array_keys(array_diff_key($definitions, $current));
	$removed = array_keys(array_diff_key($current, $definitions));

	$changed = [];
	foreach($definitions as $name => [$id, $count, $fullyUnsupported]){
		if(isset($current[$name]) && $current[$name] !== [$id, $count, $fullyUnsupported]){
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
 * @param array<string, array{string, int, bool}> $definitions
 */
function generateDefinitionsFile(array $definitions, string $headerTemplatePath) : string{
	$content = rtrim(Filesystem::fileGetContents($headerTemplatePath), "\n") . "\n\n";
	$content .= "namespace pocketmine\\data\\bedrock;\n\n";
	$content .= "/**\n";
	$content .= " * Bedrock palette block types which don't yet have complete dedicated implementations.\n";
	$content .= " *\n";
	$content .= " * @internal\n";
	$content .= " */\n";
	$content .= "final class PaletteBlockDefinitions{\n";
	$content .= "\tprivate function __construct(){\n";
	$content .= "\t\t//NOOP\n";
	$content .= "\t}\n\n";
	$content .= "\t/**\n";
	$content .= "\t * registry name => [Bedrock ID, state count, fully unsupported]\n";
	$content .= "\t *\n";
	$content .= "\t * @var array<string, array{string, int, bool}>\n";
	$content .= "\t */\n";
	$content .= "\tpublic const ALL = [\n";
	foreach($definitions as $registryName => [$id, $count, $fullyUnsupported]){
		$content .= "\t\t'{$registryName}' => ['{$id}', {$count}, " . ($fullyUnsupported ? 'true' : 'false') . "],\n";
	}
	$content .= "\t];\n}\n";
	return $content;
}

/**
 * @param list<string> $registryNames
 */
function findMissingBlockTypeIds(array $registryNames) : array{
	$reflect = new \ReflectionClass(BlockTypeIds::class);
	$missing = [];
	foreach($registryNames as $registryName){
		if(!is_int($reflect->getConstant(mb_strtoupper($registryName)))){
			$missing[] = mb_strtoupper($registryName);
		}
	}
	return $missing;
}

/**
 * Inserts the given constants (in order) immediately before FIRST_UNUSED_BLOCK_ID, assigning sequential IDs from the
 * current value and bumping FIRST_UNUSED_BLOCK_ID accordingly.
 *
 * @param list<string> $missingNames
 */
function addBlockTypeIds(string $path, array $missingNames) : void{
	$content = Filesystem::fileGetContents($path);
	if(preg_match('/public const FIRST_UNUSED_BLOCK_ID = (\d+);/', $content, $matches) !== 1){
		throw new \RuntimeException("Could not find FIRST_UNUSED_BLOCK_ID in $path");
	}

	$nextId = (int) $matches[1];
	$insert = "";
	foreach($missingNames as $name){
		$insert .= "\tpublic const {$name} = {$nextId};\n";
		$nextId++;
	}

	$original = "\tpublic const FIRST_UNUSED_BLOCK_ID = {$matches[1]};\n";
	$replacement = $insert . "\n\tpublic const FIRST_UNUSED_BLOCK_ID = {$nextId};\n";
	$updated = str_replace($original, $replacement, $content);
	if($updated === $content){
		throw new \RuntimeException("Failed to insert new constants into $path");
	}

	file_put_contents($path, $updated);
}

$deserializer = buildDedicatedDeserializer();
$unsupported = scanUnsupportedStates($deserializer, $root . '/' . PALETTE_PATH);
$definitions = buildDefinitions($deserializer, $unsupported);
$current = PaletteBlockDefinitions::ALL;

$diff = diffDefinitions($definitions, $current);
$missingTypeIds = findMissingBlockTypeIds(array_keys($definitions));

$unsupportedStateCount = array_sum($unsupported);
$isInSync = ($diff['added'] === [] && $diff['removed'] === [] && $diff['changed'] === [] && !$diff['reordered'] && $missingTypeIds === []);

echo 'Unsupported/partial block IDs: ' . count($definitions) . PHP_EOL;
echo 'Unsupported/partial states: ' . $unsupportedStateCount . PHP_EOL;

if($isInSync){
	echo 'PaletteBlockDefinitions is up to date.' . PHP_EOL;
}else{
	echo PHP_EOL;
	foreach($diff['added'] as $name){
		[$id, $count, $fullyUnsupported] = $definitions[$name];
		echo "+ {$name} => ['{$id}', {$count}, " . ($fullyUnsupported ? 'true' : 'false') . ']' . PHP_EOL;
	}
	foreach($diff['changed'] as $name){
		echo "~ {$name}: " . json_encode($current[$name]) . ' => ' . json_encode($definitions[$name]) . PHP_EOL;
	}
	foreach($diff['removed'] as $name){
		echo "- {$name}" . PHP_EOL;
	}
	if($diff['reordered']){
		echo "! definition order changed" . PHP_EOL;
	}
	foreach($missingTypeIds as $name){
		echo "? missing BlockTypeIds::{$name}" . PHP_EOL;
	}
}

if(!$write){
	if(!$isInSync){
		echo PHP_EOL . 'PaletteBlockDefinitions is out of date. Re-run with --write to regenerate it.' . PHP_EOL;
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
	addBlockTypeIds($root . '/' . BLOCK_TYPE_IDS_PATH, $missingTypeIds);
	echo 'Added ' . count($missingTypeIds) . ' constant(s) to ' . BLOCK_TYPE_IDS_PATH . PHP_EOL;
}

echo PHP_EOL . 'Now run: composer run update-codegen' . PHP_EOL;
exit(0);
