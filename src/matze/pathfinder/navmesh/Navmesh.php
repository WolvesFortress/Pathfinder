<?php

declare(strict_types=1);

namespace matze\pathfinder\navmesh;

use matze\pathfinder\setting\Settings;
use matze\pathfinder\world\FictionalWorld;
use pocketmine\math\Vector3;

class Navmesh{

	// The idea is basically to generate a navmesh based on the world and the rules, preferrably using a binary meshing algorithm
	// Per subchunk processing would be faster than per block processing, and we could skip air subchunks (if the mob can't walk in air)
	// The subchunk palette could be used to determine if there are walkable blocks in the subchunk relatively quickly
	// Sadly, as of now, PocketMine-MP does not support filtering palettes :/ https://github.com/pmmp/PocketMine-MP/issues/1214
	//
	// A great starting point would be the BlockLightUpdate class, but the binary meshing algorithm needs blocks that surround the chunk :(
	// https://github.com/pmmp/PocketMine-MP/blob/c80a4d5b550b6e2ecbcfecb041477309fb8a0e24/src/world/light/BlockLightUpdate.php#L34

	public function __construct(public FictionalWorld $world, public Settings $settings){ }

	public function generate(Vector3 $from, Vector3 $to) : self{
		// Use binary meshing to create a navmesh - we want to get walkable faces. For Minecraft mobs those are usually always facing up (standing on ground)
		// In theory this could allow implementing mobs that can walk on the ceiling or walls (spiders?)
		// This would also be useful for climbable blocks like ladders
		// We likely do not need to represent the faces in the data structure though, we only need to know in which blocks we can traverse for now
		// Checking the faces for walkability would be too expensive, and would only make sense for special cases where the mob actually needs to touch the face
		// We can probably use the Settings::$maxTravelDistanceUp/Settings::$maxTravelDistanceDown for the bit y level bitshift as long as they don't change
		// Valid blocks are checked based on the rules (see Rule.php)
		// For now we just set all blocks that are not air as walkable
		return $this;
	}
}