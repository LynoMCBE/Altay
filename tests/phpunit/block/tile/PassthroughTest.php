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

namespace pocketmine\block\tile;

use PHPUnit\Framework\TestCase;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

final class PassthroughTest extends TestCase{
	public function testUnknownBlockEntityDataIsPreserved() : void{
		$world = (new \ReflectionClass(World::class))->newInstanceWithoutConstructor();
		$input = CompoundTag::create()
			->setString(Tile::TAG_ID, "FutureTile")
			->setInt(Tile::TAG_X, 1)
			->setInt(Tile::TAG_Y, 2)
			->setInt(Tile::TAG_Z, 3)
			->setString("custom", "preserved");

		$tile = TileFactory::getInstance()->createFromData($world, $input);
		self::assertInstanceOf(Passthrough::class, $tile);

		$saved = $tile->saveNBT();
		self::assertSame("FutureTile", $saved->getString(Tile::TAG_ID));
		self::assertSame("preserved", $saved->getString("custom"));

		$spawn = $tile->getSpawnCompound();
		self::assertSame("FutureTile", $spawn->getString(Tile::TAG_ID));
		self::assertSame("preserved", $spawn->getString("custom"));
	}
}

