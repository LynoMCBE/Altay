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

namespace pocketmine\data\bedrock\block\convert;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\PaletteMappedBlock;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\PaletteBlockDefinitions;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function mb_strtoupper;
use function round;

/**
 * Completes the bundled Bedrock palette with a distinct Altay block type for every missing vanilla block ID.
 * Dedicated implementations keep priority for the states they already support.
 *
 * @internal
 */
final class PaletteBlockStateRegistry{
	/** @var array<string, list<BlockStateData>> */
	private static array $states = [];
	/** @var array<string, array<string, int>> map of block ID => cardinal direction => palette state index */
	private static array $cardinalDirectionStateIndexes = [];
	/** @var array<string, BlockBreakInfo> */
	private static array $breakInfo = [];
	/** @var array<string, float> */
	private static array $friction = [];
	/** @var array<string, int> */
	private static array $brightness = [];
	/** @var array<string, float> */
	private static array $opacity = [];
	/** @var array<string, int> */
	private static array $flameEncouragement = [];
	/** @var array<string, int> */
	private static array $flammability = [];

	private function __construct(){
		//NOOP
	}

	public static function register(BlockSerializerDeserializerRegistrar $reg) : void{
		$definitionsById = [];
		foreach(Utils::stringifyKeys(PaletteBlockDefinitions::ALL) as $registryName => [$id, $stateCount, $fullyUnsupported]){
			if(isset($definitionsById[$id])){
				throw new \LogicException("Repeated palette block definition for $id");
			}
			$definitionsById[$id] = [$registryName, $stateCount, $fullyUnsupported];
		}

		$statesById = [];
		$lookupsById = [];
		$originalDeserializers = [];
		foreach(BlockStateDictionary::loadStatesFromPalette(Filesystem::fileGetContents(BedrockDataFiles::BLOCK_PALETTE_NBT)) as $state){
			try{
				$reg->deserializer->deserializeBlock($state);
				continue;
			}catch(BlockStateDeserializeException){
				//The exact state isn't supported by a dedicated implementation yet.
			}

			$id = $state->getName();
			$index = count($statesById[$id] ?? []);
			$statesById[$id][] = $state;
			$lookupsById[$id][BlockStateDictionaryEntry::encodeStateProperties($state->getStates())] = $index;
			$originalDeserializers[$id] = $reg->deserializer->getDeserializerForId($id);
		}

		foreach(Utils::stringifyKeys($statesById) as $id => $states){
			$definition = $definitionsById[$id] ?? null;
			if($definition === null){
				throw new \LogicException("Bundled Bedrock palette has missing states for undefined block $id");
			}
			[, $expectedStateCount, $expectedFullyUnsupported] = $definition;
			if(count($states) !== $expectedStateCount){
				throw new \LogicException("$id has " . count($states) . " missing states, expected $expectedStateCount");
			}
			if(($originalDeserializers[$id] === null) !== $expectedFullyUnsupported){
				throw new \LogicException("The dedicated implementation status of $id changed; update PaletteBlockDefinitions");
			}
		}
		if(count($statesById) !== count($definitionsById)){
			throw new \LogicException("Some palette block definitions no longer have missing states; update PaletteBlockDefinitions");
		}

		self::$states = [];
		self::$cardinalDirectionStateIndexes = [];
		self::loadBlockProperties($definitionsById);
		$blocks = VanillaBlocks::getAll();
		foreach(Utils::stringifyKeys(PaletteBlockDefinitions::ALL) as $registryName => [$id, $stateCount, $_fullyUnsupported]){
			$block = $blocks[mb_strtoupper($registryName)] ?? null;
			if(!$block instanceof PaletteMappedBlock){
				throw new \LogicException("VanillaBlocks::$registryName must be a PaletteMappedBlock");
			}
			if($block->getVanillaId() !== $id || $block->getPaletteStateCount() !== $stateCount){
				throw new \LogicException("VanillaBlocks::$registryName does not match its palette block definition");
			}

			self::$states[$registryName] = $statesById[$id];
			foreach($statesById[$id] as $index => $state){
				$direction = $state->getState(BlockStateNames::MC_CARDINAL_DIRECTION);
				if($direction !== null && is_string($direction->getValue())){
					self::$cardinalDirectionStateIndexes[$id][$direction->getValue()] ??= $index;
				}
			}
			$lookup = $lookupsById[$id];
			$original = $originalDeserializers[$id];
			$reg->deserializer->map($id, static function(BlockStateReader $in) use ($id, $lookup, $original, $block) : Block{
				$key = BlockStateDictionaryEntry::encodeStateProperties($in->peekStates());
				if(isset($lookup[$key])){
					$in->consumeAllStates();
					return (clone $block)->setPaletteStateIndex($lookup[$key]);
				}
				if($original !== null){
					return $original($in);
				}
				throw new BlockStateDeserializeException("Unknown state properties for block ID \"$id\"");
			});
			$reg->serializer->map(
				$block,
				static fn(PaletteMappedBlock $block) : BlockStateData => self::getState($registryName, $block->getPaletteStateIndex())
			);
		}
	}

