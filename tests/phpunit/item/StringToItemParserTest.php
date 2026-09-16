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

namespace pocketmine\item;

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\PaletteBlockDefinitions;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function strtoupper;
use function substr;

class StringToItemParserTest extends TestCase{

	public function testOverrideRemovesOldAlias() : void{
		$parser = new StringToItemParser();

		$item1 = VanillaItems::DIAMOND();
		$item2 = VanillaItems::EMERALD();

		$parser->register("test_alias", fn() => $item1);

		self::assertContains("test_alias", $parser->lookupAliases($item1));
		self::assertNotContains("test_alias", $parser->lookupAliases($item2));

		$parser->override("test_alias", fn() => $item2);

		self::assertNotContains("test_alias", $parser->lookupAliases($item1));
		self::assertContains("test_alias", $parser->lookupAliases($item2));
	}

	public function testOverrideWithNewAlias() : void{
		$parser = new StringToItemParser();

		$item = VanillaItems::DIAMOND();

		$parser->override("new_alias", fn() => $item);

		self::assertContains("new_alias", $parser->lookupAliases($item));
		self::assertSame($item, $parser->parse("new_alias"));
	}

	public function testOverrideMultipleAliases() : void{
		$parser = new StringToItemParser();

		$item1 = VanillaItems::DIAMOND();
		$item2 = VanillaItems::EMERALD();

		$parser->register("alias1", fn() => $item1);
		$parser->register("alias2", fn() => $item1);
		$parser->register("alias3", fn() => $item1);

		self::assertCount(3, $parser->lookupAliases($item1));

		$parser->override("alias2", fn() => $item2);

		self::assertCount(2, $parser->lookupAliases($item1));
		self::assertContains("alias1", $parser->lookupAliases($item1));
		self::assertContains("alias3", $parser->lookupAliases($item1));
		self::assertNotContains("alias2", $parser->lookupAliases($item1));

		self::assertCount(1, $parser->lookupAliases($item2));
		self::assertContains("alias2", $parser->lookupAliases($item2));
	}

	public function testEveryFullyUnsupportedPaletteBlockHasAnAlias() : void{
		$parser = StringToItemParser::getInstance();
		$blocks = VanillaBlocks::getAll();
		foreach(Utils::stringifyKeys(PaletteBlockDefinitions::ALL) as $registryName => [$id, $_stateCount, $fullyUnsupported]){
			if(!$fullyUnsupported){
				continue;
			}
			$item = $parser->parse(substr($id, 10));
			self::assertInstanceOf(ItemBlock::class, $item, "Missing block item alias for $id");
			self::assertSame($blocks[strtoupper($registryName)]->getTypeId(), $item->getBlock()->getTypeId(), "Block item alias does not use its own native block type");
			self::assertSame(
				$id,
				GlobalBlockStateHandlers::getSerializer()->serializeBlock($item->getBlock())->getName(),
				"Block item alias resolves to the wrong block"
			);
		}
	}
}
