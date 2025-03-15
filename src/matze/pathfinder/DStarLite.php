<?php

declare(strict_types=1);

namespace matze\pathfinder;

use matze\pathfinder\node\Node;
use matze\pathfinder\result\PathResult;
use matze\pathfinder\setting\Settings;
use matze\pathfinder\world\FictionalWorld;
use pocketmine\math\Vector3;
use SplMinHeap;

class DStarLite{
	private array $graph = [];
	private array $rhs = [];
	private array $g = [];
	private SplMinHeap $queue;
	private array $km = [0.0];

	public function __construct(
		protected FictionalWorld $world,
		protected Settings $settings,
		private float $timeout,
		protected array $rules,
	){
		$this->queue = new SplMinHeap();
	}

	private function calculateKey(Node $node, Node $start) : array{
		$minG = min($this->g[$node->getHash()] ?? PHP_INT_MAX, $this->rhs[$node->getHash()] ?? PHP_INT_MAX);
		return [$minG + $this->h($start, $node) + $this->km[0], $minG];
	}

	private function h(Node $a, Node $b) : float{
		return abs($a->getX() - $b->getX()) + abs($a->getY() - $b->getY()) + abs($a->getZ() - $b->getZ());
	}

	private function updateVertex(Node $u, Node $start, Node $goal) : void{
		// Ensure the node is initialized in g and rhs arrays
		if(!isset($this->g[$u->getHash()])){
			$this->g[$u->getHash()] = PHP_INT_MAX;
		}
		if(!isset($this->rhs[$u->getHash()])){
			$this->rhs[$u->getHash()] = PHP_INT_MAX;
		}

		if($u->getHash() !== $goal->getHash()){
			$this->rhs[$u->getHash()] = $this->calculateRHS($u);
		}

		// Remove the node from the queue if it exists
		if(isset($this->g[$u->getHash()])){
			// Create a temporary array to hold the queue elements
			$tempQueue = [];
			while(!$this->queue->isEmpty()){
				$temp = $this->queue->extract();
				if($temp[1]->getHash() !== $u->getHash()){
					$tempQueue[] = $temp;
				}
			}
			// Rebuild the queue without the node
			foreach($tempQueue as $item){
				$this->queue->insert($item);
			}
		}

		// Add the node back to the queue if its g and rhs values are inconsistent
		if($this->g[$u->getHash()] !== $this->rhs[$u->getHash()]){
			$this->queue->insert([$this->calculateKey($u, $start), $u]);
		}
	}

	private function calculateRHS(Node $u) : float{
		$minCost = PHP_INT_MAX;
		foreach($this->getNeighbors($u) as $neighbor){
			// Ensure the neighbor is initialized in g array
			if(!isset($this->g[$neighbor->getHash()])){
				$this->g[$neighbor->getHash()] = PHP_INT_MAX;
			}

			$cost = $this->g[$neighbor->getHash()] + $this->cost($u, $neighbor);
			if($cost < $minCost){
				$minCost = $cost;
			}
		}
		return $minCost;
	}

	private function cost(Node $a, Node $b) : float{
		return $this->h($a, $b); // Assuming uniform cost for simplicity
	}

	private function getNeighbors(Node $node) : array{
		$neighbors = [];
		$directions = [
			new Vector3(1, 0, 0), new Vector3(-1, 0, 0), // East, West
			new Vector3(0, 0, 1), new Vector3(0, 0, -1),  // South, North
			new Vector3(1, 1, 0), new Vector3(-1, 1, 0), // East Up, West Up
			new Vector3(0, 1, 1), new Vector3(0, 1, -1),  // South Up, North Up
			new Vector3(1, -1, 0), new Vector3(-1, -1, 0), // East Down, West Down
			new Vector3(0, -1, 1), new Vector3(0, -1, -1), // South Down, North Down
		];

		foreach($directions as $direction){
			$neighborVector = $node->addVector($direction);
			$neighborNode = Node::fromVector3($neighborVector);

			// Check if the target position is nice to stand on
			$standCost = 0;
			if(!$this->isNicePositionToStand($neighborVector, $standCost)){
				continue; // Skip this neighbor if it's not a valid standing position
			}

			// Check if the path from the current node to the neighbor is walkable
			$walkCost = 0;
			if(!$this->isNicePositionToWalk($node, $neighborVector, $walkCost)){
				continue; // Skip this neighbor if the path is not walkable
			}

			// If both checks pass, add the neighbor to the list
			$neighbors[] = $neighborNode;
		}

		return $neighbors;
	}

