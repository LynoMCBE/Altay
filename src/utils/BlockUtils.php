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

namespace pocketmine\utils;

use pocketmine\block\Block;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use function pow;

class BlockUtils{

	/**
	 * Returns the calculated break speed as percentage progress per game tick.
	 */
	public static function getDestroySpeed(Player $player, Block $block, Item $item) : float{
		$destroySpeed = $item->getMiningEfficiency(($block->getBreakInfo()->getToolType() & $item->getBlockToolType()) !== 0);
		$speedBreak = $destroySpeed;
		$hasteLevel = 0;
		$effectManager = $player->getEffects();
		$haste = $effectManager->get(VanillaEffects::HASTE());
		$conduitPower = $effectManager->get(VanillaEffects::CONDUIT_POWER());
		$miningFatigue = $effectManager->get(VanillaEffects::MINING_FATIGUE());
		$helmet = $player->getArmorInventory()->getHelmet();
		if($haste !== null)
			$hasteLevel = $haste->getEffectLevel();
		if($conduitPower !== null){
			$conduitPowerLevel = $conduitPower->getEffectLevel();
			if($hasteLevel < $conduitPowerLevel)
				$hasteLevel = $conduitPowerLevel;
		}
		if($hasteLevel > 0)
			$speedBreak = $destroySpeed * (($hasteLevel * 0.2) + 1);
		if($miningFatigue !== null){
			$slowMininLevel = $miningFatigue->getEffectLevel();
			$speedBreak = pow(0.300000011920929, $slowMininLevel) * $speedBreak;
		}

		if(!$player->isOnGround() && !$player->isFlying()){
			$speedBreak *= 0.2;
		}
		if($player->isUnderwater() && !$helmet->hasEnchantment(VanillaEnchantments::AQUA_AFFINITY())){
			$speedBreak *= 0.2;
		}
		return $speedBreak;
	}

	/**
	 * Returns the calculated break speed as percentage progress per game tick.
	 */
	public static function getDestroyRate(Player $player, Block $block) : float{
		$breakInfo = $block->getBreakInfo();
		if(!$breakInfo->isBreakable()){
			return 0.0;
		}
		if($breakInfo->getBreakTime($player->getInventory()->getItemInHand()) <= 0.0){
			return 1.0;
		}
		$speed = self::getDestroyProgress($player, $block);
		$speedBreaker = $speed;
		$hasteLevel = 0;
		$effectManager = $player->getEffects();
		$haste = $effectManager->get(VanillaEffects::HASTE());
		$conduitPower = $effectManager->get(VanillaEffects::CONDUIT_POWER());
		$miningFatigue = $effectManager->get(VanillaEffects::MINING_FATIGUE());
		if($haste !== null)
			$hasteLevel = $haste->getEffectLevel();
		if($conduitPower !== null){
			$conduitPowerLevel = $conduitPower->getEffectLevel();
			if($hasteLevel < $conduitPowerLevel)
				$hasteLevel = $conduitPowerLevel;
		}
		if($hasteLevel > 0)
			$speedBreaker = pow(1.200000047683716, (double) $hasteLevel) * $speed;
		if($miningFatigue !== null)
			$speedBreaker *= pow(0.699999988079071, $miningFatigue->getEffectLevel());
		return $speedBreaker;
	}

	private static function getDestroyProgress(Player $player, Block $block) : float{
		$destroySpeed = $block->getBreakInfo()->getHardness();
		$item = $player->getInventory()->getItemInHand();
		if($destroySpeed > 0.0){
			$tick = 1.0 / $destroySpeed;
			if($block->getBreakInfo()->isToolCompatible(VanillaItems::AIR()))
				return (self::getDestroySpeed($player, $block, $item) * $tick) * 0.033333335;
			elseif($block->getBreakInfo()->isToolCompatible($item))
				return (self::getDestroySpeed($player, $block, $item) * $tick) * 0.033333335;
			else
				return ((self::getDestroySpeed($player, $block, $item) * $tick) * 0.0099999998);
		}
		return 1.0;
	}

}
