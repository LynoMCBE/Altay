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

namespace pocketmine\block;

use pocketmine\block\tile\Passthrough;
use pocketmine\block\tile\Spawnable;
use pocketmine\block\tile\Tile;
use pocketmine\data\bedrock\block\convert\PaletteBlockStateRegistry;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\BlockTransaction;
use pocketmine\world\format\Chunk;
use function str_replace;
use function substr;
use function ucwords;

/**
 * Base implementation for Bedrock blocks which have their own Altay block type and item, but whose gameplay logic is
 * not implemented yet. Each registry entry gets a distinct BlockTypeId and preserves all of its own palette states.
 *
 * @internal
 */
final class PaletteMappedBlock extends Block{
	private int $paletteStateIndex = 0;

	public function __construct(
		BlockIdentifier $idInfo,
		private string $vanillaId,
		private int $paletteStateCount
	){
		$name = ucwords(str_replace("_", " ", substr($vanillaId, 10)));
		parent::__construct($idInfo, $name, new BlockTypeInfo(BlockBreakInfo::instant()));
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(Facing::axis($face) !== Axis::Y){
			$direction = match($face){
				Facing::NORTH => "north",
				Facing::SOUTH => "south",
				Facing::EAST => "east",
				Facing::WEST => "west",
				default => null
			};
			if($direction !== null){
				$index = PaletteBlockStateRegistry::getCardinalDirectionStateIndex($this->vanillaId, $direction);
				if($index !== null){
					$this->paletteStateIndex = $index;
				}
			}
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function describeBlockItemState(RuntimeDataDescriber $w) : void{
		if($this->paletteStateCount > 1){
			$w->boundedIntAuto(0, $this->paletteStateCount - 1, $this->paletteStateIndex);
		}
	}

	public function getVanillaId() : string{
		return $this->vanillaId;
	}

	public function getPaletteStateCount() : int{
		return $this->paletteStateCount;
	}

	public function getPaletteStateIndex() : int{
		return $this->paletteStateIndex;
	}

	public function setPaletteStateIndex(int $paletteStateIndex) : self{
		if($paletteStateIndex < 0 || $paletteStateIndex >= $this->paletteStateCount){
			throw new \InvalidArgumentException("Palette state index must be in the range 0 ... " . ($this->paletteStateCount - 1));
		}
		$this->paletteStateIndex = $paletteStateIndex;
		return $this;
	}

	public function getBreakInfo() : BlockBreakInfo{
		return PaletteBlockStateRegistry::getBreakInfo($this->vanillaId) ?? parent::getBreakInfo();
	}

	public function getFrictionFactor() : float{
		return PaletteBlockStateRegistry::getFriction($this->vanillaId) ?? parent::getFrictionFactor();
	}

	public function getLightLevel() : int{
		return PaletteBlockStateRegistry::getBrightness($this->vanillaId) ?? parent::getLightLevel();
	}

	public function getLightFilter() : int{
		$opacity = PaletteBlockStateRegistry::getOpacity($this->vanillaId);
		return $opacity !== null ? (int) round($opacity * 15) : parent::getLightFilter();
	}

	public function isTransparent() : bool{
		$opacity = PaletteBlockStateRegistry::getOpacity($this->vanillaId);
		return $opacity !== null ? $opacity < 1.0 : parent::isTransparent();
	}

	public function getFlameEncouragement() : int{
		return PaletteBlockStateRegistry::getFlameEncouragement($this->vanillaId) ?? parent::getFlameEncouragement();
	}

	public function getFlammability() : int{
		return PaletteBlockStateRegistry::getFlammability($this->vanillaId) ?? parent::getFlammability();
	}

	public function writeStateToWorld() : void{
		$tileSaveId = PaletteBlockStateRegistry::getTileSaveId($this->vanillaId);
		if($tileSaveId === null){
			parent::writeStateToWorld();
			return;
		}

		$world = $this->position->getWorld();
		$chunk = $world->getOrLoadChunkAtPosition($this->position);
		if($chunk === null){
			throw new AssumptionFailedError("World::setBlock() should have loaded the chunk before calling this method");
		}
		$chunk->setBlockStateId(
			$this->position->x & Chunk::COORD_MASK,
			(int) $this->position->y,
			$this->position->z & Chunk::COORD_MASK,
			$this->getStateId()
		);

		$oldTile = $world->getTile($this->position);
		if($oldTile !== null && self::getTileSaveIdFromTile($oldTile) !== $tileSaveId){
			$oldTile->close();
			$oldTile = null;
		}
		if($oldTile instanceof Spawnable){
			$oldTile->clearSpawnCompoundCache();
		}elseif($oldTile === null){
			$tile = (new Passthrough($world, $this->position->asVector3()))->setSaveId($tileSaveId);
			$world->addTile($tile);
		}
	}

	private static function getTileSaveIdFromTile(Tile $tile) : string{
		return $tile instanceof Passthrough ? $tile->getPassthroughSaveId() : $tile->saveNBT()->getString(Tile::TAG_ID);
	}
}
