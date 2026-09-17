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

namespace pocketmine\data\bedrock\item;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use function is_int;
use function mb_strtoupper;
use function str_replace;
use function substr;
use function ucwords;

/**
 * Registers generic serialize/deserialize handlers for Bedrock items which don't yet have a dedicated implementation.
 *
 * These items are visual-only: they can be obtained, stored, held and transmitted, but they have no gameplay behaviour
 * (they cannot be eaten, placed, used, or equipped, and they have no durability).
 *
 * @internal
 */
final class PaletteItemRegistry{
	private function __construct(){
		//NOOP
	}

	public static function register(ItemSerializerDeserializerRegistrar $reg) : void{
		foreach(PaletteItemDefinitions::ALL as $registryName => $id){
			$reg->map1to1Item($id, self::createItem($registryName, $id));
		}
	}

	public static function createItem(string $registryName, string $bedrockId) : Item{
		return new Item(
			new ItemIdentifier(self::getTypeId($registryName)),
			ucwords(str_replace("_", " ", substr($bedrockId, 10)))
		);
	}

	private static function getTypeId(string $registryName) : int{
		$typeId = (new \ReflectionClass(ItemTypeIds::class))->getConstant(mb_strtoupper($registryName));
		if(!is_int($typeId)){
			\GlobalLogger::get()->error(self::class . ": No constant type ID found for $registryName, generating a new one");
			$typeId = ItemTypeIds::newId();
		}
		return $typeId;
	}
}
