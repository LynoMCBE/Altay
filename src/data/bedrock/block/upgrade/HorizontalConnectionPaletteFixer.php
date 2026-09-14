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

namespace pocketmine\data\bedrock\block\upgrade;

use pocketmine\block\Block;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\Stair;
use pocketmine\block\Thin;
use pocketmine\block\utils\StairShape;
use pocketmine\block\utils\SupportType;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wall;
use pocketmine\math\Facing;
use pocketmine\world\format\SubChunk;

final class HorizontalConnectionPaletteFixer{

	private function __construct(){
	}

	/**
	 * @param SubChunk[] $subChunks
	 * @phpstan-param array<int, SubChunk> $subChunks
	 */
	public static function fix(array $subChunks) : bool{
		$registry = RuntimeBlockStateRegistry::getInstance();
		if(!self::containsConnectable($subChunks, $registry)){
			return false;
		}

		foreach($subChunks as $subY => $subChunk){
			self::fixSubChunk($subChunks, $subY, $subChunk, $registry);
		}
		return true;
	}

	/**
	 * @param SubChunk[] $subChunks
	 * @phpstan-param array<int, SubChunk> $subChunks
	 */
	private static function containsConnectable(array $subChunks, RuntimeBlockStateRegistry $registry) : bool{
		foreach($subChunks as $subChunk){
			if($subChunk->isEmptyFast()){
				continue;
			}
			foreach($subChunk->getBlockLayers() as $layer){
				foreach($layer->getPalette() as $stateId){
					$probe = $registry->fromStateId($stateId);
					if($probe instanceof Stair || $probe instanceof Fence || $probe instanceof Thin){
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * @param SubChunk[] $subChunks
	 * @phpstan-param array<int, SubChunk> $subChunks
	 */
	private static function fixSubChunk(array $subChunks, int $subY, SubChunk $subChunk, RuntimeBlockStateRegistry $registry) : void{
		if($subChunk->isEmptyFast()){
			return;
		}

		$scan = false;
		foreach($subChunk->getBlockLayers() as $layer){
			foreach($layer->getPalette() as $stateId){
				$probe = $registry->fromStateId($stateId);
				if($probe instanceof Stair || $probe instanceof Fence || $probe instanceof Thin){
					$scan = true;
					break 2;
				}
			}
		}
		if(!$scan){
			return;
		}

		$yBase = $subY << SubChunk::COORD_BIT_SIZE;
		for($x = 0; $x < SubChunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < SubChunk::EDGE_LENGTH; ++$z){
				for($y = 0; $y < SubChunk::EDGE_LENGTH; ++$y){
					$oldId = $subChunk->getBlockStateId($x, $y, $z);
					$block = $registry->fromStateId($oldId);
					if(!$block instanceof Stair && !$block instanceof Fence && !$block instanceof Thin){
						continue;
					}

					$newId = self::recomputeStateId($block, $subChunks, $x, $yBase + $y, $z, $registry);
					if($newId !== $oldId){
						$subChunk->setBlockStateId($x, $y, $z, $newId);
					}
				}
			}
		}
	}

	/**
	 * @param SubChunk[] $subChunks
	 * @phpstan-param array<int, SubChunk> $subChunks
	 */
	private static function recomputeStateId(
		Block $block,
		array $subChunks,
		int $x,
		int $y,
		int $z,
		RuntimeBlockStateRegistry $registry
	) : int{
		if($block instanceof Stair){
			$block->setShape(self::stairShape($block, $subChunks, $x, $y, $z, $registry));
		}elseif($block instanceof Fence || $block instanceof Thin){
			foreach(Facing::HORIZONTAL as $facing){
				$block->setConnected($facing, self::canConnect($block, $facing, self::neighbor($subChunks, $x, $y, $z, $facing, $registry)));
			}
		}

		return $block->getStateId();
	}

	/**
	 * @param SubChunk[] $subChunks
	 * @phpstan-param array<int, SubChunk> $subChunks
	 */
	private static function neighbor(
		array $subChunks,
		int $x,
		int $y,
		int $z,
		int $facing,
		RuntimeBlockStateRegistry $registry
	) : Block{
		[$dx, $dy, $dz] = Facing::OFFSET[$facing];
		$nx = $x + $dx;
		$nz = $z + $dz;
		if($nx < 0 || $nx > SubChunk::COORD_MASK || $nz < 0 || $nz > SubChunk::COORD_MASK){
			return VanillaBlocks::AIR();
		}

		$ny = $y + $dy;
		$sub = $subChunks[$ny >> SubChunk::COORD_BIT_SIZE] ?? null;
		if($sub === null){
			return VanillaBlocks::AIR();
		}

		return $registry->fromStateId($sub->getBlockStateId($nx, $ny & SubChunk::COORD_MASK, $nz));
	}

	/**
	 * @param SubChunk[] $subChunks
	 * @phpstan-param array<int, SubChunk> $subChunks
	 */
	private static function stairShape(Stair $stair, array $subChunks, int $x, int $y, int $z, RuntimeBlockStateRegistry $registry) : StairShape{
		$clockwise = Facing::rotateY($stair->getFacing(), true);
		$backFacing = self::possibleCornerFacing($stair, $subChunks, $x, $y, $z, $registry, false);
		if($backFacing !== null){
			return $backFacing === $clockwise ? StairShape::OUTER_RIGHT : StairShape::OUTER_LEFT;
		}
		$frontFacing = self::possibleCornerFacing($stair, $subChunks, $x, $y, $z, $registry, true);
		if($frontFacing !== null){
			return $frontFacing === $clockwise ? StairShape::INNER_RIGHT : StairShape::INNER_LEFT;
		}
		return StairShape::STRAIGHT;
	}

	/**
	 * @param SubChunk[] $subChunks
	 * @phpstan-param array<int, SubChunk> $subChunks
	 */
	private static function possibleCornerFacing(Stair $stair, array $subChunks, int $x, int $y, int $z, RuntimeBlockStateRegistry $registry, bool $oppositeFacing) : ?int{
		$side = self::neighbor(
			$subChunks,
			$x,
			$y,
			$z,
			$oppositeFacing ? Facing::opposite($stair->getFacing()) : $stair->getFacing(),
			$registry
		);
		return (
			$side instanceof Stair &&
			$side->isUpsideDown() === $stair->isUpsideDown() &&
			Facing::axis($side->getFacing()) !== Facing::axis($stair->getFacing())
		) ? $side->getFacing() : null;
	}

	private static function canConnect(Block $block, int $facing, Block $side) : bool{
		if($block instanceof Fence){
			$class = $block::class;
			return $side instanceof $class || $side instanceof FenceGate || $side->getSupportType(Facing::opposite($facing)) === SupportType::FULL;
		}
		return $side instanceof Thin || $side instanceof Wall || $side->getSupportType(Facing::opposite($facing)) === SupportType::FULL;
	}
}
