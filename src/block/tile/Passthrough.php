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

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\VersionInfo;
use function in_array;

/**
 * Preserves the complete NBT of block entities which don't have a dedicated implementation yet.
 *
 * @internal
 */
final class Passthrough extends Spawnable{
	private string $saveId = "Unknown";
	private ?CompoundTag $data = null;

	public function setSaveId(string $saveId) : self{
		if($saveId === ""){
			throw new \InvalidArgumentException("Block entity save ID cannot be empty");
		}
		$this->saveId = $saveId;
		return $this;
	}

	public function getPassthroughSaveId() : string{
		return $this->saveId;
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$saveId = $nbt->getString(self::TAG_ID, "");
		if($saveId !== ""){
			$this->saveId = $saveId;
		}
		$this->data = clone $nbt;
		$this->clearSpawnCompoundCache();
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		if($this->data === null){
			return;
		}
		foreach($this->data as $key => $value){
			if(!in_array($key, [self::TAG_ID, self::TAG_X, self::TAG_Y, self::TAG_Z, VersionInfo::TAG_WORLD_DATA_VERSION], true)){
				$nbt->setTag($key, clone $value);
			}
		}
	}

	public function saveNBT() : CompoundTag{
		return parent::saveNBT()->setString(self::TAG_ID, $this->saveId);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->writeSaveData($nbt);
		$nbt->setString(self::TAG_ID, $this->saveId);
	}
}

