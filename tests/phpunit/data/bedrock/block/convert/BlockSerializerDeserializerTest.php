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

namespace pocketmine\data\bedrock\block\convert;

use PHPUnit\Framework\TestCase;
use pocketmine\block\BaseBanner;
use pocketmine\block\Bed;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\CaveVines;
use pocketmine\block\Farmland;
use pocketmine\block\MobHead;
use pocketmine\block\PaletteMappedBlock;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\data\bedrock\PaletteBlockDefinitions;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\utils\Filesystem;
use function array_unique;
use function count;
use function print_r;
use function strtoupper;

final class BlockSerializerDeserializerTest extends TestCase{
	private BlockStateToObjectDeserializer $deserializer;
	private BlockObjectToStateSerializer $serializer;

	public function setUp() : void{
		$this->deserializer = new BlockStateToObjectDeserializer();
		$this->serializer = new BlockObjectToStateSerializer();
		$registrar = new BlockSerializerDeserializerRegistrar($this->deserializer, $this->serializer);
		VanillaBlockMappings::init($registrar);
	}

	public function testAllKnownBlockStatesSerializableAndDeserializable() : void{
		foreach(RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $block){
			try{
				$blockStateData = $this->serializer->serializeBlock($block);
			}catch(BlockStateSerializeException $e){
				self::fail("Failed to serialize " . $block->getName() . ": " . $e->getMessage());
			}
			try{
				$newBlock = $this->deserializer->deserializeBlock($blockStateData);
			}catch(BlockStateDeserializeException $e){
				self::fail("Failed to deserialize " . $blockStateData->getName() . ": " . $e->getMessage() . " with data " . $blockStateData->toNbt());
			}

			if(match ($block->getTypeId()) {
				BlockTypeIds::POTION_CAULDRON,
				BlockTypeIds::OMINOUS_BANNER,
				BlockTypeIds::OMINOUS_WALL_BANNER => true,
				default => false
			}){
				//these pretend to be something else in the blockstate, and the variant switching is done via block entity data
				continue;
			}

			//The following are workarounds for differences in blockstate representation in Bedrock vs PM
			//In some cases, some properties are not stored in the blockstate (but rather in the block entity NBT), but
			//they do form part of the internal blockstate hash in PM. In other cases, PM allows representing states
			//that don't exist in Bedrock, such as the cave vines head without berries, which is a state that visually
			//exists in Bedrock, but doesn't have its own ID.
			//This leads to inconsistencies when serializing and deserializing blockstates which we need to correct for.
			if(
				($block instanceof BaseBanner && $newBlock instanceof BaseBanner) ||
				($block instanceof Bed && $newBlock instanceof Bed)
			){
				$newBlock->setColor($block->getColor());
			}elseif($block instanceof MobHead && $newBlock instanceof MobHead){
				$newBlock->setMobHeadType($block->getMobHeadType());
			}elseif($block instanceof CaveVines && $newBlock instanceof CaveVines && !$block->hasBerries()){
				$newBlock->setHead($block->isHead());
			}elseif($block instanceof Farmland && $newBlock instanceof Farmland){
				$block->setWaterPositionIndex($newBlock->getWaterPositionIndex());
			}

			self::assertSame($block->getStateId(), $newBlock->getStateId(), "Mismatch of blockstate for " . $block->getName() . ", " . print_r($block, true) . " vs " . print_r($newBlock, true));
		}
	}

	public function testEveryBundledBedrockPaletteStateIsDeserializable() : void{
		$states = BlockStateDictionary::loadStatesFromPalette(Filesystem::fileGetContents(BedrockDataFiles::BLOCK_PALETTE_NBT));
		foreach($states as $state){
			try{
				$block = $this->deserializer->deserializeBlock($state);
			}catch(BlockStateDeserializeException $e){
				self::fail("Failed to deserialize " . $state->getName() . ": " . $e->getMessage() . " with data " . $state->toNbt());
			}

			if($block instanceof PaletteMappedBlock){
				self::assertTrue(
					$state->equals($this->serializer->serializeBlock($block)),
					"Palette-mapped state did not round-trip exactly: " . $state->toNbt()
				);
			}
		}
	}

	public function testPaletteBlocksHaveDistinctNativeTypes() : void{
		$blocks = VanillaBlocks::getAll();
		$typeIds = [];
		$itemTypeIds = [];
		foreach(PaletteBlockDefinitions::ALL as $registryName => [$id, $stateCount, $_fullyUnsupported]){
			$block = $blocks[strtoupper($registryName)] ?? null;
			self::assertInstanceOf(PaletteMappedBlock::class, $block, "Missing native block registry entry for $id");
			self::assertSame($id, $block->getVanillaId());
			self::assertSame($stateCount, $block->getPaletteStateCount());
			$typeIds[] = $block->getTypeId();
			$itemTypeIds[] = $block->asItem()->getTypeId();
		}
		self::assertCount(count(PaletteBlockDefinitions::ALL), array_unique($typeIds), "Palette blocks must not share a BlockTypeId");
		self::assertCount(count(PaletteBlockDefinitions::ALL), array_unique($itemTypeIds), "Palette blocks must not share an ItemTypeId");
	}
}