	private function computeShortestPath(Node $start, Node $goal) : void{
		while(!$this->queue->isEmpty() && ($this->queue->top()[0] < $this->calculateKey($start, $start) || $this->rhs[$start->getHash()] !== $this->g[$start->getHash()])){
			$u = $this->queue->extract()[1];

			// Ensure the node is initialized in g and rhs arrays
			if(!isset($this->g[$u->getHash()])){
				$this->g[$u->getHash()] = PHP_INT_MAX;
			}
			if(!isset($this->rhs[$u->getHash()])){
				$this->rhs[$u->getHash()] = PHP_INT_MAX;
			}

			if($this->g[$u->getHash()] > $this->rhs[$u->getHash()]){
				$this->g[$u->getHash()] = $this->rhs[$u->getHash()];
				foreach($this->getNeighbors($u) as $neighbor){
					$this->updateVertex($neighbor, $start, $goal);
				}
			}else{
				$this->g[$u->getHash()] = PHP_INT_MAX;
				foreach($this->getNeighbors($u) as $neighbor){
					$this->updateVertex($neighbor, $start, $goal);
				}
				$this->updateVertex($u, $start, $goal);
			}
		}
	}

	public function findPath(Vector3 $startVector, Vector3 $targetVector) : ?PathResult{
		$startNode = Node::fromVector3($startVector);
		$goalNode = Node::fromVector3($targetVector);

		// Reset state for a new pathfinding request
		$this->graph = [];
		$this->rhs = [];
		$this->g = [];
		$this->queue = new SplMinHeap();
		$this->km = [0.0];

		// Initialize the algorithm
		$this->g[$goalNode->getHash()] = PHP_INT_MAX;
		$this->rhs[$goalNode->getHash()] = 0;
		$this->queue->insert([$this->calculateKey($goalNode, $startNode), $goalNode]);

		// Initialize the start node
		$this->g[$startNode->getHash()] = PHP_INT_MAX;
		$this->rhs[$startNode->getHash()] = $this->calculateRHS($startNode);
		$this->queue->insert([$this->calculateKey($startNode, $startNode), $startNode]);

		// Compute the shortest path
		$this->computeShortestPath($startNode, $goalNode);

		// Check if a path was found
		if($this->g[$startNode->getHash()] === PHP_INT_MAX){
			return null; // No path found
		}

		// Reconstruct the path
		$pathResult = new PathResult();
		$current = $startNode;
		while($current->getHash() !== $goalNode->getHash()){
			$pathResult->addNode($current);
			$minCost = PHP_INT_MAX;
			$nextNode = null;
			foreach($this->getNeighbors($current) as $neighbor){
				// Ensure the neighbor is initialized in g array
				if(!isset($this->g[$neighbor->getHash()])){
					$this->g[$neighbor->getHash()] = PHP_INT_MAX;
				}

				$cost = $this->g[$neighbor->getHash()];
				if($cost < $minCost){
					$minCost = $cost;
					$nextNode = $neighbor;
				}
			}
			if($nextNode === null){
				return null; // No path found
			}
			$current = $nextNode;
		}
		$pathResult->addNode($goalNode);
		return $pathResult;
	}

	public function isNicePositionToWalk(Vector3 $current, Vector3 $target, int &$cost) : bool{
		foreach($this->rules as $rule){
			if(!$rule->couldWalkTo($current, $target, $this->world, $this->settings, $cost)){
				return false;
			}
		}
		return true;
	}

	public function isNicePositionToStand(Vector3 $target, int &$cost) : bool{
		foreach($this->rules as $rule){
			if(!$rule->couldStandAt($target, $this->world, $cost)){
				return false;
			}
		}
		return true;
	}
}