	/** @param array<string, array{string, int, bool}> $definitionsById */
	private static function loadBlockProperties(array $definitionsById) : void{
		$properties = json_decode(Filesystem::fileGetContents(BedrockDataFiles::BLOCK_PROPERTIES_TABLE_JSON), true, flags: JSON_THROW_ON_ERROR);
		if(!is_array($properties)){
			throw new \LogicException("Invalid Bedrock block properties table");
		}

		self::$breakInfo = self::$friction = self::$brightness = self::$opacity = self::$flameEncouragement = self::$flammability = [];
		foreach(Utils::stringifyKeys($definitionsById) as $id => $_definition){
			$row = $properties[$id] ?? null;
			if(!is_array($row)){
				throw new \LogicException("Missing Bedrock block properties for $id");
			}
			$hardness = $row["hardness"] ?? null;
			$blastResistance = $row["blastResistance"] ?? null;
			$friction = $row["friction"] ?? null;
			$brightness = $row["brightness"] ?? null;
			$opacity = $row["opacity"] ?? null;
			$flameEncouragement = $row["flameEncouragement"] ?? null;
			$flammability = $row["flammability"] ?? null;
			if(!is_float($hardness) || !is_float($blastResistance) || !is_float($friction) ||
				(!is_float($brightness) && !is_int($brightness)) || !is_float($opacity) ||
				!is_int($flameEncouragement) || !is_int($flammability)){
				throw new \LogicException("Invalid Bedrock block properties for $id");
			}

			self::$breakInfo[$id] = new BlockBreakInfo(round($hardness, 5), blastResistance: round($blastResistance, 5) * 5);
			self::$friction[$id] = $friction;
			self::$brightness[$id] = (int) $brightness;
			self::$opacity[$id] = $opacity;
			self::$flameEncouragement[$id] = $flameEncouragement;
			self::$flammability[$id] = $flammability;
		}
	}

	private static function getState(string $registryName, int $index) : BlockStateData{
		return self::$states[$registryName][$index] ?? throw new \LogicException("Palette states for $registryName have not been initialized");
	}

	public static function getBreakInfo(string $id) : ?BlockBreakInfo{ return self::$breakInfo[$id] ?? null; }
	public static function getFriction(string $id) : ?float{ return self::$friction[$id] ?? null; }
	public static function getBrightness(string $id) : ?int{ return self::$brightness[$id] ?? null; }
	public static function getOpacity(string $id) : ?float{ return self::$opacity[$id] ?? null; }
	public static function getFlameEncouragement(string $id) : ?int{ return self::$flameEncouragement[$id] ?? null; }
	public static function getFlammability(string $id) : ?int{ return self::$flammability[$id] ?? null; }

	/**
	 * Returns the palette state index whose "minecraft:cardinal_direction" state has the given value, or null if the
	 * block has no such state.
	 */
	public static function getCardinalDirectionStateIndex(string $id, string $direction) : ?int{
		return self::$cardinalDirectionStateIndexes[$id][$direction] ?? null;
	}

	public static function getTileSaveId(string $id) : ?string{
		if(str_ends_with($id, "_hanging_sign")){ return "HangingSign"; }
		if(str_ends_with($id, "_shelf")){ return "Shelf"; }
		if(str_ends_with($id, "_copper_chest") || str_ends_with($id, "_chest") || $id === "minecraft:copper_chest"){ return "Chest"; }
		return match($id){
			"minecraft:bee_nest", "minecraft:beehive" => "Beehive",
			"minecraft:calibrated_sculk_sensor" => "CalibratedSculkSensor",
			"minecraft:chain_command_block", "minecraft:command_block", "minecraft:repeating_command_block" => "CommandBlock",
			"minecraft:chalkboard" => "ChalkboardBlock",
			"minecraft:crafter" => "Crafter",
			"minecraft:decorated_pot" => "DecoratedPot",
			"minecraft:dispenser" => "Dispenser",
			"minecraft:dropper" => "Dropper",
			"minecraft:end_gateway" => "EndGateway",
			"minecraft:end_portal" => "EndPortal",
			"minecraft:jigsaw" => "JigsawBlock",
			"minecraft:lodestone" => "Lodestone",
			"minecraft:moving_block" => "MovingBlock",
			"minecraft:piston_arm_collision", "minecraft:sticky_piston_arm_collision" => "PistonArm",
			"minecraft:sculk_catalyst" => "SculkCatalyst",
			"minecraft:sculk_sensor" => "SculkSensor",
			"minecraft:sculk_shrieker" => "SculkShrieker",
			"minecraft:straw_bed" => "Bed",
			"minecraft:structure_block" => "StructureBlock",
			"minecraft:suspicious_gravel", "minecraft:suspicious_sand" => "BrushableBlock",
			"minecraft:trial_spawner" => "TrialSpawner",
			"minecraft:vault" => "Vault",
			default => null
		};
	}
}
