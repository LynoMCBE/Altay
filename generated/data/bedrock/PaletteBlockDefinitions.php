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

namespace pocketmine\data\bedrock;

/**
 * Bedrock palette block types which don't yet have complete dedicated implementations.
 *
 * @internal
 */
final class PaletteBlockDefinitions{
	private function __construct(){
		//NOOP
	}

	/**
	 * registry name => [Bedrock ID, state count, fully unsupported]
	 *
	 * @var array<string, array{string, int, bool}>
	 */
	public const ALL = [		'acacia_shelf' => ['minecraft:acacia_shelf', 32, true],
		'allow' => ['minecraft:allow', 1, true],
		'bamboo_shelf' => ['minecraft:bamboo_shelf', 32, true],
		'bee_nest' => ['minecraft:bee_nest', 24, true],
		'beehive' => ['minecraft:beehive', 24, true],
		'birch_shelf' => ['minecraft:birch_shelf', 32, true],
		'border_block' => ['minecraft:border_block', 162, true],
		'brown_mushroom_block_palette_states' => ['minecraft:brown_mushroom_block', 2, false],
		'bubble_column' => ['minecraft:bubble_column', 2, true],
		'bush' => ['minecraft:bush', 1, true],
		'calibrated_sculk_sensor' => ['minecraft:calibrated_sculk_sensor', 12, true],
		'camera' => ['minecraft:camera', 1, true],
		'cauldron_palette_states' => ['minecraft:cauldron', 6, false],
		'chain_command_block' => ['minecraft:chain_command_block', 12, true],
		'chalkboard' => ['minecraft:chalkboard', 16, true],
		'cherry_sapling' => ['minecraft:cherry_sapling', 2, true],
		'cherry_shelf' => ['minecraft:cherry_shelf', 32, true],
		'client_request_placeholder_block' => ['minecraft:client_request_placeholder_block', 1, true],
		'closed_eyeblossom' => ['minecraft:closed_eyeblossom', 1, true],
		'command_block' => ['minecraft:command_block', 12, true],
		'composter' => ['minecraft:composter', 9, true],
		'conduit' => ['minecraft:conduit', 1, true],
		'copper_chest' => ['minecraft:copper_chest', 4, true],
		'copper_golem_statue' => ['minecraft:copper_golem_statue', 4, true],
		'crafter' => ['minecraft:crafter', 48, true],
		'creaking_heart' => ['minecraft:creaking_heart', 18, true],
		'crimson_shelf' => ['minecraft:crimson_shelf', 32, true],
		'dark_oak_shelf' => ['minecraft:dark_oak_shelf', 32, true],
		'decorated_pot' => ['minecraft:decorated_pot', 4, true],
		'deny' => ['minecraft:deny', 1, true],
		'deprecated_anvil' => ['minecraft:deprecated_anvil', 4, true],
		'deprecated_purpur_block_1' => ['minecraft:deprecated_purpur_block_1', 3, true],
		'deprecated_purpur_block_2' => ['minecraft:deprecated_purpur_block_2', 3, true],
		'dispenser' => ['minecraft:dispenser', 12, true],
		'dried_ghast' => ['minecraft:dried_ghast', 16, true],
		'dripstone_block' => ['minecraft:dripstone_block', 1, true],
		'dropper' => ['minecraft:dropper', 12, true],
		'end_gateway' => ['minecraft:end_gateway', 1, true],
		'end_portal' => ['minecraft:end_portal', 1, true],
		'exposed_copper_chest' => ['minecraft:exposed_copper_chest', 4, true],
		'exposed_copper_golem_statue' => ['minecraft:exposed_copper_golem_statue', 4, true],
		'firefly_bush' => ['minecraft:firefly_bush', 1, true],
		'frog_spawn' => ['minecraft:frog_spawn', 1, true],
		'golden_dandelion' => ['minecraft:golden_dandelion', 1, true],
		'grindstone' => ['minecraft:grindstone', 16, true],
		'heavy_core' => ['minecraft:heavy_core', 1, true],
		'honey_block' => ['minecraft:honey_block', 1, true],
		'jigsaw' => ['minecraft:jigsaw', 24, true],
		'jungle_shelf' => ['minecraft:jungle_shelf', 32, true],
		'kelp' => ['minecraft:kelp', 26, true],
		'leaf_litter' => ['minecraft:leaf_litter', 32, true],
		'lodestone' => ['minecraft:lodestone', 1, true],
		'mangrove_propagule' => ['minecraft:mangrove_propagule', 10, true],
		'mangrove_shelf' => ['minecraft:mangrove_shelf', 32, true],
		'moss_block' => ['minecraft:moss_block', 1, true],
		'moss_carpet' => ['minecraft:moss_carpet', 1, true],
		'moving_block' => ['minecraft:moving_block', 1, true],
		'mushroom_stem_palette_states' => ['minecraft:mushroom_stem', 14, false],
		'oak_shelf' => ['minecraft:oak_shelf', 32, true],
		'observer' => ['minecraft:observer', 12, true],
		'open_eyeblossom' => ['minecraft:open_eyeblossom', 1, true],
		'oxidized_copper_chest' => ['minecraft:oxidized_copper_chest', 4, true],
		'oxidized_copper_golem_statue' => ['minecraft:oxidized_copper_golem_statue', 4, true],
		'pale_hanging_moss' => ['minecraft:pale_hanging_moss', 2, true],
		'pale_moss_block' => ['minecraft:pale_moss_block', 1, true],
		'pale_moss_carpet' => ['minecraft:pale_moss_carpet', 162, true],
		'pale_oak_sapling' => ['minecraft:pale_oak_sapling', 2, true],
		'pale_oak_shelf' => ['minecraft:pale_oak_shelf', 32, true],
		'piston' => ['minecraft:piston', 6, true],
		'piston_arm_collision' => ['minecraft:piston_arm_collision', 6, true],
		'pointed_dripstone' => ['minecraft:pointed_dripstone', 10, true],
		'poplar_sapling' => ['minecraft:poplar_sapling', 2, true],
		'poplar_shelf' => ['minecraft:poplar_shelf', 32, true],
		'potent_sulfur' => ['minecraft:potent_sulfur', 5, true],
		'powder_snow' => ['minecraft:powder_snow', 1, true],
		'red_mushroom_block_palette_states' => ['minecraft:red_mushroom_block', 2, false],
		'repeating_command_block' => ['minecraft:repeating_command_block', 12, true],
		'scaffolding' => ['minecraft:scaffolding', 16, true],
		'sculk_catalyst' => ['minecraft:sculk_catalyst', 2, true],
		'sculk_sensor' => ['minecraft:sculk_sensor', 3, true],
		'sculk_shrieker' => ['minecraft:sculk_shrieker', 4, true],
		'sculk_vein' => ['minecraft:sculk_vein', 64, true],
		'seagrass' => ['minecraft:seagrass', 3, true],
		'short_dry_grass' => ['minecraft:short_dry_grass', 1, true],
		'sniffer_egg' => ['minecraft:sniffer_egg', 3, true],
		'spruce_shelf' => ['minecraft:spruce_shelf', 32, true],
		'sticky_piston' => ['minecraft:sticky_piston', 6, true],
		'sticky_piston_arm_collision' => ['minecraft:sticky_piston_arm_collision', 6, true],
		'structure_block' => ['minecraft:structure_block', 6, true],
		'sulfur_spike' => ['minecraft:sulfur_spike', 10, true],
		'suspicious_gravel' => ['minecraft:suspicious_gravel', 8, true],
		'suspicious_sand' => ['minecraft:suspicious_sand', 8, true],
		'tall_dry_grass' => ['minecraft:tall_dry_grass', 1, true],
		'target' => ['minecraft:target', 1, true],
		'trial_spawner' => ['minecraft:trial_spawner', 12, true],
		'turtle_egg' => ['minecraft:turtle_egg', 12, true],
		'unknown' => ['minecraft:unknown', 1, true],
		'vault' => ['minecraft:vault', 32, true],
		'warped_shelf' => ['minecraft:warped_shelf', 32, true],
		'waxed_copper_chest' => ['minecraft:waxed_copper_chest', 4, true],
		'waxed_copper_golem_statue' => ['minecraft:waxed_copper_golem_statue', 4, true],
		'waxed_exposed_copper_chest' => ['minecraft:waxed_exposed_copper_chest', 4, true],
		'waxed_exposed_copper_golem_statue' => ['minecraft:waxed_exposed_copper_golem_statue', 4, true],
		'waxed_oxidized_copper_chest' => ['minecraft:waxed_oxidized_copper_chest', 4, true],
		'waxed_oxidized_copper_golem_statue' => ['minecraft:waxed_oxidized_copper_golem_statue', 4, true],
		'waxed_weathered_copper_chest' => ['minecraft:waxed_weathered_copper_chest', 4, true],
		'waxed_weathered_copper_golem_statue' => ['minecraft:waxed_weathered_copper_golem_statue', 4, true],
		'weathered_copper_chest' => ['minecraft:weathered_copper_chest', 4, true],
		'weathered_copper_golem_statue' => ['minecraft:weathered_copper_golem_statue', 4, true],
		'wildflowers' => ['minecraft:wildflowers', 32, true],
	];
}
